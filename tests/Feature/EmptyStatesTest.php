<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmptyStatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_clients_list_shows_helpful_message(): void
    {
        $admin = User::factory()->create(['role' => 'founder']);

        // P10 (§33): each segment has its own empty state; Owners land on Subscribers.
        $response = $this->actingAs($admin)->get(route('clients.index'));

        $response->assertOk();
        $response->assertSee('لا يوجد مشتركون فعّالون');

        $prospects = $this->actingAs($admin)->get(route('clients.index', ['view' => 'prospects']));
        $prospects->assertSee('لا يوجد عملاء محتملون بعد');
        $prospects->assertSee(route('clients.create'), false);
    }

    public function test_empty_dashboard_appointments_shows_helpful_message(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $admin = User::factory()->create(['role' => 'founder']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        // P11 calm empty state.
        $response->assertSee('لا توجد مهام مستحقة الآن');
        $response->assertSee(__('notify.today_board.empty_message'));
        $response->assertDontSee('لم تسجل مصاريف اليوم بعد');
    }
}
