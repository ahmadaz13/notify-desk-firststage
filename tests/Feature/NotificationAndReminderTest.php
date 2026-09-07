<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationAndReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_command_generates_notifications(): void
    {
        $user = User::factory()->create();

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'مركز الأمل الطبي',
            'phone' => '0793332211',
            'city_area' => 'عمان',
            'business_category' => 'طبي',
            'lead_source' => 'Direct',
            'primary_owner_id' => $user->id,
            'status' => 'prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Appointment in 2 hours
        $aptTime = now()->addHours(2)->format('H:i');
        DB::table('appointments')->insert([
            'client_id' => $clientId,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => $aptTime,
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
            'location' => 'المركز الرئيسي',
            'notes' => 'فحص تجريبي',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Subscription with overdue schedule
        $subId = DB::table('subscriptions')->insertGetId([
            'client_id' => $clientId,
            'user_id' => $user->id,
            'billing_type' => 'monthly',
            'total_price' => 300,
            'start_date' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'subscription_id' => $subId,
            'amount_due' => 150,
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'due',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run reminders artisan command
        $this->artisan('reminders:send')
            ->assertSuccessful();

        // Check appointment notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'appointment_reminder',
            'source_type' => 'appointment',
        ]);

        // Check overdue payment notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'payment_overdue',
            'source_type' => 'payment_schedule',
        ]);
    }

    public function test_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $user->id,
            'type' => 'appointment_reminder',
            'title' => 'تذكير بموعد',
            'message' => 'لديك موعد تجريبي',
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('notifications.read', $notificationId));
        $response->assertStatus(302);

        $this->assertNotNull(
            DB::table('notifications')->where('id', $notificationId)->value('read_at')
        );
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        DB::table('notifications')->insert([
            [
                'user_id' => $user->id,
                'type' => 'reminder_1',
                'title' => 'إشعار 1',
                'message' => 'محتوى 1',
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'type' => 'reminder_2',
                'title' => 'إشعار 2',
                'message' => 'محتوى 2',
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->post(route('notifications.read-all'));
        $response->assertStatus(302);

        $unreadCount = DB::table('notifications')->where('user_id', $user->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unreadCount);
    }
}
