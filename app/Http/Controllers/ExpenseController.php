<?php

namespace App\Http\Controllers;

use App\Support\FinancialPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ExpenseController extends Controller
{
    /**
     * Store a new operational expense.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize(FinancialPermissions::MANAGE_EXPENSES);

        Log::warning('Deprecated legacy expense route POST /expenses invoked', [
            'user_id' => auth()->id(),
            'payload' => $request->except(['_token', 'password']),
        ]);

        abort(410, 'Legacy quick expense writes are deprecated. Use the V2 Operating Expenses workflow.');
    }
}
