<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\OperationalQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendClosureG3IdentityPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_perform_normal_crm_work_and_use_operational_queues(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('clients.store'), [
            'business_name' => 'G3 Staff Prospect',
            'phone' => '0791112233',
            'primary_phone_type' => 'business',
            'city_area' => 'Amman',
            'business_category' => 'Retail',
            'lead_source' => 'Direct',
        ])->assertRedirect();

        $client = Client::where('business_name', 'G3 Staff Prospect')->firstOrFail();
        $this->assertSame($staff->id, $client->primary_owner_id);

        $queues = app(OperationalQueueService::class)->queues($staff);
        $this->assertTrue($queues['active_contact_queue']->contains('id', $client->id));
    }

    public function test_staff_cannot_bypass_high_risk_financial_or_system_permissions(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('commercial-catalog.products.store'), [
                'name_ar' => 'نظام موظف',
                'name_en' => 'Staff System',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('financial-accounts.store'), [
                'code' => 'staff_cash',
                'name_ar' => 'صندوق موظف',
                'type' => 'cash',
            ])
            ->assertForbidden();

        $this->actingAs($staff)->get(route('settings.index'))->assertForbidden();
    }

    public function test_admin_keeps_approved_high_risk_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('commercial-catalog.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk();
    }

}
