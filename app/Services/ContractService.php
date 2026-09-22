<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ContractService
{
    public const TEMPLATE_VERSION = '1.1';

    public function generateNextContractNumber(): string
    {
        $year = now()->format('Y');

        return DB::transaction(function () use ($year) {
            $latest = Contract::where('contract_number', 'like', "ND-{$year}-%")
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $nextSequence = 1;
            if ($latest) {
                $parts = explode('-', $latest->contract_number);
                if (isset($parts[2]) && is_numeric($parts[2])) {
                    $nextSequence = ((int) $parts[2]) + 1;
                }
            }

            return sprintf('ND-%s-%04d', $year, $nextSequence);
        });
    }

    public function buildSnapshot(Client $client, Subscription $subscription, User $author, ?string $contractNumber = null): array
    {
        $subscription->loadMissing(['plan.product', 'plan.services', 'systems', 'invoices']);
        $isV2 = $subscription->billing_engine_version === 'v2' && $subscription->plan !== null;
        $usesMinorUnits = in_array($subscription->billing_engine_version, ['v2', 'v1_simple'], true);

        $services = $isV2
            ? $subscription->plan->services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'key' => $service->key,
                    'code' => $service->key,
                    'name_ar' => $service->name_ar,
                    'name_en' => $service->name_en,
                    'sort_order' => (int) ($service->pivot->sort_order ?? $service->sort_order ?? 0),
                    'notes' => $service->pivot->notes ?? null,
                    'price_contribution' => null,
                    'source' => 'plan_service',
                ];
            })->values()->all()
            : ($subscription->billing_engine_version === 'v1_simple' ? collect() : DB::table('subscription_service')
                ->where('subscription_id', $subscription->id)
                ->get()
                ->map(function ($service) {
                    return [
                        'id' => $service->service_id,
                        'key' => $service->service_key,
                        'code' => $service->service_key,
                        'name_ar' => $service->service_name_ar,
                        'name_en' => $service->service_name_en,
                        'sort_order' => 0,
                        'notes' => null,
                        'price_contribution' => (float) $service->price_contribution,
                        'source' => 'subscription_service',
                    ];
                }))->all();

        $schedules = DB::table('payment_schedules')
            ->where('subscription_id', $subscription->id)
            ->orderBy('sequence')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'invoice_id' => $schedule->invoice_id ?? null,
                    'schedule_engine_version' => $schedule->schedule_engine_version ?? null,
                    'sequence' => $schedule->sequence,
                    'due_date' => $schedule->due_date,
                    'amount_due' => (float) $schedule->amount_due,
                    'amount_due_minor' => $schedule->amount_due_minor !== null ? (int) $schedule->amount_due_minor : null,
                    'subtotal' => (float) ($schedule->subtotal ?? 0),
                    'discount_amount' => (float) ($schedule->discount_amount ?? 0),
                    'setup_fee_amount' => (float) ($schedule->setup_fee_amount ?? 0),
                    'tax_amount' => (float) ($schedule->tax_amount ?? 0),
                    'status' => $schedule->status,
                ];
            })->all();

        $initialInvoice = $subscription->invoices
            ->sortBy(fn ($invoice) => ($invoice->issue_date?->format('Y-m-d') ?? '').str_pad((string) $invoice->id, 20, '0', STR_PAD_LEFT))
            ->first();
        $plan = $subscription->plan;
        $product = $plan?->product;

        $financial = $usesMinorUnits
            ? [
                'currency' => $subscription->currency ?: 'JOD',
                'currency_ar' => 'د.أ',
                'base_subtotal' => $this->majorUnits((int) $subscription->subtotal_minor),
                'setup_fee' => $this->majorUnits((int) $subscription->setup_fee_minor_v2),
                'annual_discount_percentage' => 0.0,
                'discount_amount' => $this->majorUnits((int) $subscription->discount_minor),
                'tax_percentage' => ((int) ($subscription->tax_rate_bps ?? 0)) / 100,
                'tax_amount' => $this->majorUnits((int) $subscription->tax_minor_v2),
                'grand_total' => $this->majorUnits((int) $subscription->total_minor),
            ]
            : [
                'currency' => 'JOD',
                'currency_ar' => 'د.أ',
                'base_subtotal' => (float) ($subscription->base_subtotal ?? $subscription->total_price),
                'setup_fee' => (float) ($subscription->setup_fee ?? 0.000),
                'annual_discount_percentage' => (float) ($subscription->annual_discount_percentage ?? 0.00),
                'discount_amount' => (float) ($subscription->discount_amount ?? 0.000),
                'tax_percentage' => (float) ($subscription->tax_percentage ?? 16.00),
                'tax_amount' => (float) ($subscription->tax_amount ?? 0.000),
                'grand_total' => (float) ($subscription->grand_total ?? $subscription->total_price),
            ];

        return [
            'provider' => [
                'name' => 'Notify',
                'name_ar' => 'منظومة Notify لإدارة العمليات والاتصالات الذكية',
                'country' => 'المملكة الأردنية الهاشمية',
                'city' => 'عمان',
                'email' => 'support@notify.local',
                'legal_notice' => 'مسودة تشغيلية للمراجعة القانونية والاعتماد الداخلي',
            ],
            'client' => [
                'id' => $client->id,
                'business_name' => $client->business_name,
                'phone' => $client->phone,
                'contact_person' => $client->contact_person,
                'city_area' => $client->city_area,
                'business_category' => $client->business_category,
            ],
            'product' => [
                'id' => $product?->id,
                'code' => $product?->code,
                'name_ar' => $product?->name_ar,
                'name_en' => $product?->name_en,
            ],
            'systems' => $subscription->systems->map(fn ($system) => [
                'id' => $system->id,
                'code' => $system->pivot->system_code_snapshot,
                'name_ar' => $system->pivot->system_name_ar_snapshot,
                'name_en' => $system->pivot->system_name_en_snapshot,
            ])->values()->all(),
            'package' => [
                'plan_id' => $subscription->plan_id,
                'plan_code_snapshot' => $subscription->plan_code_snapshot ?: $plan?->code,
                'plan_name_snapshot' => $subscription->plan_name_snapshot ?: $plan?->name_ar,
                'name_ar' => $subscription->plan_name_snapshot ?: $plan?->name_ar,
                'name_en' => $plan?->name_en,
                'tier' => $plan?->tier,
                'offer_type' => $plan?->offer_type,
            ],
            'pricing' => [
                'plan_price_id' => $subscription->plan_price_id,
                'billing_interval' => $subscription->billing_interval_v2 ?: $subscription->billing_type,
                'currency' => $subscription->currency ?: 'JOD',
                'quantity' => (int) ($subscription->quantity ?: 1),
                'unit_price_minor' => $subscription->unit_price_minor !== null ? (int) $subscription->unit_price_minor : null,
                'setup_fee_minor' => $subscription->setup_fee_minor_v2 !== null ? (int) $subscription->setup_fee_minor_v2 : null,
                'subtotal_minor' => $subscription->subtotal_minor !== null ? (int) $subscription->subtotal_minor : null,
                'discount_minor' => $subscription->discount_minor !== null ? (int) $subscription->discount_minor : null,
                'tax_rate_bps' => $subscription->tax_rate_bps !== null ? (int) $subscription->tax_rate_bps : null,
                'tax_minor' => $subscription->tax_minor_v2 !== null ? (int) $subscription->tax_minor_v2 : null,
                'total_minor' => $subscription->total_minor !== null ? (int) $subscription->total_minor : null,
                'agreed_value_minor' => $subscription->agreed_value_minor !== null ? (int) $subscription->agreed_value_minor : null,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'billing_type' => $subscription->billing_type,
                'billing_engine_version' => $subscription->billing_engine_version,
                'start_date' => $subscription->start_date?->toDateString() ?? now()->toDateString(),
                'current_period_start' => $subscription->current_period_start?->toDateString(),
                'current_period_end' => $subscription->current_period_end?->toDateString(),
                'next_billing_date' => $subscription->next_billing_date?->toDateString(),
                'renewal_date' => $subscription->renewal_date?->toDateString(),
                'version' => $subscription->version ?? 1,
                'monthly_due_day' => $subscription->monthly_due_day ?? 1,
                'installments_count' => $subscription->installments_count ?? 1,
                'payment_terms' => $subscription->payment_terms
                    ?: ($subscription->billing_interval_v2 === 'annual' && (int) $subscription->installments_count > 1 ? 'installments' : 'full'),
            ],
            'invoice' => $initialInvoice ? [
                'id' => $initialInvoice->id,
                'invoice_number' => $initialInvoice->invoice_number,
                'subtotal_minor' => (int) $initialInvoice->subtotal_minor,
                'discount_minor' => (int) $initialInvoice->discount_minor,
                'tax_minor' => (int) $initialInvoice->tax_minor,
                'total_minor' => (int) $initialInvoice->total_minor,
            ] : null,
            'financial' => $financial,
            'services' => $services,
            'schedules' => $schedules,
            'clauses_count' => 22,
            'appendices_count' => 3,
            'metadata' => [
                'contract_number' => $contractNumber,
                'created_at' => now()->toDateTimeString(),
                'created_by' => $author->name,
                'created_by_id' => $author->id,
            ],
        ];
    }

    public function createContract(Client $client, Subscription $subscription, User $author): Contract
    {
        $contract = $this->persistDraft($client, $subscription, $author);
        $this->generateArtifact($contract);

        return $contract->fresh();
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

    public function generateArtifact(Contract $contract): Contract
    {
        $path = $contract->private_file_path ?: "contracts/{$contract->contract_number}.html";
        $html = view('contracts.template', [
            'contract' => $contract,
            'contractNumber' => $contract->contract_number,
            'snapshot' => $contract->snapshot_data,
            'issuedDate' => $contract->issued_at?->toDateString() ?? $contract->created_at->toDateString(),
            'legalReviewStatus' => $contract->legal_review_status,
            'autoPrint' => false,
        ])->render();

        if (! Storage::disk('local')->put($path, $html)) {
            throw new RuntimeException("Unable to store contract artifact for contract {$contract->id}.");
        }

        $contract->update([
            'private_file_path' => $path,
            'file_hash' => hash('sha256', $html),
        ]);

        return $contract->fresh();
    }

    public function issueContract(Contract $contract, User $actor): Contract
    {
        if ($contract->isIssued()) {
            return $contract;
        }

        $contract->update(['status' => 'issued', 'issued_at' => now()]);

        DB::table('activity_logs')->insert([
            'client_id' => $contract->client_id,
            'user_id' => $actor->id,
            'type' => 'contract_issued',
            'description' => "تم إصدار العقد الرسمي رقم {$contract->contract_number} بنجاح",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $contract;
    }

    public function voidContract(Contract $contract, string $reason, User $actor): Contract
    {
        $contract->update(['status' => 'voided']);

        DB::table('activity_logs')->insert([
            'client_id' => $contract->client_id,
            'user_id' => $actor->id,
            'type' => 'contract_voided',
            'description' => "تم إلغاء (Void) العقد رقم {$contract->contract_number}: {$reason}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $contract;
    }

    public function supersedeContract(Contract $oldContract, User $actor): Contract
    {
        $newContract = DB::transaction(function () use ($oldContract, $actor) {
            $newContract = $this->persistDraft($oldContract->client, $oldContract->subscription, $actor);

            $oldContract->update([
                'status' => 'superseded',
                'superseded_by_contract_id' => $newContract->id,
            ]);

            DB::table('activity_logs')->insert([
                'client_id' => $oldContract->client_id,
                'user_id' => $actor->id,
                'type' => 'contract_superseded',
                'description' => "تم استبدال العقد {$oldContract->contract_number} بالعقد الجديد {$newContract->contract_number}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $newContract;
        });

        $this->generateArtifact($newContract);

        return $newContract->fresh();
    }

    private function persistDraft(Client $client, Subscription $subscription, User $author): Contract
    {
        $contractNumber = $this->generateNextContractNumber();

        return Contract::create([
            'contract_number' => $contractNumber,
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'generated_by' => $author->id,
            'template_version' => self::TEMPLATE_VERSION,
            'status' => 'draft',
            'legal_review_status' => 'pending',
            'snapshot_data' => $this->buildSnapshot($client, $subscription, $author, $contractNumber),
            'private_file_path' => "contracts/{$contractNumber}.html",
            'file_hash' => null,
            'page_count' => 14,
            'issued_at' => null,
        ]);
    }

    private function majorUnits(int $minorUnits): float
    {
        return (float) Money::fromMinorUnits($minorUnits)->format();
    }
}
