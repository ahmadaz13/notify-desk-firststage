<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    use HasFactory;

    public const MONTHLY = 'monthly';
    public const ANNUAL = 'annual';
    public const CURRENCY = 'JOD';

    protected $fillable = [
        'plan_id',
        'billing_interval',
        'currency',
        'amount_minor',
        'setup_fee_minor',
        'included_branch_quantity',
        'additional_branch_price_minor',
        'default_tax_rate_bps',
        'effective_from',
        'effective_until',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'setup_fee_minor' => 'integer',
        'included_branch_quantity' => 'integer',
        'additional_branch_price_minor' => 'integer',
        'default_tax_rate_bps' => 'integer',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeEffective($query, $at = null)
    {
        $at = $at ?: now();

        return $query->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(function ($inner) use ($at) {
                $inner->whereNull('effective_until')->orWhere('effective_until', '>', $at);
            });
    }
}
