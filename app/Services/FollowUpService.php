<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FollowUpService
{
    /**
     * Create a follow-up record for a client and append to timeline.
     *
     * @param int $clientId
     * @param int $userId
     * @param array $data
     * @return int Created follow up ID
     */
    public function createFollowUp(int $clientId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($clientId, $userId, $data) {
            $client = DB::table('clients')->where('id', $clientId)->first();
            abort_unless($client, 404, 'العميل غير موجود');

            $followUpDateTime = !empty($data['follow_up_date_time'])
                ? Carbon::parse($data['follow_up_date_time'])
                : now();

            $id = DB::table('follow_ups')->insertGetId([
                'client_id' => $clientId,
                'user_id' => $userId,
                'method' => $data['method'] ?? 'phone_call',
                'reason' => $data['reason'] ?? 'متابعة دورية',
                'result' => $data['result'] ?? null,
                'next_action' => $data['next_action'],
                'next_follow_up_date' => Carbon::parse($data['next_follow_up_date'])->toDateString(),
                'follow_up_date_time' => $followUpDateTime->toDateTimeString(),
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Append to activity logs
            DB::table('activity_logs')->insert([
                'client_id' => $clientId,
                'user_id' => $userId,
                'type' => 'follow_up_recorded',
                'description' => 'تم تسجيل متابعة: ' . ($data['reason'] ?? 'متابعة') . ' - الخطوة التالية: ' . $data['next_action'] . ' (الموعد: ' . Carbon::parse($data['next_follow_up_date'])->toDateString() . ')',
                'metadata' => json_encode([
                    'follow_up_id' => $id,
                    'method' => $data['method'] ?? 'phone_call',
                    'next_action' => $data['next_action'],
                    'next_follow_up_date' => $data['next_follow_up_date'],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        });
    }
}
