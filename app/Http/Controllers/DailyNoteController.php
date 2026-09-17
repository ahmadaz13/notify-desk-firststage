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
        ]);

        $note = DailyNote::where('user_id', auth()->id())
            ->whereDate('date', Carbon::today())
            ->first();

        if ($note) {
            $note->update(['content' => $validated['content'] ?? '']);
        } else {
            $note = DailyNote::create([
                'user_id' => auth()->id(),
                'date' => Carbon::today()->toDateString(),
                'content' => $validated['content'] ?? '',
            ]);
        }

        return response()->json([
            'success' => true,
            'saved_at' => now()->format('H:i:s'),
            'note' => $note,
        ]);
    }
}
