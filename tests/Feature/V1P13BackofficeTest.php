<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingsController;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientSystemCredential;
use App\Models\CustomProject;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ReferenceOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\CompletedWorkService;
use App\Services\ContractService;
use App\Services\FreeInstallationService;
use App\Services\NotificationService;
use App\Services\ReferenceDataService;
use App\Services\UnifiedOperationalWorkProjection;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\Features;
use App\Support\ShellNavigation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P13 — Backoffice & secondary workspaces: Systems, Team & Roles, Operational Reference Data, Import,
 * Settings (every visible setting drives behaviour), Profile, Custom Projects.
 */
class V1P13BackofficeTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $founder;

    private User $cofounder;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Amman'));
        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true, 'name' => 'Founder One']);
        // V1 owner-level users are Founders only (D-25); a second Founder for owner scenarios.
        $this->cofounder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true, 'name' => 'Co Founder']);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true, 'name' => 'Staff Sara']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function client(string $name, array $attributes = []): Client
    {
        return Client::create(array_merge([
            'business_name' => $name,
            'business_category' => 'Restaurants',
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'lead_source' => 'Google Maps',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ], $attributes));
    }

    private function appointment(Client $client, string $date, string $time, array $attributes = []): Appointment
    {
        return Appointment::create(array_merge([
            'client_id' => $client->id,
            'appointment_date' => $date,
            'appointment_time' => $time,
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
        ], $attributes));
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge(SettingsController::defaults(), $overrides);
    }

    private function today(User $user): array
    {
        return app(UnifiedOperationalWorkProjection::class)->today($user, Carbon::now('Asia/Amman'));
    }

    private function findItem(array $today, string $id): ?array
    {
        return collect([$today['overdue'], $today['next'], $today['later_today']])->flatten(1)->firstWhere('id', $id);
    }

    // ---------------------------------------------------------------- Systems

    public function test_systems_page_renders_the_canonical_catalog_without_creating_identities(): void
    {
        $before = Product::count();

        $response = $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get('/administration/systems')->assertOk();
        foreach (['smart_link', 'auto_sms_system', 'e_menu', 'e_store', 'restaurant_system', 'digital_store_system', 'clink_management', 'crm_ai_tool', 'custom_system'] as $code) {
            $response->assertSee('data-system="'.$code.'"', false);
        }
        $response->assertSee('Smart-Link Premium')->assertSee('Auto SMS Sender')->assertSee('data-requires-credentials="1"', false)
            ->assertDontSee('commercial-catalog.products.store', false);

        $this->assertSame($before, Product::count(), 'Rendering never creates System identities.');
        $this->actingAs($this->staff)->get('/administration/systems')->assertForbidden();
    }

    public function test_credential_capability_is_explicit_and_cannot_hide_saved_credentials(): void
    {
        $smartLink = Product::where('code', Product::CODE_SMART_LINK)->firstOrFail();
        $autoSms = Product::where('code', Product::CODE_AUTO_SMS)->firstOrFail();
        $payload = fn (Product $p, array $extra = []) => array_merge(['name_ar' => $p->name_ar, 'name_en' => $p->name_en, 'is_active' => '1'], $extra);

        // Omitted → unchanged (a normal edit can never silently switch it off).
        $this->actingAs($this->cofounder)->patch(route('commercial-catalog.products.update', $smartLink), $payload($smartLink))->assertSessionHasNoErrors();
        $this->assertTrue($smartLink->fresh()->requires_credentials);

        // Switching on is allowed.
        $this->actingAs($this->cofounder)->patch(route('commercial-catalog.products.update', $autoSms), $payload($autoSms, ['requires_credentials' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue($autoSms->fresh()->requires_credentials);

        // Switching off is refused while a client has saved credentials for the System.
        $client = $this->client('Cred Client');
        (new ClientSystemCredential)->forceFill(['client_id' => $client->id, 'product_id' => $smartLink->id, 'username' => 'u', 'secret' => 'S3cret!', 'created_by' => $this->cofounder->id])->save();
        $this->actingAs($this->cofounder)->patch(route('commercial-catalog.products.update', $smartLink), $payload($smartLink, ['requires_credentials' => '0']))
            ->assertSessionHasErrors('requires_credentials');
        $this->assertTrue($smartLink->fresh()->requires_credentials);

        // Without saved credentials it can be switched off.
        $this->actingAs($this->cofounder)->patch(route('commercial-catalog.products.update', $autoSms), $payload($autoSms, ['requires_credentials' => '0']))->assertSessionHasNoErrors();
        $this->assertFalse($autoSms->fresh()->requires_credentials);

        $this->actingAs($this->staff)->patch(route('commercial-catalog.products.update', $autoSms), $payload($autoSms, ['requires_credentials' => '1']))->assertForbidden();
    }

    public function test_old_administration_urls_redirect_to_canonical_pages(): void
    {
        $this->actingAs($this->founder)->get('/commercial-catalog')->assertRedirect('/administration/systems')->assertStatus(301);
        $this->actingAs($this->founder)->get('/settings')->assertRedirect('/administration/settings')->assertStatus(301);
        $this->actingAs($this->founder)->get('/clients-import')->assertRedirect('/administration/import')->assertStatus(301);
        $this->assertSame(url('/administration/settings'), route('settings.index'));
    }

    public function test_administration_index_is_orientation_only(): void
    {
        $response = $this->actingAs($this->cofounder)->get(route('administration.index'))->assertOk();
        foreach (['systems', 'team', 'reference-data', 'import', 'settings'] as $key) {
            $response->assertSee('data-admin-destination="'.$key.'"', false);
        }
        $html = $response->getContent();
        $page = substr($html, strpos($html, 'data-admin-page="index"'));
        $this->assertStringNotContainsString('<form', substr($page, 0, strpos($page, '</nav>')), 'The index links to destinations; it has no forms.');
        $this->actingAs($this->staff)->get(route('administration.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------- Team & Roles

    /** D-25 (P13.1): the page presents two roles, Founder and Staff; Admin is not an active V1 role. */
    public function test_team_page_presents_founder_and_staff_only(): void
    {
        $response = $this->actingAs($this->founder)->get(route('administration.team'))->assertOk();
        $html = $response->getContent();

        // Role legend: exactly Founder and Staff, with the V1 descriptions; no Admin copy anywhere.
        $legend = substr($html, strpos($html, 'id="team-roles-title"'), 1500);
        $this->assertSame(2, substr_count($legend, '<dt>'));
        $this->assertStringContainsString(__('notify.team.roles.founder'), $legend);
        $this->assertStringContainsString(__('notify.team.roles.staff'), $legend);
        $this->assertStringContainsString(__('notify.team.role_descriptions.founder'), $legend);
        $response->assertDontSee('value="admin"', false)->assertDontSee('مدير النظام')->assertDontSee('>مدير<', false);
        $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('administration.team'))->assertOk()
            ->assertDontSee('Admin</', false)->assertDontSee('Administrator')->assertSee('Founder')->assertSee('Staff');

        // Add member: Staff only, no role picker.
        $add = substr($html, strpos($html, 'id="team-add"'), 5000);
        $this->assertStringContainsString('data-add-role-staff', $add);
        $this->assertStringNotContainsString('name="role"', $add);

        // Edit Staff: a Founder may keep Staff or promote to Founder — nothing else.
        $staffSheet = substr($html, strpos($html, 'id="team-edit-'.$this->staff->id.'"'), 7000);
        preg_match_all('/name="role" value="([a-z]+)"/', $staffSheet, $roles);
        $this->assertSame(['founder', 'staff'], $roles[1]);

        // Another Founder: full Founder-only actions for a Founder actor.
        $response->assertSee('data-team-reset="'.$this->cofounder->id.'"', false)
            ->assertSee('data-team-deactivate="'.$this->cofounder->id.'"', false)
            ->assertSee('data-team-reset="'.$this->staff->id.'"', false)
            ->assertSee('data-team-deactivate="'.$this->staff->id.'"', false);

        // Yourself: no deactivate, role read-only.
        $response->assertDontSee('data-team-deactivate="'.$this->founder->id.'"', false);
        $ownSheet = substr($html, strpos($html, 'id="team-edit-'.$this->founder->id.'"'), 7000);
        $this->assertStringContainsString('data-role-readonly', $ownSheet);

        $this->actingAs($this->founder)->get(route('administration.team.create'))->assertOk()->assertSee('data-sheet-reopen', false);
        $this->actingAs($this->staff)->get(route('administration.team'))->assertForbidden();
    }

    public function test_team_members_have_work_fields_and_founder_activation_stays_protected(): void
    {
        $this->actingAs($this->founder)->post(route('administration.team.store'), [
            'name' => 'New Staff', 'email' => 'new.staff@example.test', 'phone' => '0790001122', 'job_title' => 'Field sales',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect(route('administration.team'));
        $member = User::where('email', 'new.staff@example.test')->sole();
        $this->assertSame(['staff', '0790001122', 'Field sales', true], [$member->role, $member->phone, $member->job_title, $member->is_active]);

        $this->actingAs($this->founder)->post(route('administration.team.deactivate', $member->id))->assertRedirect(route('administration.team'));
        $this->assertFalse($member->fresh()->is_active);
        $this->actingAs($this->founder)->post(route('administration.team.activate', $member->id))->assertRedirect(route('administration.team'));
        $this->assertTrue($member->fresh()->is_active);

        // Reactivating a Founder is Founder-only: Staff and the dormant Admin value are refused.
        $inactiveFounder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => false]);
        $legacyAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->actingAs($this->staff)->post(route('administration.team.activate', $inactiveFounder->id))->assertForbidden();
        $this->actingAs($legacyAdmin)->post(route('administration.team.activate', $inactiveFounder->id))->assertForbidden();
        $this->assertFalse($inactiveFounder->fresh()->is_active);
        $this->actingAs($this->founder)->post(route('administration.team.activate', $inactiveFounder->id))->assertRedirect(route('administration.team'));
        $this->assertTrue($inactiveFounder->fresh()->is_active);

        $this->actingAs($this->staff)->post(route('administration.team.activate', $member->id))->assertForbidden();
    }

    public function test_editing_yourself_never_deactivates_or_demotes_you(): void
    {
        $base = ['name' => 'Co Founder', 'email' => $this->cofounder->email, 'role' => 'founder'];

        $this->actingAs($this->cofounder)->from(route('administration.team'))->put(route('administration.team.update', $this->cofounder->id), $base)
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($this->cofounder->fresh()->is_active);

        $this->actingAs($this->cofounder)->from(route('administration.team'))->put(route('administration.team.update', $this->cofounder->id), ['role' => 'staff', 'is_active' => '1'] + $base)
            ->assertSessionHasErrors('role');
        $this->assertSame(User::ROLE_FOUNDER, $this->cofounder->fresh()->role);

        $this->actingAs($this->cofounder)->put(route('administration.team.update', $this->cofounder->id), $base + ['is_active' => '1', 'job_title' => 'Operations lead'])
            ->assertRedirect(route('administration.team'));
        $this->assertSame('Operations lead', $this->cofounder->fresh()->job_title);
        $this->assertTrue($this->cofounder->fresh()->is_active);
    }

    /** D-25: Profile shows the actual V1 role (Founder or Staff), read-only. */
    public function test_profile_shows_the_v1_role_label(): void
    {
        $this->actingAs($this->founder)->get(route('profile.edit'))->assertOk()->assertSee(__('notify.team.roles.founder'))->assertDontSee('مدير النظام');
        $this->actingAs($this->staff)->get(route('profile.edit'))->assertOk()->assertSee(__('notify.team.roles.staff'));
        $this->actingAs($this->founder)->withSession(['locale' => 'en'])->get(route('profile.edit'))->assertOk()->assertSee('Founder')->assertDontSee('Administrator');
    }

    // ---------------------------------------------------------------- Reference data

    public function test_reference_data_page_manages_the_existing_authority_and_is_owner_only(): void
    {
        $this->assertSame(['Restaurants', 'Phone shops', 'Travel & Tourism', 'Farms / Chalets'],
            ReferenceOption::forList(ReferenceDataService::CLIENT_CATEGORY)->pluck('value')->all(), 'Business types are the client_category list.');

        $response = $this->actingAs($this->founder)->get(route('administration.reference-data'))->assertOk();
        foreach (ReferenceDataService::LISTS as $list) {
            $response->assertSee('data-reference-list="'.$list.'"', false);
        }
        $response->assertSee('مطاعم')->assertSee('خرائط Google');

        $option = ReferenceOption::where('list_key', ReferenceDataService::LEAD_SOURCE)->firstOrFail();
        $this->actingAs($this->staff)->get(route('administration.reference-data'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('administration.reference-data.store'), ['list_key' => 'lead_source', 'label_ar' => 'X'])->assertForbidden();
        $this->actingAs($this->staff)->patch(route('administration.reference-data.update', $option), ['label_ar' => 'X'])->assertForbidden();
        $this->actingAs($this->staff)->post(route('administration.reference-data.active', $option), ['active' => '0'])->assertForbidden();
        $this->assertTrue($option->fresh()->is_active);
    }

    public function test_business_types_are_managed_and_deactivation_keeps_history_renderable(): void
    {
        $this->actingAs($this->cofounder)->post(route('administration.reference-data.store'), [
            'list_key' => ReferenceDataService::CLIENT_CATEGORY, 'label_ar' => 'صالونات', 'label_en' => 'Salons',
        ])->assertSessionHasNoErrors();
        $salons = ReferenceOption::where('list_key', 'client_category')->where('value', 'Salons')->sole();
        $this->assertTrue($salons->is_active);

        // Duplicate names are refused (reactivate instead).
        $this->actingAs($this->cofounder)->post(route('administration.reference-data.store'), [
            'list_key' => ReferenceDataService::CLIENT_CATEGORY, 'label_ar' => 'صالونات 2', 'label_en' => 'salons',
        ])->assertSessionHasErrors('label_ar');

        $historical = $this->client('Old Diner', ['business_category' => 'Restaurants', 'lead_source' => 'Google Maps']);
        $restaurants = ReferenceOption::where('list_key', 'client_category')->where('value', 'Restaurants')->sole();

        // Relabel keeps the stored value; the workspace shows the localized label.
        $this->actingAs($this->cofounder)->patch(route('administration.reference-data.update', $restaurants), ['label_ar' => 'مطاعم ومقاهي', 'label_en' => 'Restaurants & cafés', 'sort_order' => 5])
            ->assertSessionHasNoErrors();
        $this->assertSame('Restaurants', $restaurants->fresh()->value);
        $this->actingAs($this->staff)->get(route('clients.show', $historical))->assertOk()->assertSee('مطاعم ومقاهي')->assertSee('خرائط Google');

        // Deactivated: not offered for new clients, still selectable/renderable on the historical client.
        $this->actingAs($this->cofounder)->post(route('administration.reference-data.active', $restaurants), ['active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($restaurants->fresh()->is_active);
        $this->actingAs($this->staff)->get(route('clients.create'))->assertOk()
            ->assertDontSee('<option value="Restaurants"', false)
            ->assertSee('<option value="Salons"', false);
        $this->actingAs($this->staff)->get(route('clients.edit', $historical))->assertOk()->assertSee('<option value="Restaurants"', false);
        $this->actingAs($this->staff)->get(route('clients.show', $historical))->assertOk()->assertSee('مطاعم ومقاهي');
        $this->assertSame('Restaurants', $historical->fresh()->business_category, 'Historical clients are never rewritten.');
    }

    public function test_lead_sources_have_one_authority(): void
    {
        $this->assertFalse(method_exists(\App\Http\Controllers\ClientController::class, 'leadSourceOptions'));
        $this->assertFalse(defined(\App\Support\ClientLifecycle::class.'::SOURCE_TYPES'));

        $this->actingAs($this->cofounder)->post(route('administration.reference-data.store'), [
            'list_key' => ReferenceDataService::LEAD_SOURCE, 'label_ar' => 'تيك توك', 'label_en' => 'TikTok',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get(route('clients.create'))->assertOk()->assertSee('<option value="TikTok"', false);
        $this->assertSame(ReferenceOption::where('list_key', 'lead_source')->where('is_active', true)->count(),
            count(app(ReferenceDataService::class)->options(ReferenceDataService::LEAD_SOURCE)));
    }

    public function test_city_area_is_suggestions_plus_free_text(): void
    {
        $this->actingAs($this->cofounder)->post(route('administration.reference-data.store'), [
            'list_key' => ReferenceDataService::CITY_AREA, 'label_ar' => 'عبدون',
        ])->assertSessionHasNoErrors();
        $option = ReferenceOption::where('list_key', 'city_area')->sole();
        $this->assertSame(['عبدون', 'عبدون', 'عبدون'], [$option->value, $option->label_ar, $option->label_en]);

        $this->actingAs($this->staff)->get(route('clients.create'))->assertOk()->assertSee('<option value="عبدون"></option>', false);

        // Editing a suggestion changes future suggestions only.
        $this->actingAs($this->cofounder)->patch(route('administration.reference-data.update', $option), ['label_ar' => 'عبدون الشمالي'])->assertSessionHasNoErrors();
        $this->assertSame('عبدون الشمالي', $option->fresh()->value);

        // Free text still works for any area.
        $this->actingAs($this->staff)->post(route('clients.store'), [
            'business_name' => 'Free Text Area', 'business_category' => 'Restaurants', 'phone' => '0795551212', 'primary_phone_type' => 'business',
            'city_area' => 'منطقة جديدة تماماً', 'lead_source' => 'Instagram',
        ])->assertSessionHasNoErrors();
        $this->assertSame('منطقة جديدة تماماً', Client::where('business_name', 'Free Text Area')->sole()->city_area);
    }

    // ---------------------------------------------------------------- Settings

    public function test_settings_page_has_four_sections_no_theme_and_is_owner_only(): void
    {
        $response = $this->actingAs($this->cofounder)->withSession(['locale' => 'en'])->get(route('settings.index'))->assertOk();
        foreach (['company', 'operations', 'subscriptions', 'features'] as $section) {
            $response->assertSee('data-settings-section="'.$section.'"', false);
        }
        $response->assertSee('Company &amp; Contracts', false)->assertSee('Optional Features')
            ->assertDontSee('dark', false)->assertDontSee('name="theme"', false)->assertDontSee('name="timezone"', false)->assertDontSee('name="currency"', false)
            ->assertDontSee('fixed_asset', false);

        $this->actingAs($this->staff)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->staff)->put(route('settings.update'), $this->settingsPayload())->assertForbidden();
    }

    public function test_company_settings_are_read_by_the_contract_authority(): void
    {
        $this->actingAs($this->cofounder)->put(route('settings.update'), $this->settingsPayload([
            'company_name_ar' => 'نوتيفاي للحلول', 'company_name_en' => 'Notify Solutions', 'company_phone' => '065000000',
            'company_email' => 'hello@notify.test', 'company_address' => 'Amman', 'tax_number' => 'TX-1', 'registration_number' => '',
            'authorized_signatory' => 'Ahmad', 'contract_prefix' => 'NDC',
        ]))->assertRedirect(route('settings.index'))->assertSessionHasNoErrors();

        $block = app(ContractService::class)->companyBlock();
        $this->assertSame('Notify Solutions', $block['name_en']);
        $this->assertSame('TX-1', $block['tax_number']);
        $this->assertSame('Ahmad', $block['authorized_signatory']);
        $this->assertArrayNotHasKey('registration_number', $block, 'Blank optional legal fields stay hidden (P8).');
        $this->assertSame('NDC', Setting::get('contract_prefix'));
        $this->assertSame('Asia/Amman', Setting::get('timezone'));
        $this->assertSame('JOD', Setting::get('currency'));
    }

    public function test_settings_validation_protects_operational_rules(): void
    {
        $cases = [
            'workday_end' => ['workday_start' => '17:00', 'workday_end' => '09:00'],
            'appointment_duration' => ['appointment_duration' => 5],
            'free_installation_duration' => ['free_installation_duration' => 900],
            'post_install_followup_days' => ['post_install_followup_days' => 0],
            'contract_prefix' => ['contract_prefix' => 'ND-2026'],
            'company_name_en' => ['company_name_en' => ''],
            'default_billing_cycle' => ['default_billing_cycle' => 'monthly', 'allow_monthly' => '0'],
            'timezone' => ['timezone' => 'UTC'],
        ];

        foreach ($cases as $field => $overrides) {
            $this->actingAs($this->cofounder)->put(route('settings.update'), $this->settingsPayload($overrides))->assertSessionHasErrors($field);
        }

        $this->actingAs($this->cofounder)->put(route('settings.update'), $this->settingsPayload(['workday_start' => '08:00', 'workday_end' => '16:30', 'appointment_duration' => 45]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['08:00', '16:30', '45'], [Setting::get('workday_start'), Setting::get('workday_end'), Setting::get('appointment_duration')]);
    }

    public function test_appointment_and_installation_durations_drive_the_today_in_progress_window(): void
    {
        $client = $this->client('Timing Client');
        $visit = $this->appointment($client, '2026-09-24', '09:30:00');                         // started 30 min ago
        $install = $this->appointment($client, '2026-09-24', '09:15:00', ['appointment_type' => AppointmentTypes::INSTALLATION]); // 45 min ago

        $today = $this->today($this->founder);
        $this->assertSame('now', $this->findItem($today, 'apt-'.$visit->id)['timing']['state']);
        $this->assertSame('now', $this->findItem($today, 'apt-'.$install->id)['timing']['state']);

        Setting::set('appointment_duration', '20');
        Setting::set('free_installation_duration', '90');
        $today = $this->today($this->founder);
        $this->assertSame('overdue', $this->findItem($today, 'apt-'.$visit->id)['timing']['state']);
        $this->assertSame('now', $this->findItem($today, 'apt-'.$install->id)['timing']['state']);

        Setting::set('free_installation_duration', '30');
        $this->assertSame('overdue', $this->findItem($this->today($this->founder), 'apt-'.$install->id)['timing']['state']);
    }

    public function test_workday_end_anchors_date_only_work_on_today(): void
    {
        $payer = $this->client('Due Today', ['stage' => ClientLifecycle::SUBSCRIBER, 'status' => 'subscriber']);
        Invoice::create([
            'client_id' => $payer->id, 'invoice_number' => 'INV-P13-1', 'status' => Invoice::STATUS_ISSUED,
            'total_minor' => 45000, 'subtotal_minor' => 45000, 'tax_minor' => 0, 'discount_minor' => 0, 'currency' => 'JOD',
            'issue_date' => '2026-09-20', 'due_date' => '2026-09-24',
        ]);
        $this->appointment($this->client('Soon'), '2026-09-24', '10:30:00');

        // Default workday end 17:00: a payment due today is later today at 10:00.
        $this->assertContains('col-'.$payer->id, collect($this->today($this->founder)['later_today'])->pluck('id')->all());

        // Workday ends 12:00: the same payment falls inside the Next window.
        Setting::set('workday_end', '12:00');
        $today = $this->today($this->founder);
        $this->assertContains('col-'.$payer->id, collect($today['next'])->pluck('id')->all());
        $this->assertSame(Carbon::parse('2026-09-24 12:00:00', 'Asia/Amman')->getTimestamp(), $this->findItem($today, 'col-'.$payer->id)['sort_at']);
    }

    public function test_post_install_follow_up_uses_the_setting_and_workday_start(): void
    {
        Setting::set('post_install_followup_days', '5');
        Setting::set('workday_start', '08:30');
        $client = $this->client('Install Client');

        app(FreeInstallationService::class)->completeInstallation($client, $this->founder, [
            'installed_at' => '2026-09-24 10:00:00', 'installed_by' => $this->founder->id, 'custom_item_names' => 'Tablet',
        ]);

        $followUp = DB::table('follow_ups')->where('client_id', $client->id)->sole();
        $this->assertSame('2026-09-29 08:30:00', Carbon::parse($followUp->follow_up_date_time)->format('Y-m-d H:i:s'));
    }

    public function test_workday_start_is_the_default_time_in_scheduling_forms(): void
    {
        Setting::set('workday_start', '08:15');
        $client = $this->client('Form Client', ['stage' => ClientLifecycle::CONTACTING]);

        $html = $this->actingAs($this->staff)->get(route('clients.show', $client))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="installation-schedule-time"[^>]*value="08:15"/', $html);
        $this->assertMatchesRegularExpression('/id="call-callback-at"[^>]*value="2026-09-25T08:15"/', $html);
    }

    public function test_operational_reminders_are_held_outside_working_hours(): void
    {
        $client = $this->client('Reminder Client', ['primary_owner_id' => $this->staff->id]);
        DB::table('follow_ups')->insert([
            'client_id' => $client->id, 'user_id' => $this->staff->id, 'method' => 'phone', 'reason' => 'Call', 'next_action' => 'Call back',
            'next_follow_up_date' => '2026-09-25', 'follow_up_date_time' => '2026-09-25 07:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(NotificationService::class);

        $this->assertSame(0, $service->sendOperationalReminders(Carbon::parse('2026-09-25 07:30:00', 'Asia/Amman')));
        $this->assertSame(1, $service->sendOperationalReminders(Carbon::parse('2026-09-25 09:00:00', 'Asia/Amman')));
        $this->assertSame(0, $service->sendOperationalReminders(Carbon::parse('2026-09-25 09:05:00', 'Asia/Amman')), 'Idempotent per occurrence.');

        Setting::set('workday_start', '07:00');
        $this->assertSame(0, $service->sendOperationalReminders(Carbon::parse('2026-09-25 18:00:00', 'Asia/Amman')));
    }

    public function test_capital_flag_keeps_route_level_enforcement(): void
    {
        $this->assertFalse(Features::capitalEnabled());
        $this->actingAs($this->cofounder)->get(route('finance.capital'))->assertNotFound();

        $this->actingAs($this->cofounder)->put(route('settings.update'), $this->settingsPayload(['feature_capital_financing' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue(Features::capitalEnabled());
        $this->actingAs($this->cofounder)->get(route('finance.capital'))->assertOk();

        $this->actingAs($this->cofounder)->put(route('settings.update'), $this->settingsPayload(['feature_capital_financing' => '0']))->assertSessionHasNoErrors();
        $this->actingAs($this->cofounder)->get(route('finance.capital'))->assertNotFound();
    }

    // ---------------------------------------------------------------- Profile

    public function test_profile_edits_work_fields_only_and_keeps_email_and_role_read_only(): void
    {
        $email = $this->staff->email;

        $this->actingAs($this->staff)->put(route('profile.update'), [
            'name' => 'Sara Haddad', 'job_title' => 'Field sales', 'phone' => '+962 79 000 1111',
            'email' => 'hijack@example.test', 'role' => 'admin',
        ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

        $staff = $this->staff->fresh();
        $this->assertSame(['Sara Haddad', 'Field sales', '+962 79 000 1111', $email, User::ROLE_STAFF], [$staff->name, $staff->job_title, $staff->phone, $staff->email, $staff->role]);

        $this->actingAs($this->staff)->get(route('profile.edit'))->assertOk()
            ->assertSee('data-profile-role', false)
            ->assertDontSee('name="email"', false)->assertDontSee('name="role"', false)
            ->assertDontSee('salary', false)->assertDontSee('national_id', false);
    }

    public function test_avatar_upload_is_validated_stored_safely_and_replaced(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff)->put(route('profile.update'), [
            'name' => 'Staff Sara', 'avatar' => UploadedFile::fake()->createWithContent('me.png', base64_decode(self::PNG_1PX)),
        ])->assertSessionHasNoErrors();
        $first = $this->staff->fresh()->avatar_path;
        $this->assertMatchesRegularExpression('#^avatars/[A-Za-z0-9]{40}\.(jpg|png)$#', $first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->staff)->get(route('profile.edit'))->assertOk()->assertSee('/storage/'.$first, false)->assertDontSee(storage_path(), false);

        // Content is checked, not only the name; non-images and oversize files are refused.
        $this->actingAs($this->staff)->put(route('profile.update'), ['name' => 'Staff Sara', 'avatar' => UploadedFile::fake()->createWithContent('evil.png', '<?php echo 1;')])
            ->assertSessionHasErrors('avatar');
        $this->actingAs($this->staff)->put(route('profile.update'), ['name' => 'Staff Sara', 'avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
        $this->actingAs($this->staff)->put(route('profile.update'), ['name' => 'Staff Sara', 'avatar' => UploadedFile::fake()->create('big.png', 2048, 'image/png')])
            ->assertSessionHasErrors('avatar');
        $this->assertSame($first, $this->staff->fresh()->avatar_path);

        // Replacing removes the old file; removing falls back to the initial.
        $this->actingAs($this->staff)->put(route('profile.update'), ['name' => 'Staff Sara', 'avatar' => UploadedFile::fake()->createWithContent('new.png', base64_decode(self::PNG_1PX))])
            ->assertSessionHasNoErrors();
        $second = $this->staff->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($this->staff)->put(route('profile.update'), ['name' => 'Staff Sara', 'remove_avatar' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($this->staff->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_profile_summary_uses_completed_work_and_the_users_next_three_appointments(): void
    {
        $client = $this->client('Summary Client');
        DB::table('contact_attempts')->insert(['client_id' => $client->id, 'user_id' => $this->staff->id, 'method' => 'phone', 'result' => 'no_answer', 'created_at' => now(), 'updated_at' => now()]);

        $mine = [];
        foreach ([['2026-09-24', '09:00:00'], ['2026-09-24', '11:00:00'], ['2026-09-25', '10:00:00'], ['2026-09-26', '10:00:00'], ['2026-09-27', '10:00:00']] as [$date, $time]) {
            $appointment = $this->appointment($client, $date, $time);
            $appointment->users()->attach($this->staff->id);
            $mine[] = $appointment;
        }
        $theirs = $this->appointment($this->client('Other Client'), '2026-09-24', '10:30:00');
        $theirs->users()->attach($this->cofounder->id);

        $expected = app(CompletedWorkService::class)->forUser($this->staff, Carbon::now('Asia/Amman'))['total'];
        $this->assertSame(1, $expected);

        $response = $this->actingAs($this->staff)->get(route('profile.edit'))->assertOk()
            ->assertSee('data-completed-today="1"', false);
        $html = $response->getContent();
        $this->assertSame(3, substr_count($html, 'data-profile-appointment='));
        // Past (09:00 today) excluded; the nearest three in order; nobody else's.
        $this->assertStringNotContainsString('data-profile-appointment="'.$mine[0]->id.'"', $html);
        $this->assertLessThan(strpos($html, 'data-profile-appointment="'.$mine[2]->id.'"'), strpos($html, 'data-profile-appointment="'.$mine[1]->id.'"'));
        $this->assertStringContainsString('data-profile-appointment="'.$mine[3]->id.'"', $html);
        $this->assertStringNotContainsString('data-profile-appointment="'.$mine[4]->id.'"', $html);
        $this->assertStringNotContainsString('data-profile-appointment="'.$theirs->id.'"', $html);
    }

    // ---------------------------------------------------------------- Custom Projects

    public function test_project_can_exist_without_a_client_but_cannot_be_invoiced(): void
    {
        $this->actingAs($this->cofounder)->post(route('custom-projects.store'), [
            'name' => 'Brand website', 'status' => 'planned', 'agreed_value_jod' => '750.500',
        ])->assertSessionHasNoErrors();
        $project = CustomProject::where('name', 'Brand website')->sole();
        $this->assertNull($project->client_id);
        $this->assertSame(750500, $project->agreed_value_minor);

        $this->actingAs($this->cofounder)->get(route('custom-projects.show', $project))->assertOk()
            ->assertSee('data-invoice-needs-client', false)
            ->assertDontSee('id="project-invoice"', false)
            ->assertDontSee('data-project-invoice-open', false);
        $this->actingAs($this->cofounder)->get(route('custom-projects.index'))->assertOk()->assertSee('data-no-client', false);
    }

    public function test_linking_a_client_writes_activity_enables_the_existing_invoice_flow_and_stays_outside_mrr(): void
    {
        $client = $this->client('Project Client', ['stage' => ClientLifecycle::SUBSCRIBER, 'status' => 'subscriber']);
        $project = CustomProject::create(['name' => 'Menu redesign', 'status' => 'active', 'agreed_value_minor' => 200000, 'created_by' => $this->cofounder->id]);

        $this->actingAs($this->cofounder)->put(route('custom-projects.update', $project), [
            'name' => 'Menu redesign', 'status' => 'active', 'client_id' => $client->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($client->id, (int) $project->fresh()->client_id);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'custom_project_linked', 'description' => 'Menu redesign']);

        $this->actingAs($this->cofounder)->get(route('custom-projects.show', $project))->assertOk()
            ->assertSee('id="project-invoice"', false)
            ->assertSee(route('clients.one-time-invoices.store', $client), false);

        $metricsBefore = DB::table('subscription_metric_events')->count();
        $this->actingAs($this->cofounder)->post(route('clients.one-time-invoices.store', $client), [
            '_idempotency_key' => 'p13-project-invoice',
            'custom_project_id' => $project->id, 'issue_date' => '2026-09-24', 'due_date' => '2026-10-01',
            'lines' => [['line_type' => 'one_time_service', 'description' => 'Menu redesign', 'quantity' => 1, 'unit_price_jod' => '200.000']],
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::where('custom_project_id', $project->id)->sole();
        $this->assertSame($client->id, (int) $invoice->client_id);
        $this->assertSame(200000, (int) $invoice->total_minor);
        $this->assertSame($metricsBefore, DB::table('subscription_metric_events')->count(), 'Project invoices never touch MRR/ARR.');
        $this->assertSame(0, DB::table('subscriptions')->count());

        // Once invoiced, the client link is fixed.
        $this->actingAs($this->cofounder)->put(route('custom-projects.update', $project), [
            'name' => 'Menu redesign', 'status' => 'active', 'client_id' => null,
        ])->assertSessionHasErrors('client_id');
        $this->assertSame($client->id, (int) $project->fresh()->client_id);
    }

    public function test_unlinking_writes_activity_on_the_previous_client(): void
    {
        $client = $this->client('Was Linked');
        $project = CustomProject::create(['client_id' => $client->id, 'name' => 'Signage', 'status' => 'planned', 'agreed_value_minor' => 0, 'created_by' => $this->cofounder->id]);

        $this->actingAs($this->cofounder)->put(route('custom-projects.update', $project), ['name' => 'Signage', 'status' => 'planned', 'client_id' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($project->fresh()->client_id);
        $this->assertDatabaseHas('activity_logs', ['client_id' => $client->id, 'type' => 'custom_project_unlinked']);
    }

    public function test_staff_sees_custom_projects_read_only(): void
    {
        $client = $this->client('Staff View Client');
        $project = CustomProject::create(['client_id' => $client->id, 'name' => 'Read only project', 'status' => 'active', 'agreed_value_minor' => 1000, 'created_by' => $this->cofounder->id]);

        $this->actingAs($this->staff)->get(route('custom-projects.index'))->assertOk()
            ->assertSee('Read only project')->assertSee('data-read-only', false)
            ->assertDontSee('data-page-action="create-custom-project"', false);
        $this->actingAs($this->staff)->get(route('custom-projects.show', $project))->assertOk()
            ->assertDontSee('data-project-edit', false)->assertDontSee('data-project-invoice-open', false)
            ->assertDontSee('id="project-invoice"', false)->assertDontSee('data-project-set-status', false);
        $this->actingAs($this->staff)->post(route('custom-projects.store'), ['name' => 'Nope', 'status' => 'planned'])->assertForbidden();
        $this->actingAs($this->staff)->put(route('custom-projects.update', $project), ['name' => 'Nope', 'status' => 'planned'])->assertForbidden();
    }

    // ---------------------------------------------------------------- Navigation

    public function test_owner_navigation_has_reference_data_and_staff_has_no_administration(): void
    {
        $owner = new ShellNavigation($this->cofounder, 'dashboard');
        $this->assertSame(['systems', 'team', 'reference-data', 'import', 'settings'], array_column($owner->groups['administration']['items'], 'key'));
        $this->assertSame(['systems', 'team', 'reference-data', 'import', 'settings'], array_column($owner->moreSections['administration']['items'], 'key'));

        $active = new ShellNavigation($this->cofounder, 'administration.reference-data');
        $this->assertSame('administration', $active->area);
        $this->assertTrue(collect($active->groups['administration']['items'])->firstWhere('key', 'reference-data')['active']);

        $staff = new ShellNavigation($this->staff, 'dashboard');
        $this->assertArrayNotHasKey('administration', $staff->groups);
        $this->assertArrayNotHasKey('administration', $staff->moreSections);

        $this->actingAs($this->cofounder)->get(route('dashboard'))->assertOk()->assertSee(route('administration.reference-data'), false);
        $this->actingAs($this->staff)->get(route('dashboard'))->assertOk()
            ->assertDontSee(route('administration.reference-data'), false)
            ->assertDontSee(route('settings.index'), false)
            ->assertDontSee(route('commercial-catalog.index'), false);
    }

    // ---------------------------------------------------------------- Import

    public function test_import_page_is_owner_only_and_previews_before_saving(): void
    {
        $this->actingAs($this->staff)->get(route('clients.import'))->assertForbidden();
        $this->actingAs($this->cofounder)->get(route('clients.import'))->assertOk()->assertSee('data-import-upload', false);

        $csv = "business_name,phone,city_area,business_category,lead_source\nمطعم تجربة,0791112233,عبدون,Restaurants,Google Maps\n,0791112234,عبدون,Restaurants,Google Maps\n";
        $file = UploadedFile::fake()->createWithContent('prospects.csv', $csv);

        $this->actingAs($this->cofounder)->post(route('clients.import.preview'), ['type' => 'prospect', 'csv_file' => $file])->assertOk()
            ->assertSee('data-import-result', false)->assertSee('data-import-confirm', false)
            ->assertSee('data-import-row="valid"', false)->assertSee('data-import-row="invalid"', false);
        $this->assertSame(0, Client::count(), 'Preview never writes clients.');

        $this->actingAs($this->cofounder)->post(route('clients.import.confirm'))->assertRedirect(route('clients.index'));
        $this->assertSame(1, Client::count());
        $this->assertSame(0, DB::table('subscriptions')->count());
    }
}
