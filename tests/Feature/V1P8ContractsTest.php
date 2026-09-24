<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Contract;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ContractPdfService;
use App\Services\ContractService;
use App\Services\SubscriptionBillingService;
use App\Support\ContractDocument;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P8 — Final contract system & professional Arabic PDF (§8, FROZEN D-10, D-11, owner decisions A–R).
 */
class V1P8ContractsTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 10:00:00');
        $this->seed(SettingsSeeder::class);
        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true, 'name' => 'Founder P8']);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
        foreach (['company_address', 'company_phone', 'company_email', 'registration_number', 'company_national_number', 'tax_number', 'authorized_signatory', 'default_contract_terms'] as $key) {
            Setting::set($key, '');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Catalog (owner decision E) ─────────────────────────────────────

    public function test_catalog_uses_frozen_business_names_with_stable_separate_identities(): void
    {
        $names = Product::pluck('name_en', 'code');
        $this->assertSame('Smart-Link Premium', $names['smart_link']);
        $this->assertSame('Auto SMS Sender', $names['auto_sms_system']);
        $this->assertSame('E-Menu', $names['e_menu']);
        $this->assertSame('E-Store', $names['e_store']);
        $this->assertSame('Restaurant System', $names['restaurant_system']);
        $this->assertSame('Store System', $names['digital_store_system']);
        $this->assertSame('Clink Management', $names['clink_management']);
        $this->assertSame('CRM + AI Tool', $names['crm_ai_tool']);
        $this->assertSame('Custom System', $names['custom_system']);
        $this->assertSame(1, Product::where('code', 'e_menu')->count());
        $this->assertSame(1, Product::where('code', 'e_store')->count());
        $this->assertNull(Product::where('code', 'clink_management')->value('description_ar'), 'No invented description.');
        $this->assertNull(Product::where('code', 'crm_ai_tool')->value('description_ar'));
    }

    // ── Draft ──────────────────────────────────────────────────────────

    public function test_paid_subscription_creates_an_unnumbered_draft_identified_as_draft(): void
    {
        [$contract] = $this->annualContract();

        $this->assertSame('draft', $contract->status);
        $this->assertNull($contract->contract_number);
        $this->assertNull($contract->snapshot_data['metadata']['contract_number']);
        $this->assertSame(ContractService::SNAPSHOT_SCHEMA, $contract->snapshot_data['schema']);

        $html = $this->documentHtml($contract);
        $this->assertStringContainsString('مسودة / DRAFT', $html);
        $this->assertStringContainsString('data-contract-status="draft"', $html);
        $this->assertStringNotContainsString('data-contract-number', $html);
        foreach (['لا يوجد عقد رسمي', 'غير متوفر', 'غير مسجل', 'ND-2026', 'ND-'.$contract->id] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }

        $this->actingAs($this->founder)->get(route('contracts.preview', $contract))->assertOk()->assertSee('مسودة / DRAFT');
    }

    // ── Issue ──────────────────────────────────────────────────────────

    public function test_issue_is_owner_only_sequential_once_and_idempotent(): void
    {
        [$first] = $this->annualContract('P8 Alpha');
        [$second] = $this->annualContract('P8 Beta');

        $this->actingAs($this->staff)->post(route('contracts.issue', $first))->assertForbidden();
        $this->assertNull($first->fresh()->contract_number);

        $this->actingAs($this->founder)->post(route('contracts.issue', $first))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('contracts.issue', $second))->assertSessionHas('success');
        $this->assertSame('ND-2026-0001', $first->fresh()->contract_number);
        $this->assertSame('ND-2026-0002', $second->fresh()->contract_number);
        $this->assertSame('issued', $first->fresh()->status);
        $this->assertSame('ND-2026-0001', $first->fresh()->snapshot_data['metadata']['contract_number']);
        $this->assertNotNull($first->fresh()->file_hash);

        $issuedAt = $first->fresh()->issued_at;
        $again = app(ContractService::class)->issueContract($first->fresh(), $this->founder);
        $this->actingAs($this->founder)->post(route('contracts.issue', $first))->assertSessionHas('success');
        $this->assertSame('ND-2026-0001', $again->contract_number);
        $this->assertEquals($issuedAt, $first->fresh()->issued_at);
        $this->assertSame(1, DB::table('activity_logs')->where('type', 'contract_issued')->where('client_id', $first->client_id)->count());
        $this->assertSame(2, Contract::whereNotNull('contract_number')->distinct()->count('contract_number'));
    }

    public function test_numbering_skips_existing_numbers_and_drafts_do_not_consume_numbers(): void
    {
        Setting::set('contract_prefix', 'NTF');
        [$legacy] = $this->annualContract('P8 Legacy');
        [$draftOnly] = $this->annualContract('P8 Draft Only');
        [$next] = $this->annualContract('P8 Next');
        DB::table('contracts')->where('id', $legacy->id)->update(['contract_number' => 'NTF-2026-0007', 'status' => 'voided']);

        app(ContractService::class)->issueContract($next, $this->founder);

        $this->assertSame('NTF-2026-0008', $next->fresh()->contract_number);
        $this->assertNull($draftOnly->fresh()->contract_number);
    }

    public function test_issued_snapshot_is_frozen_against_settings_and_catalog_changes(): void
    {
        [$contract] = $this->annualContract();
        app(ContractService::class)->issueContract($contract, $this->founder);
        $issued = $contract->fresh();
        $frozen = $issued->snapshot_data;

        Setting::set('registration_number', 'REG-LATER-1');
        Setting::set('company_name_ar', 'اسم لاحق');
        Product::where('code', 'smart_link')->update(['name_en' => 'Renamed Link']);

        $this->assertSame($frozen, $issued->fresh()->snapshot_data);
        $html = $this->documentHtml($issued->fresh());
        $this->assertStringContainsString('Smart-Link Premium', $html);
        $this->assertStringNotContainsString('Renamed Link', $html);
        $this->assertStringNotContainsString('REG-LATER-1', $html);
        $this->assertStringNotContainsString('اسم لاحق', $html);

        $this->expectException(\LogicException::class);
        $issued->fresh()->update(['snapshot_data' => ['tampered' => true]]);
    }

    // ── Legal settings (owner decision C) ──────────────────────────────

    public function test_absent_legal_settings_print_nothing_and_configured_values_appear(): void
    {
        [$contract] = $this->annualContract();

        $html = $this->documentHtml($contract);
        $this->assertStringNotContainsString('data-company-legal', $html);
        foreach (['رقم التسجيل', 'الرقم الوطني للمنشأة', 'الرقم الضريبي', 'البريد الإلكتروني', 'support@notify.local', 'غير متوفر', '____'] as $absent) {
            $this->assertStringNotContainsString($absent, $html);
        }

        Setting::set('registration_number', 'REG-777');
        Setting::set('company_national_number', 'NAT-888');
        Setting::set('tax_number', 'TAX-999');
        $draftHtml = $this->documentHtml($contract->fresh());
        $this->assertStringContainsString('REG-777', $draftHtml, 'Drafts show the settings Issue will capture.');

        app(ContractService::class)->issueContract($contract->fresh(), $this->founder);
        $issued = $contract->fresh();
        $this->assertSame(['registration_number' => 'REG-777', 'national_number' => 'NAT-888', 'tax_number' => 'TAX-999'],
            array_intersect_key($issued->snapshot_data['company'], array_flip(['registration_number', 'national_number', 'tax_number'])));

        Setting::set('tax_number', '');
        $html = $this->documentHtml($issued->fresh());
        foreach (['REG-777', 'NAT-888', 'TAX-999', 'data-company-legal="national_number"'] as $present) {
            $this->assertStringContainsString($present, $html);
        }
    }

    // ── Services (owner decisions E, F) ────────────────────────────────

    public function test_contract_lists_only_subscribed_services(): void
    {
        [$contract] = $this->annualContract('P8 Services', ['smart_link', 'auto_sms_system', 'restaurant_system']);

        $this->assertEqualsCanonicalizing(['smart_link', 'auto_sms_system', 'restaurant_system'], collect($contract->snapshot_data['services'])->pluck('code')->all());
        $html = $this->documentHtml($contract);
        foreach (['Smart-Link Premium', 'Auto SMS Sender', 'Restaurant System'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        foreach (['E-Menu', 'E-Store', 'Store System', 'Clink Management', 'CRM + AI Tool', 'Custom System'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
        $this->assertSame(3, substr_count($html, 'data-contract-service='));
    }

    public function test_custom_system_title_is_captured_at_subscription_and_printed_briefly(): void
    {
        $client = $this->client('P8 Custom');
        $custom = Product::where('code', 'custom_system')->firstOrFail();
        $store = Product::where('code', 'e_store')->firstOrFail();
        $payload = [
            'system_ids' => [$custom->id, $store->id], 'billing_interval' => 'annual', 'agreed_value_jod' => '900',
            'start_date' => '2026-10-01', 'payment_terms' => 'full',
        ];

        $this->actingAs($this->founder)->post(route('clients.guided-subscription.store', $client), $payload + ['_idempotency_key' => 'p8-custom-1'])
            ->assertSessionHasErrors('custom_system_title');
        $this->assertSame(0, Subscription::where('client_id', $client->id)->count());

        $this->actingAs($this->founder)->post(route('clients.guided-subscription.store', $client), $payload + [
            '_idempotency_key' => 'p8-custom-2',
            'custom_system_title' => 'نظام حجوزات الفروع',
            'custom_system_description' => 'إدارة حجوزات الفروع.',
        ])->assertRedirect();

        $pivot = DB::table('subscription_system')->where('product_id', $custom->id)->sole();
        $this->assertSame('نظام حجوزات الفروع', $pivot->custom_title);
        $this->assertNull(DB::table('subscription_system')->where('product_id', $store->id)->value('custom_title'));

        $contract = Contract::where('client_id', $client->id)->sole();
        $html = $this->documentHtml($contract);
        $this->assertStringContainsString('Custom System', $html);
        $this->assertStringContainsString('نظام حجوزات الفروع', $html);
        $this->assertStringContainsString('إدارة حجوزات الفروع.', $html);
        $this->assertSame(2, ContractPdfService::pageCount(app(ContractPdfService::class)->generatePdfOutput($contract)));
    }

    // ── Client (owner decision H) ──────────────────────────────────────

    public function test_contract_renders_cleanly_without_a_responsible_person(): void
    {
        [$contract] = $this->annualContract('P8 No Contact', null, false);

        $this->assertArrayNotHasKey('contact_name', $contract->snapshot_data['client']);
        $html = $this->documentHtml($contract);
        $this->assertStringContainsString('P8 No Contact', $html);
        $this->assertStringNotContainsString('المسؤول:', $html);
        $this->assertStringNotContainsString('المفوض القانوني', $html);
        $this->assertSame(2, ContractPdfService::pageCount(app(ContractPdfService::class)->generatePdfOutput($contract)));

        [$withContact] = $this->annualContract('P8 With Contact');
        $this->assertStringContainsString('المسؤول: ليان P8', $this->documentHtml($withContact));
    }

    // ── Pricing (owner decision I, §8.4.1) ─────────────────────────────

    public function test_monthly_annual_and_installment_wording_is_consistent(): void
    {
        [$monthly] = $this->contractFor('P8 Monthly', ['smart_link'], ['billing_interval' => 'monthly', 'agreed_value_minor' => 25000]);
        $html = $this->documentHtml($monthly);
        $this->assertStringContainsString('اشتراك شهري بقيمة', $html);
        $this->assertStringContainsString('يُستحق شهرياً في اليوم', $html);
        foreach (['اشتراك سنوي', 'دفعة سنوية واحدة', 'دفعة واحدة', 'تاريخ الانتهاء', 'data-installments', 'مدد سنوية'] as $annualOnly) {
            $this->assertStringNotContainsString($annualOnly, $html);
        }

        [$annual] = $this->annualContract('P8 Annual');
        $html = $this->documentHtml($annual);
        $this->assertStringContainsString('اشتراك سنوي بقيمة', $html);
        $this->assertStringContainsString('يُدفع دفعة واحدة بتاريخ', $html);
        $this->assertStringContainsString('360.000', $html);
        $this->assertStringNotContainsString('data-installments', $html);

        [$installments] = $this->contractFor('P8 Installments', ['e_menu'], [
            'billing_interval' => 'annual', 'agreed_value_minor' => 1200000, 'payment_terms' => 'installments', 'installments_count' => 12, 'installment_due_day' => 5,
        ]);
        $snapshot = $installments->snapshot_data;
        $this->assertSame(1200000, collect($snapshot['schedules'])->sum('amount_minor'));
        $html = $this->documentHtml($installments);
        $this->assertStringContainsString('مقسّط على 12 دفعة', $html);
        $this->assertSame(12, substr_count($html, '<tr><td>'), 'One row per installment.');
        $this->assertSame(2, ContractPdfService::pageCount(app(ContractPdfService::class)->generatePdfOutput($installments)), 'Worst case (12 installments) still fits 2 pages.');

        app(ContractService::class)->issueContract($installments, $this->founder);
        $this->assertSame('issued', $installments->fresh()->status);
    }

    // ── Owner contract hardening ───────────────────────────────────────

    public function test_hardened_terms_have_no_sla_no_originals_count_and_clear_termination(): void
    {
        [$contract] = $this->annualContract('P8 Hardened');
        $html = $this->documentHtml($contract);

        foreach (['99%', '99 %', 'معدل إتاحة', 'نسختين أصليتين', 'نسختين', 'شاهد', 'توقيع إلكتروني',
            'تبقى المبالغ المستحقة عن المدة الجارية واجبة السداد', 'جميع الدفعات', 'تستحق فوراً', 'فوراً', 'غرامة'] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }
        foreach ([
            'ببذل العناية المعقولة لتشغيل الخدمات المشمولة بصورة مستقرة',
            'وعدم إفشائها لأي جهة إلا بأمر قضائي رسمي',
            'لا تُسترد المبالغ التي تم سدادها مقابل فترة اشتراك أو خدمة تم تفعيلها، ما لم يتفق الطرفان خطياً على خلاف ذلك',
            'تتم تسوية المبالغ المستحقة وفق مدة الاشتراك وترتيب الدفع المتفق عليه والمبيّن في الصفحة الأولى',
            'دون اعتبار التعليق إنهاءً للاتفاقية',
            'قد تعتمد بعض الخدمات على مزودين خارجيين، مثل شركات الاتصالات أو بوابات الدفع أو خدمات الاستضافة',
            'ولا يضمن تحقيق حجم مبيعات أو أرباح محددة',
            'تخضع الاتفاقية لقوانين المملكة الأردنية الهاشمية، وتختص محاكم عمّان',
        ] as $present) {
            $this->assertStringContainsString($present, $html);
        }
        $this->assertSame(2, substr_count($html, 'التوقيع والختم إن وجد'), 'Stamp is optional for both parties.');
        $this->assertLessThanOrEqual(4, substr_count($html, '<li'), 'Page-1 key points stay short.');
    }

    public function test_monthly_and_annual_renewal_rules_are_split_and_match_on_both_pages(): void
    {
        $monthlyRule = 'يتجدد الاشتراك الشهري تلقائياً لدورة شهرية جديدة ما لم يطلب الطرف الثاني إيقاف التجديد قبل موعد استحقاق الدورة التالية.';
        $annualRule = 'يتجدد الاشتراك السنوي لمدة مماثلة ما لم يُخطر أحد الطرفين الآخر خطياً بعدم الرغبة في التجديد قبل 30 يوماً من نهاية مدة الاشتراك.';

        [$monthly] = $this->contractFor('P8 Monthly Renewal', ['smart_link'], ['billing_interval' => 'monthly', 'agreed_value_minor' => 25000]);
        $html = $this->documentHtml($monthly);
        $this->assertSame(2, substr_count($html, $monthlyRule), 'Page 1 summary and Page 2 clause 6.');
        $this->assertStringNotContainsString('30 يوماً', $html, 'No 30-day notice for monthly subscriptions.');
        $this->assertStringNotContainsString($annualRule, $html);
        $this->assertStringContainsString('دورة التجديد', $html);
        $this->assertStringNotContainsString('تاريخ الانتهاء', $html, 'No artificial end date for a recurring monthly subscription.');

        foreach ([
            $this->annualContract('P8 Annual Renewal')[0],
            $this->contractFor('P8 Installment Renewal', ['e_menu'], ['billing_interval' => 'annual', 'agreed_value_minor' => 400000, 'payment_terms' => 'installments', 'installments_count' => 4, 'installment_due_day' => 1])[0],
        ] as $annual) {
            $html = $this->documentHtml($annual);
            $this->assertSame(2, substr_count($html, $annualRule));
            $this->assertStringNotContainsString($monthlyRule, $html);
            $this->assertStringNotContainsString('دورة التجديد', $html);
        }
    }

    public function test_identifiers_are_isolated_left_to_right_in_the_rtl_document(): void
    {
        [$contract] = $this->annualContract('P8 LTR');
        app(ContractService::class)->issueContract($contract, $this->founder);
        $document = ContractDocument::for($contract->fresh());
        $html = $this->documentHtml($contract->fresh());

        $this->assertStringContainsString('dir="ltr" data-contract-number>ND-2026-0001</span>', $html);
        $this->assertStringContainsString('<span class="ltr" dir="ltr">2026-10-01</span>', $html);
        $this->assertStringContainsString('<span class="ltr" dir="ltr">360.000</span>', $html);
        $this->assertStringContainsString('<span class="ltr" dir="ltr">0790000001</span>', $html);
        $this->assertStringContainsString('<span class="svc-name" dir="ltr">Smart-Link Premium</span>', $html);
        $this->assertStringContainsString('<span dir="ltr">ND-2026-0001</span>', view('contracts.partials.pdf-footer', ['document' => $document])->render());
        $this->assertSame('ND-2026-0001', $contract->fresh()->contract_number);
    }

    public function test_representative_contracts_all_stay_exactly_two_pages(): void
    {
        $pdf = app(ContractPdfService::class);
        Setting::set('registration_number', 'REG-1');
        Setting::set('company_national_number', 'NAT-1');
        Setting::set('tax_number', 'TAX-1');
        Setting::set('company_address', 'Amman');
        Setting::set('company_phone', '0790000000');
        Setting::set('company_email', 'contracts@example.test');

        [$monthly] = $this->contractFor('P8 Two Monthly', ['smart_link', 'e_menu'], ['billing_interval' => 'monthly', 'agreed_value_minor' => 25000]);
        [$annual] = $this->annualContract('P8 Two Annual');
        [$medium] = $this->contractFor('P8 Two Four', ['e_store', 'digital_store_system'], ['billing_interval' => 'annual', 'agreed_value_minor' => 400000, 'payment_terms' => 'installments', 'installments_count' => 4, 'installment_due_day' => 1]);
        [$dense] = $this->contractFor('P8 Two Twelve', ['e_store', 'digital_store_system', 'crm_ai_tool', 'restaurant_system', 'auto_sms_system', 'smart_link', 'e_menu'], [
            'billing_interval' => 'annual', 'agreed_value_minor' => 1200000, 'payment_terms' => 'installments', 'installments_count' => 12, 'installment_due_day' => 5,
        ]);

        $this->assertSame(2, ContractPdfService::pageCount($pdf->generatePdfOutput($monthly)), 'Monthly draft');
        $this->assertSame(2, ContractPdfService::pageCount($pdf->generatePdfOutput($annual)), 'Annual draft');
        $this->assertSame(2, ContractPdfService::pageCount($pdf->generatePdfOutput($medium)), 'Annual 4 installments');
        $this->assertSame(2, ContractPdfService::pageCount($pdf->generatePdfOutput($dense)), 'Annual 12 installments, 7 services, all legal fields');
        foreach ([$annual, $dense] as $contract) {
            app(ContractService::class)->issueContract($contract, $this->founder);
            $this->assertSame(2, ContractPdfService::pageCount($pdf->generatePdfOutput($contract->fresh())), 'Issued');
        }
    }

    public function test_installment_sum_mismatch_blocks_issue_server_side(): void
    {
        [$contract] = $this->contractFor('P8 Mismatch', ['e_menu'], [
            'billing_interval' => 'annual', 'agreed_value_minor' => 300000, 'payment_terms' => 'installments', 'installments_count' => 3, 'installment_due_day' => 1,
        ]);
        $snapshot = $contract->snapshot_data;
        $snapshot['schedules'][0]['amount_minor'] -= 1;
        $contract->update(['snapshot_data' => $snapshot]);

        $this->actingAs($this->founder)->post(route('contracts.issue', $contract))->assertSessionHasErrors('contract');
        $this->assertSame('draft', $contract->fresh()->status);
        $this->assertNull($contract->fresh()->contract_number);

        $this->expectException(ValidationException::class);
        app(ContractService::class)->issueContract($contract->fresh(), $this->founder);
    }

    // ── PDF & routes (owner decisions N, O) ────────────────────────────

    public function test_pdf_is_a_valid_two_page_arabic_document_for_draft_and_issued(): void
    {
        [$contract] = $this->annualContract('P8 PDF', ['smart_link', 'auto_sms_system', 'restaurant_system']);
        $pdf = app(ContractPdfService::class);

        $draftBytes = $pdf->generatePdfOutput($contract);
        $this->assertStringStartsWith('%PDF-', $draftBytes);
        $this->assertSame(2, ContractPdfService::pageCount($draftBytes));
        $this->assertStringContainsString('XBRiyaz', $draftBytes, 'Bundled Arabic font embedded.');
        $this->assertStringEndsWith('-draft.pdf', $pdf->generateFilename($contract));

        app(ContractService::class)->issueContract($contract, $this->founder);
        $issuedBytes = $pdf->generatePdfOutput($contract->fresh());
        $this->assertSame(2, ContractPdfService::pageCount($issuedBytes));
        $this->assertStringEndsWith('-ND-2026-0001.pdf', $pdf->generateFilename($contract->fresh()));
        $this->assertStringContainsString('smart-link', $pdf->generateFilename($contract->fresh()));

        $html = $this->documentHtml($contract->fresh());
        $this->assertStringContainsString('ND-2026-0001', $html);
        $this->assertStringNotContainsString('مسودة / DRAFT', $html);
        foreach (['شاهد', 'الشاهد', 'witness', 'توقيع إلكتروني', 'e-signature', 'window.print', 'الملحق', 'الضريبة', '16%', 'زين كاش', 'تحويل بنكي'] as $removed) {
            $this->assertStringNotContainsStringIgnoringCase($removed, $html);
        }
        $this->assertStringContainsString('data-signatures', $html);
    }

    public function test_staff_can_preview_and_download_owner_issues_and_removed_routes_are_gone(): void
    {
        [$contract] = $this->annualContract();

        foreach ([$this->staff, $this->admin, $this->founder] as $user) {
            $this->actingAs($user)->get(route('contracts.preview', $contract))->assertOk();
            $this->actingAs($user)->get(route('contracts.download-pdf', $contract))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }

        $this->assertFalse(Route::has('contracts.print'));
        $this->assertFalse(Route::has('contracts.download'));
        $this->actingAs($this->founder)->get('/contracts/'.$contract->id.'/print')->assertNotFound();
        $this->actingAs($this->founder)->get('/contracts/'.$contract->id.'/download')->assertNotFound();

        $workspace = $this->actingAs($this->staff)->get(route('clients.show', $contract->client_id))->assertOk()->getContent();
        $this->assertStringContainsString(route('contracts.download-pdf', $contract), $workspace);
        $this->assertStringNotContainsString('data-issue-contract', $workspace);
        $owner = $this->actingAs($this->founder)->get(route('clients.show', $contract->client_id))->assertOk()->getContent();
        $this->assertStringContainsString('data-issue-contract', $owner);
        $this->assertStringNotContainsString('/print', $owner);
    }

    // ── Regression: Issue has no financial effect ──────────────────────

    public function test_issue_changes_no_subscription_or_financial_record(): void
    {
        [$contract, $subscription] = $this->annualContract();
        $tables = ['invoices', 'payments', 'payment_schedules', 'cash_movements', 'journal_entries', 'subscription_metric_events'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $subscriptionBefore = $subscription->fresh()->only(['status', 'agreed_value_minor', 'current_period_end', 'next_billing_date']);

        app(ContractService::class)->issueContract($contract, $this->founder);

        $this->assertEquals($before, collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]));
        $this->assertEquals($subscriptionBefore, $subscription->fresh()->only(['status', 'agreed_value_minor', 'current_period_end', 'next_billing_date']));
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** @return array{0: Contract, 1: Subscription} */
    private function annualContract(string $name = 'P8 Bakery', ?array $codes = null, bool $withContact = true): array
    {
        return $this->contractFor($name, $codes ?? ['smart_link', 'auto_sms_system', 'restaurant_system'], [
            'billing_interval' => 'annual', 'agreed_value_minor' => 360000, 'payment_terms' => 'full',
        ], $withContact);
    }

    private function contractFor(string $name, array $codes, array $terms, bool $withContact = true): array
    {
        $client = $this->client($name, $withContact);
        [$subscription, , $result] = app(SubscriptionBillingService::class)->startAgreedSubscription(
            $client,
            Product::whereIn('code', $codes)->get(),
            $terms + ['start_date' => '2026-10-01'],
            $this->founder->id
        );

        return [Contract::findOrFail($result['contract_id']), $subscription];
    }

    private function client(string $name, bool $withContact = true): Client
    {
        $client = Client::create([
            'business_name' => $name, 'business_phone' => '0790000001', 'phone' => '0790000001', 'city_area' => 'Amman',
            'business_category' => 'Restaurant', 'lead_source' => 'Direct', 'status' => 'prospect', 'stage' => 'prospect', 'created_by' => $this->founder->id,
        ]);
        if ($withContact) {
            ClientContact::create(['client_id' => $client->id, 'name' => 'ليان P8', 'role' => 'مالك', 'primary_phone' => '0790000002', 'is_primary' => true]);
        }

        return $client;
    }

    private function documentHtml(Contract $contract): string
    {
        return view('contracts.document', ['document' => ContractDocument::for($contract), 'mode' => 'pdf'])->render();
    }
}
