<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\ClientLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientStageController extends Controller
{
    public function update(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(ClientLifecycle::STAGES)],
            'closed_reason' => 'nullable|string|max:255',
        ]);

        $oldStage = ClientLifecycle::normalizeStage($client->stage, $client->status);
        $newStage = $validated['stage'];

        DB::transaction(function () use ($client, $oldStage, $newStage, $validated) {
            $updates = [
                'stage' => $newStage,
                'updated_at' => now(),
            ];

            if ($newStage === ClientLifecycle::SUBSCRIBER) {
                $updates['status'] = 'subscriber';
                $updates['closed_at'] = null;
                $updates['closed_reason'] = null;
            } elseif ($newStage === ClientLifecycle::CLOSED) {
                $updates['status'] = 'archived';
                $updates['closed_at'] = now();
                $updates['closed_reason'] = $validated['closed_reason'] ?? 'تم إغلاق ملف العميل';
            } elseif (($client->status ?? null) === 'archived') {
                $updates['status'] = 'prospect';
                $updates['closed_at'] = null;
                $updates['closed_reason'] = null;
            }

            $client->update($updates);

            DB::table('activity_logs')->insert([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'type' => 'client_stage_changed',
                'description' => 'تم تغيير مرحلة العميل من ' . ClientLifecycle::label($oldStage) . ' إلى ' . ClientLifecycle::label($newStage),
                'metadata' => json_encode([
                    'from' => $oldStage,
                    'to' => $newStage,
                    'closed_reason' => $validated['closed_reason'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'تم تحديث مرحلة العميل بنجاح.');
    }
}
