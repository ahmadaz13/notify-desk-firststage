<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use App\Services\ClientPartnerAttributionService;
use App\Support\ClientLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientIntakePhase03Test extends TestCase
{
    use RefreshDatabase;

    public function test_add_client_renders_only_the_six_normal_intake_fields(): void
    {
        $response = $this->actingAs($this->staff())->withSession(['locale' => 'en'])->get(route('clients.create'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(6, substr_count($html, 'data-intake-field='));
        foreach (['business_name', 'business_category', 'contact_person', 'phone', 'city_area', 'lead_source'] as $field) {
            $response->assertSee('data-intake-field="'.$field.'"', false);
        }

        foreach (['business_phone', 'primary_contact_role', 'source_reference', 'city', 'number_of_branches', 'notes', 'instagram', 'website', 'maps_url', 'location_text', 'partner_commission_percentage', 'partner_attribution_notes', 'stage', 'status', 'primary_owner_id'] as $field) {
            $response->assertDontSee('name="'.$field.'"', false);
        }

        $response->assertSee("x-show=\"leadSource === 'Partner'\"", false)
            ->assertSee('name="partner_id"', false);
    }

    public function test_six_field_submission_creates_only_a_prospect_with_server_defaults(): void
    {
        $staff = $this->staff();
        $otherOwner = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($staff)->post(route('clients.store'), [
            'business_name' => 'Phase 03 Client',
            'business_category' => 'Retail',
            'phone' => '0791112233',
            'city_area' => 'Jabal Amman',
            'lead_source' => 'Direct Prospecting',
            'stage' => ClientLifecycle::SUBSCRIBER,
            'status' => 'archived',
            'primary_owner_id' => $otherOwner->id,
        ]);

        $client = Client::where('business_name', 'Phase 03 Client')->firstOrFail();
        $response->assertRedirect(route('clients.show', $client));
        $this->assertSame('0791112233', $client->business_phone);
        $this->assertSame('Retail', $client->business_type);
        $this->assertSame('Jabal Amman', $client->city);
        $this->assertSame(1, $client->number_of_branches);
        $this->assertSame(ClientLifecycle::PROSPECT, $client->stage);
        $this->assertSame('prospect', $client->status);
        $this->assertSame($staff->id, $client->primary_owner_id);
        $this->assertDatabaseCount('client_contacts', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('installations', 0);
    }

    public function test_intake_validation_is_human_readable_in_english_and_arabic_and_keeps_input(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->from(route('clients.create'))
            ->post(route('clients.store'), ['business_name' => 'Kept value'])
            ->assertRedirect(route('clients.create'))
            ->assertSessionHasInput('business_name', 'Kept value')
            ->assertSessionHasErrors([
                'business_category' => 'Choose the business type.',
                'phone' => "Enter the client's phone number.",
                'city_area' => 'Enter the area.',
                'lead_source' => 'Choose the lead source.',
            ]);

        $this->actingAs($staff)
            ->withSession(['locale' => 'ar'])
            ->post(route('clients.store'), [])
            ->assertSessionHasErrors([
                'business_name' => 'أدخل اسم النشاط.',
                'business_category' => 'اختر نوع النشاط.',
                'phone' => 'أدخل رقم هاتف العميل.',
                'city_area' => 'أدخل المنطقة.',
                'lead_source' => 'اختر مصدر العميل.',
            ]);
    }

    public function test_partner_source_requires_an_active_partner_and_staff_cannot_override_commission(): void
    {
        $staff = $this->staff();
        $partner = Partner::create([
            'company_name' => 'Phase 03 Partner',
            'email' => 'phase03@example.test',
            'status' => 'active',
            'default_commission_bps' => 825,
        ]);
        $payload = [
            'business_name' => 'Partner Client',
            'business_category' => 'Services',
            'phone' => '0792223344',
            'city_area' => 'Amman',
            'lead_source' => 'Partner',
        ];

        $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->post(route('clients.store'), $payload)
            ->assertSessionHasErrors(['partner_id' => 'Select the referring partner.']);

        $this->actingAs($staff)
            ->withSession(['locale' => 'en'])
            ->post(route('clients.store'), $payload + ['partner_id' => 999999])
            ->assertSessionHasErrors(['partner_id' => 'Select an active referring partner.']);

        $this->actingAs($staff)->post(route('clients.store'), $payload + [
            'partner_id' => $partner->id,
            'partner_commission_percentage' => '99.99',
            'partner_attribution_notes' => 'Staff override attempt',
        ])->assertRedirect();

        $client = Client::where('business_name', 'Partner Client')->firstOrFail();
        $this->assertSame($partner->id, $client->partner_id);
        $this->assertSame(825, $client->partnerAttribution->commission_bps_snapshot);
        $this->assertNull($client->partnerAttribution->notes);
    }

    public function test_client_index_has_four_operational_columns_mobile_actions_and_preserved_filters(): void
    {
        $staff = $this->staff();
        $client = Client::create([
            'business_name' => 'Searchable Business',
            'phone' => '0793334455',
            'business_phone' => '065551111',
            'city_area' => 'Amman',
            'business_category' => 'Retail',
            'business_type' => 'Retail',
            'lead_source' => 'Direct Prospecting',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
            'primary_owner_id' => $staff->id,
        ]);
        $client->contacts()->create([
            'name' => 'Preferred Contact',
            'role' => 'owner',
            'primary_phone' => '0793334455',
            'whatsapp_number' => '0793334455',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($staff)->withSession(['locale' => 'en'])->get(route('clients.index', [
            'q' => 'Searchable',
            'status' => ClientLifecycle::PROSPECT,
        ]));

        $response->assertOk()
            ->assertSeeInOrder(['Business', 'Primary Contact', 'Stage', 'Next Action'])
            ->assertSee('Preferred Contact')
            ->assertSee('tel:0793334455', false)
            ->assertSee('https://wa.me/962793334455', false)
            ->assertSee(route('clients.show', $client), false)
            ->assertDontSee('Responsible')
            ->assertDontSee('Last Activity');
        $this->assertSame(4, substr_count($response->getContent(), '<th>'));

        $this->actingAs($staff)
            ->get(route('clients.index', ['q' => 'no-match']))
            ->assertOk()
            ->assertDontSee('Searchable Business');
    }

    public function test_edit_keeps_advanced_history_and_blocks_raw_workflow_and_staff_commission_changes(): void
    {
        $staff = $this->staff();
        $partner = Partner::create([
            'company_name' => 'Historical Partner',
            'email' => 'historical-phase03@example.test',
            'status' => 'active',
            'default_commission_bps' => 700,
        ]);
        $client = Client::create([
            'business_name' => 'Historical Client',
            'phone' => '0794445566',
            'business_phone' => '065552222',
            'city_area' => 'Sweifieh',
            'city' => 'Amman',
            'area' => 'Sweifieh',
            'business_category' => 'Hospitality',
            'business_type' => 'Legacy Hotel',
            'lead_source' => 'Partner',
            'source_reference' => 'Old campaign',
            'number_of_branches' => 3,
            'notes' => 'Keep this history',
            'stage' => ClientLifecycle::CONTACTING,
            'status' => 'prospect',
            'primary_owner_id' => $staff->id,
        ]);
        app(ClientPartnerAttributionService::class)->assign($client, $partner, 700, 'Historical agreement', $staff->id);

        $this->actingAs($staff)->withSession(['locale' => 'en'])->get(route('clients.edit', $client))
            ->assertOk()
            ->assertSee('<details', false)
            ->assertSee('name="source_reference"', false)
            ->assertDontSee('name="stage"', false)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="partner_commission_percentage"', false)
            ->assertDontSee('name="partner_attribution_notes"', false);

        $this->actingAs($staff)->put(route('clients.update', $client), [
            'business_name' => 'Historical Client Updated',
            'business_category' => 'Hospitality',
            'contact_person' => 'Owner Name',
            'phone' => '0794445566',
            'business_phone' => '065552222',
            'city_area' => 'Sweifieh',
            'lead_source' => 'Partner',
            'partner_id' => $partner->id,
            'source_reference' => 'Old campaign',
            'number_of_branches' => 3,
            'city' => 'Amman',
            'area' => 'Sweifieh',
            'notes' => 'Keep this history',
            'stage' => ClientLifecycle::SUBSCRIBER,
            'status' => 'archived',
            'partner_commission_percentage' => '99.99',
            'partner_attribution_notes' => 'Override attempt',
        ])->assertRedirect(route('clients.show', $client));

        $client->refresh()->load('partnerAttribution');
        $this->assertSame('Historical Client Updated', $client->business_name);
        $this->assertSame('Legacy Hotel', $client->business_type);
        $this->assertSame('Old campaign', $client->source_reference);
        $this->assertSame('Keep this history', $client->notes);
        $this->assertSame(ClientLifecycle::CONTACTING, $client->stage);
        $this->assertSame('prospect', $client->status);
        $this->assertSame(700, $client->partnerAttribution->commission_bps_snapshot);
        $this->assertSame('Historical agreement', $client->partnerAttribution->notes);
    }

    public function test_client_intake_routes_keep_existing_auth_and_policy_boundaries(): void
    {
        $this->get(route('clients.create'))->assertRedirect(route('login'));

        $inactive = User::factory()->create(['role' => 'staff', 'is_active' => false]);
        $this->actingAs($inactive)->get(route('clients.create'))->assertForbidden();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff', 'is_active' => true]);
    }
}
