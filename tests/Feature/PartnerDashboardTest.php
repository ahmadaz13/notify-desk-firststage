<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_redirected_to_partner_dashboard_after_login(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة النخبة',
            'email' => 'partner_login@example.com',
            'profit_share_percentage' => 20.00,
        ]);

        $partnerUser = User::factory()->create([
            'email' => 'partner_login@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'partner_login@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('partner.dashboard'));
        $this->assertAuthenticatedAs($partnerUser);
    }

    public function test_partner_dashboard_only_shows_their_clients(): void
    {
        $partnerA = Partner::create([
            'company_name' => 'شريك أ',
            'email' => 'partner_dash_a@example.com',
        ]);
        $userA = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partnerA->id,
        ]);

        $partnerB = Partner::create([
            'company_name' => 'شريك ب',
            'email' => 'partner_dash_b@example.com',
        ]);

        // Client belonging to Partner A
        $clientA = Client::create([
            'business_name' => 'مؤسسة الأمل للشريك أ',
            'phone' => '0791230001',
            'city_area' => 'عمان',
            'business_category' => 'تجارة',
            'lead_source' => 'Partner',
            'partner_id' => $partnerA->id,
            'status' => 'subscriber',
        ]);

        // Client belonging to Partner B
        $clientB = Client::create([
            'business_name' => 'مؤسسة الشروق للشريك ب',
            'phone' => '0791230002',
            'city_area' => 'الزرقاء',
            'business_category' => 'خدمات',
            'lead_source' => 'Partner',
            'partner_id' => $partnerB->id,
            'status' => 'subscriber',
        ]);

        // Direct company client (no partner)
        $clientDirect = Client::create([
            'business_name' => 'مكتب مباشر بدون شريك',
            'phone' => '0791230003',
            'city_area' => 'إربد',
            'business_category' => 'صحة',
            'lead_source' => 'Direct',
            'partner_id' => null,
            'status' => 'subscriber',
        ]);

        $response = $this->actingAs($userA)->get(route('partner.dashboard'));

        $response->assertOk();
        // Should see own client
        $response->assertSee('مؤسسة الأمل للشريك أ');
        // Should NOT see other partner client or direct client
        $response->assertDontSee('مؤسسة الشروق للشريك ب');
        $response->assertDontSee('مكتب مباشر بدون شريك');
    }

    public function test_partner_dashboard_does_not_show_company_financials(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك بدون مالية',
            'email' => 'partner_no_fin@example.com',
            'profit_share_percentage' => 15.00,
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($partnerUser)->get(route('partner.dashboard'));

        $response->assertOk();
        // Verify absence of sensitive administrative financial metrics
        $response->assertDontSee('لوحة المؤشرات المالية الذكية');
        $response->assertDontSee('إجمالي الإيرادات الخام (Gross Revenue)');
        $response->assertDontSee('القيمة السوقية التقديرية (Estimated Market Value)');
        $response->assertDontSee('رصيد السيولة (Liquidity Balance)');
        $response->assertDontSee('إضافة استثمار');
        $response->assertDontSee('صرف استثماري');
    }

    public function test_partner_deduction_percentage_is_used_in_earned_share(): void
    {
        // Custom 15% deduction instead of default 20%
        $partner = Partner::create([
            'company_name' => 'شريك النسبة المخصصة',
            'email' => 'custom_deduct@example.com',
            'profit_share_percentage' => 30.00,
            'deduction_percentage' => 15.00,
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $client = Client::create([
            'business_name' => 'شركة النجاح المالي',
            'phone' => '0799988877',
            'city_area' => 'عمان',
            'business_category' => 'استشارات',
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

        // Calculations:
        // Total payments = 1000.00
        // Deduction = 15% -> Net multiplier = 0.85 -> Net operating revenue = 850.00
        // Profit share = 30% of 850.00 = 255.00
        $this->assertEquals(1000.00, $partner->total_client_payments);
        $this->assertEquals(255.00, $partner->earned_share);

        $response = $this->actingAs($partnerUser)->get(route('partner.dashboard'));

        $response->assertOk();
        $response->assertSee('1,000.00');
        $response->assertSee('850.00');
        $response->assertSee('255.00');
        $response->assertSee('15.0%');
    }
}
