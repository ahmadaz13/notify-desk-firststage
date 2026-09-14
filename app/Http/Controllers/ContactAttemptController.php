<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ContactAttempt;
use App\Support\ClientLifecycle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContactAttemptController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'method' => 'required|string|max:80',
            'result' => ['required', Rule::in(ClientLifecycle::CONTACT_OUTCOMES)],
            'note' => 'nullable|string',
            'next_action' => 'nullable|string|max:255',
            'next_follow_up_date' => 'nullable|date',
            'close_client' => 'nullable|boolean',
            'closed_reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($client, $validated) {
            $attempt = ContactAttempt::create([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'method' => $validated['method'],
                'result' => $validated['result'],
                'note' => $validated['note'] ?? null,
                'next_action' => $validated['next_action'] ?? null,
                'next_follow_up_date' => $validated['next_follow_up_date'] ?? null,
            ]);

            if (!empty($validated['next_follow_up_date']) && !empty($validated['next_action'])) {
                DB::table('follow_ups')->insert([
                    'client_id' => $client->id,
                    'user_id' => auth()->id(),
                    'method' => $validated['method'],
                    'reason' => 'متابعة نتيجة تواصل: ' . $validated['result'],
                    'result' => $validated['note'] ?? null,
                    'next_action' => $validated['next_action'],
                    'next_follow_up_date' => Carbon::parse($validated['next_follow_up_date'])->toDateString(),
                    'follow_up_date_time' => Carbon::parse($validated['next_follow_up_date'])->setTime(10, 0)->toDateTimeString(),
                    'notes' => $validated['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'type' => 'contact_attempt_recorded',
                'description' => "تم تسجيل نتيجة تواصل: {$validated['result']}",
                'metadata' => json_encode([
                    'contact_attempt_id' => $attempt->id,
                    'outcome' => $validated['result'],
                    'next_action' => $validated['next_action'] ?? null,
                    'next_follow_up_date' => $validated['next_follow_up_date'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (($validated['result'] === 'not_interested') && !empty($validated['close_client'])) {
                $client->update([
                    'stage' => ClientLifecycle::CLOSED,
                    'status' => 'archived',
                    'closed_at' => now(),
                    'closed_reason' => $validated['closed_reason'] ?? 'غير مهتم حالياً',
                ]);

                DB::table('activity_logs')->insert([
                    'client_id' => $client->id,
                    'user_id' => auth()->id(),
                    'type' => 'client_closed',
                    'description' => 'تم إغلاق ملف العميل بعد نتيجة تواصل غير مهتم',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'تم تسجيل نتيجة التواصل بنجاح.');
    }
}
