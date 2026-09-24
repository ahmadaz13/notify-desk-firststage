<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Contract;
use App\Models\CustomProject;
use App\Models\Product;
use App\Models\ReferenceOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\FinancialStatementService;
use App\Services\ReferenceDataService;
use App\Support\ClientLifecycle;
use App\Support\Features;
use App\Support\ReportingPeriod;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P3 — core data-model additions M-2…M-12, reference data (§15.1) and the Capital gate (§13).
 */
class V1P3SchemaReferenceAndCapitalGateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    }

    // ── Schema (M-2, M-6, M-7, M-8, M-9, M-10) ──────────────────────────

    public function test_user_profile_columns_exist_and_are_nullable(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['phone', 'job_title', 'avatar_path']));

        $this->admin->update(['phone' => '0791234567', 'job_title' => 'Operations', 'avatar_path' => 'avatars/a.png']);
        $this->assertSame('Operations', $this->admin->fresh()->job_title);
        $this->assertNull($this->staff->fresh()->phone);
    }

    public function test_custom_project_client_is_optional_but_still_restricts_client_deletion(): void
    {
        $unlinked = CustomProject::create(['name' => 'Internal tool', 'agreed_value_minor' => 1000, 'status' => CustomProject::STATUSES[0], 'created_by' => $this->admin->id]);
        $this->assertNull($unlinked->fresh()->client_id);

        $client = $this->client();
        CustomProject::create(['client_id' => $client->id, 'name' => 'Linked', 'agreed_value_minor' => 1000, 'status' => CustomProject::STATUSES[0], 'created_by' => $this->admin->id]);

        $this->expectException(QueryException::class);
        DB::table('clients')->where('id', $client->id)->delete();
    }

    public function test_contract_number_is_nullable_but_non_null_numbers_stay_unique(): void
    {
        $client = $this->client();
        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id, 'user_id' => $this->admin->id, 'billing_type' => 'monthly', 'total_price' => 10,
            'start_date' => now()->toDateString(), 'renewal_date' => now()->addMonth()->toDateString(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = fn (?string $number) => [
            'contract_number' => $number, 'client_id' => $client->id, 'subscription_id' => $subscriptionId,
            'generated_by' => $this->admin->id, 'status' => 'draft', 'snapshot_data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ];

        DB::table('contracts')->insert($row(null));
        DB::table('contracts')->insert($row(null));
        DB::table('contracts')->insert($row('ND-2026-0001'));
        $this->assertSame(2, DB::table('contracts')->whereNull('contract_number')->count());

        $this->expectException(QueryException::class);
        DB::table('contracts')->insert($row('ND-2026-0001'));
    }

    public function test_v1_system_identities_have_explicit_credential_capability(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'requires_credentials'));

        $expected = ['smart_link' => true, 'e_menu' => true, 'e_store' => true, 'auto_sms_system' => false];
        foreach ($expected as $code => $requiresCredentials) {
            $this->assertSame(1, Product::where('code', $code)->count(), "exactly one {$code}");
            $system = Product::where('code', $code)->firstOrFail();
            $this->assertSame($requiresCredentials, $system->requires_credentials, $code);
            $this->assertTrue($system->is_active, $code);
        }
        $this->assertSame('Smart Link', Product::where('code', 'smart_link')->value('name_en'));
        $this->assertSame('E-Menu', Product::where('code', 'e_menu')->value('name_en'));
        $this->assertSame('E-Store', Product::where('code', 'e_store')->value('name_en'));

        // Unrelated existing systems are kept as they were (not renamed, not flagged).
        foreach (['restaurant_system' => 'Restaurant System', 'digital_store_system' => 'Digital Store System'] as $code => $name) {
            $system = Product::where('code', $code)->firstOrFail();
            $this->assertSame($name, $system->name_en);
            $this->assertFalse($system->requires_credentials);
        }

        // New systems default to no credential capability.
        $this->assertFalse(Product::create(['code' => 'new_system', 'name_ar' => 'نظام', 'is_active' => true])->fresh()->requires_credentials);
    }

    public function test_system_identity_migration_is_idempotent_and_preserves_existing_rows(): void
    {
        $migration = require database_path('migrations/2026_09_24_001000_seed_v1_credential_capable_systems.php');
        Product::where('code', 'e_menu')->update(['name_en' => 'E-Menu (owner renamed)', 'requires_credentials' => false]);
        Product::where('code', 'auto_sms_system')->update(['requires_credentials' => true]);
        $count = Product::count();

        $migration->up();
        $migration->up();

        $this->assertSame($count, Product::count());
        $this->assertSame('E-Menu (owner renamed)', Product::where('code', 'e_menu')->value('name_en'));
        $this->assertTrue(Product::where('code', 'e_menu')->firstOrFail()->requires_credentials);
        $this->assertFalse(Product::where('code', 'auto_sms_system')->firstOrFail()->requires_credentials);
    }

    public function test_client_primary_phone_type_defaults_to_business(): void
    {
        $client = $this->client();
        $this->assertSame('business', $client->fresh()->primary_phone_type);
        $this->assertSame(['business', 'owner', 'manager'], Client::PRIMARY_PHONE_TYPES);

        $client->update(['primary_phone_type' => 'owner']);
        $this->assertSame('owner', $client->fresh()->primary_phone_type);
    }

    public function test_client_contact_name_is_optional_and_email_is_stored(): void
    {
        $contact = ClientContact::create([
            'client_id' => $this->client()->id,
            'name' => null,
            'role' => 'owner',
            'primary_phone' => '0790000004',
            'email' => 'owner@example.com',
            'is_primary' => true,
        ]);

        $this->assertNull($contact->fresh()->name);
        $this->assertSame('owner@example.com', $contact->fresh()->email);
    }

    public function test_legacy_status_backfill_only_fills_missing_stage(): void
    {
        $migration = require database_path('migrations/2026_09_24_000800_backfill_client_stage_from_legacy_status.php');
        $client = $this->client(['status' => 'subscriber', 'stage' => ClientLifecycle::DECISION_PENDING]);

        $migration->up();
        $migration->up();

        $this->assertSame(ClientLifecycle::DECISION_PENDING, $client->fresh()->stage, 'An existing stage is never overwritten.');
    }

    // ── Reference data (M-3, §15.1) ─────────────────────────────────────

    public function test_frozen_lead_sources_are_seeded_once_and_idempotently(): void
    {
        $expected = ['Google Maps', 'Instagram', 'Referral', 'Direct Prospecting', 'Existing Client', 'Other'];
        $this->assertSame($expected, ReferenceOption::forList(ReferenceDataService::LEAD_SOURCE)->pluck('value')->all());

        app(ReferenceDataService::class)->ensureDefaults();
        app(ReferenceDataService::class)->ensureDefaults();

        $this->assertSame(6, ReferenceOption::where('list_key', ReferenceDataService::LEAD_SOURCE)->count());
        $this->assertSame(0, ReferenceOption::whereIn('list_key', [ReferenceDataService::CLIENT_CATEGORY, ReferenceDataService::CITY_AREA])->count());
    }

    public function test_reference_options_are_localized_filter_inactive_and_keep_unknown_historical_values(): void
    {
        $service = app(ReferenceDataService::class);
        ReferenceOption::where('list_key', 'lead_source')->where('value', 'Instagram')->update(['is_active' => false]);
        ReferenceOption::create(['list_key' => 'city_area', 'value' => 'Abdoun', 'label_ar' => 'عبدون', 'label_en' => 'Abdoun', 'sort_order' => 10]);

        app()->setLocale('en');
        $options = $service->options('lead_source', 'Facebook Ads');
        $this->assertSame('Direct / Field visit', $options['Direct Prospecting']);
        $this->assertArrayNotHasKey('Instagram', $options);
        $this->assertSame('Facebook Ads', $options['Facebook Ads']);

        app()->setLocale('ar');
        $this->assertSame('عبدون', $service->options('city_area')['Abdoun']);
        $this->assertSame('Legacy', $service->label('lead_source', 'Legacy'));

        $this->expectException(QueryException::class);
        ReferenceOption::create(['list_key' => 'city_area', 'value' => 'Abdoun', 'label_ar' => 'x', 'label_en' => 'x']);
    }

    public function test_client_form_lead_sources_come_from_reference_data(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['locale' => 'en'])
            ->get(route('clients.create'))
            ->assertOk()
            ->assertSee('Existing client')
            ->assertSee('Direct / Field visit');
    }

    // ── Capital & Financing gate (M-12, §13) ────────────────────────────

    public function test_capital_flag_defaults_off_and_blocks_capital_routes_with_404(): void
    {
        $this->assertSame('0', Setting::get('feature_capital_financing'));
        $this->assertFalse(Features::capitalEnabled());

        $this->actingAs($this->admin)->get(route('capital-management.index'))->assertNotFound();
        $this->actingAs($this->admin)->get(route('finance.capital'))->assertNotFound();
        $this->actingAs($this->admin)->post(route('funding-sources.store'), ['name' => 'X', 'type' => 'founder'])->assertNotFound();
        $this->actingAs($this->admin)->post(route('capital-funding-transactions.store'), [])->assertNotFound();
        $this->assertSame(0, DB::table('funding_sources')->count());

        $this->actingAs($this->admin)->get(route('finance.accounts'))->assertOk()->assertDontSee(route('finance.capital'), false);
    }

    public function test_capital_on_exposes_funding_but_never_fixed_assets(): void
    {
        Setting::set('feature_capital_financing', '1');
        $this->assertTrue(Features::capitalEnabled());

        $this->actingAs($this->admin)->get(route('finance.capital'))
            ->assertOk()
            ->assertSee(route('capital-funding-transactions.store'), false)
            ->assertDontSee('/fixed-assets', false)
            ->assertDontSee('/asset-categories', false);
        $this->actingAs($this->staff)->get(route('finance.capital'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('finance.accounts'))->assertOk()->assertSee(route('finance.capital'), false);

        foreach (['asset-categories.store', 'asset-categories.archive', 'fixed-assets.store', 'fixed-assets.reverse', 'fixed-assets.status'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} must not be registered in V1.");
        }
        $this->actingAs($this->admin)->post('/fixed-assets', [])->assertNotFound();
        $this->actingAs($this->admin)->post('/asset-categories', [])->assertNotFound();

        // Engine tables/models are retained.
        $this->assertTrue(Schema::hasTable('fixed_assets'));
        $this->assertTrue(Schema::hasTable('asset_categories'));
    }

    public function test_capital_flag_never_changes_report_math(): void
    {
        $statements = app(FinancialStatementService::class);
        $period = ReportingPeriod::fromRequest(['range' => 'this_year']);
        $asOf = now()->startOfDay();

        Setting::set('feature_capital_financing', '0');
        $off = [json_encode($statements->balanceSheet($asOf)), json_encode($statements->capitalAssetReport($period))];
        Setting::set('feature_capital_financing', '1');
        $on = [json_encode($statements->balanceSheet($asOf)), json_encode($statements->capitalAssetReport($period))];

        $this->assertSame($off, $on);

        Setting::set('feature_capital_financing', '0');
        $this->actingAs($this->admin)->get(route('finance.reports', ['report' => 'financial-position']))->assertOk();
    }

    public function test_settings_toggle_controls_the_capital_flag(): void
    {
        $settings = \App\Http\Controllers\SettingsController::defaults();
        $this->assertSame('0', $settings['feature_capital_financing']);

        $this->actingAs($this->admin)->put(route('settings.update'), array_merge($settings, ['feature_capital_financing' => '1']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Features::capitalEnabled());

        $withoutFlag = $settings;
        unset($withoutFlag['feature_capital_financing']);
        $this->actingAs($this->admin)->put(route('settings.update'), $withoutFlag)->assertSessionHasNoErrors();
        $this->assertFalse(Features::capitalEnabled());
    }

    private function client(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'P3 Schema Client',
            'phone' => '0790000005',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Referral',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }
}
