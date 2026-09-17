<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\FinancialAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancialAccountBalanceService
{
    public function currentBalanceMinor(FinancialAccount $account): int
    {
        return $this->balanceAsOfMinor($account, null);
    }

    public function balanceAsOfMinor(FinancialAccount $account, ?Carbon $asOf): int
    {
        $query = CashMovement::query()->where('financial_account_id', $account->id);
        if ($asOf !== null) {
            $query->where('occurred_at', '<=', $asOf);
        }

        $inflows = (clone $query)->where('direction', CashMovement::DIRECTION_INFLOW)->sum('amount_minor');
        $outflows = (clone $query)->where('direction', CashMovement::DIRECTION_OUTFLOW)->sum('amount_minor');

        return (int) $inflows - (int) $outflows;
    }

    public function inflowsMinor(FinancialAccount $account, ?Carbon $from = null, ?Carbon $to = null): int
    {
        return $this->sumDirection($account, CashMovement::DIRECTION_INFLOW, $from, $to);
    }

    public function outflowsMinor(FinancialAccount $account, ?Carbon $from = null, ?Carbon $to = null): int
    {
        return $this->sumDirection($account, CashMovement::DIRECTION_OUTFLOW, $from, $to);
    }

    public function companyCashMinor(bool $includeClearing = false): int
    {
        $accounts = FinancialAccount::query()
            ->when(! $includeClearing, fn ($query) => $query->where('type', '!=', FinancialAccount::TYPE_CLEARING))
            ->get();

        return $accounts->sum(fn (FinancialAccount $account) => $this->currentBalanceMinor($account));
    }

    public function accountCards(?Carbon $businessDate = null): Collection
    {
        $businessDate ??= Carbon::today();
        $from = $businessDate->copy()->startOfDay();
        $to = $businessDate->copy()->endOfDay();

        return FinancialAccount::query()
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('name_ar')
            ->get()
            ->map(fn (FinancialAccount $account) => [
                'account' => $account,
                'balance_minor' => $this->currentBalanceMinor($account),
                'today_inflows_minor' => $this->inflowsMinor($account, $from, $to),
                'today_outflows_minor' => $this->outflowsMinor($account, $from, $to),
            ]);
    }

    public function recentMovements(int $limit = 20): Collection
    {
        return CashMovement::with('financialAccount')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function sumDirection(FinancialAccount $account, string $direction, ?Carbon $from, ?Carbon $to): int
    {
        $query = CashMovement::query()
            ->where('financial_account_id', $account->id)
            ->where('direction', $direction);

        if ($from !== null) {
            $query->where('occurred_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('occurred_at', '<=', $to);
        }

        return (int) $query->sum('amount_minor');
    }
}
