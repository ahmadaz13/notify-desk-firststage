<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionAdjustment extends Model
{
    use HasFactory;

    public const TYPE_FUTURE_DEFERRED_REDUCTION = 'future_deferred_reduction';
    public const TYPE_RECOGNIZED_REVENUE_REDUCTION = 'recognized_revenue_reduction';

    protected $fillable = [
        'revenue_recognition_schedule_id',
        'credit_note_line_id',
        'adjustment_type',
        'amount_minor',
        'effective_date',
        'revenue_recognition_period_id',
        'journal_entry_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'effective_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RevenueRecognitionSchedule::class, 'revenue_recognition_schedule_id');
    }

    public function creditNoteLine(): BelongsTo
    {
        return $this->belongsTo(CreditNoteLine::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RevenueRecognitionPeriod::class, 'revenue_recognition_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
