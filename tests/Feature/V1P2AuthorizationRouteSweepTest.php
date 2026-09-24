<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveInternalUser;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientReviewItem;
use App\Models\CustomProject;
use App\Models\PaymentReceiptConfirmation;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ClientLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * P2 route sweep (§27, §32): every authenticated named route is exercised as Staff and as
 * Owner-level users. Staff must be forbidden exactly on the routes the §2.3 matrix withholds.
 */
class V1P2AuthorizationRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    /** Routes Staff may use per §2.3 (operational work, free access, paid subscription start, receipts, read-only custom projects). */
    private const STAFF_ALLOWED = [
        'dashboard', 'locale.switch', 'profile.edit', 'profile.update', 'profile.password', 'daily-notes.save',
        'clients.index', 'clients.create', 'clients.store', 'clients.show', 'clients.edit', 'clients.update', 'clients.destroy',
        'clients.stage.update', 'clients.close', 'clients.reopen',
        'clients.contacts.store', 'clients.contacts.update', 'clients.contact-attempts.store',
        'clients.installations.schedule', 'clients.installations.complete',
        'client-review-items.resolve', 'client-review-items.dismiss',
        'appointments.store', 'appointments.update', 'appointments.reschedule',
        'appointments.outcome.create', 'appointments.outcome.store', 'appointments.compact-outcome.store',
        'clients.follow-ups.store', 'follow-ups.complete', 'clients.offers.store',
        'clients.system-access.store', 'clients.system-access.destroy',
        // P5 client system credentials (manage/reveal_client_credentials: Owner + Staff)
        'clients.credentials.store', 'clients.credentials.update', 'clients.credentials.destroy',
        'clients.credentials.reveal', 'clients.credentials.copied', 'clients.credentials.send',
        'clients.guided-subscription.catalog', 'clients.guided-subscription.preview', 'clients.guided-subscription.store',
        'contracts.preview', 'contracts.download-pdf',
        'clients.payment-receipts.store', 'payment-receipts.cancel',
        'custom-projects.index', 'custom-projects.show', 'clients.custom-projects.index',
        // P6 Staff operational collections list (§9.7)
        'collections-due.index',
        'notifications.index', 'notifications.read', 'notifications.read-all',
    ];

    /** Routes restricted to Owner-level users (founder, admin). */
    private const OWNER_ONLY = [
        // Direct payments, approvals and collection corrections
        'clients.payments.normal.store', 'clients.collections.payments.store', 'clients.credit-notes.store',
        'payment-receipts.approve', 'payment-receipts.reject',
        'payments.allocations.store', 'payments.auto-allocate', 'payment-allocations.reverse', 'payments.reverse', 'payments.refunds.store',
        'credit-notes.applications.store', 'credit-note-applications.reverse', 'credit-notes.void', 'credit-notes.refunds.store',
        'collections.index', 'clients.one-time-invoices.store', 'invoices.void',
        // Subscription lifecycle and contract issuance
        'subscriptions.cancel', 'subscriptions.cancel.undo',
        'contracts.store', 'contracts.issue', 'contracts.void', 'contracts.supersede',
        // Custom Projects management
        'custom-projects.create', 'custom-projects.store', 'custom-projects.edit', 'custom-projects.update', 'custom-projects.archive',
        // Import
        'clients.import', 'clients.import.preview', 'clients.import.confirm', 'clients.import.template',
        // Administration, team, catalog, settings
        'administration.index', 'administration.team', 'administration.team.create', 'administration.team.store',
        'administration.team.edit', 'administration.team.update', 'administration.team.reset-password', 'administration.team.deactivate',
        // P13: team reactivation and Operational Reference Data (manage_reference_data)
        'administration.team.activate',
        'administration.reference-data', 'administration.reference-data.store', 'administration.reference-data.update', 'administration.reference-data.active',
        'commercial-catalog.index', 'commercial-catalog.products.store', 'commercial-catalog.products.update', 'commercial-catalog.products.archive',
        'settings.index', 'settings.update',
        // Company accounts and transfers
        'financial-accounts.index', 'financial-accounts.store', 'financial-accounts.archive',
        'financial-transfers.store', 'financial-transfers.reverse', 'cash-events.assign-account',
        // Expenses
        'operating-expenses.index', 'operating-expenses.store', 'operating-expenses.reverse',
        'expense-categories.store', 'expense-categories.update', 'expense-categories.archive',
        'vendors.store', 'vendors.archive', 'recurring-expense-templates.store', 'recurring-expense-templates.update',
        'recurring-expense-obligations.generate', 'recurring-expense-obligations.pay', 'recurring-expense-obligations.skip', 'recurring-expense-obligations.cancel',
        // Capital & Financing (feature-gated, P3). Fixed-asset and asset-category routes no longer exist in V1.
        'capital-management.index', 'funding-sources.store', 'funding-sources.archive',
        'capital-funding-transactions.store', 'capital-funding-transactions.reverse',
        // Accounting, reports, metrics
        'accounting.index', 'accounting.chart-accounts.archive', 'accounting.periods.close', 'accounting.periods.reopen',
        'accounting.backfill', 'accounting.revenue-schedules.backfill', 'accounting.revenue-recognition.run', 'accounting.revenue-recognition.confirm',
        'finance.index', 'finance.export', 'executive.index', 'saas-metrics.index', 'saas-metrics.export',
        // P6 canonical Finance destinations (§12); the legacy names above are permission-checked redirects
        'finance.collections', 'finance.expenses', 'finance.accounts', 'finance.accounting',
        'finance.reports', 'finance.reports.export', 'finance.capital',
        'subscription-billing.index', 'subscription-billing.generate-renewals', 'subscription-billing.backfill-periods',
    ];

    /** Routes scoped to the record owner, not the role: only the submitter may cancel a receipt (§9.2). */
    private const OWNERSHIP_SCOPED = ['payment-receipts.cancel'];

    private User $founder;
    private User $admin;
    private User $staff;
    private User $teamTarget;

    /** @var array<class-string<Model>, int> */
    private array $fixtures = [];

    /** @var array<string, int> */
    private array $intFixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->founder = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
        $this->teamTarget = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
        // Exercise role gates on capital routes; the OFF-state 404 is covered by the P3 capital gate tests.
        \App\Models\Setting::set('feature_capital_financing', '1');
        $this->seedFixtures();
    }

    public function test_every_authenticated_named_route_is_classified(): void
    {
        $routes = $this->authenticatedRoutes()->keys()->sort()->values()->all();
        $classified = collect([...self::STAFF_ALLOWED, ...self::OWNER_ONLY])->sort()->values()->all();

        $this->assertSame([], array_values(array_intersect(self::STAFF_ALLOWED, self::OWNER_ONLY)), 'A route cannot be both staff-allowed and owner-only.');
        $this->assertSame([], array_values(array_diff($routes, $classified)), 'Unclassified routes: classify every new route in the P2 sweep.');
        $this->assertSame([], array_values(array_diff($classified, $routes)), 'Classified routes that no longer exist.');
    }

    public function test_staff_is_forbidden_on_every_owner_only_route(): void
    {
        $unexpected = [];
        foreach (self::OWNER_ONLY as $name) {
            $status = $this->hit($this->staff, $name)->baseResponse->getStatusCode();
            if ($status !== 403) {
                $unexpected[$name] = $status;
            }
        }

        $this->assertSame([], $unexpected, 'Staff reached owner-only routes (route => status).');
    }

    public function test_staff_is_never_forbidden_on_staff_allowed_routes(): void
    {
        $forbidden = [];
        foreach (self::STAFF_ALLOWED as $name) {
            if ($this->hit($this->staff, $name)->baseResponse->getStatusCode() === 403) {
                $forbidden[] = $name;
            }
        }

        $this->assertSame([], $forbidden, 'Staff was forbidden on routes the matrix grants.');
    }

    public function test_owner_level_users_are_never_forbidden(): void
    {
        foreach ([$this->founder, $this->admin] as $owner) {
            $forbidden = [];
            foreach ($this->authenticatedRoutes()->keys()->diff(self::OWNERSHIP_SCOPED) as $name) {
                if ($this->hit($owner, $name)->baseResponse->getStatusCode() === 403) {
                    $forbidden[] = $name;
                }
            }

            $this->assertSame([], $forbidden, "Owner-level role {$owner->role} was forbidden.");
        }
    }

    public function test_inactive_owner_is_forbidden_everywhere(): void
    {
        $this->admin->update(['is_active' => false]);

        foreach (['dashboard', 'collections.index', 'settings.index', 'administration.team'] as $name) {
            $this->assertSame(403, $this->hit($this->admin, $name)->baseResponse->getStatusCode(), $name);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * @return \Illuminate\Support\Collection<string, RoutingRoute>
     */
    private function authenticatedRoutes()
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => $route->getName() !== null
                && in_array(EnsureActiveInternalUser::class, $route->gatherMiddleware(), true))
            ->keyBy(fn (RoutingRoute $route) => $route->getName());
    }

    private function hit(User $user, string $name): TestResponse
    {
        /** @var RoutingRoute $route */
        $route = $this->authenticatedRoutes()->get($name);
        $this->assertNotNull($route, "Route {$name} is missing.");

        $parameters = $this->bindParameters($route);
        $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
        $url = route($name, $parameters, false);

        DB::beginTransaction();
        try {
            return $this->actingAs($user)->call($method, $url, ['_idempotency_key' => 'sweep-'.$name.'-'.$user->id]);
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    /**
     * Resolve each route parameter from the controller signature: persisted fixtures where they exist,
     * otherwise an unsaved placeholder model (authorization must run before the model is used).
     */
    private function bindParameters(RoutingRoute $route): array
    {
        $router = $this->app->make(Router::class);
        $action = $route->getActionName();
        $signature = [];
        if (str_contains($action, '@')) {
            [$class, $methodName] = explode('@', $action);
            foreach ((new ReflectionMethod($class, $methodName))->getParameters() as $parameter) {
                $type = $parameter->getType();
                $signature[$parameter->getName()] = $type instanceof ReflectionNamedType ? $type->getName() : null;
            }
        }

        $values = [];
        foreach ($route->parameterNames() as $name) {
            $type = $signature[$name] ?? null;

            if ($type !== null && is_subclass_of($type, Model::class)) {
                $id = $this->fixtures[$type] ?? 999999;
                $router->bind($name, function ($value) use ($type) {
                    if (isset($this->fixtures[$type])) {
                        return $type::findOrFail($value);
                    }
                    $placeholder = new $type;
                    $placeholder->forceFill(['id' => (int) $value]);
                    $placeholder->exists = true;

                    return $placeholder;
                });
                $values[$name] = $id;

                continue;
            }

            $router->bind($name, fn ($value) => $value);
            $values[$name] = match ($name) {
                'report' => 'income_statement',
                'type' => 'prospects',
                'locale' => 'en',
                'id' => $this->teamTarget->id,
                default => $this->intFixtures[$name] ?? 999999,
            };
        }

        return $values;
    }

    private function seedFixtures(): void
    {
        $now = now();
        $client = Client::create([
            'business_name' => 'Sweep Client',
            'phone' => '0790000009',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ]);
        $this->fixtures[Client::class] = $client->id;
        $this->intFixtures['client'] = $client->id;

        $contact = ClientContact::create(['client_id' => $client->id, 'name' => 'Owner', 'role' => 'owner', 'primary_phone' => '0790000009', 'is_primary' => true]);
        $this->fixtures[ClientContact::class] = $contact->id;

        $review = ClientReviewItem::create(['client_id' => $client->id, 'type' => ClientReviewItem::TYPE_WRONG_INVALID, 'status' => ClientReviewItem::STATUS_PENDING]);
        $this->fixtures[ClientReviewItem::class] = $review->id;

        $appointmentId = DB::table('appointments')->insertGetId([
            'client_id' => $client->id, 'appointment_date' => $now->toDateString(), 'appointment_time' => '10:00',
            'appointment_type' => 'physical_visit', 'status' => 'scheduled', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->fixtures[\App\Models\Appointment::class] = $appointmentId;
        $this->intFixtures['appointment'] = $appointmentId;

        $this->intFixtures['followUp'] = DB::table('follow_ups')->insertGetId([
            'client_id' => $client->id, 'user_id' => $this->staff->id, 'method' => 'call', 'reason' => 'check',
            'next_action' => 'call back', 'next_follow_up_date' => $now->toDateString(), 'follow_up_date_time' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'client_id' => $client->id, 'user_id' => $this->admin->id, 'billing_type' => 'monthly', 'total_price' => 10,
            'start_date' => $now->toDateString(), 'renewal_date' => $now->copy()->addMonth()->toDateString(),
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->fixtures[Subscription::class] = $subscriptionId;

        if ($product = Product::query()->first()) {
            $this->fixtures[Product::class] = $product->id;
        }

        $project = CustomProject::create([
            'client_id' => $client->id, 'name' => 'Sweep project', 'agreed_value_minor' => 1000,
            'status' => CustomProject::STATUSES[0], 'created_by' => $this->admin->id,
        ]);
        $this->fixtures[CustomProject::class] = $project->id;

        $receipt = PaymentReceiptConfirmation::create([
            'client_id' => $client->id, 'amount_minor' => 1000, 'currency' => 'JOD', 'payment_method' => 'cash',
            'received_at' => $now, 'status' => 'pending', 'submitted_by' => $this->staff->id, 'idempotency_key' => 'sweep-receipt',
        ]);
        $this->fixtures[PaymentReceiptConfirmation::class] = $receipt->id;

        $this->intFixtures['notification'] = DB::table('notifications')->insertGetId([
            'user_id' => $this->staff->id, 'type' => 'info', 'title' => 'Sweep', 'message' => 'Sweep',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
