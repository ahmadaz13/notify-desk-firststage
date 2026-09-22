<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'client_id',
        'amount_minor',
        'allocated_at',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'allocated_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentAllocationReversal::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (PaymentAllocation $allocation) {
            throw new \DomainException('Payment allocations are immutable financial records and cannot be deleted. Use reversal instead.');
        });
    }
}
