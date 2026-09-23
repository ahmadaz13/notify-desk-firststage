<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomProject extends Model
{
    public const STATUS_PLANNED   = 'planned';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'client_id',
        'created_by',
        'name',
        'agreed_value_minor',
        'start_date',
        'target_completion_date',
        'status',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'agreed_value_minor'     => 'integer',
            'start_date'             => 'date',
            'target_completion_date' => 'date',
            'archived_at'            => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    public function agreedValueFormatted(): string
    {
        return Money::fromMinorUnits((int) ($this->agreed_value_minor ?? 0))->format();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNED   => __('custom_projects.status.planned'),
            self::STATUS_ACTIVE    => __('custom_projects.status.active'),
            self::STATUS_COMPLETED => __('custom_projects.status.completed'),
            self::STATUS_CANCELLED => __('custom_projects.status.cancelled'),
            default                => $this->status,
        };
    }

    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNED   => 'neutral',
            self::STATUS_ACTIVE    => 'primary',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'danger',
            default                => 'neutral',
        };
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
