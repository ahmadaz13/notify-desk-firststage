<?php

namespace App\Http\Controllers;

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

        abort(410, 'Legacy investment writes are deprecated. Use Capital Management funding transactions.');
    }
}
