<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contract lifecycle (§8, FROZEN D-10): Draft (no number) → Founder (owner-level) Issue (number, company block
 * and service names captured, snapshot frozen) → PDF → physical signatures.
 *
 * The contract is generated from the client, subscription, subscribed systems, agreed value, payment
 * schedule and company settings; nobody types contractual data into the document.
 */
class ContractService
{
    public const TEMPLATE_VERSION = '2.0';
    public const SNAPSHOT_SCHEMA = 2;

    /** Company settings printed on contracts; each line is printed only when configured. */
    public const COMPANY_FIELDS = [
        'name_ar' => 'company_name_ar',
        'name_en' => 'company_name_en',
        'address' => 'company_address',
        'phone' => 'company_phone',
        'email' => 'company_email',
        'registration_number' => 'registration_number',
        'national_number' => 'company_national_number',
        'tax_number' => 'tax_number',
        'authorized_signatory' => 'authorized_signatory',
        'logo' => 'company_logo',
    ];

    private const ISSUE_ATTEMPTS = 3;

    public function buildSnapshot(Client $client, Subscription $subscription, User $author): array
    {
        $client->loadMissing('primaryContact');
        $subscription->loadMissing(['systems', 'plan.product', 'invoices']);

        $interval = $subscription->billing_interval_v2 ?: $subscription->billing_type;
        $installments = (int) ($subscription->installments_count ?? 1);
        $schedules = DB::table('payment_schedules')
            ->where('subscription_id', $subscription->id)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($schedule) => [
                'sequence' => (int) $schedule->sequence,
                'due_date' => $schedule->due_date ? Carbon::parse($schedule->due_date)->toDateString() : null,
                'amount_minor' => $schedule->amount_due_minor !== null
                    ? (int) $schedule->amount_due_minor
                    : Money::fromJod((string) $schedule->amount_due)->minorUnits(),
            ])->values()->all();
        $firstInvoice = $subscription->invoices->sortBy('id')->first();

