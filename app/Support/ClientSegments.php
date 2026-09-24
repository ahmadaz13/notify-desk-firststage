<?php

namespace App\Support;

use App\Models\User;

/**
 * Client list segments (§5, FROZEN D-03). `clients.stage` is the only authority; the legacy
 * `status` column is never used for segmentation.
 */
class ClientSegments
{
    public const PROSPECTS = 'prospects';

    public const SUBSCRIBERS = 'subscribers';

    public const RENEWAL = 'renewal';

    public const CLOSED = 'closed';

    public const ALL = 'all';

    public const SEGMENTS = [self::PROSPECTS, self::SUBSCRIBERS, self::RENEWAL, self::CLOSED, self::ALL];

    public const PROSPECT_STAGES = [
        ClientLifecycle::PROSPECT,
        ClientLifecycle::CONTACTING,
        ClientLifecycle::APPOINTMENT,
        ClientLifecycle::INSTALLATION_SCHEDULED,
        ClientLifecycle::INSTALLED_FREE,
        ClientLifecycle::DECISION_PENDING,
    ];

    /** @return array<int, string>|null Stages included in the segment; null means every stage. */
    public static function stages(string $segment): ?array
    {
        return match ($segment) {
            self::PROSPECTS => self::PROSPECT_STAGES,
            self::SUBSCRIBERS => [ClientLifecycle::SUBSCRIBER],
            self::RENEWAL => [ClientLifecycle::FORMER_SUBSCRIBER],
            self::CLOSED => [ClientLifecycle::CLOSED],
            default => null,
        };
    }

    public static function forStage(string $stage): string
    {
        return match ($stage) {
            ClientLifecycle::SUBSCRIBER => self::SUBSCRIBERS,
            ClientLifecycle::FORMER_SUBSCRIBER => self::RENEWAL,
            ClientLifecycle::CLOSED => self::CLOSED,
            default => self::PROSPECTS,
        };
    }

    /** Owner-level users start on Subscribers, Staff on Prospects (§5, D-03). */
    public static function defaultFor(?User $user): string
    {
        return $user?->isOwnerLevelInternalUser() ? self::SUBSCRIBERS : self::PROSPECTS;
    }

    public static function isValid(?string $segment): bool
    {
        return in_array($segment, self::SEGMENTS, true);
    }
}
