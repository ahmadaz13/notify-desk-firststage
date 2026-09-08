<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'investor_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        Investment::create($validated);

        return back()->with('success', 'تم إضافة الاستثمار بنجاح');
    }
}
