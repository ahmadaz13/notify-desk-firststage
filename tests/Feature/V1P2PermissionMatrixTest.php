<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CustomProject;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ClientLifecycle;
use App\Support\Permissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * P2 — App\Support\Permissions role matrix (§2.1–2.3) and the controller gates it drives.
 */
class V1P2PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Exactly the Staff capabilities granted by §2.3. */
    private const EXPECTED_STAFF = [
        Permissions::SUBMIT_PAYMENT_RECEIPT,
        Permissions::MANAGE_SYSTEM_ACCESS,
        Permissions::START_PAID_SUBSCRIPTION,
        Permissions::VIEW_COLLECTIONS_DUE,
        Permissions::MANAGE_CLIENT_CREDENTIALS,
        Permissions::REVEAL_CLIENT_CREDENTIALS,
        Permissions::VIEW_CUSTOM_PROJECTS,
    ];

    private User $founder;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    }

    // ── Registry ────────────────────────────────────────────────────────

    public function test_matrix_grants_staff_exactly_the_frozen_operational_set(): void
    {
        $this->assertEqualsCanonicalizing(self::EXPECTED_STAFF, Permissions::forRole(User::ROLE_STAFF));

        foreach ([Permissions::RECORD_PAYMENT, Permissions::APPROVE_PAYMENT_RECEIPTS, Permissions::MANAGE_SUBSCRIPTION_LIFECYCLE,
            Permissions::ISSUE_CONTRACTS, Permissions::EDIT_REFERRAL_COMMISSION, Permissions::MANAGE_CUSTOM_PROJECTS,
            Permissions::MANAGE_EXPENSES, Permissions::VIEW_CASH_MANAGEMENT, Permissions::MANAGE_CASH_TRANSFERS,
            Permissions::VIEW_ACCOUNTING, Permissions::VIEW_FINANCIAL_STATEMENTS, Permissions::EXPORT_FINANCIAL_REPORTS,
            Permissions::VIEW_SAAS_METRICS, Permissions::MANAGE_CAPITAL_FUNDING, Permissions::MANAGE_COMMERCIAL_CATALOG,
            Permissions::MANAGE_TEAM, Permissions::MANAGE_REFERENCE_DATA, Permissions::IMPORT_CLIENTS,
            Permissions::MANAGE_COMPANY_SETTINGS, Permissions::MANAGE_COLLECTION_CORRECTIONS, Permissions::ISSUE_REFUNDS,
            Permissions::MANAGE_CREDIT_NOTES] as $ownerOnly) {
            $this->assertFalse(Permissions::allows($this->staff, $ownerOnly), $ownerOnly);
        }
    }

    public function test_founder_and_admin_hold_every_permission_and_matrix_covers_all(): void
    {
        $this->assertEqualsCanonicalizing(Permissions::ALL, array_keys(Permissions::ROLE_MATRIX));
        $this->assertSame(count(Permissions::ALL), count(array_unique(Permissions::ALL)));

        foreach ([$this->founder, $this->admin] as $owner) {
            $this->assertEqualsCanonicalizing(Permissions::ALL, Permissions::forRole($owner->role));
            foreach (Permissions::ALL as $permission) {
                $this->assertTrue(Gate::forUser($owner)->allows($permission), "{$owner->role}: {$permission}");
            }
        }
    }

    public function test_gate_registration_mirrors_the_matrix_for_staff(): void
    {
        foreach (Permissions::ALL as $permission) {
            $this->assertSame(
                in_array($permission, self::EXPECTED_STAFF, true),
                Gate::forUser($this->staff)->allows($permission),
                $permission
            );
        }
    }

    public function test_inactive_null_legacy_and_unknown_are_denied(): void
    {
        $inactiveAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => false]);
        $inactiveStaff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => false]);
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE, 'is_active' => true]);

        foreach (Permissions::ALL as $permission) {
            $this->assertFalse(Permissions::allows(null, $permission));
            $this->assertFalse(Permissions::allows($inactiveAdmin, $permission));
            $this->assertFalse(Permissions::allows($inactiveStaff, $permission));
            $this->assertFalse(Permissions::allows($employee, $permission));
        }
        $this->assertFalse(Permissions::allows($this->founder, 'not_a_permission'));
    }

    public function test_permission_string_values_are_preserved(): void
    {
        $this->assertSame('record_payment', Permissions::RECORD_PAYMENT);
        $this->assertSame('manage_commercial_catalog', Permissions::MANAGE_COMMERCIAL_CATALOG);
        $this->assertSame('view_financial_reports', Permissions::VIEW_FINANCIAL_REPORTS);
        $this->assertSame('submit_payment_receipt', Permissions::SUBMIT_PAYMENT_RECEIPT);
        $this->assertSame('approve_payment_receipts', Permissions::APPROVE_PAYMENT_RECEIPTS);
        $this->assertFalse(class_exists('App\\Support\\FinancialPermissions'), 'FinancialPermissions alias must be removed once migrated.');
    }

    // ── Staff operational capabilities ──────────────────────────────────

    public function test_staff_can_grant_and_revoke_free_system_access(): void
    {
        $client = $this->client();
        $system = Product::sellable()->firstOrFail();

        $this->actingAs($this->staff)
            ->post(route('clients.system-access.store', $client), ['system_ids' => [$system->id]])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('client_system', ['client_id' => $client->id, 'product_id' => $system->id, 'access_type' => 'free', 'revoked_at' => null]);

        $this->actingAs($this->staff)
            ->delete(route('clients.system-access.destroy', [$client, $system]))
            ->assertSessionHas('success');
        $this->assertNotNull(\DB::table('client_system')->where('client_id', $client->id)->where('product_id', $system->id)->value('revoked_at'));
    }

    public function test_staff_can_start_a_paid_subscription_but_not_cancel_it(): void
    {
        $client = $this->client();
        $system = Product::sellable()->firstOrFail();

        $this->actingAs($this->staff)
            ->getJson(route('clients.guided-subscription.catalog', $client))
            ->assertOk();

        $this->actingAs($this->staff)
            ->post(route('clients.guided-subscription.store', $client), [
                '_idempotency_key' => 'staff-sub-1',
                'system_ids' => [$system->id],
                'billing_interval' => 'annual',
                'agreed_value_jod' => '120.000',
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $subscription = Subscription::where('client_id', $client->id)->sole();

        $this->actingAs($this->staff)
            ->post(route('subscriptions.cancel', $subscription), ['reason' => 'x'])
            ->assertForbidden();
    }

    public function test_staff_workspace_offers_start_subscription(): void
    {
        $client = $this->client();

        $this->actingAs($this->staff)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('data-trigger-start-subscription', false)
            ->assertSee(route('clients.guided-subscription.store', $client), false);
    }

    public function test_referral_commission_is_ignored_server_side_for_staff(): void
    {
        $client = $this->client(['referral_commission_bps' => 500]);
        $payload = [
            'business_name' => 'Referral Client',
            'phone' => '0791111111',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Referral',
            'referred_by_name' => 'Sami',
            'referral_commission_percentage' => '25',
        ];

        $this->actingAs($this->staff)->put(route('clients.update', $client->id), $payload)->assertRedirect();
        $this->assertSame(500, (int) $client->fresh()->referral_commission_bps);
        $this->assertSame('Sami', $client->fresh()->referred_by_name);

        $this->actingAs($this->admin)->put(route('clients.update', $client->id), $payload)->assertRedirect();
        $this->assertSame(2500, (int) $client->fresh()->referral_commission_bps);
    }

    public function test_staff_views_custom_projects_read_only(): void
    {
        $client = $this->client();
        $project = CustomProject::create([
            'client_id' => $client->id, 'name' => 'Menu redesign', 'agreed_value_minor' => 50000,
            'status' => CustomProject::STATUSES[0], 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)->get(route('custom-projects.index'))
            ->assertOk()
            ->assertSee('Menu redesign')
            ->assertDontSee(route('custom-projects.create'), false);

        $this->actingAs($this->staff)->get(route('custom-projects.show', $project))
            ->assertOk()
            ->assertDontSee(route('custom-projects.edit', $project), false)
            ->assertDontSee(route('custom-projects.update', $project), false);

        $this->actingAs($this->staff)->get(route('clients.custom-projects.index', $client))->assertOk();

        $this->actingAs($this->staff)->put(route('custom-projects.update', $project), ['name' => 'Hijack'])->assertForbidden();
        $this->assertSame('Menu redesign', $project->fresh()->name);

        $this->actingAs($this->admin)->get(route('custom-projects.show', $project))
            ->assertOk()
            ->assertSee(route('custom-projects.edit', $project), false);
    }

    // ── Import and team authorization ───────────────────────────────────

    public function test_csv_import_is_owner_only(): void
    {
        $this->actingAs($this->staff)->get(route('clients.import'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('clients.import.confirm'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('clients.import.template', 'prospects'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('clients.import'))->assertOk();
    }

    public function test_only_a_founder_may_change_a_founders_role_or_deactivate_a_founder(): void
    {
        $otherFounder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $update = fn (User $member, array $overrides = []) => array_merge([
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role,
            'is_active' => '1',
        ], $overrides);

        // Admin: cannot demote, cannot deactivate via edit form, cannot deactivate via action.
        $this->actingAs($this->admin)->put(route('administration.team.update', $otherFounder->id), $update($otherFounder, ['role' => 'staff']))->assertForbidden();
        $withoutActive = $update($otherFounder);
        unset($withoutActive['is_active']);
        $this->actingAs($this->admin)->put(route('administration.team.update', $otherFounder->id), $withoutActive)->assertForbidden();
        $this->actingAs($this->admin)->post(route('administration.team.deactivate', $otherFounder->id))->assertForbidden();
        $this->assertSame(User::ROLE_FOUNDER, $otherFounder->fresh()->role);
        $this->assertTrue($otherFounder->fresh()->is_active);

        // Admin may still edit a Founder's non-protected details.
        $this->actingAs($this->admin)->put(route('administration.team.update', $otherFounder->id), $update($otherFounder, ['name' => 'Renamed']))->assertRedirect(route('administration.team'));
        $this->assertSame('Renamed', $otherFounder->fresh()->name);

        // Admin manages Staff normally.
        $this->actingAs($this->admin)->post(route('administration.team.deactivate', $this->staff->id))->assertRedirect(route('administration.team'));
        $this->assertFalse($this->staff->fresh()->is_active);

        // A Founder may deactivate another Founder.
        $this->actingAs($this->founder)->post(route('administration.team.deactivate', $otherFounder->id))->assertRedirect(route('administration.team'));
        $this->assertFalse($otherFounder->fresh()->is_active);
    }

    public function test_staff_cannot_reach_team_management(): void
    {
        $this->actingAs($this->staff)->get(route('administration.team'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('administration.team.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'role' => 'admin', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    private function client(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'P2 Client',
            'phone' => '0790000002',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::DECISION_PENDING,
        ], $overrides));
    }
}
