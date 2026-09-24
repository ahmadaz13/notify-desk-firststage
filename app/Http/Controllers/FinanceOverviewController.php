<?php

namespace App\Http\Controllers;

use App\Services\FinanceOverviewService;
use App\Support\Features;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Finance Overview (§12.1, D-16): decision dashboard, read-only, no forms.
 */
class FinanceOverviewController extends Controller
{
    /** Pre-P6 `/finance?section=` values → canonical Finance destinations (§12). */
    private const LEGACY_SECTIONS = [
        'overview' => 'finance.index',
        'collections' => 'finance.collections',
        'expenses' => 'finance.expenses',
        'capital_assets' => 'finance.capital',
        'reports' => 'finance.reports',
        'advanced' => 'finance.accounts',
    ];

    public function index(Request $request, FinanceOverviewService $overview): View|RedirectResponse
    {
        Gate::authorize(Permissions::VIEW_FINANCIAL_STATEMENTS);

        if ($request->has('section')) {
            $target = self::LEGACY_SECTIONS[$request->query('section')] ?? 'finance.index';
            if ($target === 'finance.capital' && ! Features::capitalEnabled()) {
                $target = 'finance.index';
            }

            return redirect()->route($target, $request->except('section'), 301);
        }

        return view('finance.overview', [
            'overview' => $overview->build(),
            'canViewSaas' => Gate::allows(Permissions::VIEW_SAAS_METRICS),
        ]);
    }
}
