<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_settings_and_activity_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        DB::table('activity_logs')->insert([
            'user_id' => $admin->id,
            'type' => 'settings_test',
            'description' => 'Visible audit entry',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Visible audit entry');
    }

    public function test_staff_cannot_view_settings_or_export_data(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.export'))->assertForbidden();
    }

    public function test_admin_can_download_export(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('settings.export'))
            ->assertOk()
            ->assertDownload('financial_report.xlsx');
    }
}
