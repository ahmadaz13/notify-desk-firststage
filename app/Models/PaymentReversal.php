<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'reason',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'reversed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
