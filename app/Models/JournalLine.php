<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'chart_account_id',
        'debit_minor',
        'credit_minor',
        'description',
        'financial_account_id',
        'user_id',
        'client_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JournalLine $line) {
            $debit = (int) $line->debit_minor;
            $credit = (int) $line->credit_minor;

            if ($debit < 0 || $credit < 0 || ($debit === 0 && $credit === 0) || ($debit > 0 && $credit > 0)) {
                throw ValidationException::withMessages(['journal_line' => 'كل سطر محاسبي يجب أن يحتوي على مدين أو دائن فقط وبقيمة موجبة.']);
            }
        });

        static::updating(function () {
            throw ValidationException::withMessages(['journal_line_id' => 'لا يمكن تعديل سطر قيد محاسبي مرحل.']);
        });

        static::deleting(function () {
            throw ValidationException::withMessages(['journal_line_id' => 'لا يمكن حذف سطر قيد محاسبي مرحل.']);
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function debitJod(): string
    {
        return Money::fromMinorUnits($this->debit_minor)->format();
    }

    public function creditJod(): string
    {
        return Money::fromMinorUnits($this->credit_minor)->format();
    }
}

