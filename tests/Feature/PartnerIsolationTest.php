<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartnerIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_partner_role_cannot_access_authenticated_app(): void
    {
        $partnerUser = User::factory()->create(['role' => 'partner']);

        $this->actingAs($partnerUser)->get('/')->assertForbidden();
        $this->actingAs($partnerUser)->post('/investments', [
            'investor_name' => 'مستثمر وهمي',
            'amount' => 5000.00,
            'entry_date' => now()->toDateString(),
        ])->assertNotFound();
        $this->actingAs($partnerUser)->post('/capital-expenses', [
            'description' => 'صرف رأسمالي غير مصرح',
            'amount' => 1200.00,
            'expense_date' => now()->toDateString(),
        ])->assertNotFound();
    }

    public function test_active_notifications_are_internal_only(): void
    {
        Carbon::setTestNow('2026-09-13 10:00:00');

        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $partner = Partner::create(['company_name' => 'شريك أ', 'email' => 'part_a@example.com']);
        $partnerUser = User::factory()->create(['role' => 'partner', 'partner_id' => $partner->id]);
        $client = Client::create([
            'business_name' => 'عميل إحالة',
            'phone' => '0791111111',
            'city_area' => 'عمان',
            'business_category' => 'تقنية',
            'lead_source' => 'partner',
            'partner_id' => $partner->id,
            'status' => 'prospect',
        ]);

        $aptId = DB::table('appointments')->insertGetId([
            'client_id' => $client->id,
            'appointment_date' => '2026-09-13',
            'appointment_time' => '11:00',
            'appointment_type' => 'meeting',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(NotificationService::class)->sendAppointmentReminders();

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'source_id' => $aptId]);
        $this->assertDatabaseHas('notifications', ['user_id' => $staff->id, 'source_id' => $aptId]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $partnerUser->id, 'source_id' => $aptId]);

        Carbon::setTestNow();
    }

    public function test_csv_import_is_internal_only_and_does_not_create_partner_ownership(): void
    {
        $partner = Partner::create(['company_name' => 'شريك الاستيراد', 'email' => 'partner_import@example.com']);
        $partnerUser = User::factory()->create(['role' => 'partner', 'partner_id' => $partner->id]);
        $staff = User::factory()->create(['role' => 'staff']);

        $rows = [
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

        $this->expectException(AuthorizationException::class);
        app(CsvImportService::class)->importValidRows($rows, 'prospect', $partnerUser->id);
    }

    public function test_internal_csv_import_does_not_assign_partner_ownership(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $imported = app(CsvImportService::class)->importValidRows([
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
        ], 'prospect', $staff->id);

        $this->assertSame(1, $imported);
        $this->assertDatabaseHas('clients', [
            'business_name' => 'متجر المستقبل',
            'phone' => '0799887766',
            'partner_id' => null,
            'primary_owner_id' => $staff->id,
        ]);
    }

    public function test_admin_legacy_financial_write_endpoints_are_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/investments', [
            'investor_name' => 'مستثمر حقيقي',
            'amount' => 10000.00,
            'entry_date' => now()->toDateString(),
        ])->assertNotFound();

        $this->actingAs($admin)->post('/capital-expenses', [
            'description' => 'شراء حواسيب للعمليات',
            'amount' => 2500.00,
            'expense_date' => now()->toDateString(),
        ])->assertNotFound();
    }
}
