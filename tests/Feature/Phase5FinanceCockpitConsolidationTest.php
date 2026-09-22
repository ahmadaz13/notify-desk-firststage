<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5FinanceCockpitConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $founder;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->founder = User::factory()->create(['role' => 'founder']);
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    public function test_finance_cockpit_overview_renders_accounting_and_saas_metrics(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finance.index'));

        $response->assertOk();

        // 6 Consolidated sections exist in navigation
        $response->assertSee('data-finance-tab="overview"', false);
        $response->assertSee('data-finance-tab="collections"', false);
        $response->assertSee('data-finance-tab="expenses"', false);
        $response->assertSee('data-finance-tab="capital_assets"', false);
        $response->assertSee('data-finance-tab="reports"', false);
        $response->assertSee('data-finance-tab="advanced"', false);

        // Core Accounting KPIs
        $response->assertSee('النقد المتاح');
        $response->assertSee('ذمم العملاء');
        $response->assertSee('الذمم المتأخرة');
        $response->assertSee('الإيراد المعترف به');
        $response->assertSee('المصاريف التشغيلية');
        $response->assertSee('صافي الدخل الإداري');

        // Separate Commercial SaaS block
        $response->assertSee(__('notify.finance.saas_block_title'));
        $response->assertSee(__('notify.finance.mrr'));
        $response->assertSee(__('notify.finance.arr'));
        $response->assertSee(__('notify.finance.active_subscriptions'));
        $response->assertSee(__('notify.finance.upcoming_renewals'));

        // Executive Statements Summary
        $response->assertSee(__('notify.finance.profit_loss'));
        $response->assertSee(__('notify.finance.balance_sheet'));
        $response->assertSee(__('notify.finance.cash_flow'));
    }

    public function test_finance_cockpit_collections_workspace(): void
    {
        $client = $this->createClient([
            'business_name' => 'Al-Amal Medical Center',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-AMAL-001',
            'client_id' => $client->id,
            'currency' => 'JOD',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'subtotal_minor' => 450000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 450000,
            'issued_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin)->get(route('finance.index', ['section' => 'collections']));

        $response->assertOk();
        $response->assertSee('مساحة متابعة تحصيل الذمم');
        $response->assertSee('Al-Amal Medical Center');
        $response->assertSee('INV-AMAL-001');
        $response->assertSee('فتح ملف العميل والتحصيل');
    }

    private function createClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'مطاعم القدس الحديثة',
            'phone' => '0799887766',
            'business_phone' => '0799887766',
            'contact_person' => 'عمر',
            'city_area' => 'عمان - شارع مكة',
            'city' => 'Amman',
            'area' => 'Mecca Street',
            'business_category' => 'مطاعم وكافيهات',
            'lead_source' => 'Direct Prospecting',
            'number_of_branches' => 1,
            'status' => 'prospect',
            'stage' => 'active',
        ], $overrides));
    }

    public function test_finance_cockpit_expenses_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finance.index', ['section' => 'expenses']));

        $response->assertOk();
        $response->assertSee('تسجيل مصروف تشغيلي');
        $response->assertSee('مصدر الدفع (Paid From)');
        $response->assertSee('حساب الشركة (Company Account)');
        $response->assertSee('دفع شخصي (Personal / Founder)');
        $response->assertSee('خيارات إضافية');
    }

    public function test_finance_cockpit_capital_and_assets_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finance.index', ['section' => 'capital_assets']));

        $response->assertOk();
        $response->assertSee('تسجيل تمويل رأسمالي أو قرض');
        $response->assertSee('مساهمة مؤسس (رأس مال / Equity)');
        $response->assertSee('قرض مؤسس / تمويل دَين (Loan Payable)');
        $response->assertSee('استثمار ملكية خارجي (External Equity)');
        $response->assertSee('تسجيل اقتناء أصل ثابت (Fixed Asset)');
        $response->assertSee(__('notify.finance.capital_invariants_note'));
    }

    public function test_finance_cockpit_reports_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finance.index', ['section' => 'reports']));

        $response->assertOk();
        $response->assertSee('القوائم المالية الإدارية');
        $response->assertSee(__('notify.finance.profit_loss'));
        $response->assertSee(__('notify.finance.balance_sheet'));
        $response->assertSee(__('notify.finance.cash_flow'));
        $response->assertSee('تقارير الاشتراكات التجارية (Commercial SaaS Reports)');
    }

    public function test_finance_cockpit_advanced_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finance.index', ['section' => 'advanced']));

        $response->assertOk();
        $response->assertSee('وحدة الأدوات المحاسبية والعمليات المتقدمة');
        $response->assertSee('الحسابات المالية ودفتر حركة النقد');
        $response->assertSee('شجرة الحسابات ودفتر الأستاذ العام');
        $response->assertSee('محرك الاعتراف بالإيراد (Revenue Recognition)');
        $response->assertSee('مصفوفة المطابقة والتدقيق بين الدفاتر والأستاذ');
    }

    public function test_staff_cannot_access_finance_cockpit(): void
    {
        $this->actingAs($this->staff)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('finance.index', ['section' => 'collections']))->assertForbidden();
        $this->actingAs($this->staff)->get(route('finance.index', ['section' => 'expenses']))->assertForbidden();
        $this->actingAs($this->staff)->get(route('finance.index', ['section' => 'capital_assets']))->assertForbidden();
        $this->actingAs($this->staff)->get(route('finance.index', ['section' => 'reports']))->assertForbidden();
        $this->actingAs($this->staff)->get(route('finance.index', ['section' => 'advanced']))->assertForbidden();
    }

    public function test_founder_can_access_finance_cockpit(): void
    {
        $this->actingAs($this->founder)->get(route('finance.index'))->assertOk();
    }
}
