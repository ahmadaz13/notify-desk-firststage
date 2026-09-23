<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit of credential access (§18.2). Never stores secrets, notes or full phone numbers.
 */
class ClientCredentialAccessLog extends Model
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const REVEALED = 'revealed';
    public const COPIED = 'copied';
    public const SENT = 'sent';
    public const DELETED = 'deleted';

    public const ACTIONS = [self::CREATED, self::UPDATED, self::REVEALED, self::COPIED, self::SENT, self::DELETED];

    public const UPDATED_AT = null;

    protected $fillable = [
        'credential_id',
        'client_id',
        'user_id',
        'action',
        'channel',
        'recipient_masked',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Credential access logs are append-only.'));
        static::deleting(fn () => throw new \LogicException('Credential access logs are append-only.'));
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ClientSystemCredential::class, 'credential_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
