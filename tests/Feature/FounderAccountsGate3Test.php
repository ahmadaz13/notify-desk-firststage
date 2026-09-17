<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Support\FinancialPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FounderAccountsGate3Test extends TestCase
{
    use RefreshDatabase;

    private const AHMAD_EMAIL = 'ahmad@notifydisk.com';
    private const KHALID_EMAIL = 'khalid@notifydisk.com';
    private const AHMAD_PASSWORD = 'test-founder-secret-one';
    private const KHALID_PASSWORD = 'test-founder-secret-two';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('founder_accounts.accounts', [
            'ahmad' => [
                'name' => 'Ahmad',
                'email' => self::AHMAD_EMAIL,
                'password' => self::AHMAD_PASSWORD,
            ],
            'khalid' => [
                'name' => 'Khalid',
                'email' => self::KHALID_EMAIL,
                'password' => self::KHALID_PASSWORD,
            ],
        ]);
    }

    public function test_founder_bootstrap_creates_required_active_founders_exactly_once(): void
    {
        Artisan::call('notify:bootstrap-founders');
        Artisan::call('notify:bootstrap-founders');

        $this->assertSame(1, User::where('email', self::AHMAD_EMAIL)->count());
        $this->assertSame(1, User::where('email', self::KHALID_EMAIL)->count());

        foreach ([self::AHMAD_EMAIL, self::KHALID_EMAIL] as $email) {
            $founder = User::where('email', $email)->firstOrFail();

            $this->assertTrue($founder->isFounder());
            $this->assertTrue($founder->is_active);
            $this->assertTrue($founder->isAdmin());
            $this->assertTrue($founder->isActiveApplicationUser());
            $this->assertNull($founder->partner_id);
        }
    }

    public function test_bootstrap_does_not_rotate_existing_password_without_explicit_reset(): void
    {
        Artisan::call('notify:bootstrap-founders');

        $originalHash = User::where('email', self::AHMAD_EMAIL)->value('password');

        config()->set('founder_accounts.accounts.ahmad.password', 'changed-test-founder-secret');

        Artisan::call('notify:bootstrap-founders');

        $this->assertSame($originalHash, User::where('email', self::AHMAD_EMAIL)->value('password'));
        $this->assertTrue(Hash::check(self::AHMAD_PASSWORD, $originalHash));
        $this->assertFalse(Hash::check('changed-test-founder-secret', $originalHash));
    }

    public function test_explicit_reset_password_option_rotates_founder_passwords_only_when_requested(): void
    {
        Artisan::call('notify:bootstrap-founders');

        config()->set('founder_accounts.accounts.ahmad.password', 'intentional-reset-secret');

        Artisan::call('notify:bootstrap-founders', ['--reset-passwords' => true]);

        $hash = User::where('email', self::AHMAD_EMAIL)->value('password');

        $this->assertTrue(Hash::check('intentional-reset-secret', $hash));
        $this->assertFalse(Hash::check(self::AHMAD_PASSWORD, $hash));
    }

    public function test_founder_passwords_are_hashed_and_never_printed_by_bootstrap(): void
    {
        Artisan::call('notify:bootstrap-founders');

        $output = Artisan::output();
        $hash = User::where('email', self::AHMAD_EMAIL)->value('password');

        $this->assertNotSame(self::AHMAD_PASSWORD, $hash);
        $this->assertTrue(Hash::check(self::AHMAD_PASSWORD, $hash));
        $this->assertStringNotContainsString(self::AHMAD_PASSWORD, $output);
        $this->assertStringNotContainsString(self::KHALID_PASSWORD, $output);
    }

    public function test_founder_authenticates_through_existing_login_and_sees_founder_label(): void
    {
        Artisan::call('notify:bootstrap-founders');

        $this->post(route('login.store'), [
            'email' => self::AHMAD_EMAIL,
            'password' => self::AHMAD_PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::where('email', self::AHMAD_EMAIL)->first());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('notify.common.role_founder'));
    }

    public function test_founder_has_owner_level_access_to_v1_protected_modules(): void
    {
        $founder = User::factory()->create(['role' => User::ROLE_FOUNDER]);

        foreach (FinancialPermissions::ALL as $permission) {
            $this->assertTrue(Gate::forUser($founder)->allows($permission), $permission);
        }

        $this->actingAs($founder)->get(route('dashboard'))->assertOk();
        $this->actingAs($founder)->get(route('clients.index'))->assertOk();
        $this->actingAs($founder)->get(route('commercial-catalog.index'))->assertOk();
        $this->actingAs($founder)->get(route('collections.index'))->assertOk();
        $this->actingAs($founder)->get(route('financial-accounts.index'))->assertOk();
        $this->actingAs($founder)->get(route('operating-expenses.index'))->assertOk();
        $this->actingAs($founder)->get(route('capital-management.index'))->assertOk();
        $this->actingAs($founder)->get(route('finance.index'))->assertOk();
        $this->actingAs($founder)->get(route('saas-metrics.index'))->assertOk();
        $this->actingAs($founder)->get(route('executive.index'))->assertOk();
        $this->actingAs($founder)->get(route('subscription-billing.index'))->assertOk();
        $this->actingAs($founder)->get(route('accounting.index'))->assertOk();
        $this->actingAs($founder)->get(route('settings.index'))->assertOk();
    }

    public function test_inactive_internal_account_is_blocked(): void
    {
        $founder = User::factory()->create([
            'role' => User::ROLE_FOUNDER,
            'is_active' => false,
        ]);

        $this->assertFalse($founder->isActiveApplicationUser());

        $this->actingAs($founder)->get(route('dashboard'))->assertForbidden();
        $this->post(route('login.store'), [
            'email' => $founder->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_partner_remains_non_authenticatable_and_not_a_user_side_effect(): void
    {
        $partner = Partner::create([
            'company_name' => 'Referral Only Partner',
            'email' => 'referral-only@example.com',
        ]);

        $partnerUser = User::factory()->create([
            'role' => User::ROLE_PARTNER,
            'partner_id' => $partner->id,
            'email' => 'legacy-partner@example.com',
            'password' => Hash::make('legacy-partner-password'),
        ]);

        $this->assertFalse($partnerUser->isPartner());
        $this->assertTrue($partnerUser->hasLegacyPartnerRole());
        $this->assertFalse($partnerUser->isActiveApplicationUser());

        $this->post(route('login.store'), [
            'email' => 'legacy-partner@example.com',
            'password' => 'legacy-partner-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_future_admin_and_employee_account_values_are_representable(): void
    {
        $admin = new User(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $employee = new User(['role' => User::ROLE_EMPLOYEE, 'is_active' => true]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($employee->isEmployee());
        $this->assertFalse($employee->isActiveApplicationUser());
    }
}
