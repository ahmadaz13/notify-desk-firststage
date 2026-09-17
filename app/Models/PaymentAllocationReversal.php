<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocationReversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_allocation_id',
        'reason',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'reversed_at' => 'datetime',
    ];

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class, 'payment_allocation_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
