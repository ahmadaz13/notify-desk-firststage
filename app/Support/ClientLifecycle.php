<?php

namespace App\Support;

class ClientLifecycle
{
    public const PROSPECT = 'prospect';
    public const CONTACTING = 'contacting';
    public const APPOINTMENT = 'appointment';
    public const INSTALLATION_SCHEDULED = 'installation_scheduled';
    public const INSTALLED_FREE = 'installed_free';
    public const DECISION_PENDING = 'decision_pending';
    public const SUBSCRIBER = 'subscriber';
    public const FORMER_SUBSCRIBER = 'former_subscriber';
    public const CLOSED = 'closed';

    public const STAGES = [
        self::PROSPECT,
        self::CONTACTING,
        self::APPOINTMENT,
        self::INSTALLATION_SCHEDULED,
        self::INSTALLED_FREE,
        self::DECISION_PENDING,
        self::SUBSCRIBER,
        self::FORMER_SUBSCRIBER,
        self::CLOSED,
    ];

    /**
     * Stages that automatic sales-pipeline transitions (call outcome, installation) must never
     * overwrite (§4: "if not subscriber/former/closed").
     */
    public const PIPELINE_LOCKED_STAGES = [
        self::SUBSCRIBER,
        self::FORMER_SUBSCRIBER,
        self::CLOSED,
    ];

    /**
     * Stages only the system may set, through the paid-subscription lifecycle.
     */
    public const SYSTEM_MANAGED_STAGES = [
        self::SUBSCRIBER,
        self::FORMER_SUBSCRIBER,
    ];

    public const CONTACT_OUTCOMES = [
        'appointment',
        'no_contact',
        'callback_later',
        'no_answer_busy',
        'wrong_invalid',
        'answered',
        'no_answer',
        'busy',
        'call_later',
        'wrong_number',
        'not_interested',
        'other',
    ];

    public static function normalizeStage(?string $stage, ?string $legacyStatus = null): string
    {
        $candidate = $stage ?: $legacyStatus;

        if ($candidate === 'archived') {
            return self::CLOSED;
        }

        return in_array($candidate, self::STAGES, true) ? $candidate : self::PROSPECT;
    }

    public static function labels(): array
    {
        return [
            self::PROSPECT => 'فرصة جديدة',
            self::CONTACTING => 'قيد التواصل',
            self::APPOINTMENT => 'موعد',
            self::INSTALLATION_SCHEDULED => 'تركيب مجدول',
            self::INSTALLED_FREE => 'تم التركيب المجاني',
            self::DECISION_PENDING => 'بانتظار القرار',
            self::SUBSCRIBER => 'مشترك',
            self::FORMER_SUBSCRIBER => 'بانتظار التجديد / مشترك سابق',
            self::CLOSED => 'مغلق',
        ];
    }

    public static function label(?string $stage, ?string $legacyStatus = null): string
    {
        $stage = self::normalizeStage($stage, $legacyStatus);

        return self::labels()[$stage] ?? $stage;
    }
}
