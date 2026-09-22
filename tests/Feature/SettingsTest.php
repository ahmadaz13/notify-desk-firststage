<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_settings_page(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        Partner::create([
            'company_name' => 'شركة الأفق الرقمي',
            'email' => 'horizon@partner.local',
            'profit_share_percentage' => 20.00,
        ]);

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('مركز التحكم والإعدادات');
        $response->assertSee('الإعدادات التشغيلية');
        $response->assertSee('مراجع الشركاء وروابط الإحالة');
        $response->assertSee('سجل النشاطات');
        $response->assertSee('تصدير التقرير المالي الشامل');
        $response->assertSee('شركة الأفق الرقمي');
    }

    public function test_non_admin_cannot_view_settings_page(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك غير مصرح',
            'email' => 'unauthorized@partner.local',
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($partnerUser)->get(route('settings.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_update_active_transfer_setting(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $data = [
            'allow_auto_transfer_clients' => '1',
        ];

        $response = $this->actingAs($admin)->post(route('settings.update'), $data);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success', 'تم حفظ الإعدادات بنجاح.');

        $this->assertEquals('1', Setting::get('allow_auto_transfer_clients'));
        $this->assertNull(Setting::get('operational_cost_percentage'));
        $this->assertNull(Setting::get('market_valuation_multiplier'));
        $this->assertNull(Setting::get('annual_discount_percentage'));
        $this->assertNull(Setting::get('sales_tax_percentage'));
        $this->assertNull(Setting::get('monthly_due_day'));
    }

    public function test_activity_log_displays_recent_entries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = \App\Models\Client::create([
            'business_name' => 'شركة النشاط التجاري',
            'phone' => '0791234567',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'Direct',
            'status' => 'prospect',
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'type' => 'client_created_by_delegate',
            'description' => 'تم إنشاء عميل جديد من خلال مندوب الشريك',
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'type' => 'client_transferred',
            'description' => 'تم نقل ملكية العميل بعد حل التعارض',
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('client_created_by_delegate');
        $response->assertSee('تم إنشاء عميل جديد من خلال مندوب الشريك');
        $response->assertSee('client_transferred');
        $response->assertSee('تم نقل ملكية العميل بعد حل التعارض');

        // Test filtering by type
        $filterResponse = $this->actingAs($admin)->get(route('settings.index', ['type' => 'client_created_by_delegate']));
        $filterResponse->assertOk();
        $filterResponse->assertSee('تم إنشاء عميل جديد من خلال مندوب الشريك');
        $filterResponse->assertDontSee('تم نقل ملكية العميل بعد حل التعارض');
    }

    public function test_export_download_returns_excel_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('settings.export'));

        $response->assertOk();
        $response->assertDownload('financial_report.xlsx');
        $this->assertStringContainsString('FinancialStatementService', $response->streamedContent());
    }
}
