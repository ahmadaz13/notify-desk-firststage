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
