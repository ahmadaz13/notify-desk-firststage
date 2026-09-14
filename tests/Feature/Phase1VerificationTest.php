<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase1VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_can_login_with_exact_email(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة الأفق الرقمي',
            'email' => 'partner@al-ofuq.com',
            'phone' => '0791112233',
        ]);

        $user = User::factory()->create([
            'email' => 'partner@al-ofuq.com',
            'password' => Hash::make('secret12345'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'partner@al-ofuq.com',
            'password' => 'secret12345',
        ]);

        $response->assertRedirect(route('partner.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_partner_can_login_with_whitespace_and_uppercase_email(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة النخبة',
            'email' => 'partner@al-nukhba.com',
        ]);

        $user = User::factory()->create([
            'email' => 'partner@al-nukhba.com',
            'password' => Hash::make('secret12345'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        // Login with uppercase and surrounding whitespace
        $response = $this->post(route('login.store'), [
            'email' => '  PARTNER@AL-NUKHBA.COM  ',
            'password' => 'secret12345',
        ]);

        $response->assertRedirect(route('partner.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_partner_can_login_with_phone_number_identifier(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة المجد',
            'email' => 'contact@al-majd.com',
            'phone' => '0795554433',
        ]);

        $user = User::factory()->create([
            'email' => 'login_user@al-majd.com',
            'password' => Hash::make('secret12345'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        // Login using the partner's registered phone number
        $response = $this->post(route('login.store'), [
            'email' => '0795554433',
            'password' => 'secret12345',
        ]);

        $response->assertRedirect(route('partner.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_partner_can_login_with_company_email_when_user_email_differs(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة الرؤية',
            'email' => 'company@al-roya.com',
        ]);

        $user = User::factory()->create([
            'email' => 'agent@demo.com',
            'password' => Hash::make('secret12345'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        // Login using company's email instead of user account email
        $response = $this->post(route('login.store'), [
            'email' => 'company@al-roya.com',
            'password' => 'secret12345',
        ]);

        $response->assertRedirect(route('partner.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_suspended_partner_account(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة معلقة',
            'email' => 'suspended@partner.com',
            'status' => 'suspended',
        ]);

        $user = User::factory()->create([
            'email' => 'suspended@partner.com',
            'password' => Hash::make('secret12345'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'suspended@partner.com',
            'password' => 'secret12345',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_first_login_sets_timestamps_and_shows_welcome(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة الانطلاق',
            'email' => 'new@partner.com',
            'status' => 'active',
            'onboarded_at' => null,
        ]);

        $user = User::factory()->create([
            'email' => 'new@partner.com',
            'password' => Hash::make('secret12345'),
            'role' => 'partner',
            'partner_id' => $partner->id,
            'first_login_at' => null,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'new@partner.com',
            'password' => 'secret12345',
        ]);

        $response->assertRedirect(route('partner.dashboard'));
        $response->assertSessionHas('is_first_login', true);

        $user->refresh();
        $partner->refresh();
        $this->assertNotNull($user->first_login_at);
        $this->assertNotNull($partner->onboarded_at);
    }

    public function test_admin_reset_password_creates_audit_log_and_expires(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $partner = Partner::create([
            'company_name' => 'شركة التجربة',
            'email' => 'trial@partner.com',
        ]);

        $partnerUser = User::factory()->create([
            'email' => 'trial@partner.com',
            'password' => Hash::make('initialpassword'),
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($admin)->post(route('partners.reset-password', $partner->id));

        $response->assertRedirect(route('partners.index'));
        $response->assertSessionHas('reset_partner_credentials');

        // Verify audit log created
        $this->assertDatabaseHas('activity_logs', [
            'type' => 'partner_password_reset',
            'user_id' => $admin->id,
        ]);

        $partnerUser->refresh();
        $this->assertNotNull($partnerUser->reset_expires_at);
        $this->assertTrue($partnerUser->reset_expires_at->isFuture());

        // Old password must fail
        $this->assertFalse(Hash::check('initialpassword', $partnerUser->password));
    }

    public function test_partner_url_tampering_cannot_view_other_partner_data(): void
    {
        $partnerA = Partner::create([
            'company_name' => 'الشريك الأول',
            'email' => 'partnerA@test.com',
        ]);
        $partnerUserA = User::factory()->create([
            'email' => 'userA@test.com',
            'role' => 'partner',
            'partner_id' => $partnerA->id,
        ]);

        $partnerB = Partner::create([
            'company_name' => 'الشريك الثاني',
            'email' => 'partnerB@test.com',
        ]);
        $clientB = Client::create([
            'business_name' => 'عميل الشريك الثاني الخاص',
            'phone' => '0799991122',
            'city_area' => 'إربد',
            'business_category' => 'تقنية',
            'lead_source' => 'Direct',
            'partner_id' => $partnerB->id,
            'primary_owner_id' => $partnerUserA->id,
        ]);

        // Partner A attempts URL tampering with ?partner_id=B
        $response = $this->actingAs($partnerUserA)->get(route('partner.dashboard', ['partner_id' => $partnerB->id]));
        $response->assertOk();
        $response->assertSee('الشريك الأول');
        $response->assertDontSee('الشريك الثاني');
        $response->assertDontSee('عميل الشريك الثاني الخاص');

        // Direct URL tampering on client detail
        $forbiddenResponse = $this->actingAs($partnerUserA)->get(route('clients.show', $clientB->id));
        $forbiddenResponse->assertForbidden();
    }

    public function test_canonical_mode_server_rendering(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        // Default mode is daily
        $responseDaily = $this->actingAs($admin)->get(route('dashboard'));
        $responseDaily->assertOk();
        $responseDaily->assertViewHas('currentMode', 'daily');

        // Explicit ?mode=financial is accepted canonically
        $responseFinancial = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'financial']));
        $responseFinancial->assertOk();
        $responseFinancial->assertViewHas('currentMode', 'financial');
        $responseFinancial->assertSee('لوحة المؤشرات المالية الذكية (Financial Dashboard)');

        // Invalid mode safely falls back to daily
        $responseInvalid = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'hacked_mode']));
        $responseInvalid->assertOk();
        $responseInvalid->assertViewHas('currentMode', 'daily');
    }

    public function test_mobile_financial_cards_are_rendered(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('dashboard', ['mode' => 'financial']));
        $response->assertOk();

        // Verify all 6 financial valuation cards are rendered in HTML
        $response->assertSee('financial-cards-grid');
        $response->assertSee('إجمالي الإيرادات الخام (Gross Revenue)');
        $response->assertSee('تكلفة التشغيل (Operating Cost)');
        $response->assertSee('صافي الإيراد التشغيلي (Net Operating Revenue)');
        $response->assertSee('صافي الربح بعد المصاريف الفعلية');
        $response->assertSee('القيمة السوقية التقديرية (Estimated Market Value)');
        $response->assertSee('رصيد السيولة (Liquidity Balance)');
    }

    public function test_fab_touch_target_and_accessibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk();

        // FAB is present, accessible, and has 48px touch target styling
        $response->assertSee('id="quick-expense-fab-btn"', false);
        $response->assertSee('aria-label="تسجيل مصروف سريع (أقل من 10 ثوانٍ)"', false);
        $response->assertSee('min-height:48px', false);
        $response->assertSee('safe-area-inset-bottom', false);
    }
}
