<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Client login credentials for a credential-capable System (§18, D-09).
 *
 * secret and note are encrypted at rest with APP_KEY (Laravel `encrypted` cast, compatible with
 * APP_PREVIOUS_KEYS rotation) and hidden from every array/JSON serialization. Plaintext is only
 * read by ClientCredentialService for an explicit, audited reveal/copy/send.
 */
class ClientSystemCredential extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'login_url',
        'username',
    ];

    protected $hidden = [
        'secret',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'note' => 'encrypted',
            'last_revealed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ClientCredentialAccessLog::class, 'credential_id');
    }

    public function belongsToClient(Client $client): bool
    {
        return $this->exists && (int) $this->client_id === (int) $client->id;
    }

    public function hasNote(): bool
    {
        return filled($this->getRawOriginal('note'));
    }
}
