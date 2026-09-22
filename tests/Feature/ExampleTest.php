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
            ->assertSee(__('notify.today.tab_today'))
            ->assertSee(__('notify.work.my_work'))
            ->assertSee(__('notify.today.all_clear'))
            ->assertDontSee('صافي نتيجة الشهر');
    }

    public function test_authenticated_user_can_view_client_detail_workspace(): void
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
            ->assertSee('notify-client-workspace', false)
            ->assertSee('نظرة عامة')
            ->assertSee('جهات الاتصال')
            ->assertSee('الخط الزمني')
            ->assertSee('سجل المواعيد')
            ->assertSee('سجل المتابعات الدورية')
            ->assertSee('الاشتراك والفوترة');
    }

    public function test_authenticated_user_can_view_import_and_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('clients.import'))
            ->assertOk()
            ->assertSee('استيراد الفرص عبر ملف CSV');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('التنبيهات والإشعارات');
    }
}
