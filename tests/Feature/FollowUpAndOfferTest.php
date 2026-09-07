<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FollowUpAndOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_follow_up_for_client(): void
    {
        $user = User::factory()->create();

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'متجر الأناقة',
            'phone' => '0789998877',
            'city_area' => 'الصويفية',
            'business_category' => 'ملابس',
            'lead_source' => 'Instagram',
            'primary_owner_id' => $user->id,
            'status' => 'prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('clients.follow-ups.store', $clientId), [
            'method' => 'phone_call',
            'reason' => 'متابعة قرار العرض التجاري',
            'result' => 'طلب مهلة حتى نهاية الأسبوع',
            'next_action' => 'معاودة الاتصال يوم الأحد',
            'next_follow_up_date' => now()->addDays(3)->toDateString(),
            'notes' => 'المسؤول مسافر حالياً',
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('follow_ups', [
            'client_id' => $clientId,
            'user_id' => $user->id,
            'reason' => 'متابعة قرار العرض التجاري',
            'next_action' => 'معاودة الاتصال يوم الأحد',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $clientId,
            'type' => 'follow_up_recorded',
        ]);
    }

    public function test_can_create_commercial_offer_for_client(): void
    {
        $user = User::factory()->create();

        $clientId = DB::table('clients')->insertGetId([
            'business_name' => 'عيادة النور',
            'phone' => '0775554433',
            'city_area' => 'الشميساني',
            'business_category' => 'عيادات',
            'lead_source' => 'Referral',
            'primary_owner_id' => $user->id,
            'status' => 'prospect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('clients.offers.store', $clientId), [
            'package' => 'الباقة المتقدمة',
            'billing_period' => 'annual',
            'price' => 600.00,
            'discount' => 50.00,
            'final_agreed_price' => 550.00,
            'offer_date' => now()->toDateString(),
            'decision_deadline' => now()->addDays(7)->toDateString(),
            'notes' => 'يشمل تدريب الفريق مجاناً',
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('commercial_offers', [
            'client_id' => $clientId,
            'user_id' => $user->id,
            'package' => 'الباقة المتقدمة',
            'billing_period' => 'annual',
            'price' => 600.00,
            'discount' => 50.00,
            'final_agreed_price' => 550.00,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $clientId,
            'type' => 'offer_created',
        ]);
    }
}
