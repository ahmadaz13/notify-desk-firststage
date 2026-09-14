<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionBillingPeriod extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'subscription_id',
        'period_number',
        'period_start',
        'period_end',
        'billing_interval',
        'plan_id',
        'plan_price_id',
        'plan_name_snapshot',
        'price_snapshot_minor',
        'quantity',
        'subtotal_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'currency',
        'invoice_id',
        'status',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'price_snapshot_minor' => 'integer',
            'quantity' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }
}
