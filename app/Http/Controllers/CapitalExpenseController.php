<?php

namespace App\Http\Controllers;

use App\Models\CapitalExpense;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CapitalExpenseController extends Controller
{
    protected function checkAdmin(): void
    {
        Gate::authorize(FinancialPermissions::MANAGE_CAPITAL_EXPENSES);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAdmin();

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
