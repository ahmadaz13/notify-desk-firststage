<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutoTransferClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_transfer_enabled_moves_client_to_partner(): void
    {
        $partnerA = Partner::create([
            'company_name' => 'الشريك أ',
            'email' => 'partner_a@example.com',
            'phone' => '0791111111',
        ]);

        $partnerB = Partner::create([
            'company_name' => 'الشريك ب',
            'email' => 'partner_b@example.com',
            'phone' => '0792222222',
        ]);

        $client = Client::create([
            'partner_id' => $partnerA->id,
            'business_name' => 'مطعم النجمة',
            'contact_person' => 'خالد',
            'phone' => '962791234567',
            'city_area' => 'عمان - الجاردنز',
            'business_category' => 'مطاعم',
            'lead_source' => 'مندوب: ' . $partnerA->company_name,
            'status' => 'prospect',
        ]);

        // Enable auto-transfer setting
        Setting::set('allow_auto_transfer_clients', '1');

        $response = $this->post(route('public.client.store', $partnerB->public_uuid), [
            'name' => 'مطعم النجمة - فرع جديد',
            'phone' => '0791234567',
            'area' => 'عمان - الجاردنز',
            'source' => 'مندوب ميداني',
        ]);

        $response->assertRedirect(route('public.client.create', $partnerB->public_uuid));
        $response->assertSessionHas('success', 'تم ربط العميل بالشريك تلقائياً');

        // Verify client transferred to partner B
        $client->refresh();
        $this->assertEquals($partnerB->id, $client->partner_id);

        // Verify activity log recorded
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_transferred',
        ]);

        // Verify NO conflict resolution request was created
        $this->assertEquals(0, ConflictResolutionRequest::count());
    }

    public function test_auto_transfer_disabled_creates_conflict_request(): void
    {
        $partnerA = Partner::create([
            'company_name' => 'الشريك أ',
            'email' => 'partner_a@example.com',
            'phone' => '0791111111',
        ]);

        $partnerB = Partner::create([
            'company_name' => 'الشريك ب',
            'email' => 'partner_b@example.com',
            'phone' => '0792222222',
        ]);

        $client = Client::create([
            'partner_id' => $partnerA->id,
            'business_name' => 'مخبز السلام',
            'contact_person' => 'أحمد',
            'phone' => '962799887766',
            'city_area' => 'عمان - الصويفية',
            'business_category' => 'مخابز',
            'lead_source' => 'مندوب: ' . $partnerA->company_name,
            'status' => 'prospect',
        ]);

        // Disable auto-transfer setting
        Setting::set('allow_auto_transfer_clients', '0');

        $response = $this->post(route('public.client.store', $partnerB->public_uuid), [
            'name' => 'مخبز السلام',
            'phone' => '0799887766',
            'area' => 'عمان - الصويفية',
            'source' => 'زيارة ميدانية',
        ]);

        $response->assertRedirect(route('public.client.create', $partnerB->public_uuid));
        $response->assertSessionHas('info');

        // Verify client remained with partner A
        $client->refresh();
        $this->assertEquals($partnerA->id, $client->partner_id);

        // Verify conflict request was created
        $this->assertEquals(1, ConflictResolutionRequest::count());
        $this->assertDatabaseHas('conflict_resolution_requests', [
            'partner_id' => $partnerB->id,
            'client_id' => $client->id,
            'status' => 'pending',
        ]);
    }
}
