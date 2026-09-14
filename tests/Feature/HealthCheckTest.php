<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'database' => 'connected',
                'cache' => 'working',
            ])
            ->assertJsonStructure([
                'status',
                'database',
                'cache',
                'timestamp',
            ]);
    }

    public function test_health_endpoint_reports_database_status(): void
    {
        // Normal DB operation reports connected
        $response = $this->getJson(route('health'));
        $response->assertOk()
            ->assertJsonPath('database', 'connected');

        // Test error status when DB throws exception
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $errorResponse = $this->getJson(route('health'));
        $errorResponse->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('database', 'error');
    }

    public function test_health_endpoint_is_rate_limited(): void
    {
        // Verify route has throttle middleware
        $route = app('router')->getRoutes()->getByName('health');
        $this->assertNotNull($route);

        $middlewares = $route->gatherMiddleware();
        $hasThrottle = false;
        foreach ($middlewares as $mw) {
            if (str_starts_with($mw, 'throttle')) {
                $hasThrottle = true;
                break;
            }
        }
        $this->assertTrue($hasThrottle, 'Health check route must be protected by throttle middleware.');

        // Request succeeds
        $response = $this->getJson(route('health'));
        $response->assertOk();
    }
}
