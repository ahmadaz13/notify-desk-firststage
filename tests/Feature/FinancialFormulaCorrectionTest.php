<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialFormulaCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_arr_uses_only_active_subscriptions(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'متجر المستقبل',
            'phone' => '0791234567',
            'city_area' => 'عمان',
            'business_category' => 'تجارة',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        // Active monthly subscription: 100 JOD / month -> 1200 ARR
        DB::table('subscriptions')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 100.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Inactive / cancelled subscription: should NOT be included in ARR
        DB::table('subscriptions')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 500.00,
            'start_date' => now()->subYear()->toDateString(),
            'status' => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Inactive / expired subscription: should NOT be included in ARR
        DB::table('subscriptions')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 300.00,
            'start_date' => now()->subMonths(6)->toDateString(),
            'status' => 'expired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertViewHas('annual_recurring_revenue', 1200.00);
    }

    public function test_arr_is_zero_when_no_active_subscriptions(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertViewHas('annual_recurring_revenue', 0.0);
        $response->assertViewHas('estimated_market_value', 0.0);
    }

    public function test_arr_includes_monthly_and_annual_correctly(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $client1 = Client::create([
            'business_name' => 'مكتبة النجاح',
            'phone' => '0792223344',
            'city_area' => 'عمان',
            'business_category' => 'تعليم',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        $client2 = Client::create([
            'business_name' => 'شركة الحلول المتطورة',
            'phone' => '0793334455',
            'city_area' => 'إربد',
            'business_category' => 'تقنية',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        // Monthly: 150.00 * 12 = 1800.00
        DB::table('subscriptions')->insert([
            'client_id' => $client1->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 150.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Annual: 2400.00 * 1 = 2400.00
        DB::table('subscriptions')->insert([
            'client_id' => $client2->id,
            'user_id' => $admin->id,
            'billing_type' => 'annual',
            'total_price' => 2400.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        // 1800 + 2400 = 4200.00
        $response->assertViewHas('annual_recurring_revenue', 4200.00);
    }

    public function test_liquidity_includes_collections_and_operational_expenses(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        // Investments: 50,000
        $investmentId = DB::table('investments')->insertGetId([
            'investor_name' => 'شريك استثماري رئيسي',
            'amount' => 50000.00,
            'entry_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Capital Expenses: 12,000
        DB::table('capital_expenses')->insert([
            'investment_id' => $investmentId,
            'description' => 'تجهيزات خوادم وتأسيس شبكي',
            'amount' => 12000.00,
            'expense_date' => '2026-01-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client = Client::create([
            'business_name' => 'مطعم الياسمين',
            'phone' => '0794445566',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 8000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Payments (All-time collections): 8,000
        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subId,
            'amount' => 8000.00,
            'payment_method' => 'bank_transfer',
            'paid_at' => now(),
            'recorded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Operational Expenses: 3,500 + 1,500 = 5,000
        DB::table('expenses')->insert([
            'amount' => 3500.00,
            'category' => 'رواتب',
            'date' => now()->toDateString(),
            'paid_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('expenses')->insert([
            'amount' => 1500.00,
            'category' => 'تسويق',
            'date' => now()->toDateString(),
            'paid_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();

        // Inflows: 50000 + 8000 = 58000
        // Outflows: 12000 + 5000 = 17000
        // Liquidity: 58000 - 17000 = 41000.00
        $response->assertViewHas('liquidity_balance', 41000.00);
        $response->assertViewHas('all_time_collections', 8000.00);
        $response->assertViewHas('all_time_operational_expenses', 5000.00);
    }

    public function test_net_profit_subtracts_actual_expenses(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'صالون الفخامة',
            'phone' => '0795556677',
            'city_area' => 'عمان',
            'business_category' => 'تجميل',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 10000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Collections: 10,000 JOD
        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subId,
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actual operational expenses recorded: 2,500 JOD
        DB::table('expenses')->insert([
            'amount' => 2500.00,
            'category' => 'سيرفرات وبنية تحتية',
            'date' => now()->toDateString(),
            'paid_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();

        // Gross = 10000.00
        // Operational Cost (20%) = 2000.00
        // Net Operating Revenue = 8000.00
        // Actual Operational Expenses = 2500.00
        // Net Profit = 8000.00 - 2500.00 = 5500.00
        $response->assertViewHas('total_gross_revenue', 10000.00);
        $response->assertViewHas('operational_cost', 2000.00);
        $response->assertViewHas('net_operating_revenue', 8000.00);
        $response->assertViewHas('all_time_operational_expenses', 2500.00);
        $response->assertViewHas('net_profit', 5500.00);
    }

    public function test_market_value_uses_corrected_arr(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'أكاديمية المعرفة',
            'phone' => '0796667788',
            'city_area' => 'عمان',
            'business_category' => 'تعليم',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        // Monthly 250.00 -> ARR = 3000.00
        DB::table('subscriptions')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 250.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertViewHas('annual_recurring_revenue', 3000.00);
        // Multiplier = 5 -> Market value = 3000 * 5 = 15000.00
        $response->assertViewHas('estimated_market_value', 15000.00);
    }

    public function test_market_value_does_not_explode_with_old_cumulative_revenue(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'شركة النسر البرمجي',
            'phone' => '0797778899',
            'city_area' => 'عمان',
            'business_category' => 'برمجيات',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);

        // Active monthly subscription: 100.00 JOD / month (true ARR = 1200.00)
        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'billing_type' => 'monthly',
            'total_price' => 100.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Huge historical cumulative collections: 50,000 JOD
        for ($i = 0; $i < 10; $i++) {
            DB::table('payments')->insert([
                'client_id' => $client->id,
                'subscription_id' => $subId,
                'amount' => 5000.00,
                'payment_method' => 'bank_transfer',
                'paid_at' => now()->subMonths($i),
                'recorded_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertViewHas('total_gross_revenue', 50000.00);

        // Under new formula:
        // ARR is based on active subscriptions (100 * 12 = 1200), NOT cumulative collections!
        // Under old broken formula: ARR would have been (50000 * 0.8) * 12 = 480,000 JOD
        // and Market Value would have exploded to 2,400,000 JOD!
        $response->assertViewHas('annual_recurring_revenue', 1200.00);
        $response->assertViewHas('estimated_market_value', 6000.00);
    }

    public function test_partner_deduction_percentage_used_in_earned_share(): void
    {
        $partner = Partner::create([
            'company_name' => 'وكالة النمو السريع',
            'email' => 'growth_agency@example.com',
            'profit_share_percentage' => 40.00,
            'deduction_percentage' => 15.00,
        ]);

        $user = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'متجر الأناقة',
            'phone' => '0798889900',
            'city_area' => 'عمان',
            'business_category' => 'أزياء',
            'lead_source' => 'Partner',
            'partner_id' => $partner->id,
            'status' => 'subscriber',
        ]);

        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'billing_type' => 'monthly',
            'total_price' => 2000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subId,
            'amount' => 2000.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Total payments = 2000.00
        // Deduction = 15% -> Net = 2000 * (1 - 0.15) = 1700.00
        // Profit share = 40% -> Earned share = 1700 * 0.40 = 680.00
        $this->assertEquals(2000.00, $partner->total_client_payments);
        $this->assertEquals(680.00, $partner->earned_share);
    }

    public function test_partner_with_null_deduction_defaults_to_20(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك النسبة التلقائية',
            'email' => 'default_deduct@example.com',
            'profit_share_percentage' => 30.00,
            'deduction_percentage' => null, // Should default to 20%
        ]);

        $user = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'business_name' => 'مقهى السعادة',
            'phone' => '0799990011',
            'city_area' => 'الزرقاء',
            'business_category' => 'مقاهي',
            'lead_source' => 'Partner',
            'partner_id' => $partner->id,
            'status' => 'subscriber',
        ]);

        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'billing_type' => 'monthly',
            'total_price' => 1000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subId,
            'amount' => 1000.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Total payments = 1000.00
        // Default deduction = 20% -> Net = 1000 * 0.8 = 800.00
        // Profit share = 30% -> Earned share = 800 * 0.30 = 240.00
        $this->assertEquals(1000.00, $partner->total_client_payments);
        $this->assertEquals(240.00, $partner->earned_share);
    }

    public function test_admin_sees_corrected_financial_cards(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('إجمالي الإيرادات الخام');
        $response->assertSee('تكلفة التشغيل');
        $response->assertSee('صافي الإيراد التشغيلي');
        $response->assertSee('صافي الربح بعد المصاريف الفعلية');
        $response->assertSee('القيمة السوقية التقديرية');
        $response->assertSee('رصيد السيولة');

        $response->assertViewHas('net_profit');
        $response->assertViewHas('annual_recurring_revenue');
        $response->assertViewHas('liquidity_balance');
        $response->assertViewHas('all_time_operational_expenses');
        $response->assertViewHas('all_time_collections');
    }
}
