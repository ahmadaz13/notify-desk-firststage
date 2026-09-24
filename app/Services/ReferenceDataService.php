<?php

namespace App\Services;

use App\Models\ReferenceOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source for operational reference lists (§15.1): client_category (shown as "Business type"),
 * lead_source, city_area.
 *
 * Clients store the option's `value` as a plain string (no FKs). A value is therefore never renamed
 * for the controlled lists and options are never deleted — they are deactivated, which removes them
 * from new-entry forms while historical client values keep rendering. City/area is suggestions + free
 * text (D-20): its value *is* the suggestion text, so editing a suggestion only changes future
 * suggestions, never stored clients.
 */
class ReferenceDataService
{
    public const CLIENT_CATEGORY = 'client_category';
    public const LEAD_SOURCE = 'lead_source';
    public const CITY_AREA = 'city_area';

    public const LISTS = [self::CLIENT_CATEGORY, self::LEAD_SOURCE, self::CITY_AREA];

    /** Lists whose stored value is a stable key with separate Arabic/English labels. */
    public const LABELLED_LISTS = [self::CLIENT_CATEGORY, self::LEAD_SOURCE];

    /**
     * V1 seed values. lead_source is the de-duplicated union of the previous hard-coded sources;
     * values keep the strings already stored on clients so historical rows still match.
     * client_category holds the active Notify business types (owner, P13). city_area starts empty.
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
        self::CLIENT_CATEGORY => [
            ['value' => 'Restaurants', 'label_ar' => 'مطاعم', 'label_en' => 'Restaurants'],
            ['value' => 'Phone shops', 'label_ar' => 'محلات الهواتف', 'label_en' => 'Phone shops'],
            ['value' => 'Travel & Tourism', 'label_ar' => 'السفر والسياحة', 'label_en' => 'Travel & Tourism'],
            ['value' => 'Farms / Chalets', 'label_ar' => 'مزارع / شاليهات', 'label_en' => 'Farms / Chalets'],
        ],
    ];

    /**
     * Idempotent seed: inserts missing default values only; never overwrites owner edits or
     * reactivates an option the owner turned off.
     */
    public function ensureDefaults(?array $lists = null): void
    {
        DB::transaction(function () use ($lists) {
            foreach (self::DEFAULTS as $listKey => $options) {
                if ($lists !== null && ! in_array($listKey, $lists, true)) {
                    continue;
                }
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

    /**
     * Localized labels for every option of a list, active or not, as [value => label]. One query;
     * for rendering many stored client values. Unknown values are rendered as stored by the caller.
     *
     * @return array<string, string>
     */
    public function labels(string $listKey): array
    {
        return ReferenceOption::query()
            ->where('list_key', $listKey)
            ->get()
            ->mapWithKeys(fn (ReferenceOption $option) => [$option->value => $option->label()])
            ->all();
    }

    /** @return Collection<string, Collection<int, ReferenceOption>> every list, ordered, for Administration. */
    public function listsForAdministration(): Collection
    {
        $options = ReferenceOption::query()
            ->whereIn('list_key', self::LISTS)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('list_key');

        return collect(self::LISTS)->mapWithKeys(fn (string $list) => [$list => $options->get($list, collect())->values()]);
    }

    /** @param array{label_ar: string, label_en?: ?string, sort_order?: ?int} $data */
    public function create(string $listKey, array $data): ReferenceOption
    {
        $labels = $this->labelsFrom($listKey, $data);
        $value = $listKey === self::CITY_AREA ? $labels['label_ar'] : ($labels['label_en'] ?: $labels['label_ar']);
        $this->assertUnique($listKey, $value);

        return ReferenceOption::create($labels + [
            'list_key' => $listKey,
            'value' => $value,
            'sort_order' => $data['sort_order'] ?? ((int) ReferenceOption::query()->where('list_key', $listKey)->max('sort_order') + 10),
            'is_active' => true,
        ]);
    }

    /** @param array{label_ar: string, label_en?: ?string, sort_order?: ?int} $data */
    public function update(ReferenceOption $option, array $data): ReferenceOption
    {
        $labels = $this->labelsFrom($option->list_key, $data);
        $attributes = $labels + ['sort_order' => $data['sort_order'] ?? $option->sort_order];

        // A suggestion's value is its text; controlled lists keep their stored key forever.
        if ($option->list_key === self::CITY_AREA && $labels['label_ar'] !== $option->value) {
            $this->assertUnique(self::CITY_AREA, $labels['label_ar'], $option->id);
            $attributes['value'] = $labels['label_ar'];
        }

        $option->update($attributes);

        return $option;
    }

    public function setActive(ReferenceOption $option, bool $active): ReferenceOption
    {
        $option->update(['is_active' => $active]);

        return $option;
    }

    /** @return array{label_ar: string, label_en: string} */
    private function labelsFrom(string $listKey, array $data): array
    {
        $ar = trim((string) ($data['label_ar'] ?? ''));
        $en = trim((string) ($data['label_en'] ?? ''));

        // City/area suggestions are one text in whatever language the owner types.
        if ($listKey === self::CITY_AREA) {
            return ['label_ar' => $ar, 'label_en' => $ar];
        }

        return ['label_ar' => $ar, 'label_en' => $en !== '' ? $en : $ar];
    }

    private function assertUnique(string $listKey, string $value, ?int $ignoreId = null): void
    {
        $exists = ReferenceOption::query()
            ->where('list_key', $listKey)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['label_ar' => __('notify.reference_data.validation.duplicate')]);
        }
    }
}
