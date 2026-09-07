<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('صباح الخير')
            ->assertSee('صافي نتيجة الشهر')
            ->assertSee('تحصيلات اليوم')
            ->assertSee('استيراد CSV');
    }

    public function test_authenticated_user_can_view_client_detail_with_tabs(): void
    {
        $user = User::factory()->create();

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'مطعم الأهرام',
            'phone' => '0798887766',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'status' => 'prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('clients.show', $clientId))
            ->assertOk()
            ->assertSee('مطعم الأهرام')
            ->assertSee('نظرة عامة والخط الزمني')
            ->assertSee('سجل المواعيد')
            ->assertSee('سجل المتابعات الدورية')
            ->assertSee('العروض التجارية المقدمة');
    }

    public function test_authenticated_user_can_view_import_and_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('clients.import'))
            ->assertOk()
            ->assertSee('استيراد جهات الاتصال عبر CSV');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('التنبيهات والإشعارات');
    }
}
