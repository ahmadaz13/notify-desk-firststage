<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'cash';
    public const TYPE_BANK = 'bank';
    public const TYPE_WALLET = 'wallet';
    public const TYPE_CLEARING = 'clearing';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CASH,
        self::TYPE_BANK,
        self::TYPE_WALLET,
        self::TYPE_CLEARING,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'type',
        'currency',
        'chart_account_id',
        'is_active',
        'archived_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(FinancialTransfer::class, 'from_financial_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(FinancialTransfer::class, 'to_financial_account_id');
    }
}
