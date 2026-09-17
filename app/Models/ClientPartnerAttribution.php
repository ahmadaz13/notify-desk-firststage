<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPartnerAttribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'partner_id',
        'commission_bps_snapshot',
        'attributed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'commission_bps_snapshot' => 'integer',
        'attributed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
