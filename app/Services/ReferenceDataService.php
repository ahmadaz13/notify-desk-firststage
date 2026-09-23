<?php

namespace App\Services;

use App\Models\ReferenceOption;
use Illuminate\Support\Facades\DB;

/**
 * Single source for operational reference lists (§15.1): client_category, lead_source, city_area.
 */
class ReferenceDataService
{
    public const CLIENT_CATEGORY = 'client_category';
    public const LEAD_SOURCE = 'lead_source';
    public const CITY_AREA = 'city_area';

    public const LISTS = [self::CLIENT_CATEGORY, self::LEAD_SOURCE, self::CITY_AREA];

    /**
     * Frozen V1 seed values. lead_source is the de-duplicated union of the previous hard-coded sources;
     * values keep the strings already stored on clients so historical rows still match.
     * client_category and city_area have no frozen seed values and start empty.
     */
    public const DEFAULTS = [
        self::LEAD_SOURCE => [
            ['value' => 'Google Maps', 'label_ar' => 'خرائط Google', 'label_en' => 'Google Maps'],
            ['value' => 'Instagram', 'label_ar' => 'Instagram', 'label_en' => 'Instagram'],
            ['value' => 'Referral', 'label_ar' => 'إحالة', 'label_en' => 'Referral'],
            ['value' => 'Direct Prospecting', 'label_ar' => 'مباشر / زيارة ميدانية', 'label_en' => 'Direct / Field visit'],
            ['value' => 'Existing Client', 'label_ar' => 'عميل حالي', 'label_en' => 'Existing client'],
            ['value' => 'Other', 'label_ar' => 'أخرى', 'label_en' => 'Other'],
        ],
    ];

    /**
     * Idempotent seed: inserts missing default values only; never overwrites owner edits.
     */
    public function ensureDefaults(): void
    {
        DB::transaction(function () {
            foreach (self::DEFAULTS as $listKey => $options) {
                foreach (array_values($options) as $index => $option) {
                    ReferenceOption::query()->firstOrCreate(
                        ['list_key' => $listKey, 'value' => $option['value']],
                        $option + ['sort_order' => ($index + 1) * 10, 'is_active' => true]
                    );
                }
            }
        });
    }

    /**
     * Active options as [value => localized label]. A current value that is unknown or inactive
     * is appended as stored so historical data still renders and stays selectable.
     *
     * @return array<string, string>
     */
    public function options(string $listKey, ?string $current = null): array
    {
        $options = ReferenceOption::query()
            ->forList($listKey)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (ReferenceOption $option) => [$option->value => $option->label()])
            ->all();

        if (filled($current) && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    public function label(string $listKey, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $option = ReferenceOption::query()->where('list_key', $listKey)->where('value', $value)->first();

        return $option?->label() ?? $value;
    }
}
