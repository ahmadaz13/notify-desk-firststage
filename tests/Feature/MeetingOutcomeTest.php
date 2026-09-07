<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeetingOutcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_meeting_outcome_and_complete_appointment(): void
    {
        $user = User::factory()->create();

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'مطعم النجوم',
            'phone' => '0791112233',
            'city_area' => 'عمان',
            'business_category' => 'مطاعم',
            'lead_source' => 'Direct',
            'primary_owner_id' => $user->id,
            'status' => 'prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appointmentId = DB::table('appointments')->insertGetId([
            'client_id' => $clientId,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
            'location' => 'عمان',
            'notes' => 'عرض أولي',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('appointments.outcome.store', $appointmentId), [
            'attendance_status' => 'attended',
            'meeting_date_time' => now()->toDateTimeString(),
            'meeting_type' => 'physical_visit',
            'demo_performed' => 1,
            'interest_level' => 'high',
            'customer_needs' => 'يحتاج إشعارات واتساب وجداول تحصيل',
            'main_objections' => 'السعر بحاجة لتخفيض بسيط',
            'price_discussed' => 1,
            'package_discussed' => 'الباقة الشاملة',
            'customer_response' => 'موافق مبدئياً بانتظار العرض النهائي',
            'next_action' => 'إرسال العرض التجاري',
            'next_follow_up_date' => now()->addDays(2)->toDateString(),
            'meeting_notes' => 'لقاء ممتاز مع صاحب المحل',
        ]);

        $response->assertRedirect(route('clients.show', $clientId));

        // Assert appointment is completed
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'completed',
        ]);

        // Assert meeting outcome is recorded
        $this->assertDatabaseHas('meeting_outcomes', [
            'appointment_id' => $appointmentId,
            'client_id' => $clientId,
            'interest_level' => 'high',
            'demo_performed' => 1,
            'next_action' => 'إرسال العرض التجاري',
        ]);

        // Assert follow-up record created
        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $clientId,
            'next_action' => 'إرسال العرض التجاري',
            'next_follow_up_date' => now()->addDays(2)->toDateString(),
        ]);

        // Assert activity log added
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $clientId,
            'type' => 'meeting_outcome_recorded',
        ]);
    }
}
