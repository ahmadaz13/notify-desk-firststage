<?php

namespace App\Http\Controllers;

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

        abort(410, 'Legacy capital expense writes are deprecated. Use Capital Management fixed assets.');
    }
}
