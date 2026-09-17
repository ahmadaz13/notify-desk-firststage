<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_number',
        'client_id',
        'payment_id',
        'credit_note_id',
        'currency',
        'amount_minor',
        'refund_method',
        'reference',
        'reason',
        'refunded_at',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'refunded_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function amountJod(): string
    {
        return Money::fromMinorUnits($this->amount_minor)->format();
    }
}
