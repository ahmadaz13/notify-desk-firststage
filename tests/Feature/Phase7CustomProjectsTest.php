<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CustomProject;
use App\Models\Invoice;
use App\Models\User;
use App\Services\SaasMetricsService;
use App\Support\ReportingPeriod;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7CustomProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function client(string $name = 'Project Client'): Client
    {
        return Client::create([
            'business_name' => $name,
            'phone' => '0790000000',
            'city_area' => 'Amman',
            'business_category' => 'Services',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'stage' => 'prospect',
        ]);
    }

    public function test_project_lifecycle_uses_exact_minor_units_and_preserves_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->client();

        $this->actingAs($admin)->post(route('custom-projects.store'), [
            'client_id' => $client->id,
            'name' => 'Website',
            'agreed_value_jod' => '123.456',
            'status' => 'planned',
        ])->assertRedirect();

        $project = CustomProject::firstOrFail();
        $this->assertSame(123456, $project->agreed_value_minor);
        app()->setLocale('en');
        $this->actingAs($admin)->get(route('custom-projects.index'))->assertOk()->assertSee('Custom Projects');
        $this->actingAs($admin)->get(route('custom-projects.create', ['client_id' => $client->id]))->assertOk()->assertSee('New project');
        $this->actingAs($admin)->get(route('custom-projects.edit', $project))->assertOk()->assertSee('Edit project');
        $this->actingAs($admin)->get(route('clients.show', $client))->assertOk()->assertSee('Website');
        $this->actingAs($admin)->get(route('clients.show', ['client' => $client->id, 'finance_advanced' => 1]))
            ->assertOk()->assertSee('sec-record-payment');
        $this->actingAs($admin)->put(route('custom-projects.update', $project), [
            'name' => 'Website',
            'agreed_value_jod' => $project->agreedValueFormatted(),
            'status' => 'cancelled',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('custom-projects.archive', $project))->assertRedirect();
        $this->assertNotNull($project->fresh()->archived_at);
        $this->assertDatabaseCount('custom_projects', 1);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_staff_and_guests_cannot_manage_projects_or_open_advanced_accounting(): void
    {
        $client = $this->client();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->get(route('custom-projects.index'))->assertRedirect();
        // P2 / FROZEN D-14: staff views custom projects read-only but cannot manage them.
        $this->actingAs($staff)->get(route('custom-projects.index'))->assertOk();
        $this->actingAs($staff)->post(route('custom-projects.store'), [
            'client_id' => $client->id, 'name' => 'Denied', 'status' => 'planned',
        ])->assertForbidden();
        $this->actingAs($staff)->get(route('finance.index', ['section' => 'advanced']))->assertForbidden();
        $this->actingAs($staff)->get(route('clients.show', ['client' => $client->id, 'finance_advanced' => 1]))
            ->assertDontSee('id="finance-advanced-tools"', false);
        $this->assertDatabaseCount('custom_projects', 0);
    }

    public function test_project_invoice_uses_existing_engine_and_does_not_change_saas_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->client();
        $otherClient = $this->client('Other Client');
        $project = CustomProject::create([
            'client_id' => $client->id, 'created_by' => $admin->id,
            'name' => 'Integration', 'agreed_value_minor' => 75000, 'status' => 'active',
        ]);

        $before = app(SaasMetricsService::class)->dashboard(ReportingPeriod::fromRequest([]));
        $payload = [
            'custom_project_id' => $project->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'lines' => [[
                'line_type' => 'one_time_service', 'description' => 'Integration',
                'quantity' => 1, 'unit_price_jod' => '75.000',
            ]],
        ];

        $this->actingAs($admin)->post(route('clients.one-time-invoices.store', $otherClient), $payload)
            ->assertSessionHasErrors('custom_project_id');
        $this->assertDatabaseCount('invoices', 0);
        $this->actingAs($admin)->post(route('clients.one-time-invoices.store', $client), $payload)
            ->assertSessionHas('success');

        $invoice = Invoice::firstOrFail();
        $this->assertSame($project->id, $invoice->custom_project_id);
        $this->assertNull($invoice->subscription_id);
        $this->assertSame(1, $project->invoices()->count());
        app()->setLocale('en');
        $this->actingAs($admin)->get(route('custom-projects.show', $project))
            ->assertOk()->assertSee('Project invoices')->assertSee($invoice->invoice_number);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscription_metric_events', 0);
        $after = app(SaasMetricsService::class)->dashboard(ReportingPeriod::fromRequest([]));
        $this->assertSame($before['ending_mrr_minor'], $after['ending_mrr_minor']);
        $this->assertSame($before['ending_arr_minor'], $after['ending_arr_minor']);
        foreach (['expansion_arr_minor', 'contraction_arr_minor', 'churn_arr_minor'] as $key) {
            $this->assertSame($before['movements'][$key], $after['movements'][$key]);
        }
    }
}
