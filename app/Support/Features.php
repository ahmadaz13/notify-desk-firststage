<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Optional feature flags (§13). Flags only change what is exposed; they never change postings
 * or report math.
 */
class Features
{
    public const CAPITAL = 'capital';

    public const SETTING_KEYS = [
        self::CAPITAL => 'feature_capital_financing',
    ];

    public static function enabled(string $feature): bool
    {
        $key = self::SETTING_KEYS[$feature] ?? null;

        return $key !== null && (string) Setting::get($key, '0') === '1';
    }

    public static function capitalEnabled(): bool
    {
        return self::enabled(self::CAPITAL);
    }
}
