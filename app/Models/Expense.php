<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    use HasFactory;

    public const ENGINE_V2 = 'v2';
    public const FUNDING_COMPANY_ACCOUNT = 'company_account';
    public const FUNDING_PERSONAL = 'personal';

    protected $fillable = [
        'expense_engine_version',
        'amount',
        'amount_minor',
        'currency',
        'category',
        'category_id',
        'category_name_snapshot',
        'vendor_id',
        'payee_name_snapshot',
        'financial_account_id',
        'funding_source',
        'paid_by_user_id',
        'incurred_on',
        'paid_at',
        'reference',
        'recurring_expense_obligation_id',
        'created_by',
        'description',
        'notes',
        'date',
        'time',
        'frequency',
        'visibility',
        'paid_by',
        'related_client_id',
        'related_appointment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'date' => 'date',
            'incurred_on' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function personalPayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(ExpenseReversal::class);
    }

    public function recurringObligation(): BelongsTo
    {
        return $this->belongsTo(RecurringExpenseObligation::class, 'recurring_expense_obligation_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'related_client_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'related_appointment_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Partners are strictly excluded from internal business operational expenses
        if ($user->isPartner()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('visibility', 'shared')
                ->orWhere('paid_by', $user->id);
        });
    }

    public function scopeToday(Builder $query, ?string $date = null): Builder
    {
        $targetDate = $date ?: Carbon::today()->toDateString();
        return $query->whereDate('date', $targetDate);
    }

    public function scopeV2(Builder $query): Builder
    {
        return $query->where('expense_engine_version', self::ENGINE_V2);
    }

    public function scopeActiveV2(Builder $query): Builder
    {
        return $query->v2()->whereDoesntHave('reversal');
    }
}
