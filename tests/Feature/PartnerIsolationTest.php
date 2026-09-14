<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartnerIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_cannot_access_main_dashboard(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة الشريك أ',
            'email' => 'partner_a@example.com',
            'profit_share_percentage' => 20.00,
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        // When partner accesses the root URL /
        $response = $this->actingAs($partnerUser)->get('/');

        $response->assertOk();
        // Must NOT see company-wide smart financial dashboard
        $response->assertDontSee('لوحة المؤشرات المالية الذكية');
        $response->assertDontSee('إجمالي الإيرادات الخام');
        $response->assertDontSee('Gross Revenue');
        $response->assertDontSee('القيمة السوقية التقديرية');
        $response->assertDontSee('رصيد السيولة');
        $response->assertDontSee('Liquidity Balance');
        $response->assertDontSee('إضافة استثمار جديد');
        $response->assertDontSee('تسجيل صرف استثماري');
    }

    public function test_partner_cannot_post_to_investments_endpoint(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة أ',
            'email' => 'partner_inv@example.com',
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($partnerUser)->post('/investments', [
            'investor_name' => 'مستثمر وهمي',
            'amount' => 5000.00,
            'entry_date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('investments', [
            'investor_name' => 'مستثمر وهمي',
        ]);
    }

    public function test_partner_cannot_post_to_capital_expenses_endpoint(): void
    {
        $partner = Partner::create([
            'company_name' => 'شركة أ',
            'email' => 'partner_capex@example.com',
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($partnerUser)->post('/capital-expenses', [
            'description' => 'صرف رأسمالي غير مصرح',
            'amount' => 1200.00,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('capital_expenses', [
            'description' => 'صرف رأسمالي غير مصرح',
        ]);
    }

    public function test_admin_can_post_to_investments_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/investments', [
            'investor_name' => 'مستثمر حقيقي',
            'amount' => 10000.00,
            'entry_date' => now()->toDateString(),
            'notes' => 'ملاحظة استثمار',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('investments', [
            'investor_name' => 'مستثمر حقيقي',
            'amount' => 10000.00,
        ]);
    }

    public function test_admin_can_post_to_capital_expenses_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/capital-expenses', [
            'description' => 'شراء حواسيب للعمليات',
            'amount' => 2500.00,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('capital_expenses', [
            'description' => 'شراء حواسيب للعمليات',
            'amount' => 2500.00,
        ]);
    }

    public function test_notifications_scoped_to_owning_partner_only(): void
    {
        Carbon::setTestNow('2026-09-13 10:00:00');

        $admin = User::factory()->create(['role' => 'admin']);

        $partnerA = Partner::create([
            'company_name' => 'شريك أ',
            'email' => 'part_a@example.com',
        ]);
        $userA = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partnerA->id,
        ]);

        $partnerB = Partner::create([
            'company_name' => 'شريك ب',
            'email' => 'part_b@example.com',
        ]);
        $userB = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partnerB->id,
        ]);

        // Client belonging to Partner A
        $clientA = Client::create([
            'business_name' => 'عميل الشريك أ',
            'phone' => '0791111111',
            'city_area' => 'عمان',
            'business_category' => 'تقنية',
            'lead_source' => 'Partner',
            'partner_id' => $partnerA->id,
            'status' => 'prospect',
        ]);

        // Appointment for Client A today within 2 hours
        $aptId = DB::table('appointments')->insertGetId([
            'client_id' => $clientA->id,
            'appointment_date' => '2026-09-13',
            'appointment_time' => '11:00',
            'appointment_type' => 'meeting',
            'status' => 'scheduled',
            'location' => 'مكتب العميل',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(NotificationService::class);
        $service->sendAppointmentReminders();

        // Admin MUST receive notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'source_id' => $aptId,
        ]);

        // Partner A (owner) MUST receive notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userA->id,
            'source_id' => $aptId,
        ]);

        // Partner B (uninvolved) MUST NOT receive notification
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $userB->id,
            'source_id' => $aptId,
        ]);
    }

    public function test_csv_import_assigns_partner_id_for_partner_users(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك الاستيراد',
            'email' => 'partner_import@example.com',
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $csvService = app(CsvImportService::class);

        $validRows = [
            [
                'data' => [
                    'business_name' => 'متجر المستقبل',
                    'phone' => '0799887766',
                    'contact_person' => 'خالد',
                    'city_area' => 'عمان',
                    'business_category' => 'تجارة',
                    'lead_source' => 'معرض',
                    'notes' => 'عميل مستورد',
                ],
            ],
        ];

        $imported = $csvService->importValidRows($validRows, 'prospect', $partnerUser->id);

        $this->assertEquals(1, $imported);
        $this->assertDatabaseHas('clients', [
            'business_name' => 'متجر المستقبل',
            'phone' => '0799887766',
            'partner_id' => $partner->id,
            'primary_owner_id' => $partnerUser->id,
        ]);
    }

    public function test_admin_navigation_not_visible_to_partner(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك بدون روابط إدارة',
            'email' => 'no_admin_links@example.com',
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $response = $this->actingAs($partnerUser)->get(route('partner.dashboard'));

        $response->assertOk();
        $response->assertDontSee('المزيد');
        $response->assertDontSee('التعارضات');
        $response->assertDontSee('#money');
        $response->assertDontSee('>المال<', false);
        $response->assertDontSee('استيراد CSV');

        // Also check layouts/app when partner visits clients index
        $responseClients = $this->actingAs($partnerUser)->get(route('clients.index'));
        $responseClients->assertOk();
        $responseClients->assertDontSee('المزيد');
        $responseClients->assertDontSee('التعارضات');
        $responseClients->assertDontSee('#money');
        $responseClients->assertDontSee('>المال<', false);
        $responseClients->assertDontSee('استيراد CSV');
    }
}
