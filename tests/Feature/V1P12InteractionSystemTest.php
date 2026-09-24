<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentReceiptConfirmation;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentReceiptService;
use App\Support\AppointmentTypes;
use App\Support\ClientLifecycle;
use App\Support\FormState;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * P12 — Interaction system: one sheet, form-field, confirmation, menu, list, filter, pagination and
 * feedback language across the high-value workflows. Business workflows and authorization unchanged.
 */
class V1P12InteractionSystemTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'P12-Sup3r-S3cret!';

    private const NOTE = 'P12 private backup code';

    private User $owner;

    private User $staff;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Amman'));
        $this->seed(SettingsSeeder::class);
        $this->owner = User::factory()->create(['role' => User::ROLE_FOUNDER, 'is_active' => true, 'name' => 'Owner One']);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true, 'name' => 'Staff Sara']);
        $this->client = $this->makeClient('Interaction Cafe', ['stage' => ClientLifecycle::CONTACTING]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeClient(string $name, array $attributes = []): Client
    {
        return Client::create(array_merge([
            'business_name' => $name,
            'business_category' => 'Cafe',
            'phone' => '079'.random_int(1000000, 9999999),
            'city_area' => 'Amman',
            'lead_source' => 'Direct',
            'stage' => ClientLifecycle::PROSPECT,
            'status' => 'prospect',
        ], $attributes));
    }

    /** Markup of one sheet (from its root to the next sheet root). */
    private function sheet(string $html, string $id): string
    {
        $idAt = strpos($html, 'id="'.$id.'" data-sheet');
        $this->assertNotFalse($idAt, "Sheet {$id} must render.");
        $start = strrpos(substr($html, 0, $idAt), '<div class="notify-sheet ');
        $next = strpos($html, '<div class="notify-sheet ', $idAt);

        return substr($html, $start, ($next === false ? strlen($html) : $next) - $start);
    }

    // ---------------------------------------------------------------- sheets

    public function test_workspace_sheets_share_one_accessible_primitive(): void
    {
        Appointment::create(['client_id' => $this->client->id, 'appointment_date' => '2026-09-24', 'appointment_time' => '11:00:00', 'appointment_type' => AppointmentTypes::PHYSICAL_VISIT, 'status' => 'scheduled']);

        $html = $this->actingAs($this->owner)->get(route('clients.show', $this->client))->assertOk()->getContent();

        $this->assertStringNotContainsString('notify-modal-backdrop', $html, 'The legacy modal system is gone from the workspace.');
        foreach (['modal-record-call', 'modal-create-appointment', 'modal-appointment-result', 'modal-schedule-installation', 'modal-close-client', 'modal-record-payment', 'modal-start-subscription'] as $id) {
            $sheet = $this->sheet($html, $id);
            $this->assertStringContainsString('hidden', substr($sheet, 0, 200));
            $this->assertMatchesRegularExpression('/role="dialog" aria-modal="true" aria-labelledby="'.$id.'-title"/', $sheet);
            $this->assertStringContainsString('<h2 class="notify-sheet__title" id="'.$id.'-title">', $sheet);
            $this->assertStringContainsString('data-sheet-close aria-label=', $sheet);
            $this->assertStringContainsString('<input type="hidden" name="_form" value="'.$id.'">', $sheet);
            $this->assertStringNotContainsString(' style="', $sheet, "{$id}: no inline styles (§21).");
            $this->assertStringNotContainsString('data-sheet-reopen', $sheet, "{$id} is closed on a normal visit.");
        }

        // Labels are bound to their controls.
        $this->assertStringContainsString('for="call-callback-at"', $html);
        $this->assertStringContainsString('id="call-callback-at"', $html);
    }

    public function test_deep_link_names_the_sheet_to_open(): void
    {
        $this->actingAs($this->owner)->get(route('clients.show', ['client' => $this->client, 'open' => 'record-call']))
            ->assertOk()
            ->assertViewHas('openSheet', 'modal-record-call')
            ->assertSee('modal-record-call', false);
        $this->assertMatchesRegularExpression("/var openSheetId = ['\"]modal-record-call['\"]/", $this->actingAs($this->owner)->get(route('clients.show', ['client' => $this->client, 'open' => 'record-call']))->getContent());
    }

    public function test_validation_failure_reopens_only_the_submitted_sheet_with_safe_old_input(): void
    {
        $workspace = route('clients.show', $this->client);

        $this->actingAs($this->owner)->from($workspace)
            ->post(route('clients.contact-attempts.store', $this->client), [
                '_form' => 'modal-record-call',
                'method' => 'phone',
                'result' => 'callback_later',
                'note' => 'Call after lunch',
            ])
            ->assertRedirect($workspace)
            ->assertSessionHasErrors('follow_up_date_time');

        $html = $this->actingAs($this->owner)->get($workspace)->getContent();
        $call = $this->sheet($html, 'modal-record-call');
        $this->assertStringContainsString('data-sheet-reopen', substr($call, 0, 200));
        $this->assertStringContainsString('data-sheet-errors', $call);
        $this->assertStringContainsString('value="callback_later" checked', $call, 'The chosen outcome is kept.');
        $this->assertStringContainsString('Call after lunch', $call);
        $this->assertMatchesRegularExpression('/id="call-callback-at"[^>]*aria-invalid="true" aria-describedby="call-callback-at-error"/', $call);
        $this->assertStringContainsString('id="call-callback-at-error"', $call);

        // Sibling sheets stay closed and clean — no leaked values or errors.
        $appointment = $this->sheet($html, 'modal-create-appointment');
        $this->assertStringNotContainsString('data-sheet-reopen', substr($appointment, 0, 200));
        $this->assertStringNotContainsString('aria-invalid', $appointment);
        $this->assertStringNotContainsString('Call after lunch', $appointment);
    }

    public function test_credential_secret_is_never_recovered_on_validation_reopen(): void
    {
        foreach (Product::V1_SYSTEM_IDENTITIES as $code => $identity) {
            Product::query()->firstOrCreate(['code' => $code], $identity + ['is_active' => true])
                ->forceFill(['requires_credentials' => $identity['requires_credentials'], 'is_active' => true])->save();
        }
        $smartLink = Product::where('code', Product::CODE_SMART_LINK)->firstOrFail();
        $workspace = route('clients.show', $this->client);

        $this->actingAs($this->staff)->from($workspace)
            ->post(route('clients.credentials.store', $this->client), [
                '_form' => 'credential-add-other',
                'product_id' => $smartLink->id,
                'login_url' => 'not a url',
                'username' => 'cafe@example.com',
                'credential_secret' => self::SECRET,
                'credential_note' => self::NOTE,
            ])
            ->assertSessionHasErrorsIn('credentials', 'login_url');

        $this->assertNull(session()->getOldInput('credential_secret'));
        $this->assertNull(session()->getOldInput('credential_note'));

        $html = $this->actingAs($this->staff)->get($workspace)->getContent();
        $sheet = $this->sheet($html, 'credential-add-other');
        $this->assertStringContainsString('data-sheet-reopen', substr($sheet, 0, 250));
        $this->assertStringContainsString('value="not a url"', $sheet);
        $this->assertStringContainsString(__('notify.ui.reenter_secret'), $sheet);
        $this->assertMatchesRegularExpression('/name="credential_secret"(?![^>]*value=)[^>]*>/', $sheet);
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString(self::NOTE, $html);
    }

    // ---------------------------------------------------------------- confirmation

    public function test_destructive_actions_use_the_one_confirmation_dialog(): void
    {
        $html = $this->actingAs($this->owner)->get(route('clients.show', $this->client))->getContent();
        $this->assertSame(1, substr_count($html, 'id="notify-confirm"'));
        $this->assertMatchesRegularExpression('/id="notify-confirm" data-sheet[^>]*hidden>\s*<div class="notify-sheet__backdrop"[^>]*><\/div>\s*<div class="notify-sheet__panel" role="alertdialog"/', $html);

        // No native browser confirm() anywhere in the views.
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertDoesNotMatchRegularExpression('/\bconfirm\(/', $file->getContents(), $file->getRelativePathname().' must not use window.confirm().');
        }
    }

    // ---------------------------------------------------------------- menus

    public function test_overflow_menus_share_one_accessible_primitive(): void
    {
        $this->client->update(['stage' => ClientLifecycle::CONTACTING]);
        $html = $this->actingAs($this->owner)->get(route('clients.show', $this->client))->getContent();

        $this->assertMatchesRegularExpression('/<details class="notify-menu" data-client-more="data-client-more" data-menu>\s*<summary class="[^"]*notify-menu__trigger" aria-haspopup="menu"/', $html);
        $this->assertStringContainsString('<div class="notify-menu__panel" role="menu"', $html);
        $this->assertStringContainsString('role="menuitem"', $html);
        // "Close client" is destructive: it sits after the separator.
        $menu = substr($html, strpos($html, 'data-client-more'), 6000);
        $this->assertLessThan(strpos($menu, 'modal-close-client'), strpos($menu, 'notify-menu__separator'));
    }

    // ---------------------------------------------------------------- lists

    public function test_finance_lists_render_as_one_table_card_pattern(): void
    {
        $payer = $this->makeClient('Jerash Market', ['stage' => ClientLifecycle::SUBSCRIBER, 'status' => 'subscriber']);
        Invoice::create(['client_id' => $payer->id, 'invoice_number' => 'INV-P12-1', 'status' => Invoice::STATUS_ISSUED, 'total_minor' => 45000, 'subtotal_minor' => 45000, 'tax_minor' => 0, 'discount_minor' => 0, 'currency' => 'JOD', 'issue_date' => '2026-09-01', 'due_date' => '2026-09-10']);
        $receipt = app(PaymentReceiptService::class)->submit($payer, ['amount' => '20.000', 'payment_method' => 'cash', 'received_at' => '2026-09-24 09:00'], $this->staff, 'p12-list');

        $pending = $this->actingAs($this->owner)->get(route('finance.collections', ['tab' => 'pending']))->assertOk()->getContent();
        $this->assertStringContainsString('<table class="notify-list__table"', $pending);
        $this->assertStringContainsString('<th scope="col"', $pending);
        $this->assertMatchesRegularExpression('/<tr data-pending-receipt="'.$receipt->id.'">\s*<td class="notify-list__primary">/', $pending);
        $this->assertStringContainsString('data-open-sheet="reject-receipt-'.$receipt->id.'"', $pending);
        $reject = $this->sheet($pending, 'reject-receipt-'.$receipt->id);
        $this->assertStringContainsString(route('payment-receipts.reject', $receipt), $reject);
        $this->assertStringContainsString('name="rejection_reason" required', $reject);
        $this->assertStringContainsString('name="_idempotency_key"', $reject);
        $this->assertStringNotContainsString('notify-fin-more', $pending);

        $due = $this->actingAs($this->owner)->get(route('finance.collections', ['tab' => 'due']))->getContent();
        $this->assertMatchesRegularExpression('/<tr class="is-overdue" data-due-invoice=/', $due);

        $staffDue = $this->actingAs($this->staff)->get(route('collections-due.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<table class="notify-list__table"', $staffDue);
        $this->assertStringContainsString('data-due-client="'.$payer->id.'"', $staffDue);

        $expenses = $this->actingAs($this->owner)->get(route('finance.expenses'))->assertOk()->getContent();
        $this->assertStringContainsString('data-empty-state', $expenses, 'Empty list uses the shared empty state.');
        $this->assertStringNotContainsString('notify-fin-menu', $expenses);
        $this->assertStringNotContainsString('notify-fin-pay', $expenses);
    }

    // ---------------------------------------------------------------- filters, search, pagination

    public function test_client_search_filters_and_pagination_keep_their_context(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->makeClient('Load Client '.$i, ['business_category' => 'Bakery']);
        }

        $html = $this->actingAs($this->owner)->get(route('clients.index', ['view' => 'prospects', 'category' => 'Bakery', 'q' => 'Load']))->assertOk()->getContent();

        // Search: labelled, clearable without losing the filter.
        $this->assertStringContainsString('<label class="notify-visually-hidden" for="client-search">', $html);
        $this->assertStringContainsString('data-search-clear', $html);
        $this->assertStringContainsString(e(route('clients.index', ['view' => 'prospects', 'category' => 'Bakery'])), $html);

        // Filters: one sheet, visible removable chip that keeps search and segment.
        $this->assertStringContainsString('id="client-filters" data-sheet', $html);
        $this->assertStringContainsString('data-open-sheet="client-filters"', $html);
        $this->assertStringContainsString('data-filter-chip', $html);
        $this->assertStringContainsString(e(route('clients.index', ['view' => 'prospects', 'q' => 'Load'])), $html);

        // Pagination: previous/next + numbers, query string preserved.
        $this->assertStringContainsString('data-pagination', $html);
        $this->assertStringContainsString('data-page-numbers', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*category=Bakery[^"]*page=2[^"]*" rel="next" data-page-next/', html_entity_decode($html));
        $this->assertStringContainsString(__('notify.ui.page_of', ['current' => 1, 'last' => 2]), $html);
    }

    // ---------------------------------------------------------------- feedback

    public function test_flash_feedback_is_typed_and_accessible(): void
    {
        $this->actingAs($this->owner)->withSession(['success' => 'Saved it'])->get(route('clients.index'))
            ->assertSee('data-flash="success"', false)
            ->assertSee('role="status" data-flash="success"', false);
        $this->actingAs($this->owner)->withSession(['warning' => 'Careful'])->get(route('clients.index'))
            ->assertSee('role="alert" data-flash="warning"', false);
        $this->actingAs($this->owner)->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['x' => 'Broken']))])->get(route('clients.index'))
            ->assertSee('role="alert" data-flash="error"', false);
    }

    // ---------------------------------------------------------------- forms

    public function test_form_state_scopes_old_input_and_errors_to_the_submitted_form(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['amount' => 'Bad amount', 'system_ids.0' => 'Bad system']));
        $this->app['request']->setLaravelSession($this->app['session.store']);
        session()->flashInput(['_form' => 'form-a', 'amount' => '12.500']);

        FormState::begin('form-a');
        $this->assertSame('aria-invalid="true" aria-describedby="a-amount-error"', FormState::attributes($errors, 'amount', 'a-amount'));
        $this->assertSame('Bad system', FormState::error($errors, 'system_ids[]'));
        FormState::end();

        FormState::begin('form-b');
        $this->assertSame('', FormState::attributes($errors, 'amount', 'b-amount'));
        $this->assertSame('aria-describedby="b-amount-hint"', FormState::attributes($errors, 'amount', 'b-amount', 'default', true));
        FormState::end();

        $this->assertSame('12.500', FormState::oldFor('form-a')('amount'));
        $this->assertSame('default', FormState::oldFor('form-b')('amount', 'default'));
        $this->assertSame('items.0.name', FormState::key('items[0][name]'));
    }

    // ---------------------------------------------------------------- finance & security

    public function test_payment_sheet_keeps_owner_and_staff_paths_distinct(): void
    {
        $owner = $this->sheet($this->actingAs($this->owner)->get(route('clients.show', $this->client))->getContent(), 'modal-record-payment');
        $this->assertStringContainsString('action="'.route('clients.payments.normal.store', $this->client).'"', $owner);
        $this->assertStringContainsString('data-payment-form="owner"', $owner);
        $this->assertStringContainsString('name="_idempotency_key"', $owner);
        $this->assertStringContainsString('inputmode="decimal"', $owner);

        $staff = $this->sheet($this->actingAs($this->staff)->get(route('clients.show', $this->client))->getContent(), 'modal-record-payment');
        $this->assertStringContainsString('action="'.route('clients.payment-receipts.store', $this->client).'"', $staff);
        $this->assertStringContainsString('data-payment-form="staff-receipt"', $staff);
        $this->assertStringNotContainsString(route('clients.payments.normal.store', $this->client), $staff);
        preg_match_all('/name="payment_method" value="([^"]+)"/', $staff, $methods);
        $this->assertSame(['cash', 'cliq'], $methods[1]);
    }

    public function test_receipt_idempotency_is_unchanged_behind_the_sheet(): void
    {
        $payload = ['_form' => 'modal-record-payment', 'amount' => '15.000', 'payment_method' => 'cash', 'received_at' => '2026-09-24 09:30', '_idempotency_key' => 'p12-same-key'];

        $this->actingAs($this->staff)->post(route('clients.payment-receipts.store', $this->client), $payload)->assertRedirect();
        $this->actingAs($this->staff)->post(route('clients.payment-receipts.store', $this->client), $payload)->assertRedirect();

        $this->assertSame(1, PaymentReceiptConfirmation::where('client_id', $this->client->id)->count());
    }
}
