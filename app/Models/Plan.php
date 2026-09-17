<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'product_id',
        'tier',
        'offer_type',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'is_active',
        'archived_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
        'tier' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'plan_service')
            ->withPivot('sort_order', 'notes')
            ->withTimestamps()
            ->orderBy('plan_service.sort_order')
            ->orderBy('services.id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function activePrices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)
            ->whereNull('archived_at')
            ->where(function ($inner) {
                $inner->whereNull('product_id')
                    ->orWhereHas('product', fn ($product) => $product->sellable());
            });
    }
}
