<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceLine extends Model
{
    use HasFactory;

    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_SETUP_FEE = 'setup_fee';
    public const TYPE_ONE_TIME_SERVICE = 'one_time_service';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'invoice_id',
        'line_type',
        'service_id',
        'plan_id',
        'plan_price_id',
        'item_code_snapshot',
        'description_snapshot',
        'quantity',
        'unit_price_minor',
        'subtotal_minor',
        'discount_minor',
        'tax_rate_bps',
        'tax_minor',
        'total_minor',
        'metadata',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_rate_bps' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function revenueRecognitionSchedule(): HasOne
    {
        return $this->hasOne(RevenueRecognitionSchedule::class);
    }

    public function totalJod(): string
    {
        return Money::fromMinorUnits($this->total_minor)->format();
    }
}
