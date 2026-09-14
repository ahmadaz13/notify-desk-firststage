<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'appointment_id',
        'installed_by',
        'installed_at',
        'branch_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function installedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InstallationItem::class);
    }
}
