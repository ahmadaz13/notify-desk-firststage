<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentSchedule extends Model
{
    use HasFactory;

    public const ENGINE_V2 = 'v2';

    protected $fillable = [
        'subscription_id',
        'schedule_engine_version',
        'invoice_id',
        'sequence',
        'amount_due',
        'amount_due_minor',
        'subtotal',
        'discount_amount',
        'setup_fee_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'payment_method',
        'due_date',
        'status',
        'reminder_sent_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'amount_due_minor' => 'integer',
        'amount_due' => 'decimal:3',
        'subtotal' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'setup_fee_amount' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'total_amount' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'due_date' => 'date',
        'reminder_sent_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_schedule_id');
    }
}
