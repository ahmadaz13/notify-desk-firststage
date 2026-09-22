<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase4SmokeVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    public function test_free_installation_completion_does_not_auto_create_subscription(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::INSTALLATION_SCHEDULED]);
        $appt = Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => AppointmentTypes::INSTALLATION,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->staff)->post(route('clients.installations.complete', $client), [
            'appointment_id' => $appt->id,
            'custom_item_names' => 'شاشة لمس ونظام نقاط البيع',
            'notes' => 'تم التركيب بنجاح دون أخطاء',
        ]);

        $response->assertRedirect();
        $fresh = $client->fresh();
        $this->assertSame(ClientLifecycle::INSTALLED_FREE, $fresh->stage);
        $this->assertDatabaseHas('installations', [
            'client_id' => $client->id,
            'appointment_id' => $appt->id,
        ]);
        // Strict invariant: zero subscriptions and zero invoices created automatically
        $this->assertDatabaseMissing('subscriptions', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('invoices', ['client_id' => $client->id]);
        // Follow-up is generated automatically
        $this->assertDatabaseHas('follow_ups', ['client_id' => $client->id]);
    }

    public function test_start_subscription_flow_resolves_price_and_creates_contract_draft(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::PROSPECT, 'number_of_branches' => 2]);
        $product = Product::create(['name_ar' => 'برنامج نقاط البيع المتطور', 'code' => 'POS_ADV']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'الباقة المتقدمة', 'code' => 'PLN_ADV', 'tier' => 1]);
        PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => 'annual',
            'amount_minor' => 100000,
            'price' => 100.000,
            'price_minor' => 100000,
            'is_active' => true,
            'effective_from' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client), [
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'billing_interval' => 'annual',
            'payment_terms' => 'installments',
            'installments_count' => 4,
            'installment_due_day' => 5,
            'notes' => 'اشتراك سنوي بأربعة أقساط',
        ]);

        $response->assertRedirect(route('clients.show', $client->id));
        $this->assertSame(ClientLifecycle::SUBSCRIBER, $client->fresh()->stage);

        $subscription = Subscription::where('client_id', $client->id)->firstOrFail();
        $this->assertDatabaseHas('invoices', ['client_id' => $client->id, 'subscription_id' => $subscription->id]);
        $this->assertDatabaseHas('contracts', [
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'status' => 'draft',
        ]);

        // Verify workspace view shows success state and contract action
        $workspaceResponse = $this->actingAs($this->admin)->get(route('clients.show', $client));
        $workspaceResponse->assertOk()
            ->assertSee('برنامج نقاط البيع المتطور')
            ->assertSee('الباقة المتقدمة')
            ->assertSee(route('contracts.print', $client->contracts()->first()->id), false);
    }

    public function test_same_product_active_subscription_conflict_protection(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::SUBSCRIBER]);
        $product = Product::create(['name_ar' => 'نظام إدارة المخزون', 'code' => 'INV_MGMT']);
        $plan = Plan::create(['product_id' => $product->id, 'name_ar' => 'باقة المخزون الأساسية', 'code' => 'INV_BASE', 'tier' => 1]);
        PlanPrice::create([
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'amount_minor' => 30000,
            'price' => 30.000,
            'price_minor' => 30000,
            'is_active' => true,
            'effective_from' => now()->subMonth(),
        ]);

        // Create active subscription
        $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client), [
            '_idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
        ]);

        // Attempting to create second active subscription for the same product must fail with validation error
        $secondAttempt = $this->actingAs($this->admin)->post(route('clients.guided-subscription.store', $client), [
            '_idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'billing_interval' => 'monthly',
        ]);

        $secondAttempt->assertSessionHasErrors(['product_id']);
    }

    public function test_simplified_record_payment_derives_financial_account_server_side(): void
    {
        $client = $this->createClient(['stage' => ClientLifecycle::SUBSCRIBER]);
        FinancialAccount::firstOrCreate(
            ['code' => 'CASH_MAIN'],
            [
                'name_ar' => 'الصندوق النقدي الرئيسي',
                'name_en' => 'Main Cash Box',
                'type' => 'cash',
                'currency' => 'JOD',
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($this->admin)->post(route('clients.payments.normal.store', $client), [
            'amount' => '85.500',
            'payment_method' => PaymentMethods::CASH,
            'reference' => 'RCPT-10024',
            'notes' => 'دفعة نقدية مسجلة من واجهة العميل السريعة',
        ]);

        $response->assertRedirect(route('clients.show', $client));
        $this->assertDatabaseHas('payments', [
            'client_id' => $client->id,
            'amount' => 85.500,
            'payment_method' => PaymentMethods::CASH,
            'reference' => 'RCPT-10024',
        ]);

        // Workspace shows latest payment summary
        $workspace = $this->actingAs($this->admin)->get(route('clients.show', $client));
        $workspaceResponse = $workspace->assertOk();
        $workspaceResponse->assertSee('85.500 د.أ')
            ->assertSee('RCPT-10024');
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'مطاعم القدس الحديثة',
            'phone' => '0799887766',
            'business_phone' => '0799887766',
            'contact_person' => 'عمر',
            'city_area' => 'عمان - شارع مكة',
            'city' => 'Amman',
            'area' => 'Mecca Street',
            'business_category' => 'مطاعم وكافيهات',
            'lead_source' => 'Direct Prospecting',
            'number_of_branches' => 1,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }
}
