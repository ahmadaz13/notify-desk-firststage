<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'role',
        'primary_phone',
        'secondary_phone',
        'whatsapp_number',
        'email',
        'preferred_contact_method',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
