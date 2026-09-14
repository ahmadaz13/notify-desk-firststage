<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class JournalEntry extends Model
{
    use HasFactory;

    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'journal_number',
        'event_type',
        'event_key',
        'source_type',
        'source_id',
        'entry_date',
        'description',
        'status',
        'reversal_of_id',
        'posted_at',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry) {
            $allowed = ['status', 'updated_at'];
            $dirty = array_keys($entry->getDirty());
            $onlyAllowed = empty(array_diff($dirty, $allowed));

            if (! $onlyAllowed || $entry->getOriginal('status') !== self::STATUS_POSTED || $entry->status !== self::STATUS_REVERSED) {
                throw ValidationException::withMessages(['journal_entry_id' => 'لا يمكن تعديل القيد المحاسبي المرحل.']);
            }
        });

        static::deleting(function () {
            throw ValidationException::withMessages(['journal_entry_id' => 'لا يمكن حذف القيد المحاسبي المرحل.']);
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalDebitMinor(): int
    {
        return (int) $this->lines()->sum('debit_minor');
    }

    public function totalCreditMinor(): int
    {
        return (int) $this->lines()->sum('credit_minor');
    }

    public function totalJod(): string
    {
        return Money::fromMinorUnits($this->totalDebitMinor())->format();
    }
}
