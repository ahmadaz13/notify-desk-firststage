<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalFundingReversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'capital_funding_transaction_id',
        'reason',
        'reversed_at',
        'reversed_by',
    ];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CapitalFundingTransaction::class, 'capital_funding_transaction_id');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
