<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const CODE_SMART_LINK = 'smart_link';
    public const CODE_E_MENU = 'e_menu';
    public const CODE_E_STORE = 'e_store';
    public const CODE_AUTO_SMS = 'auto_sms_system';

    /**
     * Canonical V1 System identities and their credential capability (§6, §18.1; owner decision in P3).
     * Auto SMS is the pre-existing `auto_sms_system` product.
     */
    public const V1_SYSTEM_IDENTITIES = [
        self::CODE_SMART_LINK => ['name_ar' => 'الرابط الذكي', 'name_en' => 'Smart Link', 'requires_credentials' => true],
        self::CODE_E_MENU => ['name_ar' => 'القائمة الإلكترونية', 'name_en' => 'E-Menu', 'requires_credentials' => true],
        self::CODE_E_STORE => ['name_ar' => 'المتجر الإلكتروني', 'name_en' => 'E-Store', 'requires_credentials' => true],
        self::CODE_AUTO_SMS => ['name_ar' => 'نظام الرسائل النصية التلقائية', 'name_en' => 'Auto SMS System', 'requires_credentials' => false],
    ];

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'is_active',
        'default_monthly_price_minor',
        'default_annual_price_minor',
        'requires_credentials',
        'archived_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_credentials' => 'boolean',
        'archived_at' => 'datetime',
        'default_monthly_price_minor' => 'integer',
        'default_annual_price_minor' => 'integer',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('tier')->orderBy('code');
    }

    public function sellablePlans(): HasMany
    {
        return $this->hasMany(Plan::class)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('tier')
            ->orderBy('code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_system')
            ->withPivot(['access_type', 'granted_at', 'revoked_at', 'note', 'granted_by'])
            ->withTimestamps();
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class, 'subscription_system')
            ->withPivot(['system_code_snapshot', 'system_name_ar_snapshot', 'system_name_en_snapshot'])
            ->withTimestamps();
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }
}
