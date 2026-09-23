<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

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

    public function preferredOperationalContact(): array
    {
        $contacts = $this->relationLoaded('contacts')
            ? $this->contacts
            : $this->contacts()->get();

        $contact = $contacts
            ->sortBy(fn (ClientContact $contact) => [
                $this->contactRolePriority($contact->role),
                $contact->is_primary ? 0 : 1,
                $contact->id,
            ])
            ->first(fn (ClientContact $contact) => filled($contact->primary_phone)
                || filled($contact->secondary_phone)
                || filled($contact->whatsapp_number))
            ?? $contacts->firstWhere('is_primary', true)
            ?? $contacts->first();

        $contactPhone = $contact?->primary_phone
            ?: $contact?->secondary_phone
            ?: $contact?->whatsapp_number;
        $legacyContactPhone = ($contact || filled($this->contact_person)) ? $this->phone : null;
        $phone = $contactPhone ?: $legacyContactPhone ?: $this->business_phone ?: $this->phone;

        return [
            'contact' => $contact,
            'name' => $contact?->name ?: $this->contact_person ?: $this->business_name,
            'role' => $contact?->role,
            'phone' => $phone,
            'whatsapp_number' => $contact?->whatsapp_number ?: $phone,
            'uses_business_fallback' => $contactPhone === null && $legacyContactPhone === null,
        ];
    }

    private function contactRolePriority(?string $role): int
    {
        $role = mb_strtolower(trim((string) $role));

        if ($role !== '' && collect(['owner', 'decision maker', 'decision_maker', 'مالك', 'صاحب', 'صاحب القرار'])
            ->contains(fn (string $term) => str_contains($role, $term))) {
            return 0;
        }

        if ($role !== '' && collect(['manager', 'مدير'])
            ->contains(fn (string $term) => str_contains($role, $term))) {
            return 1;
        }

        return 2;
    }

    public function contactAttempts(): HasMany
    {
        return $this->hasMany(ContactAttempt::class)->orderByDesc('created_at');
    }

    public function systems(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'client_system')
            ->withPivot(['access_type', 'granted_at', 'revoked_at', 'note', 'granted_by'])
            ->withTimestamps();
    }

    public function reviewItems(): HasMany
    {
        return $this->hasMany(ClientReviewItem::class)->orderByDesc('created_at');
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

    protected static function booted(): void
    {
        static::deleting(function (Client $client) {
            if ($client->invoices()->exists()
                || $client->payments()->exists()
                || $client->subscriptions()->exists()
                || $client->creditNotes()->exists()
                || $client->refunds()->exists()) {
                throw new \DomainException('Cannot delete client with existing financial history. Close the client instead.');
            }
        });
    }
}
