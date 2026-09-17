<?php

namespace App\Http\Controllers;

use App\Exports\FinancialReportExport;
use App\Models\Partner;
use App\Models\Setting;
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

        $operationalCostPercentage = (float) Setting::get('operational_cost_percentage', 20);
        $marketValuationMultiplier = (float) Setting::get('market_valuation_multiplier', 5);
        $allowAutoTransferClients = (bool) Setting::get('allow_auto_transfer_clients', 0);
        $annualDiscountPercentage = (float) Setting::get('annual_discount_percentage', 10.0);
        $salesTaxPercentage = (float) Setting::get('sales_tax_percentage', 16.0);
        $monthlyDueDay = (int) Setting::get('monthly_due_day', 1);

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
            'annualDiscountPercentage',
            'salesTaxPercentage',
            'monthlyDueDay',
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
            'allow_auto_transfer_clients' => 'nullable',
            'annual_discount_percentage' => 'nullable|numeric|min:0|max:100',
            'sales_tax_percentage' => 'nullable|numeric|min:0|max:100',
            'monthly_due_day' => 'nullable|integer|in:1,5,15,30',
        ]);

        Setting::updateOrCreate(
            ['key' => 'allow_auto_transfer_clients'],
            ['value' => $request->has('allow_auto_transfer_clients') ? '1' : '0']
        );

        if (isset($validated['annual_discount_percentage'])) {
            Setting::updateOrCreate(
                ['key' => 'annual_discount_percentage'],
                ['value' => (string) $validated['annual_discount_percentage']]
            );
        }

        if (isset($validated['sales_tax_percentage'])) {
            Setting::updateOrCreate(
                ['key' => 'sales_tax_percentage'],
                ['value' => (string) $validated['sales_tax_percentage']]
            );
        }

        if (isset($validated['monthly_due_day'])) {
            Setting::updateOrCreate(
                ['key' => 'monthly_due_day'],
                ['value' => (string) $validated['monthly_due_day']]
            );
        }

        return redirect()->route('settings.index')->with('success', 'تم حفظ الإعدادات المالية بنجاح.');
    }

    public function export(): StreamedResponse
    {
        Gate::authorize(FinancialPermissions::VIEW_FINANCIAL_REPORTS);

        return (new FinancialReportExport())->download('financial_report.xlsx');
    }
}
