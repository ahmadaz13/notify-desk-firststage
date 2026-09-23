<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCredentialAccessLog;
use App\Models\ClientSystemCredential;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Client system credentials (§18, D-09). Single owner of capability checks, the one-active-credential
 * rule, audit logging and every read of plaintext secrets.
 *
 * Secrets never enter activity logs, notifications, flash data or page HTML: they are returned only by
 * reveal() (JSON to the requesting user) and embedded by send() into a WhatsApp click-to-chat URL after
 * the user explicitly confirms.
 */
class ClientCredentialService
{
    /** Systems the client can hold credentials for: active access to a capable System, or an existing credential. */
    public function applicableSystems(Client $client): Collection
    {
        $accessIds = $client->systems()
            ->where('products.requires_credentials', true)
            ->wherePivotNull('revoked_at')
            ->pluck('products.id');
        $credentialIds = $client->credentials()->pluck('product_id');

        return Product::query()
            ->whereIn('id', $accessIds->merge($credentialIds)->unique()->all())
            ->orderBy('name_ar')
            ->get();
    }

    /** Capable Systems without a credential yet (a credential may be added before access is granted, §18.1). */
    public function addableSystems(Client $client): Collection
    {
        return Product::query()
            ->where('requires_credentials', true)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->whereNotIn('id', $client->credentials()->pluck('product_id')->all())
            ->orderBy('name_ar')
            ->get();
    }

