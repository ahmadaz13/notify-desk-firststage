<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_normalization(): void
    {
        $service = new CsvImportService();
        $this->assertEquals('791234567', $service->normalizePhone('079 123 4567'));
        $this->assertEquals('791234567', $service->normalizePhone('+962791234567'));
        $this->assertEquals('791234567', $service->normalizePhone('00962-79-123-4567'));
        $this->assertEquals('788889999', $service->normalizePhone('078-888-9999'));
    }

    public function test_csv_preview_detects_duplicates_and_invalid(): void
    {
        $user = User::factory()->create();

        // Existing client in DB
        DB::table('clients')->insert([
            'business_name' => 'مطعم الأصالة',
            'phone' => '0791112233',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $csvContent = implode("\n", [
            'business_name,phone,contact_person,city_area,business_category,lead_source,notes',
            'عميل صالح,0799991122,سامر,خلدا,مبيعات,Maps,ملاحظة',
            'عميل مكرر داتابيز,079 111 2233,أحمد,عمان,مطاعم,Google,مكرر مع الداتابيز',
            'عميل مكرر ملف 1,0785556677,عمر,تلاع العلي,خدمات,Direct,السطر الأول',
            'عميل مكرر ملف 2,078-555-6677,خالد,تلاع العلي,خدمات,Direct,السطر الثاني المكرر',
            ',0771231234,طارق,الجبيهة,تقنية,Social,اسم النشاط مفقود',
        ]);

        $file = UploadedFile::fake()->createWithContent('prospects.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('clients.import.preview'), [
            'csv_file' => $file,
            'type' => 'prospect',
        ]);

        $response->assertOk();
        $response->assertViewIs('clients.import');

        $preview = $response->viewData('preview');
        $this->assertCount(2, $preview['valid']); // عميل صالح + عميل مكرر ملف 1
        $this->assertCount(2, $preview['duplicates']); // عميل مكرر داتابيز + عميل مكرر ملف 2
        $this->assertCount(1, $preview['invalid']); // اسم النشاط مفقود
    }

    public function test_csv_confirm_imports_valid_prospects(): void
    {
        $user = User::factory()->create();

        $validRows = [
            [
                'row' => 2,
                'data' => [
                    'business_name' => 'مكتبة الفجر',
                    'phone' => '0797778899',
                    'contact_person' => 'زيد',
                    'city_area' => 'شارع الجامعة',
                    'business_category' => 'مكتبات',
                    'lead_source' => 'Maps',
                    'notes' => 'تجربة استيراد',
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->withSession([
                'csv_import_valid' => $validRows,
                'csv_import_type' => 'prospect',
            ])
            ->post(route('clients.import.confirm'));

        $response->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'business_name' => 'مكتبة الفجر',
            'phone' => '0797778899',
            'status' => 'prospect',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'type' => 'client_imported',
        ]);
    }

    public function test_csv_confirm_subscriber_type_imports_prospect_without_billing_records(): void
    {
        $user = User::factory()->create();

        $validRows = [
            [
                'row' => 2,
                'data' => [
                    'business_name' => 'شركة الأفق الرقمي',
                    'phone' => '0794443322',
                    'contact_person' => 'م. لؤي',
                    'city_area' => 'مجمع الملك حسين',
                    'business_category' => 'تقنية',
                    'lead_source' => 'Direct',
                    'billing_type' => 'monthly',
                    'total_price' => '600',
                    'start_date' => now()->toDateString(),
                    'notes' => 'مشترك جديد',
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->withSession([
                'csv_import_valid' => $validRows,
                'csv_import_type' => 'subscriber',
            ])
            ->post(route('clients.import.confirm'));

        $response->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'business_name' => 'شركة الأفق الرقمي',
            'status' => 'prospect',
        ]);

        $this->assertEquals(0, DB::table('subscriptions')->count());
        $this->assertEquals(0, DB::table('payment_schedules')->count());
    }
}
