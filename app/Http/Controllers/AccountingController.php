<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\RevenueRecognitionSchedule;
use App\Services\AccountingReconciliationService;
use App\Services\AccountingReportService;
use App\Services\AccountingSetupService;
use App\Services\JournalPostingService;
use App\Services\RevenueRecognitionService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public const TABS = ['accounts', 'journal', 'reconciliation', 'periods', 'recognition'];

    /**
     * Accounting & Entries (§12.5). Each tab computes only its own data; reconciliation runs only on
     * its tab. Maintenance operations live in the collapsed Advanced tools panel.
     */
    public function index(
        AccountingSetupService $setup,
        AccountingReportService $reports,
        AccountingReconciliationService $reconciliation,
        RevenueRecognitionService $revenueRecognition,
        Request $request
    ): View {
        Gate::authorize(Permissions::VIEW_ACCOUNTING);

        $setup->ensureSeeded();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'accounts';
        $data = [];

        switch ($tab) {
            case 'accounts':
                $data['trialBalance'] = $reports->trialBalance();
                $data['selectedAccount'] = $request->integer('account_id') ? ChartAccount::find($request->integer('account_id')) : null;
                $data['ledger'] = $data['selectedAccount'] ? $reports->ledger($data['selectedAccount']) : collect();
                break;
            case 'journal':
                $data['entries'] = JournalEntry::query()
                    ->withSum('lines as total_debit_minor', 'debit_minor')
                    ->orderByDesc('entry_date')
                    ->orderByDesc('id')
                    ->paginate(25)
                    ->withQueryString();
                $data['selectedEntry'] = $request->integer('entry')
                    ? JournalEntry::with(['lines.chartAccount', 'lines.client:id,business_name', 'reversalOf'])->find($request->integer('entry'))
                    : null;
                break;
            case 'reconciliation':
                $data['reconciliationResult'] = $reconciliation->run();
                break;
            case 'periods':
                $data['periods'] = AccountingPeriod::orderByDesc('period_key')->limit(24)->get();
                break;
            case 'recognition':
                $data['revenueRecognitionDashboard'] = $revenueRecognition->dashboard();
                break;
        }

        return view('finance.accounting', $data + [
            'tab' => $tab,
            'toolsOpen' => $request->boolean('tools'),
        ]);
    }

    public function archiveAccount(ChartAccount $chartAccount, Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_CHART_OF_ACCOUNTS);

        if ($chartAccount->is_system || $chartAccount->journalLines()->exists()) {
            return back()->withErrors(['chart_account_id' => 'لا يمكن أرشفة حساب نظامي أو مستخدم تاريخياً.']);
        }

        $chartAccount->update([
            'is_active' => false,
            'archived_at' => now(),
        ]);

        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => $request->user()->id,
            'type' => 'chart_account_archived',
            'description' => 'تمت أرشفة حساب محاسبي '.$chartAccount->code,
            'metadata' => json_encode(['chart_account_id' => $chartAccount->id, 'code' => $chartAccount->code]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'تمت أرشفة الحساب المحاسبي.');
    }

    public function closePeriod(AccountingPeriod $period, Request $request, JournalPostingService $journals): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_ACCOUNTING_PERIODS);

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);
        $journals->closePeriod($period, $request->user()->id, $validated['notes'] ?? null);

        return back()->with('success', 'تم إغلاق الفترة المحاسبية.');
    }

    public function reopenPeriod(AccountingPeriod $period, Request $request, JournalPostingService $journals): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_ACCOUNTING_PERIODS);

        $validated = $request->validate(['reason' => 'required|string|max:1000']);
        $journals->reopenPeriod($period, $request->user()->id, $validated['reason']);

        return back()->with('success', 'تمت إعادة فتح الفترة المحاسبية.');
    }

    public function backfill(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::RUN_ACCOUNTING_BACKFILL);

        $dryRun = $request->boolean('dry_run', true);
        Artisan::call('finance:backfill-accounting-ledger', $dryRun ? ['--dry-run' => true] : []);

        return back()->with('success', trim(Artisan::output()));
    }

    public function backfillRevenueSchedules(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::RUN_REVENUE_RECOGNITION);

        $dryRun = $request->boolean('dry_run', true);
        Artisan::call('finance:backfill-revenue-schedules', $dryRun ? ['--dry-run' => true] : []);

        return back()->with('success', trim(Artisan::output()));
    }

    public function recognizeRevenue(Request $request): RedirectResponse
    {
        Gate::authorize(Permissions::RUN_REVENUE_RECOGNITION);

        $validated = $request->validate([
            'through' => 'nullable|date',
            'dry_run' => 'nullable|boolean',
        ]);

        $parameters = [];
        if (! empty($validated['through'])) {
            $parameters['--through'] = $validated['through'];
        }
        if ($request->boolean('dry_run', true)) {
            $parameters['--dry-run'] = true;
        }

        Artisan::call('finance:recognize-revenue', $parameters);

        return back()->with('success', trim(Artisan::output()));
    }

    public function confirmRevenueRecognition(
        RevenueRecognitionSchedule $schedule,
        Request $request,
        RevenueRecognitionService $revenueRecognition
    ): RedirectResponse {
        Gate::authorize(Permissions::RESOLVE_REVENUE_RECOGNITION_REVIEWS);

        $validated = $request->validate([
            'recognition_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $revenueRecognition->confirmPointInTimeService(
            $schedule,
            $validated['recognition_date'],
            $validated['note'] ?? null,
            $request->user()->id
        );

        return back()->with('success', 'تم تأكيد جدول الاعتراف بالإيراد.');
    }
}
