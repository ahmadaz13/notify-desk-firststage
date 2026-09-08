<?php

namespace App\Http\Controllers;

use App\Exports\FinancialReportExport;
use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    private function checkAdmin(): void
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            abort(403, 'غير مصرح لك بالوصول إلى لوحة تحكم الإدارة.');
        }
    }

    public function index(Request $request): View
    {
        $this->checkAdmin();

        $operationalCostPercentage = (float) Setting::get('operational_cost_percentage', 20);
        $marketValuationMultiplier = (float) Setting::get('market_valuation_multiplier', 5);
        $allowAutoTransferClients = (bool) Setting::get('allow_auto_transfer_clients', 0);

        $partners = Partner::withCount('clients')->orderBy('company_name')->get();

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
            'operationalCostPercentage',
            'marketValuationMultiplier',
            'allowAutoTransferClients',
            'partners',
            'activityLogs',
            'activityTypes',
            'selectedType'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'operational_cost_percentage' => 'required|numeric|min:0|max:100',
            'market_valuation_multiplier' => 'required|numeric|min:0.1|max:100',
            'allow_auto_transfer_clients' => 'nullable',
        ]);

        Setting::updateOrCreate(
            ['key' => 'operational_cost_percentage'],
            ['value' => (string) $validated['operational_cost_percentage']]
        );

        Setting::updateOrCreate(
            ['key' => 'market_valuation_multiplier'],
            ['value' => (string) $validated['market_valuation_multiplier']]
        );

        Setting::updateOrCreate(
            ['key' => 'allow_auto_transfer_clients'],
            ['value' => $request->has('allow_auto_transfer_clients') ? '1' : '0']
        );

        return redirect()->route('settings.index')->with('success', 'تم حفظ الإعدادات المالية بنجاح.');
    }

    public function export(): StreamedResponse
    {
        $this->checkAdmin();

        return (new FinancialReportExport())->download('financial_report.xlsx');
    }
}
