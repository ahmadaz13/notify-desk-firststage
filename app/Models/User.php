<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'partner_id',
        'role',
        'first_login_at',
        'reset_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'first_login_at' => 'datetime',
            'reset_expires_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function isAdmin(): bool
    {
        return empty($this->role) || $this->role === 'admin';
    }

    public function isPartner(): bool
    {
        return $this->role === 'partner';
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class, 'paid_by');
    }

    public function dailyNotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DailyNote::class);
    }

    public function appointments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_user')->withTimestamps();
    }
}
