<?php

namespace App\Support;

use App\Models\Contract;
use App\Services\ContractService;

/**
 * View data for the contract document (§8.4), shared by the browser preview and the PDF.
 *
 * Issued contracts render only their frozen snapshot. Unissued drafts show the current company
 * settings and catalog names, i.e. exactly what Issue will freeze. Pre-V1 snapshots (no `schema`)
 * are normalized here so they stay readable (legacy fallback).
 */
final class ContractDocument
{
    public static function for(Contract $contract): array
    {
        $snapshot = $contract->snapshot_data ?? [];
        $data = ($snapshot['schema'] ?? null) === ContractService::SNAPSHOT_SCHEMA ? $snapshot : self::fromLegacy($snapshot);
        $frozen = $contract->issued_at !== null;

        if (! $frozen) {
            $service = app(ContractService::class);
            $data['company'] = $service->companyBlock();
            $data['services'] = $service->serviceDisplay($data['services'] ?? []);
        }

        $subscription = $data['subscription'] ?? [];
        $isAnnual = ($subscription['billing_interval'] ?? 'monthly') === 'annual';
        $schedules = collect($data['schedules'] ?? [])->sortBy('sequence')->values();
        $isInstallments = $isAnnual && ($subscription['payment_terms'] ?? 'full') === 'installments' && $schedules->count() > 1;

        return [
            'status' => $contract->status,
            'is_draft' => ! $frozen,
            'is_voided' => $contract->isVoided(),
            'number' => $frozen ? $contract->contract_number : null,
            'date' => ($contract->issued_at ?? $contract->created_at)?->format('Y-m-d'),
            'company' => $data['company'] ?? [],
            'client' => array_filter($data['client'] ?? [], fn ($value) => filled($value)),
            'services' => $data['services'] ?? [],
            'is_annual' => $isAnnual,
            'is_installments' => $isInstallments,
            'installments_count' => $isInstallments ? $schedules->count() : 0,
            'start_date' => $subscription['start_date'] ?? null,
            'end_date' => $isAnnual ? ($subscription['end_date'] ?? null) : null,
            'monthly_due_day' => (int) ($subscription['monthly_due_day'] ?? 1),
            'full_payment_date' => $subscription['full_payment_date'] ?? ($subscription['start_date'] ?? null),
            'agreed_value' => Money::fromMinorUnits((int) ($data['pricing']['agreed_value_minor'] ?? 0))->format(),
            'schedules' => $isInstallments ? $schedules->map(fn (array $row) => [
                'sequence' => $row['sequence'],
                'due_date' => $row['due_date'],
                'amount' => Money::fromMinorUnits((int) $row['amount_minor'])->format(),
            ])->all() : [],
            'terms' => filled($data['terms'] ?? null) ? $data['terms'] : null,
        ];
    }

    /** Maps a pre-V1 snapshot (provider/financial keys) to the V1 document shape; placeholders are dropped. */
    private static function fromLegacy(array $snapshot): array
    {
        $provider = $snapshot['provider'] ?? [];
        $placeholders = ['support@notify.local', 'مسودة تشغيلية للمراجعة القانونية والاعتماد الداخلي'];
        $company = collect([
            'name_ar' => $provider['name_ar'] ?? null,
            'name_en' => $provider['name'] ?? null,
            'address' => $provider['address'] ?? null,
            'phone' => $provider['phone'] ?? null,
            'email' => $provider['email'] ?? null,
            'registration_number' => $provider['registration_number'] ?? null,
            'tax_number' => $provider['tax_number'] ?? null,
            'authorized_signatory' => $provider['authorized_signatory'] ?? null,
        ])->filter(fn ($value) => filled($value) && ! in_array($value, $placeholders, true))->all();

        $systems = collect($snapshot['systems'] ?? []);
        $services = $systems->isNotEmpty()
            ? $systems->map(fn (array $system) => [
                'product_id' => $system['id'] ?? null,
                'name' => ($system['name_en'] ?? null) ?: ($system['name_ar'] ?? ''),
                'description_ar' => null,
            ])->all()
            : array_values(array_filter([filled($snapshot['product']['name_ar'] ?? null) || filled($snapshot['product']['name_en'] ?? null) ? [
                'product_id' => $snapshot['product']['id'] ?? null,
                'name' => ($snapshot['product']['name_en'] ?? null) ?: $snapshot['product']['name_ar'],
                'description_ar' => null,
            ] : null]));

        $pricing = $snapshot['pricing'] ?? [];
        $agreed = $pricing['agreed_value_minor'] ?? $pricing['total_minor']
            ?? Money::fromJod((string) ($snapshot['financial']['grand_total'] ?? '0'))->minorUnits();
        $client = $snapshot['client'] ?? [];

        return [
            'company' => $company,
            'client' => [
                'business_name' => $client['business_name'] ?? null,
                'phone' => $client['phone'] ?? null,
                'city_area' => $client['city_area'] ?? null,
                'contact_name' => $client['contact_person'] ?? null,
            ],
            'services' => $services,
            'subscription' => [
                'billing_interval' => ($pricing['billing_interval'] ?? $snapshot['subscription']['billing_type'] ?? 'monthly') === 'annual' ? 'annual' : 'monthly',
                'payment_terms' => $snapshot['subscription']['payment_terms'] ?? 'full',
                'monthly_due_day' => $snapshot['subscription']['monthly_due_day'] ?? 1,
                'start_date' => $snapshot['subscription']['start_date'] ?? null,
                'end_date' => $snapshot['subscription']['current_period_end'] ?? null,
            ],
            'pricing' => ['agreed_value_minor' => (int) $agreed],
            'schedules' => collect($snapshot['schedules'] ?? [])->map(fn (array $row) => [
                'sequence' => $row['sequence'] ?? 0,
                'due_date' => $row['due_date'] ?? null,
                'amount_minor' => $row['amount_due_minor'] ?? Money::fromJod((string) ($row['amount_due'] ?? '0'))->minorUnits(),
            ])->all(),
            'terms' => null,
        ];
    }
}
