<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business vs contact model (§28.1): owns primary-phone ownership and the single is_primary ClientContact.
 *
 * - clients.phone always stores the primary phone; clients.primary_phone_type records whose it is.
 * - business: business_phone = phone; a primary contact is only kept/created when contact details exist.
 * - owner|manager: the is_primary contact carries primary_phone = phone and role = type; name may be null;
 *   business_phone stays null unless explicitly supplied.
 *
 * Contact keys (name, role, phone, whatsapp, email) are applied only when present in $contact, so a caller
 * that does not submit contact details never erases them. Existing contacts are updated, never duplicated
 * and never deleted.
 */
class ClientPrimaryContactService
{
    public const BUSINESS = 'business';
    public const OWNER = 'owner';
    public const MANAGER = 'manager';

    public const CONTACT_KEYS = ['name', 'role', 'phone', 'whatsapp', 'email'];

    /**
     * @param  array{name?: ?string, role?: ?string, phone?: ?string, whatsapp?: ?string, email?: ?string}  $contact
     */
    public function sync(Client $client, string $type, string $phone, ?string $businessPhone, array $contact = []): Client
    {
        if (! in_array($type, Client::PRIMARY_PHONE_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown primary phone type [{$type}].");
        }

        $contact = collect($contact)
            ->only(self::CONTACT_KEYS)
            ->map(fn ($value) => $this->clean($value))
            ->all();

        return DB::transaction(function () use ($client, $type, $phone, $businessPhone, $contact) {
            $previousType = $client->exists ? ($client->getOriginal('primary_phone_type') ?: self::BUSINESS) : self::BUSINESS;
            $phone = (string) $this->clean($phone);

            $client->phone = $phone;
            $client->primary_phone_type = $type;
            $client->business_phone = $type === self::BUSINESS ? $phone : $this->clean($businessPhone);
            $client->save();

            $primary = $client->contacts()->where('is_primary', true)->orderBy('id')->first();

            if ($type === self::BUSINESS) {
                $primary = $this->syncBusinessContact($client, $primary, $contact, $previousType !== self::BUSINESS);
            } else {
                $primary = $this->syncPhoneOwnerContact($client, $primary, $type, $phone, $contact);
            }

            // Legacy mirror read by existing views and the contract snapshot.
            $client->forceFill(['contact_person' => $primary?->name])->save();
            $client->unsetRelation('contacts')->unsetRelation('primaryContact');

            return $client;
        });
    }

    /** owner|manager: clients.phone belongs to the is_primary contact, so that role is reserved for it. */
    public function contactOwnsPrimaryPhone(Client $client): bool
    {
        return in_array($client->primary_phone_type, [self::OWNER, self::MANAGER], true);
    }

    /**
     * Guard for generic contact actions (workspace add/edit). They may never move phone ownership: while
     * clients.phone belongs to the owner/manager contact, no other contact can become primary and that
     * contact's primary_phone cannot diverge from clients.phone. Ownership changes go through sync()
     * (client edit). Business-owned phones leave the primary human contact free to change.
     *
     * @throws ValidationException
     */
    public function assertGenericContactChangeAllowed(Client $client, ?ClientContact $contact, array $attributes): void
    {
        if (! $this->contactOwnsPrimaryPhone($client)) {
            return;
        }

        $owner = $client->contacts()->where('is_primary', true)->orderBy('id')->first();
        $isOwner = $contact && $owner && (int) $contact->id === (int) $owner->id;
        $messages = [];

        if (! empty($attributes['is_primary']) && ! $isOwner) {
            $messages['is_primary'] = __('notify.clients.contact_model.validation.primary_reserved_for_phone_owner', [
                'type' => __('notify.clients.contact_model.phone_types.'.$client->primary_phone_type),
            ]);
        }

        if ($isOwner && array_key_exists('primary_phone', $attributes)
            && $this->clean($attributes['primary_phone']) !== $this->clean($client->phone)) {
            $messages['primary_phone'] = __('notify.clients.contact_model.validation.phone_owner_number_via_client_edit');
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function syncPhoneOwnerContact(Client $client, ?ClientContact $primary, string $type, string $phone, array $contact): ClientContact
    {
        $attributes = [
            'role' => $type,
            'primary_phone' => $phone,
        ] + $this->detailAttributes($contact);

        // The person's previous direct number is replaced by the client's primary phone; keep it as their
        // second phone unless a different second phone is being set (owner decision, P4 hardening).
        $previous = $primary?->primary_phone;
        $preserve = filled($previous) && $previous !== $phone ? $previous : null;

        if (array_key_exists('phone', $contact)) {
            // A different direct number of the same person is kept as their second phone.
            $secondary = $contact['phone'] !== $phone ? $contact['phone'] : null;
            $attributes['secondary_phone'] = $secondary ?? $preserve;
        } elseif ($preserve !== null && blank($primary?->secondary_phone)) {
            $attributes['secondary_phone'] = $preserve;
        }

        if ($primary) {
            $primary->update($attributes);

            return $primary;
        }

        return $client->contacts()->create($attributes + ['is_primary' => true]);
    }

    private function syncBusinessContact(Client $client, ?ClientContact $primary, array $contact, bool $leavingPhoneOwnership): ?ClientContact
    {
        $attributes = $this->detailAttributes($contact);

        if (array_key_exists('role', $contact)) {
            $attributes['role'] = $contact['role'];
        }

        if (array_key_exists('phone', $contact)) {
            if ($primary && $leavingPhoneOwnership) {
                // owner/manager -> business: the person keeps the number that was theirs; a newly
                // supplied direct number is stored beside it instead of overwriting history.
                if ($contact['phone'] !== null && $contact['phone'] !== $primary->primary_phone) {
                    $attributes['secondary_phone'] = $contact['phone'];
                }
            } else {
                $attributes['primary_phone'] = $contact['phone'];
            }
        }

        if ($primary) {
            $primary->update($attributes);

            return $primary;
        }

        $hasDetails = collect(['name', 'phone', 'whatsapp', 'email'])
            ->contains(fn (string $key) => filled($contact[$key] ?? null));

        return $hasDetails
            ? $client->contacts()->create($attributes + ['is_primary' => true])
            : null;
    }

    private function detailAttributes(array $contact): array
    {
        $map = ['name' => 'name', 'whatsapp' => 'whatsapp_number', 'email' => 'email'];
        $attributes = [];

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $contact)) {
                $attributes[$column] = $contact[$key];
            }
        }

        return $attributes;
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
