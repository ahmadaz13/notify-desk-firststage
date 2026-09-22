<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CapitalFundingTransaction extends Model
{
    use HasFactory;

    public const TYPE_FOUNDER_CONTRIBUTION = 'founder_contribution';
    public const TYPE_OWNER_CONTRIBUTION = 'owner_contribution';
    public const TYPE_EXTERNAL_INVESTMENT = 'external_investment';
    public const TYPE_LOAN_FUNDING = 'loan_funding';
    public const TYPE_OTHER_FUNDING = 'other_funding';

    public const TYPES = [
        self::TYPE_FOUNDER_CONTRIBUTION,
        self::TYPE_OWNER_CONTRIBUTION,
        self::TYPE_EXTERNAL_INVESTMENT,
        self::TYPE_LOAN_FUNDING,
        self::TYPE_OTHER_FUNDING,
    ];

    protected $fillable = [
        'funding_number',
        'funding_source_id',
        'source_name_snapshot',
        'funding_type',
        'financial_account_id',
        'currency',
        'amount_minor',
        'received_at',
        'reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(CapitalFundingReversal::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereDoesntHave('reversal');
    }

    public function amountJod(): string
    {
        return Money::fromMinorUnits($this->amount_minor)->format();
    }

    protected static function booted(): void
    {
        static::deleting(function (CapitalFundingTransaction $funding) {
            throw new \DomainException('Capital funding transactions are immutable financial records and cannot be deleted. Use reversal instead.');
        });
    }
}
