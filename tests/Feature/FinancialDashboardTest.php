<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_metrics_calculation(): void
    {
        $this->seed(SettingsSeeder::class);
        $user = User::factory()->create(['role' => 'admin']);

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'مطعم القدس',
            'phone' => '0791234567',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $clientId,
            'user_id' => $user->id,
            'billing_type' => 'monthly',
            'total_price' => 1500.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2 Payments totaling 1500.00
        DB::table('payments')->insert([
            'client_id' => $clientId,
            'subscription_id' => $subId,
            'amount' => 1000.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $clientId,
            'subscription_id' => $subId,
            'amount' => 500.00,
            'payment_method' => 'bank_transfer',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Investments: 20000.00
        $investmentId = DB::table('investments')->insertGetId([
            'investor_name' => 'مستثمر أولي',
            'amount' => 20000.00,
            'entry_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Capital Expenses: 5000.00
        DB::table('capital_expenses')->insert([
            'investment_id' => $investmentId,
            'description' => 'تجهيز السيرفرات',
            'amount' => 5000.00,
            'expense_date' => '2026-01-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();

        // 1500 gross revenue
        $response->assertViewHas('total_gross_revenue', 1500.00);

        // 20% operational cost = 300.00
        $response->assertViewHas('operational_cost', 300.00);

        // Net operating revenue = 1500 - 300 = 1200.00
        $response->assertViewHas('net_operating_revenue', 1200.00);

        // ARR = 1200 * 12 = 14400. Market valuation = 14400 * 5 = 72000.00
        $response->assertViewHas('estimated_market_value', 72000.00);

        // Liquidity = 20000 - 5000 = 15000.00
        $response->assertViewHas('liquidity_balance', 15000.00);

        // View assertions
        $response->assertSee('إجمالي الإيرادات الخام');
        $response->assertSee('تكلفة التشغيل');
        $response->assertSee('صافي الإيراد التشغيلي');
        $response->assertSee('القيمة السوقية التقديرية');
        $response->assertSee('رصيد السيولة');
        $response->assertSee('إضافة استثمار');
        $response->assertSee('صرف استثماري');
    }

    public function test_investment_creation(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $data = [
            'investor_name' => 'علي الحسيني',
            'amount' => 25000.50,
            'entry_date' => '2026-03-01',
            'notes' => 'جولة استثمار ملائكي',
        ];

        $response = $this->actingAs($user)->post('/investments', $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'تم إضافة الاستثمار بنجاح');

        $this->assertDatabaseHas('investments', [
            'investor_name' => 'علي الحسيني',
            'amount' => 25000.50,
            'entry_date' => '2026-03-01',
        ]);
    }

    public function test_capital_expense_creation(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $investment = Investment::create([
            'investor_name' => 'صندوق الابتكار',
            'amount' => 40000.00,
            'entry_date' => '2026-02-01',
        ]);

        $data = [
            'description' => 'شراء خوادم وتجهيزات شبكية',
            'amount' => 3500.00,
            'expense_date' => '2026-03-10',
            'investment_id' => $investment->id,
        ];

        $response = $this->actingAs($user)->post('/capital-expenses', $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'تم إضافة الصرف بنجاح');

        $this->assertDatabaseHas('capital_expenses', [
            'description' => 'شراء خوادم وتجهيزات شبكية',
            'amount' => 3500.00,
            'investment_id' => $investment->id,
        ]);
    }

    public function test_settings_influence_metrics(): void
    {
        $this->seed(SettingsSeeder::class);
        $user = User::factory()->create(['role' => 'admin']);

        // Update operational cost percentage to 25
        Setting::where('key', 'operational_cost_percentage')->update(['value' => '25']);

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'شركة النور',
            'phone' => '0780000000',
            'city_area' => 'إربد',
            'business_category' => 'خدمات',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $clientId,
            'user_id' => $user->id,
            'billing_type' => 'monthly',
            'total_price' => 2000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $clientId,
            'subscription_id' => $subId,
            'amount' => 2000.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();

        // 25% of 2000 = 500
        $response->assertViewHas('operational_cost', 500.00);

        // Net operating revenue = 2000 - 500 = 1500
        $response->assertViewHas('net_operating_revenue', 1500.00);
    }
}
