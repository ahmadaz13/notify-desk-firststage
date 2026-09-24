<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\CapitalFundingReversal;
use App\Models\CapitalFundingTransaction;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetAcquisitionReversal;
use App\Models\FundingSource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
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
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
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
        // P3 / FROZEN D-05: capital funding routes exist only while Capital & Financing is enabled.
        Setting::set('feature_capital_financing', '1');
    }

    /**
     * P3 / §13: fixed-asset and asset-category routes are removed from V1; the retained engine is
     * exercised directly through CapitalManagementService.
     */
    private function assertRejected(callable $action, string $key): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
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
        // P6 (§10.2, §13): funding is received by Cash or CliQ and resolved to the V1 company account.
        $account = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $source = FundingSource::create(['name' => 'Owner', 'type' => FundingSource::TYPE_OWNER, 'is_active' => true, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('capital-funding-transactions.store'), [
            'funding_source_id' => $source->id,
            'funding_type' => CapitalFundingTransaction::TYPE_OWNER_CONTRIBUTION,
            'payment_method' => 'bank_transfer',
            'amount' => '0.001',
            'received_at' => '2026-09-14 10:00:00',
        ])->assertSessionHasErrors('payment_method');

        $this->actingAs($admin)->post(route('capital-funding-transactions.store'), [
            'funding_source_id' => $source->id,
            'funding_type' => CapitalFundingTransaction::TYPE_OWNER_CONTRIBUTION,
            'payment_method' => 'cash',
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

        $service = app(CapitalManagementService::class);
        $service->createAssetCategory(['code' => 'custom_asset', 'name_ar' => 'أصل مخصص'], $admin);
        $this->assertTrue(AssetCategory::where('code', 'custom_asset')->exists());

        $this->assertRejected(fn () => $service->acquireFixedAsset([
            'name' => 'Laptop',
            'asset_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'acquisition_cost' => '12.375',
            'funding_source' => FixedAsset::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $archived->id,
            'acquired_at' => '2026-09-14',
        ], $admin), 'financial_account_id');

        $service->acquireFixedAsset([
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
        ], $admin);

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

        $service = app(CapitalManagementService::class);
        $this->assertRejected(fn () => $service->acquireFixedAsset([
            'name' => 'Tablet',
            'asset_category_id' => $category->id,
            'acquisition_cost' => '40.000',
            'funding_source' => FixedAsset::FUNDING_PERSONAL,
            'acquired_at' => '2026-09-14',
        ], $admin), 'paid_by_user_id');

        $service->acquireFixedAsset([
            'name' => 'Tablet',
            'asset_category_id' => $category->id,
            'payee_name' => 'Retail Store',
            'acquisition_cost' => '40.000',
            'funding_source' => FixedAsset::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'acquired_at' => '2026-09-14',
        ], $admin);

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

        $service = app(CapitalManagementService::class);
        $service->changeAssetStatus($companyAsset, FixedAsset::STATUS_OUT_OF_SERVICE, $admin);
        $this->assertSame(FixedAsset::STATUS_OUT_OF_SERVICE, $companyAsset->fresh()->status);

        $this->assertRejected(fn () => $service->reverseAssetAcquisition($companyAsset, '', $admin), 'reason');
        $service->reverseAssetAcquisition($companyAsset, 'Wrong asset entry', $admin);

        $this->assertSame(1, FixedAssetAcquisitionReversal::count());
        $this->assertSame(1, CashMovement::where('event_type', CashMovement::EVENT_ASSET_ACQUISITION_REVERSAL)->count());
        $this->assertSame(60000, app(FinancialAccountBalanceService::class)->currentBalanceMinor($account->fresh()));
        $this->assertSame(0, FixedAsset::activeAcquisitions()->whereKey($companyAsset->id)->count());

        $this->assertRejected(fn () => $service->reverseAssetAcquisition($companyAsset->fresh(), 'Duplicate', $admin), 'fixed_asset_id');

        $movementCount = CashMovement::count();
        $service->reverseAssetAcquisition($personalAsset, 'Personal correction', $admin);
        $this->assertSame($movementCount, CashMovement::count());
    }

    public function test_capital_authority_and_partner_boundaries_hold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = AssetCategory::where('code', 'other')->firstOrFail();
        $account = $this->createAccount('legacy_guard_cash', 'Legacy Guard Cash', '20.000');

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
        $this->assertFalse(Route::has('fixed-assets.store'), 'Fixed assets are not part of V1 (P3 / §13).');
    }

    private function createCompanyAsset(User $admin, AssetCategory $category, FinancialAccount $account, string $amount): FixedAsset
    {
        return app(CapitalManagementService::class)->acquireFixedAsset([
            'name' => 'Company Asset',
            'asset_category_id' => $category->id,
            'acquisition_cost' => $amount,
            'funding_source' => FixedAsset::FUNDING_COMPANY_ACCOUNT,
            'financial_account_id' => $account->id,
            'acquired_at' => '2026-09-14',
        ], $admin);
    }

    private function createPersonalAsset(User $admin, User $payer, AssetCategory $category, string $amount): FixedAsset
    {
        return app(CapitalManagementService::class)->acquireFixedAsset([
            'name' => 'Personal Asset',
            'asset_category_id' => $category->id,
            'acquisition_cost' => $amount,
            'funding_source' => FixedAsset::FUNDING_PERSONAL,
            'paid_by_user_id' => $payer->id,
            'acquired_at' => '2026-09-14',
        ], $admin);
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
        return [null, User::factory()->create(['role' => 'external'])];
    }
}
