<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'invoice_number',
        'client_id',
        'subscription_id',
        'custom_project_id',
        'currency',
        'status',
        'issue_date',
        'due_date',
        'subtotal_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'billing_period_start',
        'billing_period_end',
        'description',
        'issued_at',
        'voided_at',
        'void_reason',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customProject(): BelongsTo
    {
        return $this->belongsTo(CustomProject::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)->orderBy('allocated_at')->orderBy('id');
    }

    public function creditApplications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class)->orderBy('applied_at')->orderBy('id');
    }

    public function totalJod(): string
    {
        return Money::fromMinorUnits($this->total_minor)->format();
    }

    protected static function booted(): void
    {
        static::deleting(function (Invoice $invoice) {
            if ($invoice->status !== self::STATUS_DRAFT
                || $invoice->allocations()->exists()
                || $invoice->creditApplications()->exists()) {
                throw new \DomainException('Issued, voided, or allocated invoices cannot be deleted. Use invoice voiding instead.');
            }
        });
    }
}
