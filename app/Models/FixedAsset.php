<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FixedAsset extends Model
{
    use HasFactory;

    public const FUNDING_COMPANY_ACCOUNT = 'company_account';
    public const FUNDING_PERSONAL = 'personal';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_OUT_OF_SERVICE = 'out_of_service';
    public const STATUS_DISPOSED = 'disposed';

    public const FUNDING_SOURCES = [
        self::FUNDING_COMPANY_ACCOUNT,
        self::FUNDING_PERSONAL,
    ];

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_OUT_OF_SERVICE,
        self::STATUS_DISPOSED,
    ];

    protected $fillable = [
        'asset_number',
        'name',
        'asset_category_id',
        'category_name_snapshot',
        'description',
        'vendor_id',
        'payee_name_snapshot',
        'serial_number',
        'quantity',
        'currency',
        'acquisition_cost_minor',
        'funding_source',
        'financial_account_id',
        'paid_by_user_id',
        'acquired_at',
        'in_service_at',
        'reference',
        'location',
        'status',
        'useful_life_months',
        'residual_value_minor',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'acquisition_cost_minor' => 'integer',
            'acquired_at' => 'date',
            'in_service_at' => 'date',
            'useful_life_months' => 'integer',
            'residual_value_minor' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function personalPayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acquisitionReversal(): HasOne
    {
        return $this->hasOne(FixedAssetAcquisitionReversal::class);
    }

    public function scopeActiveAcquisitions(Builder $query): Builder
    {
        return $query->whereDoesntHave('acquisitionReversal');
    }

    public function acquisitionCostJod(): string
    {
        return Money::fromMinorUnits($this->acquisition_cost_minor)->format();
    }
}
