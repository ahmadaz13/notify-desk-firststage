<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'invoice_line_id',
        'description_snapshot',
        'quantity',
        'subtotal_minor',
        'tax_minor',
        'total_minor',
        'metadata',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }
}
