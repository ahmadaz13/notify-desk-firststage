<?php

namespace App\Http\Controllers;

use App\Models\DailyNote;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyNoteController extends Controller
{
    /**
     * Auto-save or update the daily note for the authenticated user for today.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'date' => 'nullable|date',
        ]);

        $businessDate = !empty($validated['date'])
            ? Carbon::parse($validated['date'], 'Asia/Amman')->toDateString()
            : Carbon::now('Asia/Amman')->toDateString();

        $note = DailyNote::where('user_id', auth()->id())
            ->whereDate('date', $businessDate)
            ->first();

        if ($note) {
            $note->update(['content' => $validated['content'] ?? '']);
        } else {
            $note = DailyNote::create([
                'user_id' => auth()->id(),
                'date' => $businessDate,
                'content' => $validated['content'] ?? '',
            ]);
        }

        $nowAmman = Carbon::now('Asia/Amman');

        return response()->json([
            'success' => true,
            'saved_at' => $nowAmman->format('H:i:s'),
            'saved_at_formatted' => $nowAmman->format('g:i:s A'),
            'note' => $note,
        ]);
    }
}
