<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase1InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_seeder_populates_expected_settings(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertDatabaseHas('settings', [
            'key' => 'allow_auto_transfer_clients',
            'value' => 'false',
        ]);
    }

    public function test_user_roles_and_partner_relationship(): void
    {
        $partner = Partner::create([
            'company_name' => 'Growth Partners LLC',
            'email' => 'contact@growthpartners.com',
            'phone' => '0791112233',
            'profit_share_percentage' => 15.00,
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@notify.local',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);

        $partnerUser = User::create([
            'name' => 'Partner User',
            'email' => 'user@growthpartners.com',
            'password' => bcrypt('secret'),
            'partner_id' => $partner->id,
            'role' => 'partner',
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isPartner());

        $this->assertFalse($partnerUser->isAdmin());
        $this->assertFalse($partnerUser->isPartner());
        $this->assertTrue($partnerUser->hasLegacyPartnerRole());
        $this->assertFalse($partnerUser->isActiveApplicationUser());
        $this->assertEquals($partner->id, $partnerUser->partner->id);
    }

    public function test_partner_accessors_and_relationships(): void
    {
        $partner = Partner::create([
            'company_name' => 'Apex Agency',
            'email' => 'apex@agency.local',
            'profit_share_percentage' => 25.00,
        ]);

        $this->assertNotEmpty($partner->public_uuid);

        $client = Client::create([
            'business_name' => 'Tech Corp',
            'phone' => '0799998888',
            'city_area' => 'Amman',
            'business_category' => 'Technology',
            'lead_source' => 'Partner Referral',
            'partner_id' => $partner->id,
            'status' => 'subscriber',
        ]);

        $this->assertEquals($partner->id, $client->partner->id);

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => User::factory()->create()->id,
            'billing_type' => 'monthly',
            'total_price' => 1000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subscriptionId,
            'amount' => 600.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subscriptionId,
            'amount' => 400.00,
            'payment_method' => 'bank_transfer',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Total client payments = 1000
        $this->assertEquals(1000.00, $partner->total_client_payments);

        // Earned share = (1000 * 0.8) * (25 / 100) = 800 * 0.25 = 200.00
        $this->assertEquals(200.00, $partner->earned_share);

        // If profit_share_percentage is null
        $partnerNoShare = Partner::create([
            'company_name' => 'No Share Partner',
            'email' => 'noshare@local.test',
            'profit_share_percentage' => null,
        ]);
        $this->assertNull($partnerNoShare->earned_share);
    }

    public function test_conflict_resolution_request_relationships(): void
    {
        $partner = Partner::create([
            'company_name' => 'Test Partner',
            'email' => 'partner@test.local',
        ]);

        $client = Client::create([
            'business_name' => 'Existing Shop',
            'phone' => '0770001122',
            'city_area' => 'Zarqa',
            'business_category' => 'Retail',
            'lead_source' => 'Direct',
            'status' => 'prospect',
        ]);

        $resolver = User::factory()->create(['role' => 'admin']);

        $request = ConflictResolutionRequest::create([
            'partner_id' => $partner->id,
            'client_id' => $client->id,
            'submitted_phone' => '0770001122',
            'submitted_name' => 'Existing Shop Modified',
            'status' => 'pending',
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
        ]);

        $this->assertEquals($partner->id, $request->partner->id);
        $this->assertEquals($client->id, $request->client->id);
        $this->assertEquals($resolver->id, $request->resolvedBy->id);
    }
}
