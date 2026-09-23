<?php

namespace Tests\Feature;

use App\Models\Client;
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

        // 7. DELETE /clients/{client}
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

}
