<?php

namespace App\Services;

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountingReportService
{
    public function trialBalance(?Carbon $from = null, ?Carbon $to = null): array
    {
        $accounts = ChartAccount::query()
            ->with('parent')
            ->orderBy('code')
            ->get()
            ->map(function (ChartAccount $account) use ($from, $to) {
                $query = JournalLine::query()
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                    ->where('journal_lines.chart_account_id', $account->id);

                if ($from !== null) {
                    $query->whereDate('journal_entries.entry_date', '>=', $from->toDateString());
                }
                if ($to !== null) {
                    $query->whereDate('journal_entries.entry_date', '<=', $to->toDateString());
                }

                $debit = (int) (clone $query)->sum('journal_lines.debit_minor');
                $credit = (int) (clone $query)->sum('journal_lines.credit_minor');
                $net = $account->normal_balance === ChartAccount::NORMAL_DEBIT
                    ? $debit - $credit
                    : $credit - $debit;

                return [
                    'account' => $account,
                    'debit_minor' => $debit,
                    'credit_minor' => $credit,
                    'net_minor' => $net,
                ];
            });

        return [
            'rows' => $accounts,
            'total_debit_minor' => (int) $accounts->sum('debit_minor'),
            'total_credit_minor' => (int) $accounts->sum('credit_minor'),
            'difference_minor' => (int) $accounts->sum('debit_minor') - (int) $accounts->sum('credit_minor'),
        ];
    }

    public function ledger(ChartAccount $account, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $running = 0;

        return JournalLine::query()
            ->with(['entry', 'chartAccount'])
            ->where('chart_account_id', $account->id)
            ->whereHas('entry', function ($query) use ($from, $to) {
                if ($from !== null) {
                    $query->whereDate('entry_date', '>=', $from->toDateString());
                }
                if ($to !== null) {
                    $query->whereDate('entry_date', '<=', $to->toDateString());
                }
            })
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select('journal_lines.*')
            ->get()
            ->map(function (JournalLine $line) use (&$running, $account) {
                $delta = $account->normal_balance === ChartAccount::NORMAL_DEBIT
                    ? (int) $line->debit_minor - (int) $line->credit_minor
                    : (int) $line->credit_minor - (int) $line->debit_minor;
                $running += $delta;

                return [
                    'line' => $line,
                    'running_balance_minor' => $running,
                ];
            });
    }

    public function journalRegister(int $limit = 100): Collection
    {
        return JournalEntry::query()
            ->with(['lines', 'reversalOf'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'entry' => $entry,
                'total_debit_minor' => (int) $entry->lines->sum('debit_minor'),
                'total_credit_minor' => (int) $entry->lines->sum('credit_minor'),
            ]);
    }
}

