<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'status',
        'email',
        'phone',
        'profit_share_percentage',
        'deduction_percentage',
        'public_uuid',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'profit_share_percentage' => 'decimal:2',
            'deduction_percentage' => 'decimal:2',
            'onboarded_at' => 'datetime',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isActive(): bool
    {
        return empty($this->status) || $this->status === 'active';
    }

    protected static function booted(): void
    {
        static::creating(function ($partner) {
            if (empty($partner->public_uuid)) {
                $partner->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function conflictResolutionRequests(): HasMany
    {
        return $this->hasMany(ConflictResolutionRequest::class);
    }

    public function getTotalClientPaymentsAttribute(): float
    {
        return (float) DB::table('payments')
            ->join('clients', 'clients.id', '=', 'payments.client_id')
            ->where('clients.partner_id', $this->id)
            ->sum('payments.amount');
    }

    public function getEarnedShareAttribute(): ?float
    {
        if ($this->profit_share_percentage === null || $this->profit_share_percentage === '') {
            return null;
        }

        $deduction = $this->deduction_percentage !== null ? (float) $this->deduction_percentage : 20.0;
        $netMultiplier = 1 - ($deduction / 100);

        return (float) (($this->total_client_payments * $netMultiplier) * ((float) $this->profit_share_percentage / 100));
    }
}
