<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_partners_module(): void
    {
        $partnerUser = User::factory()->create(['role' => 'partner']);

        $response = $this->actingAs($partnerUser)->get('/partners');
        $response->assertStatus(403);

        $response = $this->actingAs($partnerUser)->get('/partners/create');
        $response->assertStatus(403);
    }

    public function test_admin_can_create_partner_and_associated_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $data = [
            'company_name' => 'شركة جسور للتسويق',
            'email' => 'partner@jusoor.local',
            'phone' => '0799887766',
            'profit_share_percentage' => 20.00,
        ];

        $response = $this->actingAs($admin)->post('/partners', $data);

        $response->assertRedirect('/partners');
        $response->assertSessionHas('success');

        $partner = Partner::where('email', 'partner@jusoor.local')->first();
        $this->assertNotNull($partner);
        $this->assertNotEmpty($partner->public_uuid);

        // Verify partner user was created automatically
        $user = User::where('email', 'partner@jusoor.local')->first();
        $this->assertNotNull($user);
        $this->assertEquals('partner', $user->role);
        $this->assertEquals($partner->id, $user->partner_id);

        // Verify delegate link is accessible
        $delegateUrl = route('public.client.create', $partner->public_uuid);
        $publicResponse = $this->get($delegateUrl);
        $publicResponse->assertOk();
        $publicResponse->assertSee('شركة جسور للتسويق');
    }

    public function test_admin_can_update_and_delete_partner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $partner = Partner::create([
            'company_name' => 'الأفق الأولى',
            'email' => 'first@horizon.local',
            'phone' => '0790001111',
            'profit_share_percentage' => 10.00,
        ]);

        $user = User::create([
            'name' => 'الأفق الأولى',
            'email' => 'first@horizon.local',
            'password' => bcrypt('password'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        // Update
        $response = $this->actingAs($admin)->put("/partners/{$partner->id}", [
            'company_name' => 'الأفق الحديثة',
            'email' => 'updated@horizon.local',
            'phone' => '0790002222',
            'profit_share_percentage' => 18.50,
        ]);

        $response->assertRedirect('/partners');
        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'company_name' => 'الأفق الحديثة',
            'email' => 'updated@horizon.local',
        ]);

        // Delete
        $deleteResponse = $this->actingAs($admin)->delete("/partners/{$partner->id}");
        $deleteResponse->assertRedirect('/partners');

        $this->assertDatabaseMissing('partners', ['id' => $partner->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
