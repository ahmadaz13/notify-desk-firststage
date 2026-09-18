<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Support\PaymentMethods;
use Illuminate\Validation\ValidationException;

class PaymentFinancialAccountResolver
{
    private const METHOD_ACCOUNT_TYPES = [
        PaymentMethods::CASH => FinancialAccount::TYPE_CASH,
        PaymentMethods::BANK_TRANSFER => FinancialAccount::TYPE_BANK,
        PaymentMethods::CLIQ => FinancialAccount::TYPE_BANK,
        PaymentMethods::E_WALLET => FinancialAccount::TYPE_WALLET,
        PaymentMethods::ZAIN_CASH => FinancialAccount::TYPE_WALLET,
        PaymentMethods::ORANGE_MONEY => FinancialAccount::TYPE_WALLET,
        PaymentMethods::OTHER => FinancialAccount::TYPE_OTHER,
    ];

    public function resolve(string $paymentMethod): FinancialAccount
    {
        $accountType = self::METHOD_ACCOUNT_TYPES[$paymentMethod] ?? null;

        if ($accountType === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'اختر طريقة دفع معتمدة.',
            ]);
        }

        $accountsOfType = FinancialAccount::query()
            ->where('type', $accountType)
            ->get();

        if ($accountsOfType->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_method' => 'لا يوجد حساب مالي مهيأ لطريقة الدفع المحددة.',
            ]);
        }

        $eligible = $accountsOfType
            ->filter(fn (FinancialAccount $account) => $account->is_active
                && $account->archived_at === null
                && $account->currency === 'JOD')
            ->values();

        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_method' => 'الحساب المالي المرتبط بطريقة الدفع غير نشط أو غير مؤهل.',
            ]);
        }

        if ($eligible->count() > 1) {
            throw ValidationException::withMessages([
                'payment_method' => 'تعذر تحديد الحساب المالي بشكل آمن.',
            ]);
        }

        return $eligible->first();
    }

    public function mappingSourceDescription(): string
    {
        return 'Canonical PaymentMethods values mapped to FinancialAccount::type; exactly one active JOD account of the mapped type is required.';
    }
}
