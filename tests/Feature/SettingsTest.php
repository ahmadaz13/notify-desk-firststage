<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Setting;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_three_company_wide_settings_sections(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('الشركة والوثائق')
            ->assertSee('العمليات')
            ->assertSee('الاشتراكات والعقود')
            ->assertDontSee('سجل النشاطات');

        $response = $this->actingAs($admin)->put(route('settings.update'), [
            'contract_prefix'=>'CTR','invoice_prefix'=>'BILL','timezone'=>'Asia/Amman','appointment_duration'=>45,
            'free_installation_duration'=>60,'post_install_followup_days'=>4,'workday_start'=>'09:00','workday_end'=>'17:00',
            'currency'=>'JOD','default_billing_cycle'=>'annual','auto_contract_on_paid_subscription'=>'1','allow_monthly'=>'1','allow_annual_installments'=>'1',
        ]);
        $response->assertRedirect();
        $this->assertSame('CTR', Setting::get('contract_prefix'));
        $this->assertSame('annual', Setting::get('default_billing_cycle'));
    }

    public function test_staff_cannot_view_or_update_settings(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('settings.update'))->assertForbidden();
    }

    public function test_settings_have_no_financial_export_route(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('settings.export'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('finance.export'));
    }
}
