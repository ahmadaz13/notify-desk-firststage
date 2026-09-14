<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'method',
        'result',
        'note',
        'next_action',
        'next_follow_up_date',
    ];

    protected function casts(): array
    {
        return [
            'next_follow_up_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
