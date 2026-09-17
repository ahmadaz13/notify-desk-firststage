<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionPeriod extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RECOGNIZED = 'recognized';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'revenue_recognition_schedule_id',
        'period_index',
        'period_start',
        'period_end',
        'scheduled_minor',
        'recognized_minor',
        'status',
        'recognized_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'period_index' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'scheduled_minor' => 'integer',
            'recognized_minor' => 'integer',
            'recognized_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RevenueRecognitionSchedule::class, 'revenue_recognition_schedule_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
