<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ReferenceOption;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\ReferenceDataService;
use App\Support\ClientLifecycle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4 — Client / contact model (§28.1): business details and primary contact are separate,
 * owner details are optional, and clients.phone ownership is explicit.
 */
class V1P4ClientContactModelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_client_is_created_with_only_the_required_minimum(): void
    {
        $this->actingAs($this->staff)
            ->post(route('clients.store'), $this->minimum())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $client = Client::where('business_name', 'Minimum Cafe')->sole();
        $this->assertSame('0791000001', $client->phone);
        $this->assertSame('business', $client->primary_phone_type);
        $this->assertSame('0791000001', $client->business_phone);
        $this->assertSame(ClientLifecycle::PROSPECT, $client->stage);
        $this->assertSame(0, $client->contacts()->count(), 'Business-owned phone needs no contact row.');
        $this->assertNull($client->contact_person);
    }

    public function test_primary_phone_type_is_required_and_validated_server_side(): void
    {
        $payload = $this->minimum();
        unset($payload['primary_phone_type']);

        $this->actingAs($this->staff)->post(route('clients.store'), $payload)
            ->assertSessionHasErrors('primary_phone_type');

        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum(['primary_phone_type' => 'partner']))
            ->assertSessionHasErrors('primary_phone_type');

        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum(['contact_email' => 'not-an-email']))
            ->assertSessionHasErrors('contact_email');

        $this->assertSame(0, Client::count());
    }

    public function test_required_business_fields_remain_required(): void
    {
        $this->actingAs($this->staff)->post(route('clients.store'), [])
            ->assertSessionHasErrors(['business_name', 'business_category', 'phone', 'primary_phone_type', 'city_area', 'lead_source'])
            ->assertSessionDoesntHaveErrors(['contact_name', 'contact_email', 'business_phone', 'location_text', 'contact_whatsapp']);
    }

    public function test_business_primary_phone_synchronizes_business_phone_and_ignores_a_conflicting_business_phone(): void
    {
        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum([
            'business_phone' => '064000000',
        ]))->assertSessionHasNoErrors();

        $client = Client::sole();
        $this->assertSame('0791000001', $client->phone);
        $this->assertSame('0791000001', $client->business_phone);
    }

    public function test_owner_primary_phone_creates_primary_contact_without_a_name(): void
    {
        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum([
            'primary_phone_type' => 'owner',
        ]))->assertSessionHasNoErrors();

        $client = Client::sole();
        $this->assertSame('owner', $client->primary_phone_type);
        $this->assertSame('0791000001', $client->phone);
        $this->assertNull($client->business_phone);

        $contact = $client->contacts()->sole();
        $this->assertTrue($contact->is_primary);
        $this->assertNull($contact->name);
        $this->assertSame('owner', $contact->role);
        $this->assertSame('0791000001', $contact->primary_phone);
    }

    public function test_manager_primary_phone_keeps_explicit_business_phone_and_merges_contact_details(): void
    {
        $this->actingAs($this->admin)->post(route('clients.store'), $this->minimum([
            'primary_phone_type' => 'manager',
            'business_phone' => '065550000',
            'contact_name' => 'Rana',
            'contact_whatsapp' => '0797777777',
            'contact_email' => 'rana@example.com',
            'location_text' => 'Rainbow St. 12',
        ]))->assertSessionHasNoErrors();

        $client = Client::sole();
        $this->assertSame('manager', $client->primary_phone_type);
        $this->assertSame('065550000', $client->business_phone);
        $this->assertSame('Rainbow St. 12', $client->location_text);
        $this->assertSame('Rana', $client->contact_person, 'Legacy mirror keeps contract snapshot and older views working.');

        $contact = $client->contacts()->sole();
        $this->assertSame('Rana', $contact->name);
        $this->assertSame('manager', $contact->role);
        $this->assertSame('0791000001', $contact->primary_phone);
        $this->assertSame('0797777777', $contact->whatsapp_number);
        $this->assertSame('rana@example.com', $contact->email);
    }

    public function test_business_phone_with_separate_contact_person_keeps_phone_ownership_on_business(): void
    {
        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum([
            'contact_name' => 'Abu Omar',
            'contact_role' => 'owner',
            'contact_phone' => '0782222222',
            'contact_email' => 'omar@example.com',
        ]))->assertSessionHasNoErrors();

        $client = Client::sole();
        $this->assertSame('business', $client->primary_phone_type);
        $this->assertSame('0791000001', $client->phone);
        $this->assertSame('0791000001', $client->business_phone);

        $contact = $client->contacts()->sole();
        $this->assertTrue($contact->is_primary);
        $this->assertSame('Abu Omar', $contact->name);
        $this->assertSame('owner', $contact->role);
        $this->assertSame('0782222222', $contact->primary_phone);
        $this->assertSame('omar@example.com', $contact->email);
    }

    public function test_optional_contact_fields_submitted_empty_do_not_become_required(): void
    {
        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum([
            'primary_phone_type' => 'owner',
            'contact_name' => '',
            'contact_role' => '',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'contact_email' => '',
            'business_phone' => '',
            'location_text' => '',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $contact = Client::sole()->contacts()->sole();
        $this->assertNull($contact->name);
        $this->assertNull($contact->email);
        $this->assertNull($contact->secondary_phone);
    }

    // ── Edit transitions ───────────────────────────────────────────────

    public function test_repeated_updates_never_duplicate_the_primary_contact(): void
    {
        $client = $this->createVia(['primary_phone_type' => 'owner', 'contact_name' => 'Sami']);

        foreach (range(1, 3) as $i) {
            $this->actingAs($this->staff)
                ->put(route('clients.update', $client), $this->minimum(['primary_phone_type' => 'owner', 'contact_name' => "Sami {$i}"]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, $client->contacts()->count());
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
        $this->assertSame('Sami 3', $client->contacts()->sole()->name);
    }

    public function test_business_to_owner_moves_phone_ownership_to_the_primary_contact(): void
    {
        $client = $this->createVia([
            'contact_name' => 'Lina',
            'contact_phone' => '0785555555',
        ]);
        $this->assertSame('0791000001', $client->fresh()->business_phone);

        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'primary_phone_type' => 'owner',
            'contact_name' => 'Lina',
            'contact_phone' => '0785555555',
            'business_phone' => '',
        ]))->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('owner', $client->primary_phone_type);
        $this->assertSame('0791000001', $client->phone);
        $this->assertNull($client->business_phone);

        $contact = $client->contacts()->sole();
        $this->assertSame('owner', $contact->role);
        $this->assertSame('0791000001', $contact->primary_phone);
        $this->assertSame('0785555555', $contact->secondary_phone, 'The contact\'s previous number is kept, not overwritten.');
    }

    public function test_owner_to_business_keeps_historical_contact_data(): void
    {
        $client = $this->createVia([
            'primary_phone_type' => 'owner',
            'contact_name' => 'Yousef',
            'contact_email' => 'yousef@example.com',
        ]);

        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'primary_phone_type' => 'business',
            'contact_name' => 'Yousef',
            'contact_role' => 'owner',
            'contact_phone' => '',
            'contact_email' => 'yousef@example.com',
        ]))->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('business', $client->primary_phone_type);
        $this->assertSame('0791000001', $client->business_phone);

        $contact = $client->contacts()->sole();
        $this->assertTrue($contact->is_primary);
        $this->assertSame('Yousef', $contact->name);
        $this->assertSame('owner', $contact->role);
        $this->assertSame('0791000001', $contact->primary_phone, 'The owner keeps the number that was theirs.');
        $this->assertSame('yousef@example.com', $contact->email);
    }

    public function test_owner_to_manager_updates_role_without_duplicates(): void
    {
        $client = $this->createVia(['primary_phone_type' => 'owner']);

        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'primary_phone_type' => 'manager',
        ]))->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('manager', $client->primary_phone_type);
        $contact = $client->contacts()->sole();
        $this->assertSame('manager', $contact->role);
        $this->assertSame('0791000001', $contact->primary_phone);
    }

    public function test_changing_primary_phone_updates_the_correct_destination(): void
    {
        $business = $this->createVia(['business_name' => 'Biz Phone']);
        $this->actingAs($this->staff)->put(route('clients.update', $business), $this->minimum([
            'business_name' => 'Biz Phone',
            'phone' => '0792000002',
        ]))->assertSessionHasNoErrors();
        $business->refresh();
        $this->assertSame('0792000002', $business->phone);
        $this->assertSame('0792000002', $business->business_phone);
        $this->assertSame(0, $business->contacts()->count());

        $owner = $this->createVia(['business_name' => 'Owner Phone', 'phone' => '0793000003', 'primary_phone_type' => 'owner', 'business_phone' => '064111111']);
        $this->actingAs($this->staff)->put(route('clients.update', $owner), $this->minimum([
            'business_name' => 'Owner Phone',
            'phone' => '0794000004',
            'primary_phone_type' => 'owner',
            'business_phone' => '064111111',
        ]))->assertSessionHasNoErrors();
        $owner->refresh();
        $this->assertSame('0794000004', $owner->phone);
        $this->assertSame('064111111', $owner->business_phone);
        $this->assertSame('0794000004', $owner->contacts()->sole()->primary_phone);
    }

    // ── Reference data & rendering ─────────────────────────────────────

    public function test_forms_use_reference_data_service_and_render_unknown_historical_values(): void
    {
        ReferenceOption::create(['list_key' => ReferenceDataService::CITY_AREA, 'value' => 'Abdoun', 'label_ar' => 'عبدون', 'label_en' => 'Abdoun', 'sort_order' => 10]);

        $this->actingAs($this->staff)->get(route('clients.create'))
            ->assertOk()
            ->assertSee('name="primary_phone_type"', false)
            ->assertSee('value="Direct Prospecting"', false)
            ->assertSee('<option value="Abdoun"></option>', false)
            ->assertSee('name="business_category"', false)
            ->assertDontSee('name="referral_commission_percentage"', false);

        $legacy = $this->legacyClient(['lead_source' => 'Old Flyer', 'city_area' => 'Old Town', 'business_category' => 'Legacy Bakery']);

        $this->actingAs($this->staff)->get(route('clients.edit', $legacy))
            ->assertOk()
            ->assertSee('<option value="Old Flyer" selected>Old Flyer</option>', false)
            ->assertSee('value="Old Town"', false)
            ->assertSee('value="Legacy Bakery"', false);
    }

    public function test_category_uses_controlled_select_with_other_free_text_once_configured(): void
    {
        ReferenceOption::create(['list_key' => ReferenceDataService::CLIENT_CATEGORY, 'value' => 'Restaurant', 'label_ar' => 'مطعم', 'label_en' => 'Restaurant', 'sort_order' => 10]);

        $this->actingAs($this->staff)->get(route('clients.create'))
            ->assertOk()
            ->assertSee('<select id="business_category"', false)
            ->assertSee('value="__other__"', false);

        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum([
            'business_category' => '__other__',
        ]))->assertSessionHasErrors('business_category_other');

        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum([
            'business_category' => '__other__',
            'business_category_other' => 'Pet Grooming',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Pet Grooming', Client::sole()->business_category);
    }

    public function test_existing_legacy_clients_remain_readable_and_editable(): void
    {
        $legacy = $this->legacyClient(['contact_person' => 'Old Contact']);
        ClientContact::create(['client_id' => $legacy->id, 'name' => null, 'role' => 'مالك', 'is_primary' => false]);

        $this->actingAs($this->staff)->get(route('clients.show', $legacy))
            ->assertOk()
            ->assertSee('Legacy Client')
            ->assertSee(__('notify.clients.contact_model.unnamed_contact'));

        $this->actingAs($this->staff)->get(route('clients.edit', $legacy))
            ->assertOk()
            ->assertSee('value="Old Contact"', false);
    }

    public function test_workspace_renders_business_phone_ownership_and_primary_contact(): void
    {
        $client = $this->createVia([
            'primary_phone_type' => 'owner',
            'contact_email' => 'owner@example.com',
        ]);

        $this->actingAs($this->staff)->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee(__('notify.clients.contact_model.phone_types.owner'))
            ->assertSee(__('notify.clients.contact_model.unnamed_contact'))
            ->assertSee('owner@example.com');
    }

    public function test_workspace_contact_can_be_added_without_a_name_but_not_empty(): void
    {
        $client = $this->createVia();

        $this->actingAs($this->staff)->post(route('clients.contacts.store', $client), [
            'primary_phone' => '0786666666',
            'email' => 'desk@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_contacts', ['client_id' => $client->id, 'name' => null, 'email' => 'desk@example.com']);

        $this->actingAs($this->staff)->post(route('clients.contacts.store', $client), [])
            ->assertSessionHasErrors('name');
    }

    // ── Referral (P2 rule preserved) ───────────────────────────────────

    public function test_staff_cannot_set_referral_commission_through_the_refactored_forms(): void
    {
        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum([
            'referred_by_name' => 'Sami',
            'referral_commission_percentage' => '30',
        ]))->assertSessionHasNoErrors();
        $client = Client::sole();
        $this->assertNull($client->referral_commission_bps);
        $this->assertSame('Sami', $client->referred_by_name);

        $client->forceFill(['referral_commission_bps' => 500])->save();
        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'referred_by_name' => 'Sami',
            'referral_commission_percentage' => '40',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(500, (int) $client->fresh()->referral_commission_bps);

        $this->actingAs($this->staff)->get(route('clients.edit', $client))
            ->assertOk()
            ->assertDontSee('name="referral_commission_percentage"', false);
        $this->actingAs($this->admin)->get(route('clients.edit', $client))
            ->assertOk()
            ->assertSee('name="referral_commission_percentage"', false);

        $this->actingAs($this->admin)->put(route('clients.update', $client), $this->minimum([
            'referral_commission_percentage' => '12.5',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1250, (int) $client->fresh()->referral_commission_bps);
    }

    // ── CSV import ─────────────────────────────────────────────────────

    public function test_csv_import_applies_the_same_contact_model(): void
    {
        $path = $this->csv([
            'business_name,phone,primary_phone_type,contact_name,contact_person,contact_email,business_phone,city_area,business_category,lead_source',
            'Owner Row,0795000005,owner,,,,,Amman,Cafe,Instagram',
            'Manager Row,0796000006,Manager,Huda,,huda@example.com,064222222,Irbid,Salon,Referral',
            'Legacy Row,0797000007,,,Abu Zaid,,,Zarqa,Bakery,Google Maps',
            'Bad Type,0798000008,partner,,,,,Zarqa,Bakery,Google Maps',
            'Bad Email,0799000009,owner,,,nope,,Zarqa,Bakery,Google Maps',
        ]);

        $service = app(CsvImportService::class);
        $preview = $service->previewCsv($path);
        $this->assertCount(3, $preview['valid']);
        $this->assertCount(2, $preview['invalid']);

        $this->assertSame(3, $service->importValidRows($preview['valid'], 'prospect', $this->admin->id));

        $owner = Client::where('business_name', 'Owner Row')->sole();
        $this->assertSame('owner', $owner->primary_phone_type);
        $this->assertNull($owner->business_phone);
        $ownerContact = $owner->contacts()->sole();
        $this->assertNull($ownerContact->name, 'Import never guesses owner names.');
        $this->assertSame('owner', $ownerContact->role);
        $this->assertSame('0795000005', $ownerContact->primary_phone);

        $manager = Client::where('business_name', 'Manager Row')->sole();
        $this->assertSame('manager', $manager->primary_phone_type);
        $this->assertSame('064222222', $manager->business_phone);
        $this->assertSame('huda@example.com', $manager->contacts()->sole()->email);

        // Missing primary_phone_type → documented default "business" (pre-P4 semantics).
        $legacy = Client::where('business_name', 'Legacy Row')->sole();
        $this->assertSame('business', $legacy->primary_phone_type);
        $this->assertSame('0797000007', $legacy->business_phone);
        $legacyContact = $legacy->contacts()->sole();
        $this->assertSame('Abu Zaid', $legacyContact->name);
        $this->assertTrue($legacyContact->is_primary);
        $this->assertSame(ClientLifecycle::PROSPECT, $legacy->stage);
    }

    // ── P4 hardening: primary contact consistency (P4-G2) ──────────────

    public function test_owner_phone_rejects_making_an_unrelated_contact_primary(): void
    {
        $client = $this->createVia(['primary_phone_type' => 'owner', 'contact_name' => 'Owner']);
        $owner = $client->contacts()->sole();

        $this->actingAs($this->staff)->post(route('clients.contacts.store', $client), [
            'name' => 'Accountant', 'primary_phone' => '0781111111', 'is_primary' => 1,
        ])->assertSessionHasErrors('is_primary');
        $this->assertSame(1, $client->contacts()->count(), 'Rejected, not silently normalized.');

        // An additional (non-primary) contact is still allowed.
        $this->actingAs($this->staff)->post(route('clients.contacts.store', $client), [
            'name' => 'Accountant', 'primary_phone' => '0781111111',
        ])->assertSessionHasNoErrors();
        $accountant = $client->contacts()->where('name', 'Accountant')->sole();
        $this->assertFalse($accountant->is_primary);

        $this->actingAs($this->staff)->patch(route('clients.contacts.update', [$client, $accountant]), [
            'name' => 'Accountant', 'primary_phone' => '0781111111', 'is_primary' => 1,
        ])->assertSessionHasErrors('is_primary');

        $this->assertPhoneOwnershipIntact($client, $owner, 'owner');
        $this->assertFalse($accountant->fresh()->is_primary);
    }

    public function test_manager_phone_rejects_making_an_unrelated_contact_primary(): void
    {
        $client = $this->createVia(['primary_phone_type' => 'manager']);
        $manager = $client->contacts()->sole();

        $this->actingAs($this->staff)->post(route('clients.contacts.store', $client), [
            'name' => 'Owner Later', 'primary_phone' => '0782222222', 'is_primary' => 1,
        ])->assertSessionHasErrors('is_primary');

        $this->assertPhoneOwnershipIntact($client, $manager, 'manager');
        $this->assertSame(1, $client->contacts()->count());
    }

    public function test_phone_owner_contact_is_editable_but_its_number_follows_the_client(): void
    {
        $client = $this->createVia(['primary_phone_type' => 'owner']);
        $owner = $client->contacts()->sole();

        $this->actingAs($this->staff)->patch(route('clients.contacts.update', [$client, $owner]), [
            'name' => 'Named Later', 'primary_phone' => '0791000001', 'email' => 'named@example.com',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Named Later', $owner->fresh()->name);
        $this->assertTrue($owner->fresh()->is_primary);

        $this->actingAs($this->staff)->patch(route('clients.contacts.update', [$client, $owner]), [
            'name' => 'Named Later', 'primary_phone' => '0799999999',
        ])->assertSessionHasErrors('primary_phone');

        $this->assertPhoneOwnershipIntact($client, $owner, 'owner');
    }

    public function test_business_phone_allows_an_independent_primary_human_contact(): void
    {
        $client = $this->createVia(['contact_name' => 'First Person', 'contact_phone' => '0783333333']);
        $first = $client->contacts()->sole();

        $this->actingAs($this->staff)->post(route('clients.contacts.store', $client), [
            'name' => 'Second Person', 'primary_phone' => '0784444444', 'is_primary' => 1,
        ])->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('business', $client->primary_phone_type);
        $this->assertSame('0791000001', $client->phone);
        $this->assertSame('0791000001', $client->business_phone);
        $this->assertSame('Second Person', $client->contacts()->where('is_primary', true)->sole()->name);
        $this->assertSame('0783333333', $first->fresh()->primary_phone, 'Previous primary contact is kept as history.');
        $this->assertFalse($first->fresh()->is_primary);
    }

    public function test_client_edit_remains_the_authoritative_path_to_change_ownership(): void
    {
        $client = $this->createVia(['primary_phone_type' => 'owner', 'contact_name' => 'Owner']);
        $owner = $client->contacts()->sole();

        // Owner -> business through client edit; the person keeps their data.
        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'primary_phone_type' => 'business', 'contact_name' => 'Owner', 'contact_role' => 'owner',
        ]))->assertSessionHasNoErrors();

        // Now a different human contact may become primary without touching clients.phone.
        $this->actingAs($this->staff)->post(route('clients.contacts.store', $client), [
            'name' => 'Front Desk', 'primary_phone' => '0785555555', 'is_primary' => 1,
        ])->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('business', $client->primary_phone_type);
        $this->assertSame('0791000001', $client->phone);
        $this->assertSame('Front Desk', $client->contacts()->where('is_primary', true)->sole()->name);
        $this->assertSame('0791000001', $owner->fresh()->primary_phone);

        // Business -> manager through client edit: the current primary contact takes phone ownership,
        // no duplicate primary is created and their own number is preserved as secondary_phone.
        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'primary_phone_type' => 'manager',
        ]))->assertSessionHasNoErrors();

        $client->refresh();
        $frontDesk = $client->contacts()->where('is_primary', true)->sole();
        $this->assertSame('Front Desk', $frontDesk->name);
        $this->assertSame('manager', $frontDesk->role);
        $this->assertSame('0791000001', $frontDesk->primary_phone);
        $this->assertSame('0785555555', $frontDesk->secondary_phone);
        $this->assertSame(2, $client->contacts()->count());
    }

    public function test_ownership_transition_preserves_previous_number_only_without_conflict(): void
    {
        $client = $this->createVia(['primary_phone_type' => 'owner']);
        $owner = $client->contacts()->sole();

        // Primary phone changes without a submitted second phone: the old number is preserved.
        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'primary_phone_type' => 'owner', 'phone' => '0792000002',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('0792000002', $owner->fresh()->primary_phone);
        $this->assertSame('0791000001', $owner->fresh()->secondary_phone);

        // An existing, different second phone is a conflicting value and is not overwritten.
        $this->actingAs($this->staff)->put(route('clients.update', $client), $this->minimum([
            'primary_phone_type' => 'owner', 'phone' => '0793000003',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('0793000003', $owner->fresh()->primary_phone);
        $this->assertSame('0791000001', $owner->fresh()->secondary_phone);
    }

    private function assertPhoneOwnershipIntact(Client $client, ClientContact $owner, string $type): void
    {
        $client->refresh();
        $this->assertSame($type, $client->primary_phone_type);
        $this->assertSame('0791000001', $client->phone);
        $this->assertTrue($owner->fresh()->is_primary);
        $this->assertSame('0791000001', $owner->fresh()->primary_phone);
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function minimum(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Minimum Cafe',
            'business_category' => 'Cafe',
            'phone' => '0791000001',
            'primary_phone_type' => 'business',
            'city_area' => 'Amman - Abdoun',
            'lead_source' => 'Google Maps',
        ], $overrides);
    }

    private function createVia(array $overrides = []): Client
    {
        $this->actingAs($this->staff)->post(route('clients.store'), $this->minimum($overrides))->assertSessionHasNoErrors();

        return Client::where('business_name', $overrides['business_name'] ?? 'Minimum Cafe')->latest('id')->firstOrFail();
    }

    private function legacyClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'business_name' => 'Legacy Client',
            'phone' => '0790009999',
            'business_phone' => '0790009999',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Google Maps',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ], $overrides));
    }

    private function csv(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'p4csv');
        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }
}
