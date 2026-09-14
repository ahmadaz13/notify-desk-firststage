<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description',
        'is_active',
        'archived_at',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fixedAssets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('name_ar');
    }

    public function displayName(): string
    {
        return $this->name_ar;
    }
}
