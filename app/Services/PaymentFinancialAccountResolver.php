<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Support\PaymentMethods;
use Illuminate\Validation\ValidationException;

class PaymentFinancialAccountResolver
{
    /**
     * Fixed V1 map of payment method to company account code [FROZEN D-01, D-02].
     */
    public const METHOD_ACCOUNT_CODES = [
        PaymentMethods::CASH => CompanyAccountBootstrapService::CASH_BOX_CODE,
        PaymentMethods::CLIQ => CompanyAccountBootstrapService::CLIQ_CODE,
    ];

    public function resolve(string $paymentMethod): FinancialAccount
    {
        $code = self::METHOD_ACCOUNT_CODES[$paymentMethod] ?? null;

        if ($code === null) {
            throw ValidationException::withMessages([
                'payment_method' => __('notify.payment_receipts.errors.unsupported_method'),
            ]);
        }

        $account = FinancialAccount::query()->where('code', $code)->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'payment_method' => __('notify.payment_receipts.errors.company_account_missing'),
            ]);
        }

        if (! $account->is_active || $account->archived_at !== null || $account->currency !== 'JOD') {
            throw ValidationException::withMessages([
                'payment_method' => __('notify.payment_receipts.errors.company_account_ineligible'),
            ]);
        }

        return $account;
    }
}
