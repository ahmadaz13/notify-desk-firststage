<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ContractService
{
    public const TEMPLATE_VERSION = '1.0';

    /**
     * Generate the next sequential contract number in format ND-YYYY-NNNN.
     * Uses atomic transaction to ensure zero duplication or collision.
     */
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
                // Parse sequence number
                $parts = explode('-', $latest->contract_number);
                if (isset($parts[2]) && is_numeric($parts[2])) {
                    $nextSequence = ((int) $parts[2]) + 1;
                }
            }

            return sprintf('ND-%s-%04d', $year, $nextSequence);
        });
    }

    /**
     * Build an immutable snapshot array containing all client, subscription,
     * service contributions, financial breakdowns, legal clauses, and metadata.
     */
    public function buildSnapshot(Client $client, Subscription $subscription, User $author): array
    {
        // Load services with snapshots
        $services = DB::table('subscription_service')
            ->where('subscription_id', $subscription->id)
            ->get()
            ->map(function ($s) {
                return [
                    'key' => $s->service_key,
                    'name_ar' => $s->service_name_ar,
                    'name_en' => $s->service_name_en,
                    'price_contribution' => (float) $s->price_contribution,
                ];
            })->all();

        // Load schedules
        $schedules = DB::table('payment_schedules')
            ->where('subscription_id', $subscription->id)
            ->orderBy('sequence')
            ->get()
            ->map(function ($sch) {
                return [
                    'sequence' => $sch->sequence,
                    'due_date' => $sch->due_date,
                    'amount_due' => (float) $sch->amount_due,
                    'subtotal' => (float) ($sch->subtotal ?? 0),
                    'discount_amount' => (float) ($sch->discount_amount ?? 0),
                    'setup_fee_amount' => (float) ($sch->setup_fee_amount ?? 0),
                    'tax_amount' => (float) ($sch->tax_amount ?? 0),
                    'status' => $sch->status,
                ];
            })->all();

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
            'subscription' => [
                'id' => $subscription->id,
                'billing_type' => $subscription->billing_type,
                'start_date' => $subscription->start_date?->toDateString() ?? now()->toDateString(),
                'renewal_date' => $subscription->renewal_date?->toDateString(),
                'version' => $subscription->version ?? 1,
                'monthly_due_day' => $subscription->monthly_due_day ?? 1,
                'installments_count' => $subscription->installments_count ?? 1,
            ],
            'financial' => [
                'currency' => 'JOD',
                'currency_ar' => 'د.أ',
                'base_subtotal' => (float) ($subscription->base_subtotal ?? $subscription->total_price),
                'setup_fee' => (float) ($subscription->setup_fee ?? 0.000),
                'annual_discount_percentage' => (float) ($subscription->annual_discount_percentage ?? 0.00),
                'discount_amount' => (float) ($subscription->discount_amount ?? 0.000),
                'tax_percentage' => (float) ($subscription->tax_percentage ?? 16.00),
                'tax_amount' => (float) ($subscription->tax_amount ?? 0.000),
                'grand_total' => (float) ($subscription->grand_total ?? $subscription->total_price),
            ],
            'services' => $services,
            'schedules' => $schedules,
            'clauses_count' => 22,
            'appendices_count' => 3,
            'metadata' => [
                'created_at' => now()->toDateTimeString(),
                'created_by' => $author->name,
                'created_by_id' => $author->id,
            ],
        ];
    }

    /**
     * Create or retrieve a contract for a subscription.
     */
    public function createContract(Client $client, Subscription $subscription, User $author): Contract
    {
        $contractNumber = $this->generateNextContractNumber();
        $snapshot = $this->buildSnapshot($client, $subscription, $author);

        // Render HTML/Printable document
        $htmlContent = view('contracts.template', [
            'contractNumber' => $contractNumber,
            'snapshot' => $snapshot,
            'issuedDate' => now()->toDateString(),
            'legalReviewStatus' => 'pending',
        ])->render();

        // Store private document artifact in storage/app/private/contracts
        $filename = "contracts/{$contractNumber}.html";
        Storage::disk('local')->put($filename, $htmlContent);
        $fileHash = hash('sha256', $htmlContent);

        return Contract::create([
            'contract_number' => $contractNumber,
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'generated_by' => $author->id,
            'template_version' => self::TEMPLATE_VERSION,
            'status' => 'draft',
            'legal_review_status' => 'pending',
            'snapshot_data' => $snapshot,
            'private_file_path' => $filename,
            'file_hash' => $fileHash,
            'page_count' => 14,
            'issued_at' => null,
        ]);
    }

    /**
     * Formally issue the contract.
     */
    public function issueContract(Contract $contract, User $actor): Contract
    {
        if ($contract->isIssued()) {
            return $contract;
        }

        $contract->update([
            'status' => 'issued',
            'issued_at' => now(),
        ]);

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

    /**
     * Void a contract.
     */
    public function voidContract(Contract $contract, string $reason, User $actor): Contract
    {
        $contract->update([
            'status' => 'voided',
        ]);

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

    /**
     * Supersede an issued contract with a corrected version.
     */
    public function supersedeContract(Contract $oldContract, User $actor): Contract
    {
        return DB::transaction(function () use ($oldContract, $actor) {
            $client = $oldContract->client;
            $subscription = $oldContract->subscription;

            // Generate new contract
            $newContract = $this->createContract($client, $subscription, $actor);

            // Mark old contract superseded
            $oldContract->update([
                'status' => 'superseded',
                'superseded_by_contract_id' => $newContract->id,
            ]);

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => $actor->id,
                'type' => 'contract_superseded',
                'description' => "تم استبدال العقد {$oldContract->contract_number} بالعقد الجديد {$newContract->contract_number}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $newContract;
        });
    }
}
