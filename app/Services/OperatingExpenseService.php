<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseReversal;
use App\Models\FinancialAccount;
use App\Models\RecurringExpenseObligation;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperatingExpenseService
{
    public function __construct(private readonly CashMovementService $cashMovements)
    {
    }

    public function createV2Expense(array $data, User $actor): Expense
    {
        $amountMinor = Money::fromJod($data['amount'])->minorUnits();
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة المصروف يجب أن تكون أكبر من صفر.']);
        }

        $category = ExpenseCategory::findOrFail($data['category_id']);
        if (! $category->is_active || $category->archived_at !== null) {
            throw ValidationException::withMessages(['category_id' => 'تصنيف المصروف غير نشط.']);
        }

        $vendor = isset($data['vendor_id']) ? Vendor::findOrFail($data['vendor_id']) : null;
        if ($vendor !== null && (! $vendor->is_active || $vendor->archived_at !== null)) {
            throw ValidationException::withMessages(['vendor_id' => 'المورد غير نشط.']);
        }

        $fundingSource = $data['funding_source'];
        $account = null;
        if ($fundingSource === Expense::FUNDING_COMPANY_ACCOUNT) {
            if (empty($data['financial_account_id'])) {
                throw ValidationException::withMessages(['financial_account_id' => 'يجب اختيار حساب مالي لمصروفات الشركة.']);
            }
            $account = FinancialAccount::findOrFail($data['financial_account_id']);
            $this->cashMovements->assertAccountReceivesOrdinaryMovement($account);
        } elseif ($fundingSource === Expense::FUNDING_PERSONAL) {
            if (empty($data['paid_by_user_id'])) {
                throw ValidationException::withMessages(['paid_by_user_id' => 'يجب اختيار الشخص الذي دفع المصروف شخصياً.']);
            }
        } else {
            throw ValidationException::withMessages(['funding_source' => 'مصدر التمويل غير صالح.']);
        }

        $obligation = isset($data['recurring_expense_obligation_id'])
            ? RecurringExpenseObligation::findOrFail($data['recurring_expense_obligation_id'])
            : null;
        if ($obligation !== null) {
            $this->assertPayableObligation($obligation);
        }

        return DB::transaction(function () use ($data, $actor, $amountMinor, $category, $vendor, $fundingSource, $account, $obligation) {
            $paidAt = Carbon::parse($data['paid_at'] ?? now());
            $incurredOn = Carbon::parse($data['incurred_on'] ?? $paidAt->toDateString())->toDateString();
            $payeeSnapshot = $this->payeeSnapshot($vendor, $data['payee_name'] ?? null);
            $categorySnapshot = $category->displayName();

            $expense = Expense::create([
                'expense_engine_version' => Expense::ENGINE_V2,
                'amount' => Money::fromMinorUnits($amountMinor)->format(),
                'amount_minor' => $amountMinor,
                'currency' => 'JOD',
                'category' => $categorySnapshot,
                'category_id' => $category->id,
                'category_name_snapshot' => $categorySnapshot,
                'vendor_id' => $vendor?->id,
                'payee_name_snapshot' => $payeeSnapshot,
                'financial_account_id' => $fundingSource === Expense::FUNDING_COMPANY_ACCOUNT ? $account?->id : null,
                'funding_source' => $fundingSource,
                'paid_by_user_id' => $fundingSource === Expense::FUNDING_PERSONAL ? (int) $data['paid_by_user_id'] : null,
                'incurred_on' => $incurredOn,
                'paid_at' => $paidAt,
                'reference' => $data['reference'] ?? null,
                'recurring_expense_obligation_id' => $obligation?->id,
                'created_by' => $actor->id,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'date' => $incurredOn,
                'time' => $paidAt->format('H:i:s'),
                'frequency' => 'one_time',
                'visibility' => 'shared',
                'paid_by' => $fundingSource === Expense::FUNDING_PERSONAL ? (int) $data['paid_by_user_id'] : $actor->id,
            ]);

            if ($fundingSource === Expense::FUNDING_COMPANY_ACCOUNT && $account !== null) {
                $this->cashMovements->recordExpensePaid($expense, $account, $actor->id);
            }

            if ($obligation !== null) {
                $obligation->update([
                    'status' => RecurringExpenseObligation::STATUS_PAID,
                    'paid_expense_id' => $expense->id,
                ]);
                $this->log($actor->id, 'recurring_expense_obligation_paid', 'تم دفع التزام مصروف متكرر', [
                    'recurring_expense_obligation_id' => $obligation->id,
                    'expense_id' => $expense->id,
                    'amount_minor' => $amountMinor,
                ]);
            }

            $this->log($actor->id, 'expense_created_v2', 'تم إنشاء مصروف تشغيلي V2', [
                'expense_id' => $expense->id,
                'amount_minor' => $amountMinor,
                'category' => $categorySnapshot,
                'payee' => $payeeSnapshot,
                'funding_source' => $fundingSource,
            ]);

            return $expense->fresh(['categoryModel', 'vendor', 'financialAccount', 'personalPayer']);
        });
    }

    public function reverseExpense(Expense $expense, string $reason, User $actor, ?Carbon $reversedAt = null): ExpenseReversal
    {
        if ($expense->expense_engine_version !== Expense::ENGINE_V2) {
            throw ValidationException::withMessages(['expense_id' => 'يمكن عكس مصروفات V2 فقط.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'سبب العكس مطلوب.']);
        }
        if ($expense->reversal()->exists()) {
            throw ValidationException::withMessages(['expense_id' => 'تم عكس هذا المصروف مسبقاً.']);
        }

        return DB::transaction(function () use ($expense, $reason, $actor, $reversedAt) {
            $reversal = ExpenseReversal::create([
                'expense_id' => $expense->id,
                'reason' => $reason,
                'reversed_at' => $reversedAt ?? now(),
                'reversed_by' => $actor->id,
            ]);

            if ($expense->funding_source === Expense::FUNDING_COMPANY_ACCOUNT) {
                $this->cashMovements->recordExpenseReversal($reversal, $actor->id);
            }

            $obligation = $expense->recurringObligation;
            if ($obligation !== null
                && $obligation->status === RecurringExpenseObligation::STATUS_PAID
                && (int) $obligation->paid_expense_id === (int) $expense->id) {
                $obligation->update([
                    'status' => RecurringExpenseObligation::STATUS_PENDING,
                    'paid_expense_id' => null,
                ]);
            }

            $this->log($actor->id, 'expense_reversed', 'تم عكس مصروف تشغيلي V2', [
                'expense_id' => $expense->id,
                'expense_reversal_id' => $reversal->id,
                'amount_minor' => $expense->amount_minor,
                'funding_source' => $expense->funding_source,
                'reason' => $reason,
            ]);

            return $reversal->fresh(['expense']);
        });
    }

    public function activeTotals(): array
    {
        $active = Expense::activeV2();
        $today = today();
        $monthStart = $today->copy()->startOfMonth();

        return [
            'today_minor' => (int) (clone $active)->whereDate('paid_at', $today)->sum('amount_minor'),
            'month_minor' => (int) (clone $active)->whereBetween('paid_at', [$monthStart, $today->copy()->endOfDay()])->sum('amount_minor'),
            'company_minor' => (int) (clone $active)->where('funding_source', Expense::FUNDING_COMPANY_ACCOUNT)->sum('amount_minor'),
            'personal_minor' => (int) (clone $active)->where('funding_source', Expense::FUNDING_PERSONAL)->sum('amount_minor'),
        ];
    }

    private function assertPayableObligation(RecurringExpenseObligation $obligation): void
    {
        if ($obligation->status !== RecurringExpenseObligation::STATUS_PENDING || $obligation->paid_expense_id !== null) {
            throw ValidationException::withMessages(['recurring_expense_obligation_id' => 'تمت معالجة هذا الالتزام مسبقاً.']);
        }
    }

    private function payeeSnapshot(?Vendor $vendor, ?string $payeeName): ?string
    {
        if ($vendor !== null) {
            return $vendor->name;
        }

        $payeeName = trim((string) $payeeName);

        return $payeeName === '' ? null : $payeeName;
    }

    private function log(?int $userId, string $type, string $description, array $metadata): void
    {
        DB::table('activity_logs')->insert([
            'client_id' => null,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
