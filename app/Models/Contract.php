<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_number',
        'client_id',
        'subscription_id',
        'generated_by',
        'template_version',
        'status',
        'legal_review_status',
        'snapshot_data',
        'private_file_path',
        'file_hash',
        'page_count',
        'issued_at',
        'superseded_by_contract_id',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'issued_at' => 'datetime',
        'page_count' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'superseded_by_contract_id');
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Official number, or the Draft label for unnumbered drafts (never a substitute number). */
    public function displayNumber(): string
    {
        return $this->contract_number ?: __('notify.contracts.draft_label');
    }

    /**
     * Once issued, the official number and the contractual snapshot are frozen (§8.2). Only the
     * lifecycle status (void / supersede) may change afterwards.
     */
    protected static function booted(): void
    {
        static::updating(function (Contract $contract) {
            if ($contract->getOriginal('issued_at') !== null
                && $contract->isDirty(['contract_number', 'snapshot_data', 'issued_at', 'client_id', 'subscription_id', 'template_version'])) {
                throw new \LogicException('An issued contract is immutable.');
            }
        });
    }
}
