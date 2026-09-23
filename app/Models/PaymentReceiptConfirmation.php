<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Staff-reported customer payment awaiting Owner review [FROZEN D-15].
 *
 * Pre-financial: pending, rejected and cancelled rows have zero financial effect.
 * Only approval produces an authoritative Payment, referenced by the unique payment_id.
 */
class PaymentReceiptConfirmation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const STAFF_MAX_BACKDATE_DAYS = 7;

    protected $fillable = [
        'client_id',
        'amount_minor',
        'currency',
        'payment_method',
        'received_at',
        'reference',
        'note',
        'status',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'payment_id',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'received_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function amountFormatted(): string
    {
        return Money::fromMinorUnits((int) $this->amount_minor)->format();
    }

    public function methodLabel(): string
    {
        return __('notify.client_workspace.payment_methods.'.$this->payment_method);
    }

    public function statusLabel(): string
    {
        return __('notify.payment_receipts.status.'.$this->status);
    }
}
