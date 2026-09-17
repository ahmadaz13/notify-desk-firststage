<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanPriceService;
use App\Services\SubscriptionBillingService;
use App\Support\ClientLifecycle;
use App\Support\Money;
use Database\Seeders\CommercialCatalogSeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialProductArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(ServiceCatalogSeeder::class);
        $this->seed(CommercialCatalogSeeder::class);
    }

    public function test_product_can_have_multiple_plans_and_plan_retains_services_and_prices(): void
    {
        $product = Product::where('code', 'restaurant_system')->firstOrFail();
        $service = Service::firstOrFail();

        $basic = $this->createPlanWithPrice($product, 'restaurant_basic_gate', 'Basic', '10.000');
        $advanced = $this->createPlanWithPrice($product, 'restaurant_advanced_gate', 'Advanced', '20.000');
        $advanced->plan->services()->sync([$service->id]);

        $this->assertTrue($product->plans()->whereKey($basic->plan_id)->exists());
        $this->assertTrue($product->plans()->whereKey($advanced->plan_id)->exists());
        $this->assertTrue($advanced->plan->fresh()->product->is($product));
        $this->assertTrue($advanced->plan->fresh('services')->services->contains('id', $service->id));
        $this->assertSame($advanced->id, app(PlanPriceService::class)->activeEffectivePrice($advanced->id)->id);
        $this->assertSame(20000, $advanced->fresh()->amount_minor);
    }

    public function test_admin_catalog_renders_products_and_nested_plans(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('commercial-catalog.index'))
            ->assertOk()
            ->assertSee('نظام المطاعم')
            ->assertSee('restaurant_system')
            ->assertSee('الباقة الأولى');
    }

    public function test_client_can_start_separate_active_subscriptions_for_different_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $restaurant = Product::where('code', 'restaurant_system')->firstOrFail();
        $store = Product::where('code', 'digital_store_system')->firstOrFail();
        $restaurantPrice = $this->createPlanWithPrice($restaurant, 'restaurant_multi_product', 'Restaurant Basic', '15.000');
        $storePrice = $this->createPlanWithPrice($store, 'store_multi_product', 'Store Basic', '18.000');

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $restaurantPrice->id,
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $storePrice->id,
            'quantity' => 1,
            'start_date' => '2026-09-16',
        ])->assertSessionHas('success');

        $this->assertSame(2, Subscription::where('client_id', $client->id)->where('status', 'active')->count());
        $this->assertSame(2, Invoice::where('client_id', $client->id)->count());
    }

    public function test_client_cannot_start_parallel_active_subscriptions_for_plans_in_the_same_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $product = Product::where('code', 'restaurant_system')->firstOrFail();
        $basic = $this->createPlanWithPrice($product, 'restaurant_unique_basic', 'Restaurant Basic', '15.000');
        $advanced = $this->createPlanWithPrice($product, 'restaurant_unique_advanced', 'Restaurant Advanced', '25.000');

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $basic->id,
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $advanced->id,
            'quantity' => 1,
            'start_date' => '2026-09-16',
        ])->assertSessionHasErrors('plan_price_id');

        $this->assertSame(1, Subscription::where('client_id', $client->id)->where('status', 'active')->count());
        $this->assertSame(1, Invoice::where('client_id', $client->id)->count());
    }

    public function test_plan_change_remains_the_tier_change_path_within_the_same_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $product = Product::where('code', 'restaurant_system')->firstOrFail();
        $basic = $this->createPlanWithPrice($product, 'restaurant_change_basic', 'Restaurant Basic', '15.000');
        $advanced = $this->createPlanWithPrice($product, 'restaurant_change_advanced', 'Restaurant Advanced', '25.000');

        [$subscription] = app(SubscriptionBillingService::class)->startPaidSubscription($client, $basic, [
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ], $admin->id);

        app(SubscriptionBillingService::class)->schedulePlanChange($subscription, $advanced, 1, $admin->id);

        $this->assertSame($advanced->plan_id, $subscription->fresh()->pending_plan_id);
        $this->assertSame(1, Subscription::where('client_id', $client->id)->where('status', 'active')->count());
    }

    public function test_plan_change_cannot_target_a_product_held_by_another_active_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $restaurant = Product::where('code', 'restaurant_system')->firstOrFail();
        $store = Product::where('code', 'digital_store_system')->firstOrFail();
        $restaurantPrice = $this->createPlanWithPrice($restaurant, 'restaurant_change_source', 'Restaurant Basic', '15.000');
        $storePrice = $this->createPlanWithPrice($store, 'store_change_existing', 'Store Basic', '18.000');
        $storeUpgrade = $this->createPlanWithPrice($store, 'store_change_target', 'Store Pro', '28.000');
        $billing = app(SubscriptionBillingService::class);

        [$restaurantSubscription] = $billing->startPaidSubscription($client, $restaurantPrice, [
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ], $admin->id);
        $billing->startPaidSubscription($client, $storePrice, [
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ], $admin->id);

        try {
            $billing->schedulePlanChange($restaurantSubscription, $storeUpgrade, 1, $admin->id);
            $this->fail('A plan change must not create a second active subscription for the same product.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('plan_price_id', $exception->errors());
        }

        $this->assertNull($restaurantSubscription->fresh()->pending_plan_id);
    }

    public function test_cancelled_subscription_cannot_be_reactivated_into_an_already_active_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $product = Product::where('code', 'restaurant_system')->firstOrFail();
        $basic = $this->createPlanWithPrice($product, 'restaurant_reactivate_basic', 'Restaurant Basic', '15.000');
        $advanced = $this->createPlanWithPrice($product, 'restaurant_reactivate_advanced', 'Restaurant Advanced', '25.000');
        $billing = app(SubscriptionBillingService::class);

        [$cancelled] = $billing->startPaidSubscription($client, $basic, [
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ], $admin->id);
        $cancelled->update(['status' => 'cancelled', 'ended_at' => '2026-09-30']);
        $billing->startPaidSubscription($client, $advanced, [
            'quantity' => 1,
            'start_date' => '2026-10-01',
        ], $admin->id);

        try {
            $billing->reactivate($cancelled->fresh(), $basic, [
                'quantity' => 1,
                'start_date' => '2026-10-02',
            ], $admin->id);
            $this->fail('Reactivation must not create a second active subscription for the same product.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('plan_price_id', $exception->errors());
        }

        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame(2, Invoice::where('client_id', $client->id)->count());
    }

    public function test_archived_product_is_not_sellable_for_new_subscriptions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $product = Product::where('code', 'auto_sms_system')->firstOrFail();
        $price = $this->createPlanWithPrice($product, 'auto_sms_archived', 'Auto SMS Standard', '12.000');

        $product->update(['is_active' => false, 'archived_at' => now()]);

        $this->assertFalse(Plan::sellable()->whereKey($price->plan_id)->exists());

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ])->assertSessionHasErrors('plan_price_id');
    }

    public function test_inactive_plan_is_not_sellable_for_new_subscriptions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $product = Product::where('code', 'auto_sms_system')->firstOrFail();
        $price = $this->createPlanWithPrice($product, 'auto_sms_inactive_plan', 'Auto SMS Standard', '12.000');

        $price->plan->update(['is_active' => false, 'archived_at' => now()]);

        $this->actingAs($admin)->post(route('clients.paid-subscriptions.store', $client), [
            'plan_price_id' => $price->id,
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ])->assertSessionHasErrors('plan_price_id');

        $this->assertSame(0, Subscription::where('client_id', $client->id)->count());
    }

    public function test_archiving_product_preserves_existing_subscription_and_invoice_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();
        $product = Product::where('code', 'auto_sms_system')->firstOrFail();
        $price = $this->createPlanWithPrice($product, 'auto_sms_history', 'Auto SMS Standard', '12.000');

        [$subscription, $invoice] = app(SubscriptionBillingService::class)->startPaidSubscription($client, $price, [
            'quantity' => 1,
            'start_date' => '2026-09-15',
        ], $admin->id);
        $subscriptionSnapshot = $subscription->only(['plan_id', 'plan_price_id', 'unit_price_minor', 'total_minor', 'status']);
        $invoiceSnapshot = $invoice->only(['subscription_id', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'status']);

        $product->update(['is_active' => false, 'archived_at' => now()]);

        $this->assertSame($subscriptionSnapshot, $subscription->fresh()->only(array_keys($subscriptionSnapshot)));
        $this->assertSame($invoiceSnapshot, $invoice->fresh()->only(array_keys($invoiceSnapshot)));
        $this->assertTrue($subscription->fresh('plan.product')->plan->product->is($product));

        $renewal = app(SubscriptionBillingService::class)->generateRenewals('2026-10-15', false, $admin->id);

        $this->assertSame(1, $renewal['renewed']);
        $this->assertSame(2, Invoice::where('subscription_id', $subscription->id)->count());
    }

    private function createPlanWithPrice(Product $product, string $code, string $name, string $amountJod): PlanPrice
    {
        $plan = Plan::create([
            'product_id' => $product->id,
            'code' => $code,
            'name_ar' => $name,
            'description_ar' => 'وصف '.$name,
            'tier' => 1,
            'offer_type' => 'package',
            'is_active' => true,
        ]);

        return PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => PlanPrice::MONTHLY,
            'currency' => PlanPrice::CURRENCY,
            'amount_minor' => Money::fromJod($amountJod)->minorUnits(),
            'setup_fee_minor' => 0,
            'included_branch_quantity' => 1,
            'effective_from' => now()->subMinute(),
            'is_active' => true,
        ])->load('plan.product');
    }

    private function createClient(): Client
    {
        return Client::create([
            'business_name' => 'Commercial Product Client',
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Restaurant',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
    }
}
