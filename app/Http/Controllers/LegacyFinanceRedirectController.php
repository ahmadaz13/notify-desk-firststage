<?php

namespace App\Http\Controllers;

use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Pre-P6 Finance GET pages 301-redirect to their canonical §12 destination for one release.
 * Each redirect first enforces the permission the old page required, so access never widens.
 */
class LegacyFinanceRedirectController extends Controller
{
    public function collections(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::VIEW_FINANCIAL_REPORTS, 'finance.collections');
    }

    public function financialAccounts(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::VIEW_CASH_MANAGEMENT, 'finance.accounts');
    }

    public function operatingExpenses(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::VIEW_EXPENSE_MANAGEMENT, 'finance.expenses');
    }

    public function accounting(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::VIEW_ACCOUNTING, 'finance.accounting');
    }

    public function executive(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::VIEW_EXECUTIVE_DASHBOARD, 'finance.index');
    }

    public function saasMetrics(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::VIEW_SAAS_METRICS, 'finance.reports', ['report' => 'subscription-metrics']);
    }

    public function saasMetricsExport(Request $request, string $report): RedirectResponse
    {
        return $this->to($request, Permissions::EXPORT_SAAS_METRICS, 'finance.reports.export', [
            'report' => 'subscription-metrics',
            'dataset' => $report,
        ]);
    }

    public function subscriptionBilling(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::RUN_SUBSCRIPTION_BILLING, 'finance.accounting', ['tools' => 1]);
    }

    public function capitalManagement(Request $request): RedirectResponse
    {
        return $this->to($request, Permissions::VIEW_CAPITAL_MANAGEMENT, 'finance.capital');
    }

    private function to(Request $request, string $permission, string $route, array $parameters = []): RedirectResponse
    {
        Gate::authorize($permission);

        return redirect()->route($route, $parameters + $request->query(), 301);
    }
}
