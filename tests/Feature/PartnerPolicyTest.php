<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_partner_user_cannot_view_or_update_any_clients(): void
    {
        $partner = Partner::create(['company_name' => 'الشريك أ', 'email' => 'partner_a@test.local']);
        $partnerUser = User::factory()->create(['role' => 'partner', 'partner_id' => $partner->id]);
        $client = Client::create([
            'business_name' => 'عميل إحالة تاريخي',
            'phone' => '0791110000',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'partner',
            'partner_id' => $partner->id,
            'status' => 'prospect',
        ]);

        $this->actingAs($partnerUser)->get(route('clients.show', $client->id))->assertForbidden();
        $this->actingAs($partnerUser)->put(route('clients.update', $client->id), [
            'business_name' => 'تعديل غير مصرح',
            'phone' => '0791110000',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'partner',
        ])->assertForbidden();
    }

    public function test_internal_staff_can_view_partner_attributed_clients_without_partner_scope(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $partner = Partner::create(['company_name' => 'شريك إحالة', 'email' => 'referrer@partner.local']);
        $partnerClient = Client::create([
            'business_name' => 'عميل إحالة',
            'phone' => '0794440000',
            'city_area' => 'الزرقاء',
            'business_category' => 'خدمات',
            'lead_source' => 'partner',
            'partner_id' => $partner->id,
            'status' => 'prospect',
        ]);
        $directClient = Client::create([
            'business_name' => 'عميل مباشر',
            'phone' => '0795550000',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'direct',
            'status' => 'prospect',
        ]);

        $this->actingAs($staff)->get(route('clients.show', $partnerClient->id))->assertOk();
        $this->actingAs($staff)->get(route('clients.show', $directClient->id))->assertOk();

        $index = $this->actingAs($staff)->get(route('clients.index'));
        $index->assertOk();
        $index->assertSee('عميل إحالة');
        $index->assertSee('عميل مباشر');
    }

    public function test_staff_can_perform_normal_operational_update(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $client = Client::create([
            'business_name' => 'عميل تشغيل',
            'phone' => '0793330000',
            'city_area' => 'عمان',
            'business_category' => 'تقنية',
            'lead_source' => 'direct',
            'status' => 'prospect',
        ]);

        $this->actingAs($staff)->put(route('clients.update', $client->id), [
            'business_name' => 'عميل تشغيل محدث',
            'phone' => '0793330000',
            'city_area' => 'عمان - الجبيهة',
            'business_category' => 'تقنية',
            'lead_source' => 'direct',
        ])->assertRedirect(route('clients.show', $client->id));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'business_name' => 'عميل تشغيل محدث',
        ]);
    }
}
