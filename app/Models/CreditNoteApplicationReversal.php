<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteApplicationReversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_application_id',
        'reason',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'reversed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreditNoteApplication::class, 'credit_note_application_id');
    }
}
