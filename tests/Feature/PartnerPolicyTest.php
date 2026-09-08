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

    public function test_partner_cannot_view_or_update_other_clients(): void
    {
        $partnerA = Partner::create([
            'company_name' => 'الشريك أ',
            'email' => 'partner_a@test.local',
        ]);

        $partnerB = Partner::create([
            'company_name' => 'الشريك ب',
            'email' => 'partner_b@test.local',
        ]);

        $partnerUserA = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partnerA->id,
        ]);

        // Client belonging to Partner B
        $clientB = Client::create([
            'business_name' => 'عميل الشريك ب',
            'phone' => '0791110000',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'Partner',
            'partner_id' => $partnerB->id,
            'status' => 'prospect',
        ]);

        // Client belonging to Admin (no partner)
        $adminClient = Client::create([
            'business_name' => 'عميل الإدارة المباشر',
            'phone' => '0792220000',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'Direct',
            'partner_id' => null,
            'status' => 'subscriber',
        ]);

        // Partner A cannot view Client B
        $responseB = $this->actingAs($partnerUserA)->get(route('clients.show', $clientB->id));
        $responseB->assertStatus(403);

        // Partner A cannot view Admin's direct client
        $responseAdmin = $this->actingAs($partnerUserA)->get(route('clients.show', $adminClient->id));
        $responseAdmin->assertStatus(403);

        // Partner A cannot update Client B
        $responseUpdate = $this->actingAs($partnerUserA)->put(route('clients.update', $clientB->id), [
            'business_name' => 'تعديل غير مصرح',
            'phone' => '0791110000',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'Partner',
        ]);
        $responseUpdate->assertStatus(403);
    }

    public function test_partner_can_view_and_update_own_clients(): void
    {
        $partner = Partner::create([
            'company_name' => 'شريك معتمد',
            'email' => 'approved@partner.local',
        ]);

        $partnerUser = User::factory()->create([
            'role' => 'partner',
            'partner_id' => $partner->id,
        ]);

        $ownClient = Client::create([
            'business_name' => 'عميل الشريك المعتمد',
            'phone' => '0793330000',
            'city_area' => 'عمان',
            'business_category' => 'تقنية',
            'lead_source' => 'Partner',
            'partner_id' => $partner->id,
            'status' => 'prospect',
        ]);

        // Can view own client
        $response = $this->actingAs($partnerUser)->get(route('clients.show', $ownClient->id));
        $response->assertOk();
        $response->assertSee('عميل الشريك المعتمد');

        // Can update own client
        $updateResponse = $this->actingAs($partnerUser)->put(route('clients.update', $ownClient->id), [
            'business_name' => 'عميل الشريك المعتمد المحدث',
            'phone' => '0793330000',
            'city_area' => 'عمان - الجبيهة',
            'business_category' => 'تقنية',
            'lead_source' => 'Partner',
        ]);

        $updateResponse->assertRedirect(route('clients.show', $ownClient->id));
        $this->assertDatabaseHas('clients', [
            'id' => $ownClient->id,
            'business_name' => 'عميل الشريك المعتمد المحدث',
            'city_area' => 'عمان - الجبيهة',
            'partner_id' => $partner->id,
        ]);
    }

    public function test_admin_can_view_all_clients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $partner = Partner::create([
            'company_name' => 'شريك فرعي',
            'email' => 'sub@partner.local',
        ]);

        $partnerClient = Client::create([
            'business_name' => 'عميل الشريك الفرعي',
            'phone' => '0794440000',
            'city_area' => 'الزرقاء',
            'business_category' => 'خدمات',
            'lead_source' => 'Partner',
            'partner_id' => $partner->id,
            'status' => 'subscriber',
        ]);

        $directClient = Client::create([
            'business_name' => 'عميل الإدارة',
            'phone' => '0795550000',
            'city_area' => 'عمان',
            'business_category' => 'خدمات',
            'lead_source' => 'Direct',
            'partner_id' => null,
            'status' => 'subscriber',
        ]);

        // Admin can view both
        $this->actingAs($admin)->get(route('clients.show', $partnerClient->id))->assertOk();
        $this->actingAs($admin)->get(route('clients.show', $directClient->id))->assertOk();
    }

    public function test_partner_index_only_lists_their_own_clients(): void
    {
        $partnerA = Partner::create(['company_name' => 'أ', 'email' => 'a@p.local']);
        $partnerB = Partner::create(['company_name' => 'ب', 'email' => 'b@p.local']);

        $userA = User::factory()->create(['role' => 'partner', 'partner_id' => $partnerA->id]);

        Client::create([
            'business_name' => 'خاص بالشريك أ',
            'phone' => '0791111111',
            'city_area' => 'عمان',
            'business_category' => 'عام',
            'lead_source' => 'Direct',
            'partner_id' => $partnerA->id,
            'status' => 'prospect',
        ]);

        Client::create([
            'business_name' => 'خاص بالشريك ب',
            'phone' => '0792222222',
            'city_area' => 'عمان',
            'business_category' => 'عام',
            'lead_source' => 'Direct',
            'partner_id' => $partnerB->id,
            'status' => 'prospect',
        ]);

        $response = $this->actingAs($userA)->get(route('clients.index'));
        $response->assertOk();
        $response->assertSee('خاص بالشريك أ');
        $response->assertDontSee('خاص بالشريك ب');
    }
}
