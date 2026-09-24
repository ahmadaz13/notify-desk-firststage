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

        // P6: one real route per Finance section (§12); capital is hidden while its feature is OFF.
        foreach (['overview', 'collections', 'expenses', 'accounts', 'accounting', 'reports'] as $section) {
            $response->assertSee('data-finance-section="'.$section.'"', false);
        }
        $response->assertDontSee('data-finance-section="capital"', false);

        // Four primary KPIs + compact accounting result (§12.1)
        foreach (['available-cash', 'receivables', 'collections', 'expenses'] as $kpi) {
            $response->assertSee('data-kpi="'.$kpi.'"', false);
        }
        $response->assertSee(__('notify.finance_hub.overview.recognized_revenue'));
        $response->assertSee(__('notify.finance_hub.overview.net_result'));

        // Separate subscription-metrics strip, never presented as revenue
        $response->assertSee(__('notify.finance_hub.overview.saas_title'));
        $response->assertSee(__('notify.finance_hub.overview.mrr'));
        $response->assertSee(__('notify.finance_hub.overview.arr'));
        $response->assertSee(__('notify.finance_hub.overview.active_subscriptions'));
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

        $response = $this->actingAs($this->admin)->get(route('finance.collections', ['tab' => 'due']));

        $response->assertOk();
        $response->assertSee('data-collections-panel="due"', false);
        $response->assertSee('Al-Amal Medical Center');
        $response->assertSee('INV-AMAL-001');
        $response->assertSee(route('clients.show', $client), false);
        $this->actingAs($this->admin)->get(route('finance.index', ['section' => 'collections']))->assertRedirect(route('finance.collections'));
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
        // P7: the one-time expense form needs at least one active category (empty state otherwise).
        $this->seed(\Database\Seeders\ExpenseCategorySeeder::class);
        $response = $this->actingAs($this->admin)->get(route('finance.expenses'));

        $response->assertOk();
        $response->assertSee('data-finance-page="expenses"', false);
        $response->assertSee(route('operating-expenses.store'), false);
    }

    public function test_finance_cockpit_capital_and_assets_workspace(): void
    {
        // P3 / FROZEN D-05: Capital & Financing is hidden and 404 while the feature is OFF (default).
        $this->actingAs($this->admin)->get(route('finance.capital'))->assertNotFound();
        $this->actingAs($this->admin)->get(route('finance.index'))->assertDontSee(route('finance.capital'), false);

        \App\Models\Setting::set('feature_capital_financing', '1');
        $this->actingAs($this->admin)->get(route('finance.index'))->assertSee(route('finance.capital'), false);
        $this->actingAs($this->admin)->get(route('finance.capital'))
            ->assertOk()
            ->assertSee(__('notify.finance_hub.capital.not_revenue'));
    }

    public function test_finance_cockpit_reports_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('finance.reports'));

        $response->assertOk();
        $response->assertSee(__('notify.finance_hub.reports.names.profit-and-loss'));
        $response->assertSee(__('notify.finance_hub.reports.names.financial-position'));
        $response->assertSee(__('notify.finance_hub.reports.names.cash-flow'));
        $response->assertSee(__('notify.finance_hub.reports.export_csv'));
        $response->assertSee(route('finance.reports.export', ['report' => 'profit-and-loss']), false);
    }

    public function test_finance_cockpit_advanced_workspace(): void
    {
        $this->actingAs($this->admin)->get(route('finance.index', ['section' => 'advanced']))->assertRedirect(route('finance.accounts'));

        $this->actingAs($this->admin)->get(route('finance.accounts'))
            ->assertOk()
            ->assertSee('data-company-account="CASH-BOX"', false)
            ->assertSee('data-company-account="CLIQ"', false);
        $this->actingAs($this->admin)->get(route('finance.accounting'))
            ->assertOk()
            ->assertSee('data-advanced-tools', false)
            ->assertSee(__('notify.finance_hub.accounting.tools_title'));
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
