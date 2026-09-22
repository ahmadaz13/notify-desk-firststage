<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PartnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_login_is_unavailable(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة النخبة',
            'email' => 'partner_login@example.com',
        ]);

        User::factory()->create([
            'email' => 'partner_login@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $this->post('/login', [
            'email' => 'partner_login@example.com',
            'password' => 'secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_legacy_partner_role_is_blocked_from_authenticated_app_routes(): void
    {
        $partnerUser = User::factory()->create(['role' => 'partner']);

        $this->actingAs($partnerUser)
            ->get(route('clients.index'))
            ->assertForbidden();
    }
}
