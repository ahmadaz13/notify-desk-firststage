<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_FOUNDER = 'founder';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_EMPLOYEE = 'employee';

    public static function ownerLevelRoles(): array
    {
        return [self::ROLE_FOUNDER, self::ROLE_ADMIN];
    }

    public static function activeInternalRoles(): array
    {
        return [self::ROLE_FOUNDER, self::ROLE_ADMIN, self::ROLE_STAFF];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'phone',
        'job_title',
        'avatar_path',
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
            'is_active' => 'boolean',
            'first_login_at' => 'datetime',
            'reset_expires_at' => 'datetime',
        ];
    }

    public function isFounder(): bool
    {
        return $this->role === self::ROLE_FOUNDER;
    }

    public function isOwnerLevelInternalUser(): bool
    {
        return in_array($this->role, [self::ROLE_FOUNDER, self::ROLE_ADMIN], true);
    }

    public function isAdmin(): bool
    {
        return $this->isOwnerLevelInternalUser();
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function isActiveApplicationUser(): bool
    {
        return $this->is_active !== false && ($this->isAdmin() || $this->isStaff());
    }

    /** Directory of profile photos on the public disk (§17). */
    public const AVATAR_DIRECTORY = 'avatars';

    /** Public URL of the profile photo, or null to show the initial. Never a filesystem path. */
    public function avatarUrl(): ?string
    {
        $path = (string) $this->avatar_path;
        if ($path === '' || ! str_starts_with($path, self::AVATAR_DIRECTORY.'/')) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim((string) $this->name), 0, 1)) ?: '?';
    }

    public function roleLabelKey(): string
    {
        return match ($this->role) {
            self::ROLE_FOUNDER => 'notify.common.role_founder',
            self::ROLE_STAFF => 'notify.common.role_staff',
            self::ROLE_ADMIN => 'notify.common.role_admin',
            default => 'notify.common.role_guest',
        };
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
