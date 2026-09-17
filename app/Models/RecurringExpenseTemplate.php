<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpenseTemplate extends Model
{
    use HasFactory;

    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';
    public const FREQUENCY_ANNUAL = 'annual';

    public const FREQUENCIES = [
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_QUARTERLY,
        self::FREQUENCY_ANNUAL,
    ];

    protected $fillable = [
        'name',
        'category_id',
        'vendor_id',
        'payee_name',
        'currency',
        'amount_minor',
        'frequency',
        'interval_count',
        'start_date',
        'end_date',
        'next_due_date',
        'default_financial_account_id',
        'default_funding_source',
        'default_paid_by_user_id',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'interval_count' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function defaultFinancialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'default_financial_account_id');
    }

    public function defaultPayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_paid_by_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(RecurringExpenseObligation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function amountJod(): string
    {
        return Money::fromMinorUnits($this->amount_minor)->format();
    }
}
