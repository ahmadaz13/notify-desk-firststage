<?php

namespace App\Http\Controllers;

use App\Models\DailyNote;
use App\Support\OperationalTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyNoteController extends Controller
{
    /**
     * Auto-save the signed-in user's personal note for the operational day (Asia/Amman).
     *
     * The date comes from the page the note was typed on, so a tab left open past midnight still
     * saves to the day it shows; it is limited to today or yesterday (the server is the authority).
     */
    public function save(Request $request): JsonResponse
    {
        $today = OperationalTime::now()->startOfDay();

        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->copy()->subDay()->toDateString(),
                'before_or_equal:'.$today->toDateString(),
            ],
        ]);

        $businessDate = ! empty($validated['date'])
            ? Carbon::parse($validated['date'], OperationalTime::TIMEZONE)->toDateString()
            : $today->toDateString();

        $note = DailyNote::where('user_id', $request->user()->id)
            ->whereDate('date', $businessDate)
            ->first();

        if ($note) {
            $note->update(['content' => $validated['content'] ?? '']);
        } else {
            $note = DailyNote::create([
                'user_id' => $request->user()->id,
                'date' => $businessDate,
                'content' => $validated['content'] ?? '',
            ]);
        }

        $savedAt = OperationalTime::now();

        return response()->json([
            'success' => true,
            'saved_at' => $savedAt->format('H:i:s'),
            'saved_at_formatted' => OperationalTime::clock($savedAt),
            'note' => $note,
        ]);
    }
}
