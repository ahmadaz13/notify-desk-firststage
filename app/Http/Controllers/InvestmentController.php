<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvestmentController extends Controller
{
    protected function checkAdmin(): void
    {
        Gate::authorize(FinancialPermissions::MANAGE_INVESTMENTS);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAdmin();

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
