<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPartnerAttribution;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientPartnerAttributionService
{
    public function assign(
        Client $client,
        Partner $partner,
        ?int $commissionBps = null,
        ?string $notes = null,
        ?int $userId = null
    ): ClientPartnerAttribution {
        return DB::transaction(function () use ($client, $partner, $commissionBps, $notes, $userId) {
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $partner->refresh();

            if (! $partner->isActive()) {
                throw ValidationException::withMessages([
                    'partner_id' => 'لا يمكن إسناد عميل إلى شريك مؤرشف.',
                ]);
            }

            $snapshotBps = $commissionBps ?? $partner->effectiveDefaultCommissionBps() ?? 0;
            if ($snapshotBps < 0 || $snapshotBps > 10000) {
                throw ValidationException::withMessages([
                    'partner_commission_percentage' => 'يجب أن تكون نسبة العمولة بين 0 و100%.',
                ]);
            }

            $attribution = ClientPartnerAttribution::firstOrNew(['client_id' => $client->id]);
            $isNewAgreement = ! $attribution->exists || (int) $attribution->partner_id !== (int) $partner->id;
            $attribution->fill([
                'partner_id' => $partner->id,
                'commission_bps_snapshot' => $snapshotBps,
                'notes' => filled($notes) ? trim($notes) : null,
            ]);
            if ($isNewAgreement) {
                $attribution->attributed_at = now();
                $attribution->created_by = $userId;
            }
            $attribution->save();

            if ((int) $client->partner_id !== (int) $partner->id) {
                $client->update(['partner_id' => $partner->id]);
            }

            return $attribution->fresh(['partner']);
        });
    }

    public function clear(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $client->partnerAttribution()->delete();
            $client->update(['partner_id' => null]);
        });
    }
}
