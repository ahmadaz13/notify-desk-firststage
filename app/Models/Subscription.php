<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    /**
     * Billing engines whose subscriptions are recurring and carry billing periods + SaaS metric events:
     * `v2` (plan-price engine) and `v1_simple` (V1 agreed-value subscriptions, §7). Both use the same
     * period-based ARR normalization (SaasMetricEventService::normalizedArrMinorForPeriod).
     */
    public const RECURRING_BILLING_ENGINES = ['v2', 'v1_simple'];

    protected $fillable = [
        'client_id',
        'plan_id',
        'plan_price_id',
        'user_id',
        'billing_engine_version',
        'billing_interval_v2',
        'currency',
        'quantity',
        'plan_code_snapshot',
        'plan_name_snapshot',
        'unit_price_minor',
        'setup_fee_minor_v2',
        'subtotal_minor',
        'discount_minor',
        'tax_rate_bps',
        'tax_minor_v2',
        'total_minor',
        'agreed_value_minor',
        'payment_terms',
        'current_period_start',
        'current_period_end',
        'next_billing_date',
        'billing_type',
        'total_price',
        'setup_fee',
        'base_subtotal',
        'annual_discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'grand_total',
        'monthly_due_day',
        'start_date',
        'renewal_date',
        'installments_count',
        'status',
        'version',
        'previous_subscription_id',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'cancel_at_period_end',
        'cancellation_requested_at',
        'ended_at',
        'pending_plan_id',
        'pending_plan_price_id',
        'pending_quantity',
        'pending_change_effective_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:3',
        'setup_fee' => 'decimal:3',
        'base_subtotal' => 'decimal:3',
        'annual_discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:3',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:3',
        'grand_total' => 'decimal:3',
        'monthly_due_day' => 'integer',
        'installments_count' => 'integer',
        'version' => 'integer',
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'setup_fee_minor_v2' => 'integer',
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_rate_bps' => 'integer',
        'tax_minor_v2' => 'integer',
        'total_minor' => 'integer',
        'agreed_value_minor' => 'integer',
        'current_period_start' => 'date',
        'current_period_end' => 'date',
        'next_billing_date' => 'date',
        'start_date' => 'date',
        'renewal_date' => 'date',
        'cancelled_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'cancellation_requested_at' => 'datetime',
        'ended_at' => 'datetime',
        'pending_quantity' => 'integer',
        'pending_change_effective_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function previousSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'previous_subscription_id');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class)->orderBy('sequence')->orderBy('due_date');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'subscription_service')
            ->withPivot('service_key', 'service_name_ar', 'service_name_en', 'price_contribution')
            ->withTimestamps();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('id');
    }

    public function contract(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Contract::class)->latestOfMany();
    }

    public function systems(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'subscription_system')
            ->withPivot(['system_code_snapshot', 'system_name_ar_snapshot', 'system_name_en_snapshot'])
            ->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('issue_date')->orderByDesc('id');
    }

    public function billingPeriods(): HasMany
    {
        return $this->hasMany(SubscriptionBillingPeriod::class)->orderBy('period_start')->orderBy('id');
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->orderBy('effective_at')->orderBy('id');
    }

    public function metricEvents(): HasMany
    {
        return $this->hasMany(SubscriptionMetricEvent::class)->orderBy('effective_at')->orderBy('id');
    }

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function pendingPlanPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'pending_plan_price_id');
    }
}
