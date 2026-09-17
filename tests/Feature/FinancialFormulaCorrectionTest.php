<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialFormulaCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_gross_revenue_formula_is_not_used_by_dashboard_financial_mode(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient();

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'amount' => 50000.00,
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
            ->assertDontSee('إجمالي الإيرادات الخام');
    }

    public function test_legacy_settings_are_marked_deprecated_and_do_not_change_dashboard_authority(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        Setting::set('operational_cost_percentage', '77');
        Setting::set('market_valuation_multiplier', '88');

        $settings = $this->actingAs($admin)->get(route('settings.index'));
        $settings->assertOk()
            ->assertSee('Deprecated')
            ->assertSee('لا تؤثر على P&amp;L أو Finance أو Executive', false)
            ->assertSee('لا توجد قيمة سوقية تقديرية في V1');

        $dashboard = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'financial']));
        $dashboard->assertOk()
            ->assertDontSee('77%')
            ->assertDontSee('تكلفة التشغيل')
            ->assertDontSee('القيمة السوقية التقديرية');
    }

    public function test_finance_executive_and_saas_routes_do_not_use_legacy_dashboard_formulas(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('finance.index'))
            ->assertOk()
            ->assertSee('الإيراد المعترف به')
            ->assertDontSee('Gross Revenue')
            ->assertDontSee('Estimated Market Value');

        $this->actingAs($admin)->get(route('executive.index'))
            ->assertOk()
            ->assertSee('MRR/ARR = مقاييس عقود SaaS')
            ->assertDontSee('Estimated Market Value');

        $this->actingAs($admin)->get(route('saas-metrics.index'))
            ->assertOk()
            ->assertSee('مقاييس الاشتراكات والاحتفاظ (SaaS Metrics)')
            ->assertDontSee('إجمالي الإيرادات الخام')
            ->assertDontSee('تكلفة التشغيل');
    }

    public function test_partner_share_accessors_remain_historical_metadata_only(): void
    {
        $partner = Partner::create([
            'company_name' => 'Historical Partner',
            'email' => 'historical-partner@example.com',
            'profit_share_percentage' => 40.00,
            'deduction_percentage' => 15.00,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->createClient(['partner_id' => $partner->id, 'lead_source' => 'Partner']);

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'amount' => 2000.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(2000.00, $partner->total_client_payments);
        $this->assertEquals(680.00, $partner->earned_share);
        $this->actingAs($admin)->get(route('dashboard', ['mode' => 'financial']))
            ->assertOk()
            ->assertDontSee('680.00');
    }

    private function createClient(array $attributes = []): Client
    {
        return Client::create($attributes + [
            'business_name' => 'G1 Client',
            'phone' => '0791234567',
            'city_area' => 'Amman',
            'business_category' => 'Retail',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
        ]);
    }
}
