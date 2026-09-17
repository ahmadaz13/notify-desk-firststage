<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientPartnerAttributionService;
use App\Services\CollectionCorrectionService;
use App\Services\PartnerCommissionService;
use App\Services\PaymentAllocationService;
use App\Services\RefundService;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerReferralDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_is_external_referrer_without_user_or_credentials(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('partners.store'), [
            'company_name' => 'Referral Company',
            'contact_name' => 'Referral Contact',
            'email' => 'referral@example.test',
            'phone' => '0791112233',
            'default_commission_percentage' => '7.50',
        ])->assertRedirect(route('partners.index'))
            ->assertSessionMissing('new_partner_credentials');

        $partner = Partner::where('email', 'referral@example.test')->firstOrFail();

        $this->assertSame(750, $partner->default_commission_bps);
        $this->assertDatabaseMissing('users', ['email' => 'referral@example.test']);
        $this->assertFalse(array_key_exists('password', $partner->getAttributes()));
    }

    public function test_partner_can_have_multiple_clients_while_direct_client_has_no_attribution(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->createPartner(1000);
        $service = app(ClientPartnerAttributionService::class);
        $first = $this->createClient('First Referral');
        $second = $this->createClient('Second Referral');
        $direct = $this->createClient('Direct Client');

        $service->assign($first, $partner, null, null, $admin->id);
        $service->assign($second, $partner, 1250, null, $admin->id);

        $this->assertSame(2, $partner->attributions()->count());
        $this->assertSame($partner->id, $first->fresh()->partner_id);
        $this->assertSame(1000, $first->fresh('partnerAttribution')->partnerAttribution->commission_bps_snapshot);
        $this->assertSame(1250, $second->fresh('partnerAttribution')->partnerAttribution->commission_bps_snapshot);
        $this->assertNull($direct->partner_id);
        $this->assertNull($direct->partnerAttribution);
    }

    public function test_internal_staff_can_create_client_with_default_snapshotted_partner_rate(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $partner = $this->createPartner(900);

        $this->actingAs($staff)->post(route('clients.store'), [
            'business_name' => 'Staff Referral',
            'phone' => '0792223344',
            'city_area' => 'Amman',
            'business_category' => 'Services',
            'lead_source' => 'Partner',
            'partner_id' => $partner->id,
            'partner_commission_percentage' => '11.25',
        ])->assertRedirect();

        $client = Client::where('business_name', 'Staff Referral')->firstOrFail();
        $this->assertSame($partner->id, $client->partner_id);
        $this->assertSame(900, $client->partnerAttribution->commission_bps_snapshot);
    }

    public function test_public_partner_referral_snapshots_default_rate(): void
    {
        $partner = $this->createPartner(675);

        $this->post(route('public.client.store', $partner->public_uuid), [
            'name' => 'Public Referral',
            'phone' => '0793334455',
            'area' => 'Amman',
            'source' => 'Partner link',
        ])->assertSessionHas('success');

        $client = Client::where('business_name', 'Public Referral')->firstOrFail();
        $this->assertSame($partner->id, $client->partner_id);
        $this->assertSame(675, $client->partnerAttribution->commission_bps_snapshot);
    }

    public function test_default_rate_changes_and_partner_archival_preserve_client_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $partner = $this->createPartner(500);
        $client = $this->createClient('Historical Referral');
        app(ClientPartnerAttributionService::class)->assign($client, $partner, null, 'Original agreement', $admin->id);

        $partner->update([
            'default_commission_bps' => 2000,
            'profit_share_percentage' => '20.00',
            'status' => 'suspended',
        ]);

        $attribution = $client->fresh('partnerAttribution.partner')->partnerAttribution;
        $this->assertSame(500, $attribution->commission_bps_snapshot);
        $this->assertSame('suspended', $attribution->partner->status);
        $this->assertSame($partner->id, $client->fresh()->partner_id);

        $this->actingAs($staff)->put(route('clients.update', $client), [
            'business_name' => 'Historical Referral Updated',
            'phone' => $client->phone,
            'city_area' => $client->city_area,
            'business_category' => $client->business_category,
            'lead_source' => $client->lead_source,
            'partner_id' => $partner->id,
        ])->assertRedirect(route('clients.show', $client));

        $this->assertSame(500, $client->fresh('partnerAttribution')->partnerAttribution->commission_bps_snapshot);
    }

    public function test_partner_detail_counts_clients_and_active_subscribers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->createPartner(1000);
        $active = $this->createClient('Active Subscriber');
        $prospect = $this->createClient('Prospect Referral');
        $attributions = app(ClientPartnerAttributionService::class);
        $attributions->assign($active, $partner, null, null, $admin->id);
        $attributions->assign($prospect, $partner, null, null, $admin->id);
        Subscription::create([
            'client_id' => $active->id,
            'user_id' => $admin->id,
            'billing_engine_version' => 'v2',
            'billing_type' => 'monthly',
            'total_price' => '0.000',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $summary = app(PartnerCommissionService::class)->summary($partner);

        $this->assertSame(2, $summary['total_clients']);
        $this->assertSame(1, $summary['active_subscribers']);
        $this->actingAs($admin)->get(route('partners.show', $partner))->assertOk()
            ->assertSee('Active Subscriber')
            ->assertSee('Prospect Referral');
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('partners.show', $partner))
            ->assertForbidden();
    }

    public function test_commission_uses_cash_movements_and_net_of_refunds_and_reversals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->createPartner(1000);
        $client = $this->createClient('Collected Client');
        app(ClientPartnerAttributionService::class)->assign($client, $partner, null, null, $admin->id);
        $account = FinancialAccount::create([
            'code' => 'partner-commission-cash',
            'name_ar' => 'Partner Commission Cash',
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => 'JOD',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $payments = app(PaymentAllocationService::class);
        $keptPayment = $payments->recordV2Payment($client, [
            'amount' => '100.000',
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-17 10:00:00',
        ], [], false, $admin->id);
        app(RefundService::class)->refundFromPayment($keptPayment, [
            'amount' => '20.000',
            'financial_account_id' => $account->id,
            'refund_method' => 'cash',
            'refunded_at' => '2026-09-17 11:00:00',
            'reason' => 'Partial customer refund',
        ], $admin->id);

        $reversedPayment = $payments->recordV2Payment($client, [
            'amount' => '50.000',
            'financial_account_id' => $account->id,
            'payment_method' => 'cash',
            'received_at' => '2026-09-17 12:00:00',
        ], [], false, $admin->id);
        app(CollectionCorrectionService::class)->reversePayment($reversedPayment, 'Incorrect receipt', $admin->id);

        $summary = app(PartnerCommissionService::class)->summary($partner);
        $row = $summary['clients']->first();

        $this->assertSame(80000, $row['net_collected_minor']);
        $this->assertSame(8000, $row['commission_minor']);
        $this->assertSame(80000, $summary['net_collected_minor']);
        $this->assertSame(8000, $summary['commission_minor']);
    }

    private function createPartner(int $commissionBps): Partner
    {
        return Partner::create([
            'company_name' => 'Partner '.$commissionBps,
            'email' => 'partner-'.$commissionBps.'-'.str()->random(5).'@example.test',
            'default_commission_bps' => $commissionBps,
            'profit_share_percentage' => number_format($commissionBps / 100, 2, '.', ''),
            'status' => 'active',
        ]);
    }

    private function createClient(string $name): Client
    {
        return Client::create([
            'business_name' => $name,
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'business_category' => 'Services',
            'lead_source' => 'Referral',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
    }
}
