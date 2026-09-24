<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientCredentialAccessLog;
use App\Models\ClientSystemCredential;
use App\Models\Product;
use App\Models\User;
use App\Services\ClientCredentialService;
use App\Support\ClientLifecycle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * P5 — Client system credentials (§18, D-09). Secrecy is the exit criterion.
 */
class V1P5ClientCredentialTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'Sup3r-S3cret!pw';
    private const NOTE = 'Backup PIN 4455';

    private User $admin;
    private User $staff;
    private Client $client;
    private Client $otherClient;

    /** @var array<string, Product> */
    private array $systems = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true, 'name' => 'Owner Admin']);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true, 'name' => 'Staff Sara']);

        foreach (Product::V1_SYSTEM_IDENTITIES as $code => $identity) {
            $this->systems[$code] = Product::query()->firstOrCreate(['code' => $code], $identity + ['is_active' => true]);
            $this->systems[$code]->forceFill(['requires_credentials' => $identity['requires_credentials'], 'is_active' => true])->save();
        }
        $this->systems['plain'] = Product::create(['code' => 'plain_system', 'name_ar' => 'نظام عادي', 'name_en' => 'Plain System', 'is_active' => true, 'requires_credentials' => false]);

        $this->client = $this->makeClient('Credential Cafe', '0791234567');
        $this->otherClient = $this->makeClient('Other Bakery', '0797654321');
    }

    // ── Capability ─────────────────────────────────────────────────────

    public function test_credential_capable_systems_are_accepted(): void
    {
        foreach ([Product::CODE_SMART_LINK, Product::CODE_E_MENU, Product::CODE_E_STORE] as $code) {
            $this->actingAs($this->staff)
                ->post(route('clients.credentials.store', $this->client), $this->payload($code))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $this->client->credentials()->count());
    }

    public function test_systems_without_credential_capability_are_rejected_server_side(): void
    {
        foreach ([Product::CODE_AUTO_SMS, 'plain'] as $code) {
            $this->actingAs($this->admin)
                ->post(route('clients.credentials.store', $this->client), $this->payload($code))
                ->assertSessionHasErrorsIn('credentials', 'product_id');
        }

        $this->assertSame(0, ClientSystemCredential::withTrashed()->count());
        $this->assertSame(0, ClientCredentialAccessLog::count());
    }

    // ── Encryption & serialization ─────────────────────────────────────

    public function test_secret_and_note_are_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK, ['credential_note' => self::NOTE]);

        $raw = DB::table('client_system_credentials')->where('id', $credential->id)->first();
        $this->assertNotSame(self::SECRET, $raw->secret);
        $this->assertStringNotContainsString(self::SECRET, $raw->secret);
        $this->assertNotSame(self::NOTE, $raw->note);
        $this->assertStringNotContainsString(self::NOTE, $raw->note);

        $fresh = $credential->fresh();
        $this->assertSame(self::SECRET, $fresh->secret, 'Authorized server code decrypts with APP_KEY.');
        $this->assertSame(self::NOTE, $fresh->note);

        $this->assertArrayNotHasKey('secret', $fresh->toArray());
        $this->assertArrayNotHasKey('note', $fresh->toArray());
        $this->assertStringNotContainsString(self::SECRET, $fresh->toJson());
        $this->assertStringNotContainsString(self::SECRET, json_encode($this->client->load('credentials')));
    }

    // ── HTML leakage ───────────────────────────────────────────────────

    public function test_client_page_and_edit_form_never_contain_the_plaintext_secret_or_note(): void
    {
        $this->createCredential(Product::CODE_SMART_LINK, ['credential_note' => self::NOTE]);

        foreach ([$this->staff, $this->admin] as $user) {
            $html = $this->actingAs($user)->get(route('clients.show', $this->client))->assertOk()->getContent();

            $this->assertStringNotContainsString(self::SECRET, $html);
            $this->assertStringNotContainsString(self::NOTE, $html);
            $this->assertStringNotContainsString(e(self::SECRET), $html);
            $this->assertStringNotContainsString(rawurlencode(self::SECRET), $html);
            $this->assertStringContainsString('••••••••', $html);
            $this->assertStringContainsString('name="credential_secret"', $html);
            $this->assertMatchesRegularExpression('/<input[^>]*name="credential_secret"(?![^>]*value=)[^>]*>/', $html);
        }

        $this->actingAs($this->staff)->get(route('clients.edit', $this->client))
            ->assertOk()
            ->assertDontSee(self::SECRET, false);
    }

    public function test_validation_failure_does_not_flash_the_secret_back(): void
    {
        $response = $this->actingAs($this->staff)
            ->from(route('clients.show', $this->client))
            ->post(route('clients.credentials.store', $this->client), $this->payload(Product::CODE_SMART_LINK, [
                'login_url' => 'not a url',
                'credential_note' => self::NOTE,
            ]));

        $response->assertSessionHasErrorsIn('credentials', 'login_url');
        $this->assertNull(session()->getOldInput('credential_secret'));
        $this->assertNull(session()->getOldInput('credential_note'));
        $this->assertSame('not a url', session()->getOldInput('login_url'));

        $html = $this->actingAs($this->staff)->get(route('clients.show', $this->client))->getContent();
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString(self::NOTE, $html);
    }

    // ── Authorization ──────────────────────────────────────────────────

    public function test_staff_and_owner_can_manage_and_reveal(): void
    {
        $credential = $this->createCredential(Product::CODE_E_MENU, [], $this->staff);

        foreach ([$this->staff, $this->admin] as $user) {
            $this->actingAs($user)->put(route('clients.credentials.update', [$this->client, $credential]), [
                'login_url' => 'https://menu.example.com/login', 'username' => 'cafe-'.$user->id, 'credential_secret' => '',
            ])->assertSessionHasNoErrors();

            $this->actingAs($user)->postJson(route('clients.credentials.reveal', [$this->client, $credential]))
                ->assertOk()
                ->assertExactJson(['secret' => self::SECRET, 'note' => null]);
        }
    }

    public function test_unauthorized_users_are_denied(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK);
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE, 'is_active' => true]);
        $inactiveStaff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => false]);

        foreach ([$employee, $inactiveStaff] as $user) {
            $this->actingAs($user)->postJson(route('clients.credentials.reveal', [$this->client, $credential]))->assertForbidden();
            $this->actingAs($user)->post(route('clients.credentials.store', $this->client), $this->payload(Product::CODE_E_STORE))->assertForbidden();
            $this->actingAs($user)->delete(route('clients.credentials.destroy', [$this->client, $credential]))->assertForbidden();
        }

        auth()->logout();
        $this->postJson(route('clients.credentials.reveal', [$this->client, $credential]))->assertUnauthorized();

        $this->assertSame(0, ClientCredentialAccessLog::where('action', ClientCredentialAccessLog::REVEALED)->count());
        $this->assertNull($credential->fresh()->last_revealed_at);
    }

    public function test_credentials_cannot_be_reached_through_another_clients_url(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK);

        $this->actingAs($this->admin)->postJson(route('clients.credentials.reveal', [$this->otherClient, $credential]))->assertNotFound();
        $this->actingAs($this->admin)->postJson(route('clients.credentials.copied', [$this->otherClient, $credential]))->assertNotFound();
        $this->actingAs($this->admin)->postJson(route('clients.credentials.send', [$this->otherClient, $credential]), ['recipient' => '0790000000'])->assertNotFound();
        $this->actingAs($this->admin)->put(route('clients.credentials.update', [$this->otherClient, $credential]), [
            'username' => 'hijack', 'credential_secret' => 'overwritten',
        ])->assertNotFound();
        $this->actingAs($this->admin)->delete(route('clients.credentials.destroy', [$this->otherClient, $credential]))->assertNotFound();

        $fresh = $credential->fresh();
        $this->assertSame(self::SECRET, $fresh->secret);
        $this->assertNull($fresh->deleted_at);
        $this->assertSame(1, ClientCredentialAccessLog::count(), 'Only the original created entry exists.');
    }

    // ── Reveal ─────────────────────────────────────────────────────────

    public function test_reveal_returns_minimal_json_audits_and_stamps_last_revealed(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK, ['credential_note' => self::NOTE]);
        Log::spy();

        $response = $this->actingAs($this->staff)
            ->withHeader('User-Agent', 'NotifyTest/1.0')
            ->postJson(route('clients.credentials.reveal', [$this->client, $credential]))
            ->assertOk()
            ->assertExactJson(['secret' => self::SECRET, 'note' => self::NOTE]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->assertNotNull($credential->fresh()->last_revealed_at);
        $log = ClientCredentialAccessLog::where('action', ClientCredentialAccessLog::REVEALED)->sole();
        $this->assertSame($this->staff->id, $log->user_id);
        $this->assertSame($this->client->id, $log->client_id);
        $this->assertSame($credential->id, $log->credential_id);
        $this->assertSame('NotifyTest/1.0', $log->user_agent);
        $this->assertNotNull($log->ip);
        $this->assertNotNull($log->created_at);

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');

        $this->actingAs($this->staff)->get(route('clients.credentials.reveal', [$this->client, $credential]))->assertStatus(405);
    }

    public function test_reveal_is_throttled_to_twenty_per_minute(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($this->staff)->postJson(route('clients.credentials.reveal', [$this->client, $credential]))->assertOk();
        }
        $this->actingAs($this->staff)->postJson(route('clients.credentials.reveal', [$this->client, $credential]))->assertStatus(429);

        $this->assertSame(20, ClientCredentialAccessLog::where('action', ClientCredentialAccessLog::REVEALED)->count());
    }

    // ── Copy / send ────────────────────────────────────────────────────

    public function test_copy_and_send_are_audited_and_send_masks_the_recipient(): void
    {
        $credential = $this->createCredential(Product::CODE_E_STORE, [
            'login_url' => 'https://store.example.com/admin', 'username' => 'owner@cafe.jo',
        ]);

        $this->actingAs($this->staff)->postJson(route('clients.credentials.copied', [$this->client, $credential]))->assertOk();
        $this->assertSame('copy', ClientCredentialAccessLog::where('action', ClientCredentialAccessLog::COPIED)->sole()->channel);

        $this->assertSame(0, ClientCredentialAccessLog::where('action', ClientCredentialAccessLog::SENT)->count(), 'Nothing is sent without the explicit action.');

        $url = $this->actingAs($this->staff)
            ->postJson(route('clients.credentials.send', [$this->client, $credential]), ['recipient' => '079 555 1234'])
            ->assertOk()
            ->json('url');

        $this->assertStringStartsWith('https://wa.me/962795551234?text=', $url);
        $message = rawurldecode(substr($url, strpos($url, 'text=') + 5));
        $this->assertStringContainsString('https://store.example.com/admin', $message);
        $this->assertStringContainsString('owner@cafe.jo', $message);
        $this->assertStringContainsString(self::SECRET, $message);

        $sent = ClientCredentialAccessLog::where('action', ClientCredentialAccessLog::SENT)->sole();
        $this->assertSame('whatsapp', $sent->channel);
        $this->assertSame('+9627•••••34', $sent->recipient_masked);
        $this->assertStringNotContainsString('795551234', (string) $sent->recipient_masked);

        $this->actingAs($this->staff)
            ->postJson(route('clients.credentials.send', [$this->client, $credential]), ['recipient' => 'abc'])
            ->assertStatus(422);
    }

    public function test_send_preview_defaults_recipient_and_masks_password(): void
    {
        ClientContact::create(['client_id' => $this->client->id, 'role' => 'owner', 'primary_phone' => '0791234567', 'whatsapp_number' => '0781112222', 'is_primary' => true]);
        $credential = $this->createCredential(Product::CODE_SMART_LINK);
        $service = app(ClientCredentialService::class);

        $this->assertSame('0781112222', $service->defaultRecipient($this->client));
        $this->assertStringNotContainsString(self::SECRET, $service->previewMessage($credential));

        $this->actingAs($this->staff)->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('value="0781112222"', false)
            ->assertSee(route('clients.credentials.send', [$this->client, $credential]), false);

        $this->assertSame('0797654321', $service->defaultRecipient($this->otherClient), 'Falls back to the client phone.');
    }

    public function test_secret_never_reaches_activity_logs_audit_rows_or_notifications(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK, ['username' => 'cafe-admin', 'credential_note' => self::NOTE]);
        $this->actingAs($this->staff)->put(route('clients.credentials.update', [$this->client, $credential]), [
            'username' => 'cafe-admin', 'credential_secret' => 'N3w-Secret-Value',
        ]);
        $this->actingAs($this->staff)->postJson(route('clients.credentials.reveal', [$this->client, $credential]));
        $this->actingAs($this->staff)->postJson(route('clients.credentials.send', [$this->client, $credential]), ['recipient' => '0795551234']);
        $this->actingAs($this->staff)->delete(route('clients.credentials.destroy', [$this->client, $credential]));

        $haystack = json_encode([
            DB::table('activity_logs')->get(),
            DB::table('client_credential_access_logs')->get(),
            DB::table('notifications')->get(),
        ], JSON_UNESCAPED_UNICODE);

        foreach ([self::SECRET, 'N3w-Secret-Value', self::NOTE, 'cafe-admin'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $haystack);
        }
        $description = DB::table('activity_logs')->where('type', 'client_credentials_updated')->value('description');
        $this->assertStringContainsString('الرابط الذكي', $description, 'Activity names the System (default Arabic locale).');
        $this->assertStringContainsString('Staff Sara', $description);
    }

    // ── Lifecycle ──────────────────────────────────────────────────────

    public function test_update_without_new_password_preserves_secret_and_note(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK, ['credential_note' => self::NOTE]);
        $encryptedBefore = DB::table('client_system_credentials')->where('id', $credential->id)->value('secret');

        $this->actingAs($this->staff)->put(route('clients.credentials.update', [$this->client, $credential]), [
            'login_url' => 'https://link.example.com/new', 'username' => 'renamed', 'credential_secret' => '', 'credential_note' => '',
        ])->assertSessionHasNoErrors();

        $fresh = $credential->fresh();
        $this->assertSame($encryptedBefore, DB::table('client_system_credentials')->where('id', $credential->id)->value('secret'));
        $this->assertSame(self::SECRET, $fresh->secret);
        $this->assertSame(self::NOTE, $fresh->note);
        $this->assertSame('renamed', $fresh->username);
        $this->assertSame($this->staff->id, $fresh->updated_by);

        $this->actingAs($this->staff)->put(route('clients.credentials.update', [$this->client, $credential]), [
            'username' => 'renamed', 'credential_secret' => 'Changed-1', 'clear_note' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Changed-1', $credential->fresh()->secret);
        $this->assertNull($credential->fresh()->note);
        $this->assertSame(2, ClientCredentialAccessLog::where('action', ClientCredentialAccessLog::UPDATED)->count());
    }

    public function test_one_active_credential_soft_delete_and_recreation(): void
    {
        $credential = $this->createCredential(Product::CODE_SMART_LINK);

        $this->actingAs($this->staff)
            ->post(route('clients.credentials.store', $this->client), $this->payload(Product::CODE_SMART_LINK))
            ->assertSessionHasErrorsIn('credentials', 'product_id');
        $this->assertSame(1, ClientSystemCredential::count());

        // The same System for a different client is independent.
        $this->actingAs($this->staff)
            ->post(route('clients.credentials.store', $this->otherClient), $this->payload(Product::CODE_SMART_LINK))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->delete(route('clients.credentials.destroy', [$this->client, $credential]))->assertRedirect();
        $this->assertSoftDeleted('client_system_credentials', ['id' => $credential->id]);
        $this->assertSame(1, ClientCredentialAccessLog::where('credential_id', $credential->id)->where('action', ClientCredentialAccessLog::DELETED)->count());
        $this->actingAs($this->staff)->postJson(route('clients.credentials.reveal', [$this->client, $credential]))->assertNotFound();

        $this->actingAs($this->staff)
            ->post(route('clients.credentials.store', $this->client), $this->payload(Product::CODE_SMART_LINK, ['credential_secret' => 'Second-Life']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->client->credentials()->count());
        $this->assertSame(2, ClientSystemCredential::withTrashed()->where('client_id', $this->client->id)->count());
        $this->assertSame('Second-Life', $this->client->credentials()->sole()->secret);
        $this->assertSame(2, ClientCredentialAccessLog::where('client_id', $this->client->id)->where('action', ClientCredentialAccessLog::CREATED)->count(), 'Audit history is kept.');
    }

    public function test_access_log_is_append_only(): void
    {
        $this->createCredential(Product::CODE_SMART_LINK);
        $log = ClientCredentialAccessLog::sole();

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'revealed']);
    }

    public function test_secret_is_required_on_create(): void
    {
        $this->actingAs($this->staff)
            ->post(route('clients.credentials.store', $this->client), $this->payload(Product::CODE_SMART_LINK, ['credential_secret' => '']))
            ->assertSessionHasErrorsIn('credentials', 'credential_secret');

        $this->actingAs($this->staff)
            ->post(route('clients.credentials.store', $this->client), $this->payload(Product::CODE_SMART_LINK, ['username' => 'not-an-email user']))
            ->assertSessionHasNoErrors();
    }

    // ── Systems UI ─────────────────────────────────────────────────────

    public function test_only_credential_capable_systems_expose_credential_controls(): void
    {
        $this->grantAccess($this->client, Product::CODE_AUTO_SMS);
        $this->grantAccess($this->client, Product::CODE_E_MENU);

        $response = $this->actingAs($this->staff)->get(route('clients.show', $this->client))->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-credential-card', $html);
        // P10: credentials sit inside their System row; only capable Systems carry the credential block.
        $this->assertMatchesRegularExpression('/<h3 class="notify-system__name\s+notify-credential__system\s*">'.preg_quote(e('القائمة الإلكترونية'), '/').'<\/h3>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<h3 class="notify-system__name\s+notify-credential__system\s*">'.preg_quote(e('نظام الرسائل النصية التلقائية'), '/').'<\/h3>/', $html);
        $this->assertStringNotContainsString('value="'.$this->systems[Product::CODE_AUTO_SMS]->id.'">', $this->addFormHtml($html));
        $this->assertStringNotContainsString('value="'.$this->systems['plain']->id.'">', $this->addFormHtml($html));

        $credential = $this->createCredential(Product::CODE_E_MENU);
        $html = $this->actingAs($this->staff)->get(route('clients.show', $this->client))->getContent();
        foreach (['reveal', 'copied', 'send'] as $action) {
            $this->assertStringContainsString(route('clients.credentials.'.$action, [$this->client, $credential]), $html);
        }
    }

    public function test_client_with_only_auto_sms_shows_no_credential_placeholders(): void
    {
        foreach ([Product::CODE_SMART_LINK, Product::CODE_E_MENU, Product::CODE_E_STORE] as $code) {
            $this->systems[$code]->forceFill(['is_active' => false])->save();
        }
        $this->grantAccess($this->client, Product::CODE_AUTO_SMS);

        $this->actingAs($this->staff)->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertDontSee('data-credential-card', false)
            ->assertDontSee('name="credential_secret"', false)
            ->assertDontSee('••••••••', false);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function payload(string $code, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->systems[$code]->id,
            'login_url' => 'https://login.example.com',
            'username' => 'cafe@example.com',
            'credential_secret' => self::SECRET,
        ], $overrides);
    }

    private function createCredential(string $code, array $overrides = [], ?User $user = null): ClientSystemCredential
    {
        $this->actingAs($user ?? $this->admin)
            ->post(route('clients.credentials.store', $this->client), $this->payload($code, $overrides))
            ->assertSessionHasNoErrors();

        return $this->client->credentials()->where('product_id', $this->systems[$code]->id)->sole();
    }

    private function grantAccess(Client $client, string $code): void
    {
        $client->systems()->syncWithoutDetaching([$this->systems[$code]->id => ['access_type' => 'free', 'granted_at' => now()->toDateString()]]);
    }

    private function addFormHtml(string $html): string
    {
        $start = strpos($html, 'notify-credential__add');

        return $start === false ? '' : substr($html, $start, 4000);
    }

    private function makeClient(string $name, string $phone): Client
    {
        return Client::create([
            'business_name' => $name,
            'phone' => $phone,
            'business_phone' => $phone,
            'primary_phone_type' => 'business',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Google Maps',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ]);
    }
}
