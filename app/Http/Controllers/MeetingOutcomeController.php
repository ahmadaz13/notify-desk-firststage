<?php

namespace App\Http\Controllers;

use App\Services\MeetingOutcomeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingOutcomeController extends Controller
{
    public function create(int $appointment)
    {
        $item = DB::table('appointments')
            ->join('clients', 'clients.id', '=', 'appointments.client_id')
            ->where('appointments.id', $appointment)
            ->select('appointments.*', 'clients.business_name', 'clients.phone', 'clients.contact_person')
            ->first();

        abort_unless($item, 404, 'الموعد غير موجود');
        $clientModel = \App\Models\Client::findOrFail($item->client_id);
        \Illuminate\Support\Facades\Gate::authorize('view', $clientModel);

        return view('appointments.outcome', compact('item'));
    }

    public function store(Request $request, int $appointment, MeetingOutcomeService $outcomeService)
    {
        $item = DB::table('appointments')->where('id', $appointment)->first();
        abort_unless($item, 404);
        $clientModel = \App\Models\Client::findOrFail($item->client_id);
        \Illuminate\Support\Facades\Gate::authorize('update', $clientModel);

        $data = $request->validate([
            'attendance_status' => 'required|in:attended,attended_late,cancelled,no_show',
            'meeting_date_time' => 'nullable|date',
            'meeting_type' => 'required|string|max:80',
            'demo_performed' => 'nullable|boolean',
            'interest_level' => 'required|in:high,medium,low,none',
            'customer_needs' => 'nullable|string',
            'main_objections' => 'nullable|string',
            'price_discussed' => 'nullable|boolean',
            'package_discussed' => 'nullable|string|max:120',
            'customer_response' => 'nullable|string',
            'next_action' => 'required|string|max:255',
            'next_follow_up_date' => 'nullable|date',
            'meeting_notes' => 'nullable|string',
        ]);

        $apt = DB::table('appointments')->where('id', $appointment)->first();
        abort_unless($apt, 404, 'الموعد غير موجود');

        $outcomeService->recordOutcome($appointment, auth()->id(), $data);

        return redirect()->route('clients.show', $apt->client_id)->with('success', 'تم حفظ مخرجات الاجتماع وإكمال الموعد بنجاح.');
    }
}