    public function store(Client $client, Product $product, array $data, User $actor, ?Request $request = null): ClientSystemCredential
    {
        $this->assertCapable($product);

        return DB::transaction(function () use ($client, $product, $data, $actor, $request) {
            // Serialize writers per client so two requests cannot both create the active credential.
            Client::query()->whereKey($client->id)->lockForUpdate()->first();

            if ($client->credentials()->where('product_id', $product->id)->exists()) {
                throw ValidationException::withMessages([
                    'product_id' => __('notify.credentials.validation.already_exists', ['system' => $this->systemName($product)]),
                ]);
            }

            $credential = new ClientSystemCredential();
            $credential->forceFill([
                'client_id' => $client->id,
                'product_id' => $product->id,
                'login_url' => $data['login_url'] ?? null,
                'username' => $data['username'] ?? null,
                'secret' => (string) $data['secret'],
                'note' => filled($data['note'] ?? null) ? $data['note'] : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit($credential, ClientCredentialAccessLog::CREATED, $actor, $request);
            $this->activity($credential, $product, $actor, 'client_credentials_created', 'notify.credentials.activity_created');

            return $credential;
        });
    }

    /** A blank secret keeps the stored one; the note is replaced only when typed or explicitly cleared. */
    public function update(ClientSystemCredential $credential, array $data, User $actor, ?Request $request = null): ClientSystemCredential
    {
        $product = $credential->product;
        $this->assertCapable($product);

        return DB::transaction(function () use ($credential, $product, $data, $actor, $request) {
            $attributes = [
                'login_url' => $data['login_url'] ?? null,
                'username' => $data['username'] ?? null,
                'updated_by' => $actor->id,
            ];

            if (filled($data['secret'] ?? null)) {
                $attributes['secret'] = (string) $data['secret'];
            }

            if (! empty($data['clear_note'])) {
                $attributes['note'] = null;
            } elseif (filled($data['note'] ?? null)) {
                $attributes['note'] = $data['note'];
            }

            $credential->forceFill($attributes)->save();

            $this->audit($credential, ClientCredentialAccessLog::UPDATED, $actor, $request);
            $this->activity($credential, $product, $actor, 'client_credentials_updated', 'notify.credentials.activity_updated');

            return $credential;
        });
    }

    public function delete(ClientSystemCredential $credential, User $actor, ?Request $request = null): void
    {
        DB::transaction(function () use ($credential, $actor, $request) {
            $this->audit($credential, ClientCredentialAccessLog::DELETED, $actor, $request);
            $credential->forceFill(['updated_by' => $actor->id])->save();
            $credential->delete();
            $this->activity($credential, $credential->product, $actor, 'client_credentials_deleted', 'notify.credentials.activity_deleted');
        });
    }

    /**
     * @return array{secret: string, note: ?string}
     */
    public function reveal(ClientSystemCredential $credential, User $actor, ?Request $request = null): array
    {
        return DB::transaction(function () use ($credential, $actor, $request) {
            $credential->forceFill(['last_revealed_at' => now()])->save();
            $this->audit($credential, ClientCredentialAccessLog::REVEALED, $actor, $request);

            return ['secret' => (string) $credential->secret, 'note' => $credential->note];
        });
    }

    public function recordCopied(ClientSystemCredential $credential, User $actor, ?Request $request = null): void
    {
        $this->audit($credential, ClientCredentialAccessLog::COPIED, $actor, $request, 'copy');
    }

    /**
     * Explicit, confirmed send: logs `sent` (masked recipient) and returns the wa.me URL carrying the message.
     */
    public function send(ClientSystemCredential $credential, string $recipient, User $actor, ?Request $request = null): string
    {
        $number = $this->whatsappNumber($recipient);

        if ($number === null) {
            throw ValidationException::withMessages(['recipient' => __('notify.credentials.validation.recipient_invalid')]);
        }

        $this->audit($credential, ClientCredentialAccessLog::SENT, $actor, $request, 'whatsapp', $this->maskRecipient($number));

        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->message($credential, (string) $credential->secret));
    }

    /** Primary contact WhatsApp → primary contact phone → client phone. */
    public function defaultRecipient(Client $client): ?string
    {
        $primary = $client->contacts()->where('is_primary', true)->orderBy('id')->first();

        return $primary?->whatsapp_number ?: $primary?->primary_phone ?: $client->phone;
    }

    /** Message preview shown before confirmation; the password is masked. */
    public function previewMessage(ClientSystemCredential $credential): string
    {
        return $this->message($credential, '••••••••');
    }

    public function maskRecipient(string $digits): string
    {
        $digits = preg_replace('/\D+/', '', $digits);

        if (strlen($digits) < 7) {
            return '•••'.substr($digits, -2);
        }

        return '+'.substr($digits, 0, 4).'•••••'.substr($digits, -2);
    }

    public function whatsappNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '962'.substr($digits, 1);
        } elseif (str_starts_with($digits, '7') && strlen($digits) === 9) {
            $digits = '962'.$digits;
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    public function systemName(?Product $product): string
    {
        if (! $product) {
            return '';
        }

        return app()->getLocale() === 'en' ? ($product->name_en ?: $product->name_ar) : ($product->name_ar ?: $product->name_en);
    }

    private function message(ClientSystemCredential $credential, string $secret): string
    {
        $lines = [$this->systemName($credential->product)];

        if (filled($credential->login_url)) {
            $lines[] = __('notify.credentials.message_url', ['url' => $credential->login_url]);
        }
        if (filled($credential->username)) {
            $lines[] = __('notify.credentials.message_username', ['username' => $credential->username]);
        }
        $lines[] = __('notify.credentials.message_password', ['password' => $secret]);
        $lines[] = '';
        $lines[] = __('notify.credentials.message_signoff');

        return implode("\n", $lines);
    }

    private function assertCapable(?Product $product): void
    {
        if (! $product || ! $product->requires_credentials) {
            throw ValidationException::withMessages([
                'product_id' => __('notify.credentials.validation.not_capable'),
            ]);
        }
    }

    private function audit(
        ClientSystemCredential $credential,
        string $action,
        User $actor,
        ?Request $request,
        ?string $channel = null,
        ?string $recipientMasked = null
    ): void {
        ClientCredentialAccessLog::create([
            'credential_id' => $credential->id,
            'client_id' => $credential->client_id,
            'user_id' => $actor->id,
            'action' => $action,
            'channel' => $channel,
            'recipient_masked' => $recipientMasked,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 512) : null,
        ]);
    }

    /** Human-readable client activity: names the System and the actor only, never credential values. */
    private function activity(ClientSystemCredential $credential, ?Product $product, User $actor, string $type, string $key): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $credential->client_id,
            'user_id' => $actor->id,
            'type' => $type,
            'description' => __($key, ['system' => $this->systemName($product), 'user' => $actor->name]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
