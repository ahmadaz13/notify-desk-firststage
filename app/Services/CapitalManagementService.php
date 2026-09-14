<?php

namespace App\Services;

use App\Models\AssetCategory;
use App\Models\CapitalFundingReversal;
use App\Models\CapitalFundingTransaction;
use App\Models\FinancialAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetAcquisitionReversal;
use App\Models\FundingSource;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CapitalManagementService
{
    public function __construct(private readonly CashMovementService $cashMovements)
    {
    }

    public function createFundingSource(array $data, User $actor): FundingSource
    {
        if (! in_array($data['type'], FundingSource::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'نوع مصدر التمويل غير صالح.']);
        }

        $source = FundingSource::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'user_id' => $data['user_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        $this->log($actor->id, 'funding_source_created', 'تم إنشاء مصدر تمويل', [
            'funding_source_id' => $source->id,
            'source_name' => $source->name,
            'source_type' => $source->type,
        ]);

        return $source;
    }

    public function recordCapitalFunding(array $data, User $actor): CapitalFundingTransaction
    {
        $amountMinor = Money::fromJod($data['amount'])->minorUnits();
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'قيمة التمويل يجب أن تكون أكبر من صفر.']);
        }
        if (! in_array($data['funding_type'], CapitalFundingTransaction::TYPES, true)) {
            throw ValidationException::withMessages(['funding_type' => 'نوع التمويل غير صالح.']);
        }

        $account = FinancialAccount::findOrFail($data['financial_account_id']);
        $this->cashMovements->assertAccountReceivesOrdinaryMovement($account);

        $source = isset($data['funding_source_id']) ? FundingSource::findOrFail($data['funding_source_id']) : null;
        if ($source !== null && (! $source->is_active || $source->archived_at !== null)) {
            throw ValidationException::withMessages(['funding_source_id' => 'مصدر التمويل غير نشط.']);
        }
        $sourceName = $source?->name ?: trim((string) ($data['source_name'] ?? ''));
        if ($sourceName === '') {
            throw ValidationException::withMessages(['source_name' => 'اسم مصدر التمويل مطلوب عند عدم اختيار مصدر محفوظ.']);
        }

        return DB::transaction(function () use ($data, $actor, $amountMinor, $account, $source, $sourceName) {
            $transaction = CapitalFundingTransaction::create([
                'funding_number' => $this->nextFundingNumber(),
                'funding_source_id' => $source?->id,
                'source_name_snapshot' => $sourceName,
                'funding_type' => $data['funding_type'],
                'financial_account_id' => $account->id,
                'currency' => 'JOD',
                'amount_minor' => $amountMinor,
                'received_at' => Carbon::parse($data['received_at']),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->cashMovements->recordCapitalFundingReceived($transaction, $account, $actor->id);
            $this->log($actor->id, 'capital_funding_received', 'تم تسجيل تمويل رأسمالي', [
                'capital_funding_transaction_id' => $transaction->id,
                'funding_number' => $transaction->funding_number,
                'source_name' => $sourceName,
                'funding_type' => $transaction->funding_type,
                'amount_minor' => $amountMinor,
                'financial_account_id' => $account->id,
            ]);

            return $transaction->fresh(['fundingSource', 'financialAccount']);
        });
    }

    public function reverseCapitalFunding(CapitalFundingTransaction $transaction, string $reason, User $actor, ?Carbon $reversedAt = null): CapitalFundingReversal
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'سبب العكس مطلوب.']);
        }
        if ($transaction->reversal()->exists()) {
            throw ValidationException::withMessages(['capital_funding_transaction_id' => 'تم عكس هذا التمويل مسبقاً.']);
        }

        return DB::transaction(function () use ($transaction, $reason, $actor, $reversedAt) {
            $reversal = CapitalFundingReversal::create([
                'capital_funding_transaction_id' => $transaction->id,
                'reason' => $reason,
                'reversed_at' => $reversedAt ?? now(),
                'reversed_by' => $actor->id,
            ]);
            $this->cashMovements->recordCapitalFundingReversal($reversal, $actor->id);
            $this->log($actor->id, 'capital_funding_reversed', 'تم عكس تمويل رأسمالي', [
                'capital_funding_transaction_id' => $transaction->id,
                'capital_funding_reversal_id' => $reversal->id,
                'amount_minor' => $transaction->amount_minor,
                'reason' => $reason,
            ]);

            return $reversal->fresh();
        });
    }

    public function createAssetCategory(array $data, User $actor): AssetCategory
    {
        $category = AssetCategory::create([
            'code' => $data['code'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $actor->id,
        ]);
        $this->log($actor->id, 'asset_category_created', 'تم إنشاء تصنيف أصل', [
            'asset_category_id' => $category->id,
            'code' => $category->code,
        ]);

        return $category;
    }

    public function acquireFixedAsset(array $data, User $actor): FixedAsset
    {
        $amountMinor = Money::fromJod($data['acquisition_cost'])->minorUnits();
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['acquisition_cost' => 'قيمة الأصل يجب أن تكون أكبر من صفر.']);
        }
        $quantity = (int) ($data['quantity'] ?? 1);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'الكمية يجب أن تكون أكبر من صفر.']);
        }

        $category = AssetCategory::findOrFail($data['asset_category_id']);
        if (! $category->is_active || $category->archived_at !== null) {
            throw ValidationException::withMessages(['asset_category_id' => 'تصنيف الأصل غير نشط.']);
        }
        $vendor = isset($data['vendor_id']) ? Vendor::findOrFail($data['vendor_id']) : null;
        if ($vendor !== null && (! $vendor->is_active || $vendor->archived_at !== null)) {
            throw ValidationException::withMessages(['vendor_id' => 'المورد غير نشط.']);
        }

        $fundingSource = $data['funding_source'];
        $account = null;
        if ($fundingSource === FixedAsset::FUNDING_COMPANY_ACCOUNT) {
            if (empty($data['financial_account_id'])) {
                throw ValidationException::withMessages(['financial_account_id' => 'يجب اختيار حساب مالي للأصل الممول من الشركة.']);
            }
            $account = FinancialAccount::findOrFail($data['financial_account_id']);
            $this->cashMovements->assertAccountReceivesOrdinaryMovement($account);
        } elseif ($fundingSource === FixedAsset::FUNDING_PERSONAL) {
            if (empty($data['paid_by_user_id'])) {
                throw ValidationException::withMessages(['paid_by_user_id' => 'يجب اختيار الشخص الذي دفع الأصل شخصياً.']);
            }
        } else {
            throw ValidationException::withMessages(['funding_source' => 'مصدر التمويل غير صالح.']);
        }

        $residualMinor = null;
        if (isset($data['residual_value']) && $data['residual_value'] !== '') {
            $residualMinor = Money::fromJod($data['residual_value'])->minorUnits();
            if ($residualMinor < 0) {
                throw ValidationException::withMessages(['residual_value' => 'القيمة المتبقية لا يمكن أن تكون سالبة.']);
            }
        }

        return DB::transaction(function () use ($data, $actor, $amountMinor, $quantity, $category, $vendor, $fundingSource, $account, $residualMinor) {
            $payeeSnapshot = $vendor?->name ?: trim((string) ($data['payee_name'] ?? ''));
            $asset = FixedAsset::create([
                'asset_number' => $this->nextAssetNumber(),
                'name' => $data['name'],
                'asset_category_id' => $category->id,
                'category_name_snapshot' => $category->displayName(),
                'description' => $data['description'] ?? null,
                'vendor_id' => $vendor?->id,
                'payee_name_snapshot' => $payeeSnapshot === '' ? null : $payeeSnapshot,
                'serial_number' => $data['serial_number'] ?? null,
                'quantity' => $quantity,
                'currency' => 'JOD',
                'acquisition_cost_minor' => $amountMinor,
                'funding_source' => $fundingSource,
                'financial_account_id' => $fundingSource === FixedAsset::FUNDING_COMPANY_ACCOUNT ? $account?->id : null,
                'paid_by_user_id' => $fundingSource === FixedAsset::FUNDING_PERSONAL ? (int) $data['paid_by_user_id'] : null,
                'acquired_at' => $data['acquired_at'],
                'in_service_at' => $data['in_service_at'] ?? null,
                'reference' => $data['reference'] ?? null,
                'location' => $data['location'] ?? null,
                'status' => FixedAsset::STATUS_ACTIVE,
                'useful_life_months' => $data['useful_life_months'] ?? null,
                'residual_value_minor' => $residualMinor,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($fundingSource === FixedAsset::FUNDING_COMPANY_ACCOUNT && $account !== null) {
                $this->cashMovements->recordAssetAcquisition($asset, $account, $actor->id);
            }

            $this->log($actor->id, 'fixed_asset_acquired', 'تم تسجيل أصل ثابت', [
                'fixed_asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'category' => $asset->category_name_snapshot,
                'acquisition_cost_minor' => $asset->acquisition_cost_minor,
                'funding_source' => $asset->funding_source,
                'financial_account_id' => $asset->financial_account_id,
                'paid_by_user_id' => $asset->paid_by_user_id,
            ]);

            return $asset->fresh(['category', 'vendor', 'financialAccount', 'personalPayer']);
        });
    }

    public function reverseAssetAcquisition(FixedAsset $asset, string $reason, User $actor, ?Carbon $reversedAt = null): FixedAssetAcquisitionReversal
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'سبب العكس مطلوب.']);
        }
        if ($asset->acquisitionReversal()->exists()) {
            throw ValidationException::withMessages(['fixed_asset_id' => 'تم عكس اقتناء هذا الأصل مسبقاً.']);
        }

        return DB::transaction(function () use ($asset, $reason, $actor, $reversedAt) {
            $reversal = FixedAssetAcquisitionReversal::create([
                'fixed_asset_id' => $asset->id,
                'reason' => $reason,
                'reversed_at' => $reversedAt ?? now(),
                'reversed_by' => $actor->id,
            ]);
            if ($asset->funding_source === FixedAsset::FUNDING_COMPANY_ACCOUNT) {
                $this->cashMovements->recordAssetAcquisitionReversal($reversal, $actor->id);
            }
            $this->log($actor->id, 'fixed_asset_acquisition_reversed', 'تم عكس اقتناء أصل ثابت', [
                'fixed_asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'fixed_asset_acquisition_reversal_id' => $reversal->id,
                'acquisition_cost_minor' => $asset->acquisition_cost_minor,
                'reason' => $reason,
            ]);

            return $reversal->fresh();
        });
    }

    public function activeTotals(): array
    {
        return [
            'funding_minor' => (int) CapitalFundingTransaction::active()->sum('amount_minor'),
            'company_asset_minor' => (int) FixedAsset::activeAcquisitions()->where('funding_source', FixedAsset::FUNDING_COMPANY_ACCOUNT)->sum('acquisition_cost_minor'),
            'personal_asset_minor' => (int) FixedAsset::activeAcquisitions()->where('funding_source', FixedAsset::FUNDING_PERSONAL)->sum('acquisition_cost_minor'),
            'active_asset_count' => FixedAsset::activeAcquisitions()->where('status', FixedAsset::STATUS_ACTIVE)->count(),
        ];
    }

    public function changeAssetStatus(FixedAsset $asset, string $status, User $actor): FixedAsset
    {
        if (! in_array($status, [FixedAsset::STATUS_ACTIVE, FixedAsset::STATUS_OUT_OF_SERVICE], true)) {
            throw ValidationException::withMessages(['status' => 'حالة الأصل غير صالحة في D2B.']);
        }
        $asset->update(['status' => $status]);
        $this->log($actor->id, 'fixed_asset_status_changed', 'تم تغيير حالة أصل ثابت', [
            'fixed_asset_id' => $asset->id,
            'asset_number' => $asset->asset_number,
            'status' => $status,
        ]);

        return $asset->fresh();
    }

    private function nextFundingNumber(): string
    {
        $year = now()->format('Y');
        $next = CapitalFundingTransaction::where('funding_number', 'like', 'FND-'.$year.'-%')->count() + 1;

        return 'FND-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function nextAssetNumber(): string
    {
        $year = now()->format('Y');
        $next = FixedAsset::where('asset_number', 'like', 'AST-'.$year.'-%')->count() + 1;

        return 'AST-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
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
