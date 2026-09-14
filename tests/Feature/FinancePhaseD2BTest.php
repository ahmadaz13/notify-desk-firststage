<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\CapitalExpense;
use App\Models\CapitalFundingReversal;
use App\Models\CapitalFundingTransaction;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetAcquisitionReversal;
use App\Models\FundingSource;
use App\Models\Investment;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CapitalManagementService;
use App\Services\FinancialAccountBalanceService;
use App\Support\Money;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePhaseD2BTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        $this->seed(AssetCategorySeeder::class);
    }

    public function test_admin_can_create_funding_source_and_partner_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $partnerUser] = $this->createPartnerUser();

        $this->actingAs($admin)->post(route('funding-sources.store'), [
            'name' => 'Ahmad Founder',
            'type' => FundingSource::TYPE_FOUNDER,
            'email' => 'founder@example.com',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('funding_sources', [
            'name' => 'Ahmad Founder',
            'type' => FundingSource::TYPE_FOUNDER,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', ['type' => 'funding_source_created']);

        $this->actingAs($partnerUser)->post(route('funding-sources.store'), [
            'name' => 'Blocked',
            'type' => FundingSource::TYPE_INVESTOR,
        ])->assertForbidden();
    }

    public function test_capital_funding_exact_cash_behavior_and_reversal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = $this->createAccount('funding_cash', 'Funding Cash');
        $archived = $this->createAccount('funding_archived', 'Funding Archived');
        $archived->update(['is_active' => false, 'archived_at' => now()]);
        $source = FundingSource::create(['name' => 'Owner', 'type' => FundingSource::TYPE_OWNER, 'is_active' => true, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('capital-funding-transactions.store'), [
            'funding_source_id' => $source->id,
            'funding_type' => CapitalFundingTransaction::TYPE_OWNER_CONTRIBUTION,
            'financial_account_id' => $archived->id,
            'amount' => '0.001',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHasErrors('financial_account_id');

        $this->actingAs($admin)->post(route('capital-funding-transactions.store'), [
            'funding_source_id' => $source->id,
            'funding_type' => CapitalFundingTransaction::TYPE_OWNER_CONTRIBUTION,
            'financial_account_id' => $account->id,
            'amount' => '0.001',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHas('success');

        $funding = CapitalFundingTransaction::firstOrFail();
        $this->assertSame(1, $funding->amount_minor);
        $this->assertStringStartsWith('FND-2026-', $funding->funding_number);
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_CAPITAL_FUNDING_RECEIVED)->count());
        $this->assertSame(1, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Subscription::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Expense::count());

        $this->actingAs($admin)->post(route('capital-funding-transactions.reverse', $funding), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('capital-funding-transactions.reverse', $funding), [
            'reason' => 'Wrong funding entry',
            'reversed_at' => '2026-09-14 11:00:00',
        ])->assertSessionHas('success');

        $this->assertSame(1, CapitalFundingReversal::count());
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_CAPITAL_FUNDING_REVERSAL)->count());
        $this->assertSame(0, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));

        $this->actingAs($admin)->post(route('capital-funding-transactions.reverse', $funding), [
            'reason' => 'Duplicate',
        ])->assertSessionHasErrors('capital_funding_transaction_id');
    }

    public function test_asset_category_and_company_funded_fixed_asset_cash_behavior(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = $this->createAccount('asset_cash', 'Asset Cash', '100.000');
        $archived = $this->createAccount('asset_archived', 'Asset Archived');
        $archived->update(['is_active' => false, 'archived_at' => now()]);
        $category = AssetCategory::where('code', 'computers')->firstOrFail();
        $vendor = Vendor::create(['name' => 'Hardware Vendor', 'is_active' => true, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('asset-categories.store'), [
            'code' => 'custom_asset',
            'name_ar' => 'أصل مخصص',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('fixed-assets.store'), [
            'name' => 'Laptop',
            'asset_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'acquisition_cost' => '12.375',
            'funding_source' => FixedAsset::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $archived->id,
            'acquired_at' => '2026-09-14',
        ])->assertSessionHasErrors('financial_account_id');

        $this->actingAs($admin)->post(route('fixed-assets.store'), [
            'name' => 'Laptop',
            'asset_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'serial_number' => 'SN-1',
            'quantity' => 1,
            'acquisition_cost' => '12.375',
            'funding_source' => FixedAsset::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'acquired_at' => '2026-09-14',
            'in_service_at' => '2026-09-15',
            'location' => 'Office',
        ])->assertSessionHas('success');

        $asset = FixedAsset::firstOrFail();
        $this->assertSame(12375, $asset->acquisition_cost_minor);
        $this->assertStringStartsWith('AST-2026-', $asset->asset_number);
        $this->assertSame($category->displayName(), $asset->category_name_snapshot);
        $this->assertSame($vendor->name, $asset->payee_name_snapshot);
        $this->assertSame(0, Expense::count());
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_ASSET_ACQUISITION)->count());
        $this->assertSame(87625, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));
    }

    public function test_personally_funded_asset_requires_payer_and_creates_no_cash_movement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['role' => 'admin']);
        $category = AssetCategory::where('code', 'mobile_devices')->firstOrFail();

        $this->actingAs($admin)->post(route('fixed-assets.store'), [
            'name' => 'Tablet',
            'asset_category_id' => $category->id,
            'acquisition_cost' => '40.000',
            'funding_source' => FixedAsset::FUNDING_PERSONAL,
            'acquired_at' => '2026-09-14',
        ])->assertSessionHasErrors('paid_by_user_id');

        $this->actingAs($admin)->post(route('fixed-assets.store'), [
            'name' => 'Tablet',
            'asset_category_id' => $category->id,
            'payee_name' => 'Retail Store',
            'acquisition_cost' => '40.000',
            'funding_source' => FixedAsset::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'acquired_at' => '2026-09-14',
        ])->assertSessionHas('success');

        $asset = FixedAsset::firstOrFail();
        $this->assertSame($payer->id, $asset->paid_by_user_id);
        $this->assertSame(40000, $asset->acquisition_cost_minor);
        $this->assertSame(0, CashMovement::count());
    }

    public function test_asset_acquisition_reversal_and_status_are_append_only_and_cash_aware(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['role' => 'admin']);
        $account = $this->createAccount('asset_reverse_cash', 'Asset Reverse Cash', '60.000');
        $category = AssetCategory::where('code', 'tools')->firstOrFail();
        $companyAsset = $this->createCompanyAsset($admin, $category, $account, '10.000');
        $personalAsset = $this->createPersonalAsset($admin, $payer, $category, '5.000');

        $this->actingAs($admin)->patch(route('fixed-assets.status', $companyAsset), [
            'status' => FixedAsset::STATUS_OUT_OF_SERVICE,
        ])->assertSessionHas('success');
        $this->assertSame(FixedAsset::STATUS_OUT_OF_SERVICE, $companyAsset->fresh()->status);

        $this->actingAs($admin)->post(route('fixed-assets.reverse', $companyAsset), [])
            ->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('fixed-assets.reverse', $companyAsset), [
            'reason' => 'Wrong asset entry',
        ])->assertSessionHas('success');

        $this->assertSame(1, FixedAssetAcquisitionReversal::count());
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_ASSET_ACQUISITION_REVERSAL)->count());
        $this->assertSame(60000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));
        $this->assertSame(0, FixedAsset::activeAcquisitions()->whereKey($companyAsset->id)->count());

        $this->actingAs($admin)->post(route('fixed-assets.reverse', $companyAsset), [
            'reason' => 'Duplicate',
        ])->assertSessionHasErrors('fixed_asset_id');

        $movementCount = CashMovement::count();
        $this->actingAs($admin)->post(route('fixed-assets.reverse', $personalAsset), [
            'reason' => 'Personal correction',
        ])->assertSessionHas('success');
        $this->assertSame($movementCount, CashMovement::count());
    }

    public function test_legacy_rows_remain_readable_and_partner_boundaries_hold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = AssetCategory::where('code', 'other')->firstOrFail();
        $account = $this->createAccount('legacy_guard_cash', 'Legacy Guard Cash', '20.000');

        Investment::create([
            'investor_name' => 'Legacy Investor',
            'amount' => '7.00',
            'entry_date' => '2026-09-01',
        ]);
        CapitalExpense::create([
            'description' => 'Legacy capital expense',
            'amount' => '3.00',
            'expense_date' => '2026-09-02',
        ]);

        $this->assertSame(1, Investment::count());
        $this->assertSame(1, CapitalExpense::count());
        $this->assertSame(1, CashMovement::count());
        $this->assertSame(0, FixedAsset::count());
        $this->assertSame(0, CapitalFundingTransaction::count());

        $service = app(CapitalManagementService::class);
        $this->assertSame(0, $service->activeTotals()['funding_minor']);
        $this->assertSame(0, $service->activeTotals()['company_asset_minor']);

        $this->createCompanyAsset($admin, $category, $account, '2.000');
        $this->assertSame(0, Expense::count());

        [, $partnerUser] = $this->createPartnerUser();
        $this->actingAs($partnerUser)->get(route('capital-management.index'))->assertForbidden();
        $this->actingAs($partnerUser)->post(route('capital-funding-transactions.store'), [
            'source_name' => 'Blocked',
            'funding_type' => CapitalFundingTransaction::TYPE_OTHER_FUNDING,
            'financial_account_id' => $account->id,
            'amount' => '1.000',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertForbidden();
        $this->actingAs($partnerUser)->post(route('fixed-assets.store'), [
            'name' => 'Blocked Asset',
            'asset_category_id' => $category->id,
            'acquisition_cost' => '1.000',
            'funding_source' => FixedAsset::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'acquired_at' => '2026-09-14',
        ])->assertForbidden();
    }

    private function createCompanyAsset(User $admin, AssetCategory $category, FinancialAccount $account, string $amount): FixedAsset
    {
        $this->actingAs($admin)->post(route('fixed-assets.store'), [
            'name' => 'Company Asset',
            'asset_category_id' => $category->id,
            'acquisition_cost' => $amount,
            'funding_source' => FixedAsset::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'acquired_at' => '2026-09-14',
        ])->assertSessionHas('success');

        return FixedAsset::orderByDesc('id')->firstOrFail();
    }

    private function createPersonalAsset(User $admin, User $payer, AssetCategory $category, string $amount): FixedAsset
    {
        $this->actingAs($admin)->post(route('fixed-assets.store'), [
            'name' => 'Personal Asset',
            'asset_category_id' => $category->id,
            'acquisition_cost' => $amount,
            'funding_source' => FixedAsset::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'acquired_at' => '2026-09-14',
        ])->assertSessionHas('success');

        return FixedAsset::orderByDesc('id')->firstOrFail();
    }

    private function createAccount(string $code, string $name, string $openingBalance = '0.000'): FinancialAccount
    {
        $account = FinancialAccount::create([
            'code' => $code,
            'name_ar' => $name,
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => 'JOD',
            'is_active' => true,
        ]);

        $minor = Money::fromJod($openingBalance)->minorUnits();
        if ($minor > 0) {
            CashMovement::create([
                'financial_account_id' => $account->id,
                'direction' => CashMovement::DIRECTION_INFLOW,
                'amount_minor' => $minor,
                'currency' => 'JOD',
                'event_type' => CashMovement::EVENT_OPENING_BALANCE,
                'event_key' => 'test_d2b_account:'.$account->id.':opening',
                'source_type' => FinancialAccount::class,
                'source_id' => $account->id,
                'occurred_at' => '2026-09-14 08:00:00',
            ]);
        }

        return $account;
    }

    private function createPartnerUser(): array
    {
        $partner = Partner::create([
            'company_name' => 'Phase D2B Partner',
            'email' => 'phase-d2b-partner@example.com',
        ]);

        $user = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        return [$partner, $user];
    }
}
