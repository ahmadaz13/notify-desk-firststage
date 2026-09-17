<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ContractService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ContractAutoDraftGate4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
    }

    public function test_paid_subscription_creates_one_immutable_v2_commercial_contract_snapshot(): void
    {
        [$founder, $client, $product, $plan, $price, $service] = $this->commercialFixture();

        [$subscription, $invoice, $contractResult] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $client,
            $price,
            ['quantity' => 2, 'start_date' => '2026-09-17', 'discount_jod' => '3.000'],
            $founder->id
        );

        $contract = Contract::sole();
        $snapshot = $contract->snapshot_data;

        $this->assertSame('ready', $contractResult['status']);
        $this->assertSame('draft', $contract->status);
        $this->assertSame($client->id, $contract->client_id);
        $this->assertSame($subscription->id, $contract->subscription_id);
        $this->assertSame($product->id, $snapshot['product']['id']);
        $this->assertSame('gate4_product', $snapshot['product']['code']);
        $this->assertSame('Gate 4 Product', $snapshot['product']['name_en']);
        $this->assertSame($plan->id, $snapshot['package']['plan_id']);
        $this->assertSame('gate4_plan', $snapshot['package']['plan_code_snapshot']);
        $this->assertSame('Gate 4 Plan', $snapshot['package']['plan_name_snapshot']);
        $this->assertSame($price->id, $snapshot['pricing']['plan_price_id']);
        $this->assertSame($subscription->unit_price_minor, $snapshot['pricing']['unit_price_minor']);
        $this->assertSame($subscription->setup_fee_minor_v2, $snapshot['pricing']['setup_fee_minor']);
        $this->assertSame($subscription->subtotal_minor, $snapshot['pricing']['subtotal_minor']);
        $this->assertSame($subscription->discount_minor, $snapshot['pricing']['discount_minor']);
        $this->assertSame($subscription->tax_rate_bps, $snapshot['pricing']['tax_rate_bps']);
        $this->assertSame($subscription->tax_minor_v2, $snapshot['pricing']['tax_minor']);
        $this->assertSame($subscription->total_minor, $snapshot['pricing']['total_minor']);
        $this->assertSame($invoice->total_minor, $snapshot['invoice']['total_minor']);
        $this->assertSame($service->id, $snapshot['services'][0]['id']);
        $this->assertSame('gate4_service', $snapshot['services'][0]['code']);
        $this->assertSame(10, $snapshot['services'][0]['sort_order']);
        $this->assertSame('Included for Gate 4', $snapshot['services'][0]['notes']);
        $this->assertSame('plan_service', $snapshot['services'][0]['source']);
        $this->assertSame($contract->contract_number, $snapshot['metadata']['contract_number']);
        Storage::disk('local')->assertExists($contract->private_file_path);
        $this->assertNotNull($contract->file_hash);

        $frozen = $contract->snapshot_data;
        $product->update(['name_ar' => 'منتج معدل', 'name_en' => 'Changed Product', 'is_active' => false, 'archived_at' => now()]);
        $plan->update(['name_ar' => 'باقة معدلة', 'name_en' => 'Changed Plan']);
        $plan->services()->detach($service->id);
        $price->update(['amount_minor' => 999999, 'setup_fee_minor' => 888888]);

        $this->assertSame($frozen, $contract->fresh()->snapshot_data);

        $this->actingAs($founder)
            ->get(route('contracts.print', $contract))
            ->assertOk()
            ->assertSee('size: A4 portrait', false)
            ->assertSee('Gate 4 Product')
            ->assertSee('Gate 4 Plan')
            ->assertSee('Gate 4 Service');
    }

    public function test_auto_draft_and_manual_recovery_are_idempotent(): void
    {
        [$founder, $client, , , $price] = $this->commercialFixture();
        [$subscription] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $client,
            $price,
            ['quantity' => 1, 'start_date' => '2026-09-17'],
            $founder->id
        );

        $contract = Contract::sole();
        $sameContract = app(ContractService::class)->ensureDraftContract($client, $subscription, $founder);
        $this->assertTrue($contract->is($sameContract));

        Storage::disk('local')->delete($contract->private_file_path);
        $this->actingAs($founder)
            ->post(route('contracts.store', ['client' => $client, 'subscription' => $subscription]))
            ->assertRedirect(route('clients.show', $client))
            ->assertSessionHas('success');

        $this->assertSame(1, Contract::where('subscription_id', $subscription->id)->where('status', 'draft')->count());
        Storage::disk('local')->assertExists($contract->private_file_path);

        app(ContractService::class)->issueContract($contract->fresh(), $founder);
        $currentContract = app(ContractService::class)->ensureDraftContract($client, $subscription, $founder);
        $this->assertTrue($contract->is($currentContract));
        $this->assertSame(1, Contract::where('subscription_id', $subscription->id)->count());

        Storage::disk('local')->delete($contract->private_file_path);
        $this->actingAs($founder)->get(route('contracts.download', $contract))->assertStatus(409);
        $this->actingAs($founder)
            ->post(route('contracts.store', ['client' => $client, 'subscription' => $subscription]))
            ->assertSessionHas('success');
        $this->assertSame(1, Contract::where('subscription_id', $subscription->id)->count());
        Storage::disk('local')->assertExists($contract->private_file_path);
    }

    public function test_contract_lifecycle_remains_issue_supersede_and_void(): void
    {
        [$founder, $client, , , $price] = $this->commercialFixture();
        app(SubscriptionBillingService::class)->startPaidSubscription(
            $client,
            $price,
            ['quantity' => 1, 'start_date' => '2026-09-17'],
            $founder->id
        );

        $service = app(ContractService::class);
        $issued = $service->issueContract(Contract::sole(), $founder);
        $replacement = $service->supersedeContract($issued, $founder);
        $voided = $service->voidContract($replacement, 'Gate 4 lifecycle test', $founder);

        $this->assertSame('superseded', $issued->fresh()->status);
        $this->assertSame($replacement->id, $issued->fresh()->superseded_by_contract_id);
        $this->assertSame('voided', $voided->fresh()->status);
    }

    public function test_artifact_failure_does_not_roll_back_subscription_invoice_or_draft(): void
    {
        [$founder, $client, , , $price] = $this->commercialFixture();
        $contractService = $this->partialMock(ContractService::class, function ($mock) {
            $mock->shouldReceive('generateArtifact')->once()->andThrow(new RuntimeException('simulated storage failure'));
        });
        $this->app->instance(ContractService::class, $contractService);

        [$subscription, $invoice, $contractResult] = app(SubscriptionBillingService::class)->startPaidSubscription(
            $client,
            $price,
            ['quantity' => 1, 'start_date' => '2026-09-17'],
            $founder->id
        );

        $this->assertSame('failed', $contractResult['status']);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'subscription_id' => $subscription->id]);
        $this->assertDatabaseHas('contracts', ['subscription_id' => $subscription->id, 'status' => 'draft']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $founder->id,
            'type' => 'contract_draft_recovery_required',
            'source_id' => $subscription->id,
        ]);
    }

    private function commercialFixture(): array
    {
        $founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $client = Client::create([
            'business_name' => 'Gate 4 Client',
            'contact_person' => 'Contract Signer',
            'phone' => '0790000044',
            'city_area' => 'Amman',
            'business_category' => 'Technology',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
            'created_by' => $founder->id,
        ]);
        $product = Product::create([
            'code' => 'gate4_product',
            'name_ar' => 'منتج البوابة الرابعة',
            'name_en' => 'Gate 4 Product',
            'is_active' => true,
            'created_by' => $founder->id,
        ]);
        $plan = Plan::create([
            'product_id' => $product->id,
            'code' => 'gate4_plan',
            'name_ar' => 'Gate 4 Plan',
            'name_en' => 'Gate 4 Plan',
            'tier' => 2,
            'offer_type' => 'package',
            'is_active' => true,
            'created_by' => $founder->id,
        ]);
        $service = Service::create([
            'key' => 'gate4_service',
            'name_ar' => 'خدمة البوابة الرابعة',
            'name_en' => 'Gate 4 Service',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $plan->services()->attach($service->id, ['sort_order' => 10, 'notes' => 'Included for Gate 4']);
        $price = PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => 20000,
            'setup_fee_minor' => 5000,
            'included_branch_quantity' => 1,
            'additional_branch_price_minor' => 4000,
            'default_tax_rate_bps' => 1600,
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'created_by' => $founder->id,
        ])->load('plan.product');

        return [$founder, $client, $product, $plan, $price, $service];
    }
}
