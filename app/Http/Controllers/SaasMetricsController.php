<?php

namespace App\Http\Controllers;

use App\Services\SaasMetricsReconciliationService;
use App\Services\SaasMetricsService;
use App\Support\Permissions;
use App\Support\ReportingPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaasMetricsController extends Controller
{
    public function index(Request $request, SaasMetricsService $metrics, SaasMetricsReconciliationService $reconciliation)
    {
        Gate::authorize(Permissions::VIEW_SAAS_METRICS);

        $period = ReportingPeriod::fromRequest($request);
        $report = $metrics->dashboard($period);
        $reconciliationResult = $reconciliation->run($period);

        return view('saas-metrics.index', compact('period', 'report', 'reconciliationResult', 'metrics'));
    }

    public function export(Request $request, string $report, SaasMetricsService $metrics): StreamedResponse
    {
        Gate::authorize(Permissions::EXPORT_SAAS_METRICS);

        $period = ReportingPeriod::fromRequest($request);
        $rows = $metrics->exportRows($report, $period);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'saas-'.$report.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
