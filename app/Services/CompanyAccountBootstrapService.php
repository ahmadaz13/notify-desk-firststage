<?php

namespace App\Services;

use App\Models\FinancialAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ensures the two fixed V1 company accounts exist [FROZEN D-01].
 *
 * Invoked from a data migration, DatabaseSeeder and `php artisan notify:bootstrap`.
 * Never call this inside a web request.
 */
class CompanyAccountBootstrapService
{
    public const CASH_BOX_CODE = 'CASH-BOX';
    public const CLIQ_CODE = 'CLIQ';

    public const DEFAULT_ACCOUNTS = [
        [
            'code' => self::CASH_BOX_CODE,
            'name_ar' => 'الصندوق',
            'name_en' => 'Cash Box',
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => 'JOD',
        ],
        [
            'code' => self::CLIQ_CODE,
            'name_ar' => 'كليك',
            'name_en' => 'CliQ',
            'type' => FinancialAccount::TYPE_BANK,
            'currency' => 'JOD',
        ],
    ];

    public function __construct(private readonly AccountingSetupService $accountingSetup)
    {
    }

    /**
     * Idempotent: finds each account by its stable code and creates it only when missing.
     * Existing accounts (including these two) are never renamed, merged or archived.
     *
     * @return Collection<string, FinancialAccount> keyed by code
     */
    public function ensureDefaults(): Collection
    {
        return DB::transaction(function () {
            $this->accountingSetup->ensureSeeded();

            return collect(self::DEFAULT_ACCOUNTS)->mapWithKeys(function (array $definition) {
                $account = FinancialAccount::query()->firstOrCreate(
                    ['code' => $definition['code']],
                    $definition + ['is_active' => true]
                );

                $this->accountingSetup->ensureFinancialAccountMapping($account);

                return [$definition['code'] => $account->fresh()];
            });
        });
    }
}
