<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\FollowUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FollowUpController extends Controller
{
    public function store(Request $request, int $client, FollowUpService $followUpService): RedirectResponse
    {
        $clientModel = Client::findOrFail($client);
        Gate::authorize('update', $clientModel);

        $data = $request->validate([
            'method' => 'required|string|max:80',
            'reason' => 'required|string|max:150',
            'result' => 'nullable|string',
            'next_action' => 'required|string|max:255',
            'next_follow_up_date' => 'required|date',
            'follow_up_date_time' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $followUpService->createFollowUp($client, auth()->id(), $data);

        return back()->with('success', 'تم تسجيل المتابعة بنجاح.');
    }

    public function complete(Request $request, int $followUp, FollowUpService $followUpService): RedirectResponse
    {
        $item = DB::table('follow_ups')->where('id', $followUp)->first();
        abort_unless($item, 404, 'المتابعة غير موجودة');

        $client = Client::findOrFail($item->client_id);
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'outcome' => 'required|string|in:subscribe,start_subscription,callback_later,wait,call_later,appointment,no_answer,no_answer_busy,not_interested',
            'note' => 'nullable|string',
            'reason' => 'nullable|string',
            'follow_up_date_time' => 'nullable|date',
            'next_follow_up_date' => 'nullable|date',
            'next_follow_up_time' => 'nullable',
            'appointment_date' => 'nullable|date',
            'appointment_time' => 'nullable',
            'appointment_type' => 'nullable|string|max:80',
            'location' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $followUpService->completeFollowUp($followUp, $request->user(), $validated['outcome'], $validated);

        if (in_array($validated['outcome'], ['subscribe', 'start_subscription'], true)) {
            return redirect()->route('clients.show', ['client' => $client->id, '#collapsible-management'])
                ->with('success', 'تم إكمال المتابعة بنجاح والعميل جاهز لبدء الاشتراك.');
        }

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'تم تسجيل نتيجة المتابعة بنجاح.');
    }
}
