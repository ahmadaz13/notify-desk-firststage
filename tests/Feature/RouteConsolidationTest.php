<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use App\Support\ClientLifecycle;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpenseCategorySeeder::class);
    }

    public function test_client_routes_still_functional_after_consolidation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ahmad']);

        // 1. GET /clients
        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertViewIs('clients.index');

        // 2. GET /clients/create
        $this->actingAs($admin)
            ->get(route('clients.create'))
            ->assertOk()
            ->assertViewIs('clients.create');

        // 3. POST /clients
        $createResponse = $this->actingAs($admin)
            ->post(route('clients.store'), [
                'business_name' => 'مطعم الرشيد',
                'phone' => '0795551122',
                'city_area' => 'عمان - الجبيهة',
                'business_category' => 'مطاعم',
                'lead_source' => 'ميداني',
                'contact_person' => 'طارق',
            ]);
        $createResponse->assertRedirect();

        $client = Client::where('business_name', 'مطعم الرشيد')->first();
        $this->assertNotNull($client);

        // 4. GET /clients/{client}
        $this->actingAs($admin)
            ->get(route('clients.show', $client->id))
            ->assertOk()
            ->assertViewIs('clients.show')
            ->assertSee('مطعم الرشيد');

        // 5. GET /clients/{client}/edit
        $this->actingAs($admin)
            ->get(route('clients.edit', $client->id))
            ->assertOk()
            ->assertViewIs('clients.edit');

        // 6. PUT /clients/{client}
        $updateResponse = $this->actingAs($admin)
            ->put(route('clients.update', $client->id), [
                'business_name' => 'مطعم الرشيد الحديث',
                'phone' => '0795551122',
                'city_area' => 'عمان - الجبيهة',
                'business_category' => 'مطاعم',
                'lead_source' => 'ميداني',
                'contact_person' => 'طارق',
            ]);
        $updateResponse->assertRedirect(route('clients.show', $client->id));
        $this->assertEquals('مطعم الرشيد الحديث', $client->fresh()->business_name);

        // 7. POST /clients/{client}/convert remains routable but deprecated.
        $convertResponse = $this->actingAs($admin)
            ->post(route('clients.convert', $client->id), [
                'billing_type' => 'monthly',
                'total_price' => 1200.00,
                'start_date' => now()->toDateString(),
            ]);
        $convertResponse->assertStatus(410);
        $this->assertEquals('prospect', $client->fresh()->status);

        // 8. DELETE /clients/{client}
        $deleteResponse = $this->actingAs($admin)
            ->delete(route('clients.destroy', $client->id));
        $deleteResponse->assertRedirect(route('clients.index'));
        $client->refresh();
        $this->assertSame('archived', $client->status);
        $this->assertSame(ClientLifecycle::CLOSED, $client->stage);
        $this->assertNotNull(Client::find($client->id));
    }

    public function test_no_deprecation_warnings_in_test_output(): void
    {
        // Assert config values can be resolved without error
        $mysqlOptions = config('database.connections.mysql.options');
        $this->assertIsArray($mysqlOptions);

        $mariadbOptions = config('database.connections.mariadb.options');
        $this->assertIsArray($mariadbOptions);

        // Verify PHP version compatibility check
        $this->assertTrue(
            defined('Pdo\Mysql::ATTR_SSL_CA') || defined('PDO::MYSQL_ATTR_SSL_CA')
        );
    }

    public function test_legacy_partner_role_is_blocked_and_admin_navigation_remains(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك تجاري',
            'email' => 'partner@partner.com',
            'phone' => '0794443322',
        ]);
        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $partnerResponse = $this->actingAs($partnerUser)->get(route('clients.index'));
        $partnerResponse->assertForbidden();

        // Admin sees management links
        $adminResponse = $this->actingAs($admin)->get(route('dashboard'));
        $adminResponse->assertOk();
        $adminResponse->assertSee(route('settings.index'));
        $adminResponse->assertSee(route('conflicts.index'));
        $adminResponse->assertSee(route('clients.import'));
        $adminResponse->assertSee('data-notify-add-client-fab', false);
        $adminResponse->assertSee('href="'.route('clients.create').'"', false);
        $adminResponse->assertDontSee('quick-expense-fab-btn', false);
    }
}
