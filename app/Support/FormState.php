<?php

namespace App\Support;

use Closure;
use Illuminate\Support\ViewErrorBag;

/**
 * Form state for Blade forms (P12).
 *
 * Several forms on one page share field names (every sheet has `note`, `amount`, `appointment_date`),
 * so each form posts a hidden `_form` id. Inside `@formscope('id') … @endformscope` old input and
 * errors only apply when that form was the one submitted — nothing bleeds into sibling sheets.
 *
 * `@invalid('amount', 'payment-amount')` renders aria-invalid / aria-describedby for the field,
 * pointing at the error element rendered by <x-notify.form-field>. Values are never echoed here.
 */
final class FormState
{
    private static ?string $scope = null;

    public static function begin(string $formId): void
    {
        self::$scope = $formId;
    }

    public static function end(): void
    {
        self::$scope = null;
    }

    /** True when no form scope is active, or the active form is the one that was submitted. */
    public static function active(?string $formId = null): bool
    {
        $formId ??= self::$scope;

        return $formId === null || old('_form') === $formId;
    }

    /** Scoped old() for one form: `$old = FormState::oldFor('modal-record-call'); $old('note')`. */
    public static function oldFor(string $formId): Closure
    {
        return fn (string $key, mixed $default = null) => old('_form') === $formId ? old($key, $default) : $default;
    }

    public static function attributes(?ViewErrorBag $errors, string $field, string $id, string $bag = 'default', bool $hint = false): string
    {
        $described = $hint ? [$id.'-hint'] : [];
        $invalid = self::error($errors, $field, $bag) !== null;

        if ($invalid) {
            $described[] = $id.'-error';
        }

        $attributes = $invalid ? ['aria-invalid="true"'] : [];
        if ($described !== []) {
            $attributes[] = 'aria-describedby="'.e(implode(' ', $described)).'"';
        }

        return implode(' ', $attributes);
    }

    /** First error for a field (array fields also match their items, e.g. system_ids.*). */
    public static function error(?ViewErrorBag $errors, ?string $field, string $bag = 'default'): ?string
    {
        if ($errors === null || $field === null || ! self::active()) {
            return null;
        }

        $messages = $errors->getBag($bag);
        $key = self::key($field);

        return $messages->first($key) ?: ($messages->first($key.'.*') ?: null);
    }

    /** `service_ids[]` → `service_ids`, `items[0][name]` → `items.0.name`. */
    public static function key(string $field): string
    {
        return trim(str_replace(['[]', '][', '[', ']'], ['', '.', '.', ''], $field), '.');
    }
}
