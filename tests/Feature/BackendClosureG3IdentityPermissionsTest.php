<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use App\Services\OperationalQueueService;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendClosureG3IdentityPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_perform_normal_crm_work_and_use_operational_queues(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('clients.store'), [
            'business_name' => 'G3 Staff Prospect',
            'phone' => '0791112233',
            'city_area' => 'Amman',
            'business_category' => 'Retail',
            'lead_source' => 'Direct',
        ])->assertRedirect();

        $client = Client::where('business_name', 'G3 Staff Prospect')->firstOrFail();
        $this->assertSame($staff->id, $client->primary_owner_id);

        $queues = app(OperationalQueueService::class)->queues($staff);
        $this->assertTrue($queues['active_contact_queue']->contains('id', $client->id));
    }

    public function test_staff_cannot_bypass_high_risk_financial_or_system_permissions(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('commercial-catalog.plans.store'), [
                'code' => 'staff_forbidden',
                'name_ar' => 'باقة موظف',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('financial-accounts.store'), [
                'code' => 'staff_cash',
                'name_ar' => 'صندوق موظف',
                'type' => 'cash',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('settings.update'), [
                'currency' => 'JOD',
            ])
            ->assertForbidden();
    }

    public function test_admin_keeps_approved_high_risk_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('commercial-catalog.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk();
    }

    public function test_public_referral_ingress_preserves_attribution_without_partner_user_creation(): void
    {
        $partner = Partner::create([
            'company_name' => 'G3 Referral Partner',
            'email' => 'g3-referral@example.com',
        ]);

        $this->post(route('public.client.store', $partner->public_uuid), [
            'phone' => '0795556677',
            'name' => 'G3 Public Lead',
            'area' => 'Amman',
            'source' => 'Referral',
        ])->assertRedirect(route('public.client.create', $partner->public_uuid));

        $this->assertDatabaseHas('clients', [
            'business_name' => 'G3 Public Lead',
            'partner_id' => $partner->id,
            'stage' => ClientLifecycle::PROSPECT,
        ]);
        $this->assertDatabaseMissing('users', [
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);
    }
}
