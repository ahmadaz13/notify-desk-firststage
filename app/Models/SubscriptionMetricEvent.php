<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionMetricEvent extends Model
{
    use HasFactory;

    public const TYPE_NEW = 'new';
    public const TYPE_EXPANSION = 'expansion';
    public const TYPE_CONTRACTION = 'contraction';
    public const TYPE_CHURN = 'churn';
    public const TYPE_REACTIVATION = 'reactivation';

    public $timestamps = false;

    protected $fillable = [
        'subscription_id',
        'subscription_event_id',
        'effective_at',
        'movement_type',
        'arr_before_minor',
        'arr_after_minor',
        'arr_delta_minor',
        'plan_before_id',
        'plan_after_id',
        'billing_interval_before',
        'billing_interval_after',
        'quantity_before',
        'quantity_after',
        'source_type',
        'source_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'arr_before_minor' => 'integer',
            'arr_after_minor' => 'integer',
            'arr_delta_minor' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionEvent(): BelongsTo
    {
        return $this->belongsTo(SubscriptionEvent::class);
    }
}
