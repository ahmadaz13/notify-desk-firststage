<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CreditNoteApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'invoice_id',
        'amount_minor',
        'applied_at',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'applied_at' => 'datetime',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(CreditNoteApplicationReversal::class);
    }
}
