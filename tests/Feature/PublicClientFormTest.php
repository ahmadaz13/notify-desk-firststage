<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ConflictResolutionRequest;
use App\Models\Partner;
use App\Models\User;
use App\Notifications\ConflictDetected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicClientFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_client_creation_via_delegate_link(): void
    {
        $partner = Partner::create([
            'company_name' => 'رواد النجاح',
            'email' => 'success@pioneers.local',
        ]);

        $data = [
            'phone' => '079 555 6677',
            'name' => 'مطعم الياسمين',
            'area' => 'عمان - الجاردنز',
            'source' => 'زيارة ميدانية',
        ];

        $response = $this->post(route('public.client.store', $partner->public_uuid), $data);

        $response->assertRedirect(route('public.client.create', $partner->public_uuid));
        $response->assertSessionHas('success', 'تم إضافة العميل بنجاح');

        $client = Client::where('business_name', 'مطعم الياسمين')->first();
        $this->assertNotNull($client);
        $this->assertEquals($partner->id, $client->partner_id);
        $this->assertEquals('962795556677', $client->phone);

        // Check activity log
        $this->assertDatabaseHas('activity_logs', [
            'client_id' => $client->id,
            'type' => 'client_created_by_delegate',
        ]);
    }

    public function test_duplicate_client_conflict_detection_creates_resolution_request_and_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        // Existing client in database
        $existingClient = Client::create([
            'business_name' => 'صيدلية الشفاء',
            'phone' => '962791112233',
            'city_area' => 'الشميساني',
            'business_category' => 'صيدلية',
            'lead_source' => 'Direct',
            'status' => 'prospect',
        ]);

        $partner = Partner::create([
            'company_name' => 'شريك التسويق الذكي',
            'email' => 'smart@partner.local',
        ]);

        // Delegate submits with existing phone number (with spaces or 0 prefix)
        $data = [
            'phone' => '079 111 2233',
            'name' => 'صيدلية الشفاء الجديدة',
            'area' => 'الشميساني الغربي',
            'source' => 'مندوب فرعي',
        ];

        $response = $this->post(route('public.client.store', $partner->public_uuid), $data);

        $response->assertRedirect(route('public.client.create', $partner->public_uuid));
        $response->assertSessionHas('info', 'تم استلام طلبك، سيتم مراجعته من قبل الإدارة');

        // Verify ConflictResolutionRequest was created
        $conflict = ConflictResolutionRequest::where('client_id', $existingClient->id)->first();
        $this->assertNotNull($conflict);
        $this->assertEquals($partner->id, $conflict->partner_id);
        $this->assertEquals('962791112233', $conflict->submitted_phone);
        $this->assertEquals('صيدلية الشفاء الجديدة', $conflict->submitted_name);
        $this->assertEquals('pending', $conflict->status);

        // Verify Admin notification was sent
        Notification::assertSentTo($admin, ConflictDetected::class);
    }
}
