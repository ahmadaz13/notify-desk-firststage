<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinancialTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'from_financial_account_id',
        'to_financial_account_id',
        'currency',
        'amount_minor',
        'transferred_at',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'transferred_at' => 'datetime',
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'from_financial_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'to_financial_account_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(FinancialTransferReversal::class);
    }

    public function amountJod(): string
    {
        return Money::fromMinorUnits($this->amount_minor)->format();
    }

    protected static function booted(): void
    {
        static::deleting(function (FinancialTransfer $transfer) {
            throw new \DomainException('Financial transfers are immutable financial records and cannot be deleted. Use reversal instead.');
        });
    }
}
