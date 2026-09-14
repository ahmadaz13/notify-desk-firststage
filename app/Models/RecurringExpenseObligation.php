<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpenseObligation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_SKIPPED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'recurring_expense_template_id',
        'due_date',
        'expected_amount_minor',
        'currency',
        'category_id',
        'category_name_snapshot',
        'vendor_id',
        'payee_name_snapshot',
        'default_financial_account_id',
        'default_funding_source',
        'status',
        'paid_expense_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'expected_amount_minor' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecurringExpenseTemplate::class, 'recurring_expense_template_id');
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

    public function paidExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'paid_expense_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function expectedAmountJod(): string
    {
        return Money::fromMinorUnits($this->expected_amount_minor)->format();
    }
}
