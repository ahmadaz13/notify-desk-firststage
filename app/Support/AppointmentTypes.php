<?php

namespace App\Support;

class AppointmentTypes
{
    public const PHYSICAL_VISIT = 'physical_visit';
    public const ONLINE_DEMO = 'online_demo';
    public const PHONE_CALL = 'phone_call';
    public const INSTALLATION = 'installation';

    public const TYPES = [
        self::PHYSICAL_VISIT,
        self::ONLINE_DEMO,
        self::PHONE_CALL,
        self::INSTALLATION,
    ];

    public static function labels(): array
    {
        return [
            self::PHYSICAL_VISIT => 'زيارة ميدانية',
            self::ONLINE_DEMO => 'عرض أونلاين',
            self::PHONE_CALL => 'مكالمة هاتفية',
            self::INSTALLATION => 'تركيب مجاني',
        ];
    }

    public static function label(?string $type): string
    {
        return self::labels()[$type] ?? (string) $type;
    }

    public static function activeStatuses(): array
    {
        return ['scheduled', 'confirmed', 'rescheduled'];
    }
}
