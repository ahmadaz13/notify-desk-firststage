<?php

namespace App\Http\Controllers;

use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class InvestmentController extends Controller
{
    protected function checkAdmin(): void
    {
        Gate::authorize(FinancialPermissions::MANAGE_INVESTMENTS);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAdmin();

        Log::warning('Deprecated legacy investment route POST /investments invoked', [
            'user_id' => auth()->id(),
            'payload' => $request->except(['_token', 'password']),
        ]);

        abort(410, 'Legacy investment writes are deprecated. Use Capital Management funding transactions.');
    }
}
