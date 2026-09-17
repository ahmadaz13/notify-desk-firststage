<?php

namespace App\Exports;

use App\Services\FinancialStatementService;
use App\Support\Money;
use App\Support\ReportingPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExport
{
    /**
     * Generate and stream download of the financial report in Excel-compatible CSV format.
     */
    public function download(string $fileName = 'financial_report.xlsx'): StreamedResponse
    {
        $period = ReportingPeriod::fromRequest(request());
        $statements = app(FinancialStatementService::class);
        $dashboard = $statements->dashboard($period);
        $profitAndLoss = $statements->profitAndLoss($period);
        $cashFlow = $statements->cashFlow($period);
        $capitalAssets = $statements->capitalAssetReport($period);

        $rows = [
            ['تقرير المؤشرات والملخص المالي', 'Notify V1 F1 Management Reporting Export'],
            ['تاريخ التصدير', now()->format('Y-m-d H:i:s')],
            ['الفترة', $period->start->toDateString().' - '.$period->end->toDateString()],
            ['المصدر', 'FinancialStatementService / F1 accounting reports'],
            [''],
            ['المؤشر المالي', 'القيمة (د.أ)', 'الملاحظات'],
            ['Cash Available', $this->jod($dashboard['cash_available_minor']), 'D1 financial accounts plus GL reconciliation'],
            ['Accounts Receivable', $this->jod($dashboard['accounts_receivable_minor']), 'ReceivableService and accounting reconciliation'],
            ['Recognized Revenue', $this->jod($profitAndLoss['total_revenue_minor']), 'Revenue recognition schedules and journals'],
            ['Operating Expenses', $this->jod($profitAndLoss['total_expenses_minor']), 'V2 expenses posted to accounting'],
            ['Management Net Income', $this->jod($profitAndLoss['net_income_minor']), 'Recognized revenue minus V2 operating expenses'],
            ['Closing Cash', $this->jod($cashFlow['closing_cash_minor']), 'Immutable cash movements'],
            ['Company-funded Assets', $this->jod($capitalAssets['company_funded_assets_minor']), 'Fixed assets at recorded acquisition cost'],
            ['Personally-funded Assets', $this->jod($capitalAssets['personally_funded_assets_minor']), 'Fixed assets funded personally'],
        ];

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // Output UTF-8 Byte Order Mark (BOM) so Excel renders Arabic text properly
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $fileName, $headers);
    }

    private function jod(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.Money::fromMinorUnits(abs($minor))->format();
    }
}
