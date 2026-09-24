<?php

namespace Tests\Feature;

use App\Models\ChartAccount;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\CompanyAccountBootstrapService;
use App\Services\PaymentFinancialAccountResolver;
use App\Support\ClientLifecycle;
use App\Support\PaymentMethods;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P1 — M-1 bootstrap, fixed Cash/CliQ resolver, PaymentMethods::v1() [FROZEN D-01, D-02].
 */
class V1P1CompanyAccountBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_has_exactly_one_cash_box_and_one_cliq_with_chart_mappings(): void
    {
        $this->assertSame(1, FinancialAccount::where('code', 'CASH-BOX')->count());
        $this->assertSame(1, FinancialAccount::where('code', 'CLIQ')->count());

        $cashBox = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $this->assertSame('الصندوق', $cashBox->name_ar);
        $this->assertSame('Cash Box', $cashBox->name_en);
        $this->assertSame(FinancialAccount::TYPE_CASH, $cashBox->type);
        $this->assertSame('JOD', $cashBox->currency);
        $this->assertTrue($cashBox->is_active);
        $this->assertNull($cashBox->archived_at);
        $this->assertNotNull($cashBox->chart_account_id);

        $cliq = FinancialAccount::where('code', 'CLIQ')->firstOrFail();
        $this->assertSame('كليك', $cliq->name_ar);
        $this->assertSame('CliQ', $cliq->name_en);
        $this->assertSame(FinancialAccount::TYPE_BANK, $cliq->type);
        $this->assertSame('JOD', $cliq->currency);
        $this->assertNotNull($cliq->chart_account_id);

        $this->assertNotSame($cashBox->chart_account_id, $cliq->chart_account_id);
        $this->assertSame('1100', ChartAccount::findOrFail($cashBox->chart_account_id)->parent?->code);
    }

    public function test_bootstrap_is_idempotent_across_service_command_and_seeder(): void
    {
        $service = app(CompanyAccountBootstrapService::class);
        $service->ensureDefaults();
        $service->ensureDefaults();

        $this->artisan('notify:bootstrap')->assertSuccessful();
        $this->artisan('notify:bootstrap')->assertSuccessful();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, FinancialAccount::where('code', 'CASH-BOX')->count());
        $this->assertSame(1, FinancialAccount::where('code', 'CLIQ')->count());
        $this->assertSame(2, FinancialAccount::count());
        $this->assertSame(
            2,
            ChartAccount::whereIn('id', FinancialAccount::pluck('chart_account_id'))->count()
        );
    }

    public function test_bootstrap_never_renames_archives_or_merges_existing_accounts(): void
    {
        $cashBox = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();
        $cashBox->update(['name_en' => 'Owner renamed box']);

        $other = FinancialAccount::create([
            'code' => 'LEGACY-BANK',
            'name_ar' => 'بنك قديم',
            'name_en' => 'Legacy bank',
            'type' => FinancialAccount::TYPE_BANK,
            'currency' => 'JOD',
            'is_active' => true,
        ]);

        app(CompanyAccountBootstrapService::class)->ensureDefaults();

        $this->assertSame('Owner renamed box', $cashBox->fresh()->name_en);
        $this->assertSame('Legacy bank', $other->fresh()->name_en);
        $this->assertTrue($other->fresh()->is_active);
        $this->assertNull($other->fresh()->archived_at);
        $this->assertSame(3, FinancialAccount::count());
    }

    public function test_resolver_maps_cash_to_cash_box_and_cliq_to_cliq_by_code_not_type(): void
    {
        // Extra accounts of the same types must not create ambiguity for the fixed map.
        FinancialAccount::create(['code' => 'PETTY', 'name_ar' => 'نثرية', 'type' => FinancialAccount::TYPE_CASH, 'currency' => 'JOD', 'is_active' => true]);
        FinancialAccount::create(['code' => 'BANK-2', 'name_ar' => 'بنك', 'type' => FinancialAccount::TYPE_BANK, 'currency' => 'JOD', 'is_active' => true]);

        $resolver = app(PaymentFinancialAccountResolver::class);

        $this->assertSame('CASH-BOX', $resolver->resolve(PaymentMethods::CASH)->code);
        $this->assertSame('CLIQ', $resolver->resolve(PaymentMethods::CLIQ)->code);
    }

    public function test_resolver_rejects_unsupported_methods_for_new_records(): void
    {
        $resolver = app(PaymentFinancialAccountResolver::class);

        foreach ([PaymentMethods::BANK_TRANSFER, PaymentMethods::ZAIN_CASH, PaymentMethods::ORANGE_MONEY, PaymentMethods::E_WALLET, PaymentMethods::OTHER, 'bogus'] as $method) {
            try {
                $resolver->resolve($method);
                $this->fail("Method {$method} must be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment_method', $exception->errors());
            }
        }
    }

    public function test_resolver_rejects_inactive_archived_non_jod_or_missing_company_account(): void
    {
        $resolver = app(PaymentFinancialAccountResolver::class);
        $cashBox = FinancialAccount::where('code', 'CASH-BOX')->firstOrFail();

        foreach ([['is_active' => false], ['archived_at' => now()], ['currency' => 'USD']] as $state) {
            $cashBox->update($state);
            try {
                $resolver->resolve(PaymentMethods::CASH);
                $this->fail('Ineligible CASH-BOX must be rejected: '.json_encode(array_keys($state)));
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment_method', $exception->errors());
            }
            $cashBox->update(['is_active' => true, 'archived_at' => null, 'currency' => 'JOD']);
        }

        FinancialAccount::where('code', 'CLIQ')->update(['code' => 'CLIQ-OLD']);
        $this->expectException(ValidationException::class);
        $resolver->resolve(PaymentMethods::CLIQ);
    }

    public function test_payment_methods_v1_is_cash_and_cliq_while_history_labels_remain(): void
    {
        $this->assertSame(['cash', 'cliq'], PaymentMethods::v1());
        $this->assertSame(['cash', 'cliq'], array_keys(PaymentMethods::v1Labels()));
        $this->assertContains(PaymentMethods::BANK_TRANSFER, PaymentMethods::values());
    }

    public function test_new_record_payment_selector_offers_only_cash_and_cliq(): void
    {
        $admin = User::factory()->create(['role' => 'founder', 'is_active' => true]);
        $client = Client::create([
            'business_name' => 'Selector Client',
            'phone' => '0790000001',
            'city_area' => 'Amman',
            'business_category' => 'Cafe',
            'lead_source' => 'Direct',
            'status' => 'subscriber',
            'stage' => ClientLifecycle::SUBSCRIBER,
        ]);

        $html = $this->actingAs($admin)->get(route('clients.show', $client))->assertOk()->getContent();
        // P12: the method is a segmented Cash / CliQ choice inside the payment sheet (was a <select>).
        preg_match('/<div class="notify-choices notify-choices--segmented" data-payment-methods>.*?<\/div>/s', $html, $matches);

        $this->assertNotEmpty($matches, 'Payment method selector must render.');
        preg_match_all('/name="payment_method" value="([^"]*)"/', $matches[0], $values);
        $this->assertSame(['cash', 'cliq'], $values[1]);
    }
}
