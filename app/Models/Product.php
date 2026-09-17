<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
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
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('tier')->orderBy('code');
    }

    public function sellablePlans(): HasMany
    {
        return $this->hasMany(Plan::class)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('tier')
            ->orderBy('code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }
}
