<?php

namespace App\Http\Controllers;

use App\Exports\FinancialReportExport;
use App\Support\FinancialPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    private function checkAdmin(): void
    {
        Gate::authorize(FinancialPermissions::MANAGE_FINANCIAL_SETTINGS);
    }

    public function index(Request $request): View
    {
        $this->checkAdmin();

        $selectedType = $request->get('type', 'all');
        $activityQuery = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->leftJoin('clients', 'clients.id', '=', 'activity_logs.client_id')
            ->select(
                'activity_logs.*',
                'users.name as user_name',
                'clients.business_name as client_name'
            )
            ->orderByDesc('activity_logs.created_at')
            ->limit(50);

        if ($selectedType && $selectedType !== 'all') {
            $activityQuery->where('activity_logs.type', $selectedType);
        }

        $activityLogs = $activityQuery->get();
        $activityTypes = DB::table('activity_logs')->distinct()->pluck('type')->filter();

        return view('settings.index', compact(
            'activityLogs',
            'activityTypes',
            'selectedType'
        ));
    }

    public function export(): StreamedResponse
    {
        Gate::authorize(FinancialPermissions::VIEW_FINANCIAL_REPORTS);

        return (new FinancialReportExport())->download('financial_report.xlsx');
    }
}
