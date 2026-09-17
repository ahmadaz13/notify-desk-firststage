<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartnerShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_share_values_remain_historical_metadata_only(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك الحساب الدقيق',
            'email' => 'calc_accuracy@example.com',
            'profit_share_percentage' => 30.00,
        ]);
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'business_name' => 'عيادة الشفاء',
            'phone' => '0791122334',
            'city_area' => 'إربد',
            'business_category' => 'طب وصحة',
            'lead_source' => 'partner',
            'partner_id' => $partner->id,
            'status' => 'subscriber',
        ]);

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'billing_type' => 'monthly',
            'total_price' => 2000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            [
                'client_id' => $client->id,
                'subscription_id' => $subscriptionId,
                'amount' => 1500.00,
                'payment_method' => 'bank_transfer',
                'paid_at' => now(),
                'recorded_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_id' => $client->id,
                'subscription_id' => $subscriptionId,
                'amount' => 500.00,
                'payment_method' => 'cash',
                'paid_at' => now(),
                'recorded_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertEquals(2000.00, $partner->total_client_payments);
        $this->assertEquals(480.00, $partner->earned_share);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_internal_dashboard_does_not_show_partner_share_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertDontSee('ملخص أرباحك');
        $response->assertDontSee('حصتك التقديرية');
    }
}
