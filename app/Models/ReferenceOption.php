<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One value of an operational reference list (§15.1). Client columns stay plain strings (no FKs).
 */
class ReferenceOption extends Model
{
    protected $fillable = [
        'list_key',
        'value',
        'label_ar',
        'label_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeForList(Builder $query, string $listKey): Builder
    {
        return $query->where('list_key', $listKey)->orderBy('sort_order')->orderBy('id');
    }

    public function label(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->label_ar : $this->label_en;
    }
}
