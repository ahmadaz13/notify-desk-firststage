<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_creation_does_not_create_active_login_account_or_credentials(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('partners.store'), [
            'company_name' => 'شركة الريادة الأولى',
            'email' => 'partner_created@example.com',
            'phone' => '0797766554',
            'profit_share_percentage' => 20.00,
            'deduction_percentage' => 20.00,
            'password' => 'ignored-password',
        ]);

        $response->assertRedirect(route('partners.index'));
        $response->assertSessionMissing('new_partner_credentials');
        $response->assertSessionHas('new_delegate_link');

        $this->assertDatabaseHas('partners', ['email' => 'partner_created@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'partner_created@example.com']);
    }

}
