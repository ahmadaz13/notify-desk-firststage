<?php

namespace App\ViewModels;

use App\Models\Appointment;
use App\Models\Product;
use App\Models\Subscription;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Human wording for client data (P10): stage labels/tones, dates with Latin digits (D-21),
 * System names from subscription snapshots, closure reasons. No business rules live here.
 */
class ClientPresenter
{
    /**
     * Localized label of a stored reference value (\15.1). Values that are unknown, historical or
     * typed as "Other" render exactly as stored.
     *
     * @param  array<string, string>  $labels  ReferenceDataService::labels()
     */
    public static function referenceLabel(array $labels, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return $labels[$value] ?? $value;
    }

    public static function stageLabel(string $stage): string
    {
        return __('notify.clients.stages.'.ClientLifecycle::normalizeStage($stage));
    }

    /** Colour is always paired with the label and an icon (§36). */
    public static function stageTone(string $stage): string
    {
        return match ($stage) {
            ClientLifecycle::SUBSCRIBER => 'success',
            ClientLifecycle::FORMER_SUBSCRIBER, ClientLifecycle::INSTALLED_FREE, ClientLifecycle::DECISION_PENDING => 'warning',
            ClientLifecycle::CLOSED => 'neutral',
            default => 'info',
        };
    }

    public static function stageIcon(string $stage): string
    {
        return match ($stage) {
            ClientLifecycle::CONTACTING => 'phone',
            ClientLifecycle::APPOINTMENT => 'calendar',
            ClientLifecycle::INSTALLATION_SCHEDULED => 'wrench',
            ClientLifecycle::INSTALLED_FREE => 'package',
            ClientLifecycle::DECISION_PENDING => 'clipboard-list',
            ClientLifecycle::SUBSCRIBER => 'check-circle',
            ClientLifecycle::FORMER_SUBSCRIBER => 'activity',
            ClientLifecycle::CLOSED => 'x',
            default => 'users',
        };
    }

    public static function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);
        if ($date->isToday()) {
            return __('notify.client_hub.time.today');
        }
        if ($date->isTomorrow()) {
            return __('notify.client_hub.time.tomorrow');
        }
        if ($date->isYesterday()) {
            return __('notify.client_hub.time.yesterday');
        }

        return $date->format('Y-m-d');
    }

    public static function dateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return self::date($date).' · '.$date->format('H:i');
    }

    public static function appointmentWhen(Appointment $appointment): string
    {
        $date = self::date($appointment->appointment_date);
        $time = $appointment->appointment_time ? substr((string) $appointment->appointment_time, 0, 5) : null;

        return trim($date.($time ? ' · '.$time : ''));
    }

    public static function systemNames(Subscription $subscription): string
    {
        $arabic = str_starts_with(app()->getLocale(), 'ar');
        $names = $subscription->relationLoaded('systems')
            ? $subscription->systems->map(fn ($system) => $arabic
                ? ($system->pivot->system_name_ar_snapshot ?: $system->name_ar)
                : ($system->pivot->system_name_en_snapshot ?: $system->name_en ?: $system->name_ar))->filter()->unique()
            : collect();

        if ($names->isNotEmpty()) {
            return $names->join($arabic ? '، ' : ', ');
        }

        // Legacy plan-based subscriptions (before agreed-value V1) keep their product/plan names.
        $plan = $subscription->relationLoaded('plan') ? $subscription->plan : null;
        $legacy = collect([
            $arabic ? ($plan?->product?->name_ar ?: $plan?->product?->name_en) : ($plan?->product?->name_en ?: $plan?->product?->name_ar),
            $arabic ? ($plan?->name_ar ?: $plan?->name_en) : ($plan?->name_en ?: $plan?->name_ar),
        ])->filter()->unique();

        return $legacy->isNotEmpty()
            ? $legacy->join(' · ')
            : (string) ($subscription->plan_name_snapshot ?: __('notify.client_hub.subscription.title'));
    }

    public static function systemName(Product $system): string
    {
        return str_starts_with(app()->getLocale(), 'ar')
            ? (string) ($system->name_ar ?: $system->name_en)
            : (string) ($system->name_en ?: $system->name_ar);
    }

    public static function cycleLabel(Subscription $subscription): string
    {
        $interval = $subscription->billing_interval_v2 ?: ($subscription->billing_type ?: 'monthly');

        return $interval === 'annual'
            ? __('notify.client_hub.subscription.annual')
            : __('notify.client_hub.subscription.monthly');
    }

    public static function closedReasonLabel(?string $closedReason): ?string
    {
        if (blank($closedReason)) {
            return null;
        }

        $code = Str::before($closedReason, ':');
        $note = Str::contains($closedReason, ':') ? trim(Str::after($closedReason, ':')) : null;
        $key = 'notify.client_workspace.close_reasons.'.$code;
        $label = trans()->has($key) ? __($key) : $code;

        return $note ? $label.' · '.$note : $label;
    }

    public static function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '962'.substr($digits, 1);
        } elseif (str_starts_with($digits, '7')) {
            $digits = '962'.$digits;
        }

        return 'https://wa.me/'.$digits;
    }

    public static function telUrl(?string $phone): ?string
    {
        $clean = preg_replace('/[^0-9+]/', '', (string) $phone);

        return $clean !== '' ? 'tel:'.$clean : null;
    }
}
