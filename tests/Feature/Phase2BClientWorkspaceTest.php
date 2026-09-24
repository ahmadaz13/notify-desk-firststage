<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientReviewItem;
use App\Models\User;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2BClientWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_detail_uses_workspace_shell_and_presentation_view_models(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->client([
            'business_name' => 'شركة الإشعارات التجارية ذات الاسم الطويل جداً لفرع خلدا',
            'stage' => ClientLifecycle::DECISION_PENDING,
        ]);
        $client->contacts()->create([
            'name' => 'ليان',
            'role' => 'مالكة',
            'primary_phone' => '0791001001',
            'is_primary' => true,
        ]);
        DB::table('activity_logs')->insert([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'type' => 'client_created',
            'description' => 'تم إنشاء العميل',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:30',
            'appointment_type' => 'physical_visit',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($admin)->get(route('clients.show', $client));

        // P10 (§19): one card stack (header, next step, systems, activity, details) replaces the tabbed sections.
        $response->assertOk()
            ->assertViewHas('workspace')
            ->assertSee('data-client-workspace', false)
            ->assertSee('data-client-header', false)
            ->assertSee('data-client-state', false)
            ->assertSee('data-client-activity', false)
            ->assertSee('data-client-details', false)
            ->assertDontSee('CLIENT_DETAIL_01')
            ->assertSee('شركة الإشعارات التجارية ذات الاسم الطويل جداً لفرع خلدا')
            ->assertSee('بانتظار القرار')
            ->assertSee('ليان')
            ->assertSee('تم إنشاء العميل');
    }

    public function test_contact_outcome_ui_uses_canonical_phase_2b_choices_without_close_checkbox(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->client();

        $response = $this->actingAs($admin)->get(route('clients.show', $client));

        // P10: the Record call sheet is the single call-outcome surface (the legacy CONTACT_01 panel is retired).
        $html = $response->assertOk()->getContent();
        $sheet = str($html)->betweenFirst('id="modal-record-call"', '</form>')->toString();
        foreach (['appointment', 'callback_later', 'no_answer_busy', 'wrong_invalid', 'not_interested'] as $outcome) {
            $this->assertStringContainsString('value="'.$outcome.'"', $sheet);
        }
        $this->assertStringContainsString(route('clients.contact-attempts.store', $client->id), $sheet);
        $this->assertStringNotContainsString('name="close_client"', $html);
        $this->assertStringNotContainsString('CONTACT_01', $html);
    }

    public function test_not_interested_requires_note_and_wrong_invalid_preserves_review_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $notInterested = $this->client(['phone' => '0792002001']);
        $wrongInvalid = $this->client(['phone' => '0792002002']);

        $this->actingAs($admin)
            ->from(route('clients.show', $notInterested))
            ->post(route('clients.contact-attempts.store', $notInterested), [
                'method' => 'phone',
                'result' => 'not_interested',
            ])
            ->assertRedirect(route('clients.show', $notInterested))
            ->assertSessionHasErrors('note');

        $this->actingAs($admin)
            ->post(route('clients.contact-attempts.store', $wrongInvalid), [
                'method' => 'phone',
                'result' => 'wrong_invalid',
                'note' => 'Phone disconnected',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_review_items', [
            'client_id' => $wrongInvalid->id,
            'type' => ClientReviewItem::TYPE_WRONG_INVALID,
            'status' => ClientReviewItem::STATUS_PENDING,
        ]);
        $this->assertNotSame(ClientLifecycle::CLOSED, $wrongInvalid->fresh()->stage);
    }

    public function test_workspace_preserves_legacy_action_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->client();
        Appointment::create([
            'client_id' => $client->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00',
            'appointment_type' => 'installation',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($admin)->get(route('clients.show', $client));

        // P10: every workflow sheet still posts to the existing controllers (§13).
        $response->assertOk()
            ->assertSee(route('clients.contact-attempts.store', $client->id), false)
            ->assertSee(route('clients.installations.schedule', $client), false)
            ->assertSee(route('clients.installations.complete', $client), false)
            ->assertSee(route('appointments.store'), false)
            ->assertSee(route('clients.payments.normal.store', $client), false)
            ->assertSee(route('clients.guided-subscription.store', $client), false)
            ->assertSee(route('clients.close', $client->id), false);

        // Retired from the workspace: legacy collections form, offers (not V1) and one-time invoices (Custom Projects, D-14).
        $response->assertDontSee(route('clients.collections.payments.store', $client), false)
            ->assertDontSee(route('clients.offers.store', $client), false)
            ->assertDontSee(route('clients.one-time-invoices.store', $client), false);
    }

    private function client(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Phase 2B Client',
            'phone' => '0791234567',
            'business_phone' => '0791234567',
            'contact_person' => 'Nader',
            'city_area' => 'Amman',
            'city' => 'Amman',
            'area' => 'Khalda',
            'business_category' => 'Retail',
            'business_type' => 'Retail',
            'lead_source' => 'Direct',
            'number_of_branches' => 1,
            'status' => 'prospect',
            'stage' => ClientLifecycle::PROSPECT,
        ], $overrides));
    }
}
