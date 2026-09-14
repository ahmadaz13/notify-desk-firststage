<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevenueRecognitionSchedule extends Model
{
    use HasFactory;

    public const POLICY_SUBSCRIPTION_MONTHLY = 'subscription_monthly';
    public const POLICY_SUBSCRIPTION_ANNUAL = 'subscription_annual';
    public const POLICY_POINT_IN_TIME_SETUP = 'point_in_time_setup';
    public const POLICY_POINT_IN_TIME_SERVICE = 'point_in_time_service';
    public const POLICY_MANUAL_REVIEW = 'manual_review';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected $fillable = [
        'invoice_line_id',
        'invoice_id',
        'subscription_id',
        'revenue_account_id',
        'policy',
        'currency',
        'original_recognizable_minor',
        'recognition_start_date',
        'recognition_end_date',
        'period_count',
        'status',
        'requires_manual_confirmation',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_recognizable_minor' => 'integer',
            'recognition_start_date' => 'date',
            'recognition_end_date' => 'date',
            'period_count' => 'integer',
            'requires_manual_confirmation' => 'boolean',
        ];
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'revenue_account_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(RevenueRecognitionPeriod::class)->orderBy('period_index');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(RevenueRecognitionAdjustment::class);
    }
}
