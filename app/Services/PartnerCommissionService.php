<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\Partner;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnerCommissionService
{
    public function summary(Partner $partner): array
    {
        $clients = Client::query()
            ->with(['partnerAttribution.partner'])
            ->where(function ($query) use ($partner) {
                $query->whereHas('partnerAttribution', fn ($attribution) => $attribution->where('partner_id', $partner->id))
                    ->orWhere(function ($legacy) use ($partner) {
                        $legacy->whereDoesntHave('partnerAttribution')->where('partner_id', $partner->id);
                    });
            })
            ->orderBy('business_name')
            ->get();

        $clientIds = $clients->pluck('id');
        $netCollected = $this->netCollectedByClient($clientIds);
        $activeSubscriberIds = DB::table('subscriptions')
            ->whereIn('client_id', $clientIds)
            ->where('billing_engine_version', 'v2')
            ->where('status', 'active')
            ->distinct()
            ->pluck('client_id');

        $rows = $clients->map(function (Client $client) use ($netCollected, $activeSubscriberIds) {
            $snapshotBps = $client->partnerAttribution?->commission_bps_snapshot;
            $netMinor = (int) ($netCollected[$client->id] ?? 0);
            $commissionMinor = $snapshotBps === null ? null : $this->commissionMinor($netMinor, $snapshotBps);

            return [
                'client' => $client,
                'is_active_subscriber' => $activeSubscriberIds->contains($client->id),
                'commission_bps_snapshot' => $snapshotBps,
                'commission_percentage' => $snapshotBps === null ? null : $this->formatBps($snapshotBps),
                'net_collected_minor' => $netMinor,
                'net_collected' => Money::fromMinorUnits($netMinor)->format(),
                'commission_minor' => $commissionMinor,
                'commission' => $commissionMinor === null ? null : Money::fromMinorUnits($commissionMinor)->format(),
                'is_legacy_attribution' => $client->partnerAttribution === null,
            ];
        });

        $knownCommission = $rows->whereNotNull('commission_minor');

        return [
            'total_clients' => $clients->count(),
            'active_subscribers' => $activeSubscriberIds->count(),
            'net_collected_minor' => $rows->sum('net_collected_minor'),
            'net_collected' => Money::fromMinorUnits((int) $rows->sum('net_collected_minor'))->format(),
            'commission_minor' => $knownCommission->sum('commission_minor'),
            'commission' => Money::fromMinorUnits((int) $knownCommission->sum('commission_minor'))->format(),
            'has_legacy_attributions' => $rows->contains('is_legacy_attribution', true),
            'clients' => $rows,
        ];
    }

    public function commissionMinor(int $eligibleNetCollectedMinor, int $commissionBps): int
    {
        if ($eligibleNetCollectedMinor <= 0 || $commissionBps <= 0) {
            return 0;
        }

        return intdiv(($eligibleNetCollectedMinor * $commissionBps) + 5000, 10000);
    }

    private function netCollectedByClient(Collection $clientIds): Collection
    {
        if ($clientIds->isEmpty()) {
            return collect();
        }

        $receipts = DB::table('cash_movements')
            ->join('payments', 'payments.id', '=', 'cash_movements.source_id')
            ->where('cash_movements.source_type', Payment::class)
            ->where('cash_movements.event_type', CashMovement::EVENT_PAYMENT_RECEIVED)
            ->whereIn('payments.client_id', $clientIds)
            ->selectRaw('payments.client_id, SUM(cash_movements.amount_minor) as total_minor')
            ->groupBy('payments.client_id')
            ->pluck('total_minor', 'payments.client_id');

        $reversals = DB::table('cash_movements')
            ->join('payment_reversals', 'payment_reversals.id', '=', 'cash_movements.source_id')
            ->join('payments', 'payments.id', '=', 'payment_reversals.payment_id')
            ->where('cash_movements.source_type', PaymentReversal::class)
            ->where('cash_movements.event_type', CashMovement::EVENT_PAYMENT_REVERSAL)
            ->whereIn('payments.client_id', $clientIds)
            ->selectRaw('payments.client_id, SUM(cash_movements.amount_minor) as total_minor')
            ->groupBy('payments.client_id')
            ->pluck('total_minor', 'payments.client_id');

        $refunds = DB::table('cash_movements')
            ->join('refunds', 'refunds.id', '=', 'cash_movements.source_id')
            ->where('cash_movements.source_type', Refund::class)
            ->where('cash_movements.event_type', CashMovement::EVENT_REFUND_ISSUED)
            ->whereIn('refunds.client_id', $clientIds)
            ->selectRaw('refunds.client_id, SUM(cash_movements.amount_minor) as total_minor')
            ->groupBy('refunds.client_id')
            ->pluck('total_minor', 'refunds.client_id');

        return $clientIds->mapWithKeys(function ($clientId) use ($receipts, $reversals, $refunds) {
            $net = (int) ($receipts[$clientId] ?? 0)
                - (int) ($reversals[$clientId] ?? 0)
                - (int) ($refunds[$clientId] ?? 0);

            return [$clientId => max($net, 0)];
        });
    }

    private function formatBps(int $bps): string
    {
        return sprintf('%d.%02d%%', intdiv($bps, 100), $bps % 100);
    }
}