        return [
            'schema' => self::SNAPSHOT_SCHEMA,
            'company' => $this->companyBlock(),
            'client' => $this->clientBlock($client),
            'services' => $this->serviceDisplay($this->subscribedServices($subscription)),
            'subscription' => [
                'id' => $subscription->id,
                'billing_interval' => $interval === 'annual' ? 'annual' : 'monthly',
                'payment_terms' => $subscription->payment_terms
                    ?: ($interval === 'annual' && $installments > 1 ? 'installments' : 'full'),
                'installments_count' => $installments,
                'monthly_due_day' => (int) ($subscription->monthly_due_day ?? 1),
                'start_date' => $subscription->start_date?->toDateString() ?? $subscription->current_period_start?->toDateString(),
                'end_date' => $subscription->current_period_end?->toDateString(),
                'full_payment_date' => $schedules[0]['due_date'] ?? $firstInvoice?->due_date?->toDateString() ?? $subscription->start_date?->toDateString(),
            ],
            'pricing' => [
                'currency' => $subscription->currency ?: 'JOD',
                'agreed_value_minor' => $this->agreedValueMinor($subscription),
            ],
            'schedules' => $schedules,
            'terms' => filled(Setting::get('default_contract_terms')) ? trim((string) Setting::get('default_contract_terms')) : null,
            'metadata' => [
                'template_version' => self::TEMPLATE_VERSION,
                'contract_number' => null,
                'issued_at' => null,
                'created_at' => now()->toDateTimeString(),
                'created_by' => $author->name,
                'created_by_id' => $author->id,
            ],
        ];
    }

    /** Current Company & Contracts settings; unset fields are omitted, never replaced by placeholders. */
    public function companyBlock(): array
    {
        return collect(self::COMPANY_FIELDS)
            ->map(fn (string $setting) => trim((string) Setting::get($setting, '')))
            ->filter(fn (string $value) => $value !== '')
            ->all();
    }

    /**
     * Contract display names and short descriptions come from the current catalog (by product id),
     * so a draft shows what Issue will freeze. Custom System details stay as captured.
     */
    public function serviceDisplay(array $services): array
    {
        $products = Product::whereIn('id', collect($services)->pluck('product_id')->filter())->get()->keyBy('id');

        return collect($services)->map(function (array $service) use ($products) {
            $product = $products->get($service['product_id'] ?? null);
            if ($product === null) {
                return $service;
            }

            return array_merge($service, [
                'code' => $product->code,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'name' => $product->name_en ?: $product->name_ar,
                'description_ar' => filled($product->description_ar) ? $product->description_ar : null,
            ]);
        })->values()->all();
    }

    public function createContract(Client $client, Subscription $subscription, User $author): Contract
    {
        return $this->ensureDraftContract($client, $subscription, $author);
    }

    public function ensureDraftContract(Client $client, Subscription $subscription, User $author): Contract
    {
        return DB::transaction(function () use ($client, $subscription, $author) {
            $lockedSubscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $existing = Contract::where('subscription_id', $lockedSubscription->id)
                ->whereIn('status', ['draft', 'issued'])
                ->latest('id')
                ->first();

            return $existing ?: $this->persistDraft($client, $lockedSubscription, $author);
        });
    }

    /**
     * Founder (owner-level) Issue: exactly once, under a row lock. Re-running on an issued contract returns it unchanged.
     */
    public function issueContract(Contract $contract, User $actor): Contract
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $this->issueLocked($contract->id, $actor));
            } catch (UniqueConstraintViolationException $exception) {
                // Another Issue took the same sequence number concurrently; recompute and retry.
                if ($attempt >= self::ISSUE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /** Installment plans must add up to the agreed value exactly before a contract can be issued. */
    public function assertIssuable(array $snapshot): void
    {
        $agreed = (int) ($snapshot['pricing']['agreed_value_minor'] ?? 0);
        if ($agreed <= 0) {
            throw ValidationException::withMessages(['contract' => __('notify.contracts.errors.missing_value')]);
        }

        if (($snapshot['subscription']['billing_interval'] ?? null) === 'annual'
            && ($snapshot['subscription']['payment_terms'] ?? null) === 'installments') {
            $schedules = collect($snapshot['schedules'] ?? []);
            if ($schedules->isEmpty() || (int) $schedules->sum('amount_minor') !== $agreed) {
                throw ValidationException::withMessages(['contract' => __('notify.contracts.errors.installments_mismatch')]);
            }
        }
    }

    public function voidContract(Contract $contract, string $reason, User $actor): Contract
    {
        $contract->update(['status' => 'voided']);
        $this->log($contract, $actor, 'contract_voided', "تم إلغاء العقد {$contract->displayNumber()}: {$reason}");

        return $contract;
    }

    public function supersedeContract(Contract $oldContract, User $actor): Contract
    {
        return DB::transaction(function () use ($oldContract, $actor) {
            $newContract = $this->persistDraft($oldContract->client, $oldContract->subscription, $actor);
            $oldContract->update(['status' => 'superseded', 'superseded_by_contract_id' => $newContract->id]);
            $this->log($oldContract, $actor, 'contract_superseded', "تم استبدال العقد {$oldContract->displayNumber()} بمسودة عقد جديدة");

            return $newContract;
        });
    }

    private function issueLocked(int $contractId, User $actor): Contract
    {
        $contract = Contract::whereKey($contractId)->lockForUpdate()->firstOrFail();
        if ($contract->isIssued()) {
            return $contract;
        }
        if ($contract->status !== 'draft') {
            throw ValidationException::withMessages(['contract' => __('notify.contracts.errors.not_draft')]);
        }

        // Pricing/subscription data stays as captured at draft creation; legacy drafts are rebuilt once.
        $snapshot = ($contract->snapshot_data['schema'] ?? null) === self::SNAPSHOT_SCHEMA
            ? $contract->snapshot_data
            : $this->buildSnapshot($contract->client, $contract->subscription, $actor);
        $this->assertIssuable($snapshot);

        $issuedAt = now();
        $number = $contract->contract_number ?: $this->nextContractNumber($issuedAt);
        $snapshot['company'] = $this->companyBlock();
        $snapshot['services'] = $this->serviceDisplay($snapshot['services'] ?? []);
        $snapshot['metadata'] = array_merge($snapshot['metadata'] ?? [], [
            'template_version' => self::TEMPLATE_VERSION,
            'contract_number' => $number,
            'issued_at' => $issuedAt->toDateTimeString(),
            'issued_by' => $actor->name,
            'issued_by_id' => $actor->id,
        ]);

        $contract->update([
            'contract_number' => $number,
            'status' => 'issued',
            'issued_at' => $issuedAt,
            'template_version' => self::TEMPLATE_VERSION,
            'snapshot_data' => $snapshot,
            'file_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE)),
        ]);
        $this->log($contract, $actor, 'contract_issued', "تم إصدار العقد الرسمي رقم {$number}");

        return $contract->fresh();
    }

    /** `{prefix}-{YYYY}-{NNNN}`, sequential per year; drafts never consume a number. */
    private function nextContractNumber(Carbon $issuedAt): string
    {
        $prefix = strtoupper(trim((string) Setting::get('contract_prefix', 'ND'))) ?: 'ND';
        $year = $issuedAt->format('Y');
        $pattern = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)$/';

        $last = Contract::where('contract_number', 'like', $prefix.'-'.$year.'-%')
            ->lockForUpdate()
            ->pluck('contract_number')
            ->map(fn (string $number) => preg_match($pattern, $number, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return sprintf('%s-%s-%04d', $prefix, $year, $last + 1);
    }

    private function persistDraft(Client $client, Subscription $subscription, User $author): Contract
    {
        return Contract::create([
            'contract_number' => null,
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'generated_by' => $author->id,
            'template_version' => self::TEMPLATE_VERSION,
            'status' => 'draft',
            'legal_review_status' => 'pending',
            'snapshot_data' => $this->buildSnapshot($client, $subscription, $author),
            'private_file_path' => null,
            'file_hash' => null,
            'page_count' => null,
            'issued_at' => null,
        ]);
    }

    private function clientBlock(Client $client): array
    {
        $contact = $client->primaryContact;
        $phone = $client->business_phone ?: $client->phone;
        $contactPhone = $contact?->primary_phone ?: $contact?->secondary_phone;
        $city = $client->city_area ?: collect([$client->city, $client->area])->filter()->join('، ');

        return array_filter([
            'business_name' => $client->business_name,
            'phone' => $phone,
            'city_area' => $city,
            'address' => $client->location_text,
            'contact_name' => $contact?->name ?: $client->contact_person,
            'contact_phone' => $contactPhone !== $phone ? $contactPhone : null,
        ], fn ($value) => filled($value));
    }

    private function subscribedServices(Subscription $subscription): array
    {
        if ($subscription->systems->isNotEmpty()) {
            return $subscription->systems->map(fn (Product $system) => [
                'product_id' => $system->id,
                'code' => $system->pivot->system_code_snapshot,
                'name_ar' => $system->pivot->system_name_ar_snapshot,
                'name_en' => $system->pivot->system_name_en_snapshot,
                'name' => $system->pivot->system_name_en_snapshot ?: $system->pivot->system_name_ar_snapshot,
                'description_ar' => null,
                'custom_title' => $system->pivot->custom_title,
                'custom_description' => $system->pivot->custom_description,
            ])->values()->all();
        }

        // Pre-V1 plan subscriptions: the plan's product is the subscribed service.
        $product = $subscription->plan?->product;

        return $product === null ? [] : [[
            'product_id' => $product->id,
            'code' => $product->code,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'name' => $product->name_en ?: $product->name_ar,
            'description_ar' => null,
            'custom_title' => null,
            'custom_description' => null,
        ]];
    }

    private function agreedValueMinor(Subscription $subscription): int
    {
        if ($subscription->agreed_value_minor !== null) {
            return (int) $subscription->agreed_value_minor;
        }
        if ($subscription->total_minor !== null) {
            return (int) $subscription->total_minor;
        }

        return Money::fromJod((string) ($subscription->grand_total ?? $subscription->total_price ?? '0'))->minorUnits();
    }

    private function log(Contract $contract, User $actor, string $type, string $description): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => $contract->client_id,
            'user_id' => $actor->id,
            'type' => $type,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
