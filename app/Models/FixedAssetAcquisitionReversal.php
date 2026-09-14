<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetAcquisitionReversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixed_asset_id',
        'reason',
        'reversed_at',
        'reversed_by',
    ];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
