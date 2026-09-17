<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportingReconciliationService;
use App\Services\FinancialStatementService;
use App\Services\SaasMetricsReconciliationService;
use App\Services\SaasMetricsService;
use App\Support\FinancialPermissions;
use App\Support\ReportingPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExecutiveDashboardController extends Controller
{
    public function index(
        Request $request,
        SaasMetricsService $saas,
        SaasMetricsReconciliationService $saasReconciliation,
        FinancialStatementService $statements,
        FinancialReportingReconciliationService $financialReconciliation
    ) {
        Gate::authorize(FinancialPermissions::VIEW_EXECUTIVE_DASHBOARD);

        $period = ReportingPeriod::fromRequest($request);
        $saasReport = $saas->dashboard($period);
        $finance = $statements->dashboard($period);
        $financialReconciliationResult = $financialReconciliation->run($period);
        $saasReconciliationResult = $saasReconciliation->run($period);

        return view('executive.index', compact(
            'period',
            'saas',
            'saasReport',
            'finance',
            'financialReconciliationResult',
            'saasReconciliationResult'
        ));
    }
}
