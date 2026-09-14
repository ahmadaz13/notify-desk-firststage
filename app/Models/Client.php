<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function primaryOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_owner_id');
    }

    public function contracts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('id');
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class)->orderByDesc('id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    public function contactAttempts(): HasMany
    {
        return $this->hasMany(ContactAttempt::class)->orderByDesc('created_at');
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class)->orderByDesc('installed_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('issue_date')->orderByDesc('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderByDesc('issue_date')->orderByDesc('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderByDesc('refunded_at')->orderByDesc('id');
    }
}
