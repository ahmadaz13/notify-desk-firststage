<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory;

    public const DIRECTION_INFLOW = 'inflow';
    public const DIRECTION_OUTFLOW = 'outflow';

    public const EVENT_OPENING_BALANCE = 'opening_balance';
    public const EVENT_PAYMENT_RECEIVED = 'payment_received';
    public const EVENT_PAYMENT_REVERSAL = 'payment_reversal';
    public const EVENT_REFUND_ISSUED = 'refund_issued';
    public const EVENT_TRANSFER_OUT = 'transfer_out';
    public const EVENT_TRANSFER_IN = 'transfer_in';
    public const EVENT_EXPENSE_PAID = 'expense_paid';
    public const EVENT_EXPENSE_REVERSAL = 'expense_reversal';
    public const EVENT_CAPITAL_FUNDING_RECEIVED = 'capital_funding_received';
    public const EVENT_CAPITAL_FUNDING_REVERSAL = 'capital_funding_reversal';
    public const EVENT_ASSET_ACQUISITION = 'asset_acquisition';
    public const EVENT_ASSET_ACQUISITION_REVERSAL = 'asset_acquisition_reversal';

    protected $fillable = [
        'financial_account_id',
        'direction',
        'amount_minor',
        'currency',
        'event_type',
        'event_key',
        'source_type',
        'source_id',
        'occurred_at',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amountJod(): string
    {
        return Money::fromMinorUnits($this->amount_minor)->format();
    }
}
