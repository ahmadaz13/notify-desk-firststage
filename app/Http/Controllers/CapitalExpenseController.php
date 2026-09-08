<?php

namespace App\Http\Controllers;

use App\Models\CapitalExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CapitalExpenseController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'investment_id' => 'nullable|exists:investments,id',
        ]);

        CapitalExpense::create($validated);

        return back()->with('success', 'تم إضافة الصرف بنجاح');
    }
}
