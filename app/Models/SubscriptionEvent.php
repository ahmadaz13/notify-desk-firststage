<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use HasFactory;

    public const TYPE_STARTED = 'started';
    public const TYPE_RENEWED = 'renewed';
    public const TYPE_PLAN_CHANGE_SCHEDULED = 'plan_change_scheduled';
    public const TYPE_PLAN_CHANGE_APPLIED = 'plan_change_applied';
    public const TYPE_CANCELLATION_SCHEDULED = 'cancellation_scheduled';
    public const TYPE_CANCELLATION_CANCELLED = 'cancellation_cancelled';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_REACTIVATED = 'reactivated';

    public $timestamps = false;

    protected $fillable = [
        'subscription_id',
        'event_type',
        'effective_at',
        'from_plan_id',
        'to_plan_id',
        'from_price_minor',
        'to_price_minor',
        'billing_interval',
        'quantity',
        'metadata',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'from_price_minor' => 'integer',
            'to_price_minor' => 'integer',
            'quantity' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
