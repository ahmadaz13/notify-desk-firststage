<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PartnerCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_password_is_flashed_on_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('partners.store'), [
            'company_name' => 'شركة الريادة الأولى',
            'email' => 'partner_created@example.com',
            'phone' => '0797766554',
            'profit_share_percentage' => 20.00,
            'deduction_percentage' => 20.00,
        ]);

        $response->assertRedirect(route('partners.index'));
        $response->assertSessionHas('new_partner_credentials');

        $credentials = session('new_partner_credentials');
        $this->assertIsArray($credentials);
        $this->assertEquals('شركة الريادة الأولى', $credentials['company_name']);
        $this->assertEquals('partner_created@example.com', $credentials['email']);
        $this->assertNotEmpty($credentials['password']);
        $this->assertStringContainsString('/login', $credentials['login_url']);
        $this->assertStringContainsString('/p/', $credentials['delegate_link']);

        // Verify password works to authenticate the user
        $partnerUser = User::where('email', 'partner_created@example.com')->first();
        $this->assertNotNull($partnerUser);
        $this->assertTrue(Hash::check($credentials['password'], $partnerUser->password));
    }

    public function test_partner_can_reset_password_via_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $partner = Partner::create([
            'company_name' => 'شركة الأفق الجديد',
            'email' => 'partner_reset@example.com',
        ]);

        $partnerUser = User::factory()->create([
            'email' => 'partner_reset@example.com',
            'password' => Hash::make('oldpassword123'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($admin)->post(route('partners.reset-password', $partner->id));

        $response->assertRedirect(route('partners.index'));
        $response->assertSessionHas('reset_partner_credentials');

        $credentials = session('reset_partner_credentials');
        $this->assertIsArray($credentials);
        $this->assertNotEmpty($credentials['password']);
        $this->assertNotEquals('oldpassword123', $credentials['password']);

        // Refresh user and verify new password
        $partnerUser->refresh();
        $this->assertTrue(Hash::check($credentials['password'], $partnerUser->password));
    }
}
