<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'credit_note_number',
        'client_id',
        'original_invoice_id',
        'currency',
        'status',
        'issue_date',
        'subtotal_minor',
        'tax_minor',
        'total_minor',
        'reason',
        'issued_at',
        'voided_at',
        'void_reason',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class)->orderBy('applied_at')->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('refunded_at')->orderBy('id');
    }

    public function totalJod(): string
    {
        return Money::fromMinorUnits($this->total_minor)->format();
    }
}
