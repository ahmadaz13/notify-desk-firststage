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

    public function test_legacy_dashboard_financial_widgets_are_deprecated_and_non_authoritative(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('payments')->insert([
            'client_id' => DB::table('clients')->insertGetId([
                'business_name' => 'Legacy Dashboard Client',
                'phone' => '0791234567',
                'city_area' => 'Amman',
                'business_category' => 'Retail',
                'lead_source' => 'Direct',
                'status' => 'subscriber',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'amount' => 5000,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'financial']));

        $response->assertOk()
            ->assertViewHas('legacyFinancialSummary')
            ->assertSee('Legacy financial widgets')
            ->assertSee('Executive Dashboard')
            ->assertSee('Finance Reports')
            ->assertDontSee('إجمالي الإيرادات الخام')
            ->assertDontSee('تكلفة التشغيل')
            ->assertDontSee('القيمة السوقية التقديرية')
            ->assertDontSee('رصيد السيولة')
            ->assertDontSee('إضافة استثمار جديد')
            ->assertDontSee('تسجيل صرف استثماري');

        $this->assertSame('deprecated', $response->viewData('legacyFinancialSummary')['status']);
    }

    public function test_legacy_investment_write_is_deprecated_and_does_not_create_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/investments', [
            'investor_name' => 'Deprecated Investor',
            'amount' => 25000.50,
            'entry_date' => '2026-03-01',
            'notes' => 'Legacy path',
        ]);

        $response->assertStatus(410);
        $this->assertDatabaseMissing('investments', [
            'investor_name' => 'Deprecated Investor',
        ]);
    }

    public function test_legacy_capital_expense_write_is_deprecated_and_does_not_create_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $investment = Investment::create([
            'investor_name' => 'Historical Investor',
            'amount' => 40000.00,
            'entry_date' => '2026-02-01',
        ]);

        $response = $this->actingAs($admin)->post('/capital-expenses', [
            'description' => 'Deprecated capital expense',
            'amount' => 3500.00,
            'expense_date' => '2026-03-10',
            'investment_id' => $investment->id,
        ]);

        $response->assertStatus(410);
        $this->assertDatabaseMissing('capital_expenses', [
            'description' => 'Deprecated capital expense',
        ]);
    }

    public function test_deprecated_financial_settings_do_not_drive_dashboard_metrics(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        Setting::where('key', 'operational_cost_percentage')->update(['value' => '99']);
        Setting::where('key', 'market_valuation_multiplier')->update(['value' => '99']);

        $response = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'financial']));

        $response->assertOk()
            ->assertSee('Deprecated')
            ->assertDontSee('99%')
            ->assertDontSee('القيمة السوقية التقديرية')
            ->assertDontSee('تكلفة التشغيل');
    }
}
