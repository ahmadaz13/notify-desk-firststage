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

    public function test_partner_sees_share_card_when_percentage_set(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك التقنية الحديثة',
            'email' => 'partner_tech@example.com',
            'profit_share_percentage' => 25.00,
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $client = Client::create([
            'business_name' => 'مطعم النجوم',
            'phone' => '0798765432',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Partner',
            'partner_id' => $partner->id,
            'status' => 'subscriber',
        ]);

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id,
            'user_id' => $partnerUser->id,
            'billing_type' => 'monthly',
            'total_price' => 1000.00,
            'start_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subscriptionId,
            'amount' => 1000.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $partnerUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($partnerUser)->get('/');

        $response->assertOk();
        $response->assertSee('ملخص أرباحك');
        $response->assertSee('إجمالي مدفوعات عملائك');
        $response->assertSee('1,000.00');
        $response->assertSee('صافي العائد التشغيلي');
        $response->assertSee('800.00');
        $response->assertSee('حصتك التقديرية');
        // Total payments = 1000, 80% net = 800, 25% share = 200.00
        $response->assertSee('200.00');
    }

    public function test_partner_does_not_see_share_card_when_percentage_null(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك قيد التفاوض',
            'email' => 'partner_pending@example.com',
            'profit_share_percentage' => null,
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($partnerUser)->get('/');

        $response->assertOk();
        $response->assertSee('لم يتم تحديد نسبة الربح بعد، تواصل مع الإدارة');
    }

    public function test_admin_does_not_see_share_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertDontSee('ملخص أرباحك');
        $response->assertDontSee('حصتك التقديرية');
    }

    public function test_calculation_accuracy(): void
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
            'lead_source' => 'Partner',
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

        // Insert payments: 1500.00 + 500.00 = 2000.00
        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subscriptionId,
            'amount' => 1500.00,
            'payment_method' => 'bank_transfer',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'client_id' => $client->id,
            'subscription_id' => $subscriptionId,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalClientPayments = $partner->total_client_payments;
        $netRevenue = $totalClientPayments * 0.8;
        $earnedShare = $partner->earned_share;

        $this->assertEquals(2000.00, $totalClientPayments);
        $this->assertEquals(1600.00, $netRevenue);

        // Expected earnedShare = (2000.00 * 0.8) * (30 / 100) = 480.00
        $expectedShare = (2000.00 * 0.8) * (30.00 / 100);
        $this->assertEquals(480.00, $expectedShare);
        $this->assertEquals($expectedShare, $earnedShare);
    }
}
