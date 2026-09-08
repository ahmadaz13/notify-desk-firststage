<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\FollowUpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FollowUpController extends Controller
{
    public function store(Request $request, int $client, FollowUpService $followUpService)
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
}
