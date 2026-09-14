<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Partner $partnerA;
    protected User $partnerUserA;
    protected Partner $partnerB;
    protected User $partnerUserB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create([
            'email' => 'admin@notify.local',
            'role' => 'admin',
        ]);

        $this->partnerA = Partner::create([
            'company_name' => 'Partner A Telecom',
            'contact_person' => 'Ahmad Partner A',
            'email' => 'partnerA@notify.local',
            'phone' => '0791111111',
            'commission_rate' => 15.00,
            'is_active' => true,
        ]);

        $this->partnerUserA = User::factory()->create([
            'email' => 'userA@partner.local',
            'role' => 'partner',
            'partner_id' => $this->partnerA->id,
        ]);

        $this->partnerB = Partner::create([
            'company_name' => 'Partner B Solutions',
            'contact_person' => 'Sami Partner B',
            'email' => 'partnerB@notify.local',
            'phone' => '0792222222',
            'commission_rate' => 10.00,
            'is_active' => true,
        ]);

        $this->partnerUserB = User::factory()->create([
            'email' => 'userB@partner.local',
            'role' => 'partner',
            'partner_id' => $this->partnerB->id,
        ]);
    }

    private function createClientAndSubscription(?int $partnerId = null): array
    {
        $client = Client::create([
            'business_name' => 'مطعم القدس العربي',
            'phone' => '0795554433',
            'city_area' => 'عمان - الجبيهة',
            'business_category' => 'مطاعم وكافيهات',
            'lead_source' => 'field_visit',
            'status' => 'subscriber',
            'partner_id' => $partnerId,
            'created_by' => $this->admin->id,
        ]);

        $subscription = Subscription::create([
            'client_id' => $client->id,
            'user_id' => $this->admin->id,
            'billing_type' => 'annual',
            'total_price' => 500.000,
            'base_subtotal' => 500.000,
            'setup_fee' => 50.000,
            'annual_discount_percentage' => 10.00,
            'discount_amount' => 50.000,
            'tax_percentage' => 16.00,
            'tax_amount' => 80.000,
            'grand_total' => 580.000,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'version' => 1,
        ]);

        DB::table('payment_schedules')->insert([
            'subscription_id' => $subscription->id,
            'sequence' => 1,
            'due_date' => now()->toDateString(),
            'amount_due' => 580.000,
            'subtotal' => 500.000,
            'discount_amount' => 50.000,
            'setup_fee_amount' => 50.000,
            'tax_amount' => 80.000,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$client, $subscription];
    }

    public function test_sequential_contract_number_generation_and_uniqueness(): void
    {
        $service = app(ContractService::class);
        $year = now()->format('Y');

        $num1 = $service->generateNextContractNumber();
        $this->assertEquals("ND-{$year}-0001", $num1);

        [$client, $subscription] = $this->createClientAndSubscription();
        $contract1 = $service->createContract($client, $subscription, $this->admin);
        $this->assertEquals("ND-{$year}-0001", $contract1->contract_number);

        $num2 = $service->generateNextContractNumber();
        $this->assertEquals("ND-{$year}-0002", $num2);

        $contract2 = $service->createContract($client, $subscription, $this->admin);
        $this->assertEquals("ND-{$year}-0002", $contract2->contract_number);
    }

    public function test_contract_snapshot_immutability(): void
    {
        $service = app(ContractService::class);
        [$client, $subscription] = $this->createClientAndSubscription();

        $contract = $service->createContract($client, $subscription, $this->admin);

        // Verify initial snapshot values
        $this->assertEquals('مطعم القدس العربي', $contract->snapshot_data['client']['business_name']);
        $this->assertEquals(580.000, $contract->snapshot_data['financial']['grand_total']);
        $this->assertEquals('Notify', $contract->snapshot_data['provider']['name']);

        // Mutate original client and subscription
        $client->update([
            'business_name' => 'مطعم الأندلس الجديد',
            'phone' => '0780000000',
        ]);
        $subscription->update([
            'grand_total' => 999.999,
        ]);

        // Reload contract from database and confirm snapshot is intact
        $freshContract = $contract->fresh();
        $this->assertEquals('مطعم القدس العربي', $freshContract->snapshot_data['client']['business_name']);
        $this->assertEquals('0795554433', $freshContract->snapshot_data['client']['phone']);
        $this->assertEquals(580.000, $freshContract->snapshot_data['financial']['grand_total']);
    }

    public function test_contract_lifecycle_draft_issue_supersede_void(): void
    {
        $service = app(ContractService::class);
        [$client, $subscription] = $this->createClientAndSubscription();

        // 1. Creation -> Draft
        $contract = $service->createContract($client, $subscription, $this->admin);
        $this->assertEquals('draft', $contract->status);
        $this->assertNull($contract->issued_at);
        $this->assertEquals('pending', $contract->legal_review_status);

        // 2. Issuance -> Issued
        $issued = $service->issueContract($contract, $this->admin);
        $this->assertEquals('issued', $issued->status);
        $this->assertNotNull($issued->issued_at);
        $this->assertTrue($issued->isIssued());

        // 3. Supersede -> Old is superseded, new draft created
        $superseded = $service->supersedeContract($issued, $this->admin);
        $this->assertEquals('draft', $superseded->status);
        $this->assertNotEquals($issued->contract_number, $superseded->contract_number);
        $this->assertEquals('superseded', $issued->fresh()->status);
        $this->assertEquals($superseded->id, $issued->fresh()->superseded_by_contract_id);

        // 4. Void -> Voided with reason
        $voided = $service->voidContract($superseded, 'خطأ في بنود الخدمات', $this->admin);
        $this->assertEquals('voided', $voided->status);
        $this->assertTrue($voided->isVoided());
    }

    public function test_contract_authorization_and_partner_isolation(): void
    {
        $service = app(ContractService::class);
        // Client owned by Partner A
        [$clientA, $subscriptionA] = $this->createClientAndSubscription($this->partnerA->id);
        $contractA = $service->createContract($clientA, $subscriptionA, $this->admin);

        // Client owned by Partner B
        [$clientB, $subscriptionB] = $this->createClientAndSubscription($this->partnerB->id);
        $contractB = $service->createContract($clientB, $subscriptionB, $this->admin);

        // Admin can preview, download, and manage both
        $this->actingAs($this->admin)->get(route('contracts.preview', $contractA->id))->assertOk();
        $this->actingAs($this->admin)->get(route('contracts.download', $contractA->id))->assertOk();
        $this->actingAs($this->admin)->get(route('contracts.preview', $contractB->id))->assertOk();

        // Partner A can preview and download their own contract
        $this->actingAs($this->partnerUserA)->get(route('contracts.preview', $contractA->id))->assertOk();
        $this->actingAs($this->partnerUserA)->get(route('contracts.download', $contractA->id))->assertOk();

        // Partner A CANNOT view or download Partner B's contract (Forbidden 403)
        $this->actingAs($this->partnerUserA)->get(route('contracts.preview', $contractB->id))->assertForbidden();
        $this->actingAs($this->partnerUserA)->get(route('contracts.download', $contractB->id))->assertForbidden();

        // Partner A CANNOT issue, void, or supersede contracts (Forbidden 403)
        $this->actingAs($this->partnerUserA)->post(route('contracts.issue', $contractA->id))->assertForbidden();
        $this->actingAs($this->partnerUserA)->post(route('contracts.void', $contractA->id), ['reason' => 'test'])->assertForbidden();
        $this->actingAs($this->partnerUserA)->post(route('contracts.supersede', $contractA->id))->assertForbidden();

        // Partner A CANNOT create contracts (Forbidden 403)
        $this->actingAs($this->partnerUserA)
            ->post(route('contracts.store', ['client' => $clientA->id, 'subscription' => $subscriptionA->id]))
            ->assertForbidden();

        // Guest is redirected to login
        auth()->logout();
        $this->get(route('contracts.preview', $contractA->id))->assertRedirect(route('login'));
    }

    public function test_contract_template_rendering_and_clauses_verification(): void
    {
        $service = app(ContractService::class);
        [$client, $subscription] = $this->createClientAndSubscription();
        $contract = $service->createContract($client, $subscription, $this->admin);

        $response = $this->actingAs($this->admin)->get(route('contracts.preview', $contract->id));

        $response->assertOk();
        $response->assertSee($contract->contract_number);
        $response->assertSee('Notify');
        $response->assertDontSee('NotifyDesk');
        $response->assertSee('عقد تقديم خدمات وحلول برمجية سحابية');
        $response->assertSee('المملكة الأردنية الهاشمية');
        $response->assertSee('مسودة تشغيلية للمراجعة القانونية');

        // Check essential clauses
        $response->assertSee('1. موضوع العقد');
        $response->assertSee('2. تفاصيل الخدمة والباقة المختارة');
        $response->assertSee('4. قيمة الاشتراك والرسوم وجدول الدفعات');
        $response->assertSee('9. الملكية الفكرية والملفات التقنية');
        $response->assertSee('17. سرية البيانات وأمن المعلومات');
        $response->assertSee('20. الإشعارات والمراسلات الرسمية');
        $response->assertSee('21. القانون الواجب التطبيق وحل النزاعات');
        $response->assertSee('22. أحكام عامة ونسخ العقد');

        // Check appendices
        $response->assertSee('الملحق رقم (1): تفاصيل التفعيل والتسليم الفني للخدمة');
        $response->assertSee('الملحق رقم (2): إقرار العميل باستلام وجاهزية الخدمة');
        $response->assertSee('الملحق رقم (3): الاعتماد الداخلي للتسليم والتحصيل المالي');

        // Check signatures
        $response->assertSee('توقيع الطرف الأول (المزود - Notify)');
        $response->assertSee('توقيع الطرف الثاني (العميل المشترك)');
        $response->assertSee('شاهد إثبات وتوثيق التعاقد');
    }

    public function test_contract_file_storage_and_integrity(): void
    {
        $service = app(ContractService::class);
        [$client, $subscription] = $this->createClientAndSubscription();
        $contract = $service->createContract($client, $subscription, $this->admin);

        // Verify file stored in storage/app/private/contracts
        $this->assertTrue(Storage::disk('local')->exists($contract->private_file_path));

        // Verify SHA256 matches
        $content = Storage::disk('local')->get($contract->private_file_path);
        $calculatedHash = hash('sha256', $content);
        $this->assertEquals($calculatedHash, $contract->file_hash);

        // Verify download response
        $response = $this->actingAs($this->admin)->get(route('contracts.download', $contract->id));
        $response->assertOk();
        $this->assertStringContainsString("Contract-{$contract->contract_number}.html", $response->headers->get('content-disposition'));
    }
}
