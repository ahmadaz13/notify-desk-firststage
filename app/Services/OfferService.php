<?php

namespace App\Services;

use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OfferService
{
    /**
     * Create a commercial offer for a client and append to timeline.
     *
     * @param int $clientId
     * @param int $userId
     * @param array $data
     * @return int Created offer ID
     */
    public function createOffer(int $clientId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($clientId, $userId, $data) {
            $client = DB::table('clients')->where('id', $clientId)->first();
            abort_unless($client, 404, 'العميل غير موجود');

            $priceMinor = Money::fromJod((string) $data['price'])->minorUnits();
            $discountMinor = Money::fromJod((string) ($data['discount'] ?? '0'))->minorUnits();
            $finalPriceMinor = isset($data['final_agreed_price'])
                ? Money::fromJod((string) $data['final_agreed_price'])->minorUnits()
                : max(0, $priceMinor - $discountMinor);
            $price = Money::fromMinorUnits($priceMinor)->format();
            $discount = Money::fromMinorUnits($discountMinor)->format();
            $finalPrice = Money::fromMinorUnits($finalPriceMinor)->format();

            $offerDate = !empty($data['offer_date'])
                ? Carbon::parse($data['offer_date'])->toDateString()
                : now()->toDateString();

            $decisionDeadline = !empty($data['decision_deadline'])
                ? Carbon::parse($data['decision_deadline'])->toDateString()
                : null;

            $id = DB::table('commercial_offers')->insertGetId([
                'client_id' => $clientId,
                'user_id' => $userId,
                'package' => $data['package'],
                'billing_period' => $data['billing_period'] ?? 'monthly',
                'price' => $price,
                'discount' => $discount,
                'final_agreed_price' => $finalPrice,
                'offer_date' => $offerDate,
                'decision_deadline' => $decisionDeadline,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Append to activity logs
            DB::table('activity_logs')->insert([
                'client_id' => $clientId,
                'user_id' => $userId,
                'type' => 'offer_created',
                'description' => "تم تقديم عرض تجاري ({$data['package']} - السعر الصافي: {$finalPrice} د.أ)",
                'metadata' => json_encode([
                    'offer_id' => $id,
                    'package' => $data['package'],
                    'price' => $price,
                    'discount' => $discount,
                    'final_price' => $finalPrice,
                    'decision_deadline' => $decisionDeadline,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        });
    }
}
