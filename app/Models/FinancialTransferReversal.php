<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransferReversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_transfer_id',
        'reason',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'reversed_at' => 'datetime',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(FinancialTransfer::class, 'financial_transfer_id');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
