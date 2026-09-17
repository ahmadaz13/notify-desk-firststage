<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingSource extends Model
{
    use HasFactory;

    public const TYPE_FOUNDER = 'founder';
    public const TYPE_OWNER = 'owner';
    public const TYPE_INVESTOR = 'investor';
    public const TYPE_LENDER = 'lender';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_FOUNDER,
        self::TYPE_OWNER,
        self::TYPE_INVESTOR,
        self::TYPE_LENDER,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'name',
        'type',
        'user_id',
        'phone',
        'email',
        'notes',
        'is_active',
        'archived_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CapitalFundingTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at')->orderBy('name');
    }
}
