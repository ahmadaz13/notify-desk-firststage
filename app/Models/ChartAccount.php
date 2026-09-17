<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ChartAccount extends Model
{
    use HasFactory;

    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_EXPENSE = 'expense';

    public const NORMAL_DEBIT = 'debit';
    public const NORMAL_CREDIT = 'credit';

    public const TYPES = [
        self::TYPE_ASSET,
        self::TYPE_LIABILITY,
        self::TYPE_EQUITY,
        self::TYPE_REVENUE,
        self::TYPE_EXPENSE,
    ];

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'account_type',
        'parent_id',
        'normal_balance',
        'is_system',
        'is_active',
        'archived_at',
        'allow_direct_posting',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'allow_direct_posting' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ChartAccount $account) {
            if ($account->is_system || $account->journalLines()->exists()) {
                throw ValidationException::withMessages(['chart_account_id' => 'لا يمكن حذف حساب نظامي أو حساب لديه قيود محاسبية.']);
            }
        });

        static::updating(function (ChartAccount $account) {
            if ($account->isDirty('code') && $account->journalLines()->exists()) {
                throw ValidationException::withMessages(['code' => 'لا يمكن تغيير رمز حساب لديه تاريخ محاسبي.']);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopePostingEnabled(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNull('archived_at')
            ->where('allow_direct_posting', true);
    }

    public function displayName(): string
    {
        return $this->name_ar.' ('.$this->code.')';
    }
}

