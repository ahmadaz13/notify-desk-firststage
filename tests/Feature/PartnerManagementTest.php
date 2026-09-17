<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_internal_or_non_admin_cannot_access_partners_module(): void
    {
        $partnerUser = User::factory()->create(['role' => 'partner']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($partnerUser)->get('/partners')->assertForbidden();
        $this->actingAs($staff)->get('/partners')->assertForbidden();
    }

    public function test_admin_can_create_partner_referrer_without_user_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/partners', [
            'company_name' => 'شركة جسور للتسويق',
            'email' => 'partner@jusoor.local',
            'phone' => '0799887766',
            'profit_share_percentage' => 20.00,
        ]);

        $response->assertRedirect('/partners');
        $response->assertSessionMissing('new_partner_credentials');

        $partner = Partner::where('email', 'partner@jusoor.local')->first();
        $this->assertNotNull($partner);
        $this->assertNotEmpty($partner->public_uuid);
        $this->assertDatabaseMissing('users', ['email' => 'partner@jusoor.local']);

        $this->get(route('public.client.create', $partner->public_uuid))->assertOk();
    }

    public function test_admin_update_and_archive_preserve_partner_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Partner::create([
            'company_name' => 'الأفق الأولى',
            'email' => 'first@horizon.local',
            'phone' => '0790001111',
            'profit_share_percentage' => 10.00,
        ]);

        $this->actingAs($admin)->put("/partners/{$partner->id}", [
            'company_name' => 'الأفق الحديثة',
            'email' => 'updated@horizon.local',
            'phone' => '0790002222',
            'profit_share_percentage' => 18.50,
        ])->assertRedirect('/partners');

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'company_name' => 'الأفق الحديثة',
            'email' => 'updated@horizon.local',
        ]);

        $this->actingAs($admin)->delete("/partners/{$partner->id}")->assertRedirect('/partners');

        $this->assertDatabaseHas('partners', ['id' => $partner->id, 'status' => 'suspended']);
    }
}
