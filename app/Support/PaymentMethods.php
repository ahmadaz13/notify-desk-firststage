<?php

namespace App\Support;

class PaymentMethods
{
    public const CASH = 'cash';
    public const BANK_TRANSFER = 'bank_transfer';
    public const CLIQ = 'cliq';
    public const E_WALLET = 'e_wallet';
    public const ZAIN_CASH = 'zain_cash';
    public const ORANGE_MONEY = 'orange_money';
    public const OTHER = 'other';

    /**
     * The only methods accepted for new V1 payment records [FROZEN D-02].
     * The other constants remain solely so historical records stay readable.
     */
    public static function v1(): array
    {
        return [self::CASH, self::CLIQ];
    }

    public static function v1Labels(): array
    {
        return collect(self::v1())
            ->mapWithKeys(fn (string $method) => [$method => __('notify.client_workspace.payment_methods.'.$method)])
            ->all();
    }

    /** Display label for any stored method: localized for V1 methods, historical label otherwise. */
    public static function label(?string $method): string
    {
        if (in_array($method, self::v1(), true)) {
            return __('notify.client_workspace.payment_methods.'.$method);
        }

        return self::labels()[$method] ?? (string) $method;
    }

    public static function labels(): array
    {
        return [
            self::BANK_TRANSFER => 'تحويل بنكي (Bank Transfer)',
            self::CLIQ => 'كليك (CliQ)',
            self::CASH => 'نقداً (Cash)',
            self::ZAIN_CASH => 'زين كاش (Zain Cash)',
            self::ORANGE_MONEY => 'أورنج موني (Orange Money)',
            self::E_WALLET => 'محفظة إلكترونية (E-Wallet)',
            self::OTHER => 'أخرى',
        ];
    }

    public static function values(): array
    {
        return array_keys(self::labels());
    }
}
