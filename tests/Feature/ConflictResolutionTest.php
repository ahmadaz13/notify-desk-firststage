<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_resolves_conflict_with_transfer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $originalPartner = Partner::create([
            'company_name' => 'الشريك القديم',
            'email' => 'old@partner.local',
        ]);

        $newPartner = Partner::create([
            'company_name' => 'الشريك الجديد',
            'email' => 'new@partner.local',
        ]);

        $client = Client::create([
            'business_name' => 'مخبز الأمانة',
            'phone' => '962799990000',
            'city_area' => 'إربد',
            'business_category' => 'مخبز',
            'lead_source' => 'Direct',
            'partner_id' => $originalPartner->id,
            'status' => 'prospect',
        ]);

        $conflict = ConflictResolutionRequest::create([
            'partner_id' => $newPartner->id,
            'client_id' => $client->id,
            'submitted_phone' => '962799990000',
            'submitted_name' => 'مخبز الأمانة الحديث',
            'submitted_area' => 'إربد - شارع الجامعة',
            'submitted_source' => 'المندوب أحمد',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('conflicts.resolve', $conflict->id), [
            'action' => 'transfer',
        ]);

        $response->assertRedirect(route('conflicts.index'));
        $response->assertSessionHas('success');

        // Client partner_id should now be the new partner
        $client->refresh();
        $this->assertEquals($newPartner->id, $client->partner_id);
        $this->assertEquals('مخبز الأمانة الحديث', $client->business_name);
        $this->assertEquals('إربد - شارع الجامعة', $client->city_area);

        // Conflict status resolved_transferred
        $conflict->refresh();
        $this->assertEquals('resolved_transferred', $conflict->status);
        $this->assertEquals($admin->id, $conflict->resolved_by);
        $this->assertNotNull($conflict->resolved_at);

        // Activity log checked
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_transferred',
        ]);
    }

    public function test_admin_resolves_conflict_with_update_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $originalPartner = Partner::create([
            'company_name' => 'شريك أساسي',
            'email' => 'basic@partner.local',
        ]);

        $claimantPartner = Partner::create([
            'company_name' => 'شريك مطالب',
            'email' => 'claimant@partner.local',
        ]);

        $client = Client::create([
            'business_name' => 'محل ورود الربيع',
            'phone' => '962788887777',
            'city_area' => 'عمان',
            'business_category' => 'زهور',
            'lead_source' => 'Instagram',
            'partner_id' => $originalPartner->id,
            'status' => 'subscriber',
        ]);

        $conflict = ConflictResolutionRequest::create([
            'partner_id' => $claimantPartner->id,
            'client_id' => $client->id,
            'submitted_phone' => '962788887777',
            'submitted_name' => 'ورود الربيع الدولي',
            'submitted_area' => 'عمان - تلاع العلي',
            'submitted_source' => 'مندوب ترشيح',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('conflicts.resolve', $conflict->id), [
            'action' => 'update',
        ]);

        $response->assertRedirect(route('conflicts.index'));

        // Client data updated BUT partner_id remains unchanged
        $client->refresh();
        $this->assertEquals($originalPartner->id, $client->partner_id);
        $this->assertEquals('ورود الربيع الدولي', $client->business_name);
        $this->assertEquals('عمان - تلاع العلي', $client->city_area);

        $conflict->refresh();
        $this->assertEquals('resolved_updated', $conflict->status);
    }

    public function test_admin_resolves_conflict_with_reject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $partner = Partner::create([
            'company_name' => 'شريك مرفوض',
            'email' => 'rejected@partner.local',
        ]);

        $client = Client::create([
            'business_name' => 'سوبرماركت المدينة',
            'phone' => '962777776666',
            'city_area' => 'الزرقاء',
            'business_category' => 'تموينات',
            'lead_source' => 'Direct',
            'partner_id' => null,
            'status' => 'prospect',
        ]);

        $conflict = ConflictResolutionRequest::create([
            'partner_id' => $partner->id,
            'client_id' => $client->id,
            'submitted_phone' => '962777776666',
            'submitted_name' => 'سوبرماركت المدينة الزرقاء',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('conflicts.resolve', $conflict->id), [
            'action' => 'reject',
        ]);

        $response->assertRedirect(route('conflicts.index'));

        // Client remains untouched
        $client->refresh();
        $this->assertNull($client->partner_id);
        $this->assertEquals('سوبرماركت المدينة', $client->business_name);

        $conflict->refresh();
        $this->assertEquals('rejected', $conflict->status);
    }
}
