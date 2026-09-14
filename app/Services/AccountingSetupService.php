<?php

namespace App\Services;

use App\Models\AccountingSystemMapping;
use App\Models\AssetCategory;
use App\Models\ChartAccount;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingSetupService
{
    public const SYSTEM_MAPPINGS = [
        'billing_clearing' => '2100',
        'accounts_receivable' => '1200',
        'deferred_revenue' => '2400',
        'customer_credits' => '2500',
        'sales_tax_payable' => '2600',
        'related_party_payable' => '2200',
        'loan_payable' => '2300',
        'contributed_capital' => '3100',
        'opening_balance_equity' => '3200',
        'unclassified_funding' => '2900',
        'saas_subscription_revenue' => '4100',
        'one_time_service_revenue' => '4200',
    ];

    public function ensureSeeded(): void
    {
        DB::transaction(function () {
            $accounts = [
                ['1000', 'الأصول', 'Assets', ChartAccount::TYPE_ASSET, null, false],
                ['1100', 'النقد وما في حكمه', 'Cash and Cash Equivalents', ChartAccount::TYPE_ASSET, '1000', false],
                ['1200', 'ذمم العملاء', 'Accounts Receivable', ChartAccount::TYPE_ASSET, '1000', true],
                ['1500', 'الأصول الثابتة', 'Fixed Assets', ChartAccount::TYPE_ASSET, '1000', false],
                ['2000', 'الالتزامات', 'Liabilities', ChartAccount::TYPE_LIABILITY, null, false],
                ['2100', 'حساب تسوية التحصيلات', 'Billing Clearing', ChartAccount::TYPE_LIABILITY, '2000', true],
                ['2200', 'مبالغ مستحقة لأطراف ذات علاقة', 'Due to Related Parties', ChartAccount::TYPE_LIABILITY, '2000', true],
                ['2300', 'قروض مستحقة', 'Loans Payable', ChartAccount::TYPE_LIABILITY, '2000', true],
                ['2400', 'إيرادات مؤجلة', 'Deferred Revenue', ChartAccount::TYPE_LIABILITY, '2000', true],
                ['2500', 'أرصدة العملاء الدائنة', 'Customer Credits', ChartAccount::TYPE_LIABILITY, '2000', true],
                ['2600', 'ضريبة المبيعات المستحقة', 'Sales Tax Payable', ChartAccount::TYPE_LIABILITY, '2000', true],
                ['2900', 'تمويل غير مصنف', 'Unclassified Funding', ChartAccount::TYPE_LIABILITY, '2000', true],
                ['3000', 'حقوق الملكية', 'Equity', ChartAccount::TYPE_EQUITY, null, false],
                ['3100', 'رأس المال والمساهمات', 'Contributed Capital', ChartAccount::TYPE_EQUITY, '3000', true],
                ['3200', 'حقوق ملكية الرصيد الافتتاحي', 'Opening Balance Equity', ChartAccount::TYPE_EQUITY, '3000', true],
                ['4000', 'الإيرادات', 'Revenue', ChartAccount::TYPE_REVENUE, null, false],
                ['4100', 'إيرادات اشتراكات SaaS', 'SaaS Subscription Revenue', ChartAccount::TYPE_REVENUE, '4000', true],
                ['4200', 'إيرادات الخدمات غير المتكررة', 'One-Time Service Revenue', ChartAccount::TYPE_REVENUE, '4000', true],
                ['5000', 'المصاريف التشغيلية', 'Operating Expenses', ChartAccount::TYPE_EXPENSE, null, false],
            ];

            foreach ($accounts as [$code, $nameAr, $nameEn, $type, $parentCode, $posting]) {
                $parent = $parentCode === null ? null : ChartAccount::where('code', $parentCode)->first();
                $this->upsertAccount($code, $nameAr, $nameEn, $type, $parent?->id, $posting, true);
            }

            foreach (self::SYSTEM_MAPPINGS as $key => $code) {
                $account = ChartAccount::where('code', $code)->firstOrFail();
                AccountingSystemMapping::updateOrCreate(
                    ['mapping_key' => $key],
                    ['chart_account_id' => $account->id]
                );
            }

            FinancialAccount::query()->orderBy('id')->each(fn (FinancialAccount $account) => $this->ensureFinancialAccountMapping($account));
            ExpenseCategory::query()->orderBy('id')->each(fn (ExpenseCategory $category) => $this->ensureExpenseCategoryMapping($category));
            AssetCategory::query()->orderBy('id')->each(fn (AssetCategory $category) => $this->ensureAssetCategoryMapping($category));
        });
    }

    public function systemAccount(string $mappingKey): ChartAccount
    {
        $this->ensureSeeded();

        $mapping = AccountingSystemMapping::with('chartAccount')
            ->where('mapping_key', $mappingKey)
            ->first();

        if ($mapping?->chartAccount === null) {
            throw ValidationException::withMessages(['accounting_mapping' => 'حساب النظام المحاسبي غير مضبوط: '.$mappingKey]);
        }

        return $mapping->chartAccount;
    }

    public function ensureFinancialAccountMapping(FinancialAccount $financialAccount): ChartAccount
    {
        $this->ensureBaseAccountsOnly();
        if ($financialAccount->chart_account_id !== null) {
            return ChartAccount::findOrFail($financialAccount->chart_account_id);
        }

        $parent = ChartAccount::where('code', '1100')->firstOrFail();
        $account = $this->upsertAccount(
            $this->ledgerCode('111', $financialAccount->id),
            $financialAccount->name_ar,
            $financialAccount->name_en ?: $financialAccount->code,
            ChartAccount::TYPE_ASSET,
            $parent->id,
            true,
            false,
            'Dedicated GL cash ledger for D1 financial account '.$financialAccount->code
        );

        $financialAccount->forceFill(['chart_account_id' => $account->id])->save();

        return $account;
    }

    public function ensureExpenseCategoryMapping(ExpenseCategory $category): ChartAccount
    {
        $this->ensureBaseAccountsOnly();
        if ($category->chart_account_id !== null) {
            return ChartAccount::findOrFail($category->chart_account_id);
        }

        $parent = ChartAccount::where('code', '5000')->firstOrFail();
        $account = $this->upsertAccount(
            $this->ledgerCode('51', $category->id),
            $category->displayName(),
            $category->name_en ?: $category->key,
            ChartAccount::TYPE_EXPENSE,
            $parent->id,
            true,
            false,
            'Operating expense category ledger'
        );

        $category->forceFill(['chart_account_id' => $account->id])->save();

        return $account;
    }

    public function ensureAssetCategoryMapping(AssetCategory $category): ChartAccount
    {
        $this->ensureBaseAccountsOnly();
        if ($category->chart_account_id !== null) {
            return ChartAccount::findOrFail($category->chart_account_id);
        }

        $parent = ChartAccount::where('code', '1500')->firstOrFail();
        $account = $this->upsertAccount(
            $this->ledgerCode('15', $category->id),
            $category->name_ar,
            $category->name_en ?: $category->code,
            ChartAccount::TYPE_ASSET,
            $parent->id,
            true,
            false,
            'Fixed asset category ledger'
        );

        $category->forceFill(['chart_account_id' => $account->id])->save();

        return $account;
    }

    private function ensureBaseAccountsOnly(): void
    {
        if (ChartAccount::where('code', '1000')->exists()) {
            return;
        }

        $this->ensureSeeded();
    }

    private function upsertAccount(
        string $code,
        string $nameAr,
        ?string $nameEn,
        string $type,
        ?int $parentId,
        bool $posting,
        bool $system,
        ?string $description = null
    ): ChartAccount {
        return ChartAccount::updateOrCreate(
            ['code' => $code],
            [
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'account_type' => $type,
                'parent_id' => $parentId,
                'normal_balance' => in_array($type, [ChartAccount::TYPE_ASSET, ChartAccount::TYPE_EXPENSE], true)
                    ? ChartAccount::NORMAL_DEBIT
                    : ChartAccount::NORMAL_CREDIT,
                'is_system' => $system,
                'is_active' => true,
                'archived_at' => null,
                'allow_direct_posting' => $posting,
                'description' => $description,
            ]
        );
    }

    private function ledgerCode(string $prefix, int $id): string
    {
        return $prefix.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
