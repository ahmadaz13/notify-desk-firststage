<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    public const ENGINE_V2 = 'v2';

    protected $fillable = [
        'client_id',
        'subscription_id',
        'payment_schedule_id',
        'amount',
        'amount_minor',
        'currency',
        'payment_engine_version',
        'payment_method',
        'reference',
        'paid_at',
        'received_at',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)->orderBy('allocated_at')->orderBy('id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('refunded_at')->orderBy('id');
    }
}
