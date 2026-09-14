<?php

namespace App\Exports;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExport
{
    /**
     * Generate and stream download of the financial report in Excel-compatible CSV format.
     */
    public function download(string $fileName = 'financial_report.xlsx'): StreamedResponse
    {
        $totalPayments = (float) DB::table('payments')->sum('amount');
        $totalExpenses = (float) DB::table('expenses')->sum('amount');
        $totalInvestments = (float) DB::table('investments')->sum('amount');
        $totalCapitalExpenses = (float) DB::table('capital_expenses')->sum('amount');

        $opCostPct = (float) Setting::get('operational_cost_percentage', 20);
        $operationalCost = $totalPayments * ($opCostPct / 100);
        $netRevenue = $totalPayments - $operationalCost;
        $liquidityBalance = $totalInvestments - $totalCapitalExpenses;

        $rows = [
            ['تقرير المؤشرات والملخص المالي', 'Notify V3 Financial Summary Report'],
            ['تاريخ التصدير', now()->format('Y-m-d H:i:s')],
            [''],
            ['المؤشر المالي', 'القيمة (د.أ)', 'الملاحظات'],
            ['إجمالي المقبوضات المحصلة (Total Payments)', number_format($totalPayments, 2), 'إجمالي مبالغ المدفوعات المسجلة للعملاء'],
            ['إجمالي المصروفات العامة (Total Expenses)', number_format($totalExpenses, 2), 'المصروفات التشغيلية المسجلة'],
            ['نسبة تكلفة التشغيل المعتمدة', $opCostPct . '%', 'نسبة تكاليف تشغيل النظام'],
            ['تكلفة التشغيل المحسوبة (Operating Cost)', number_format($operationalCost, 2), 'تكلفة البنية التحتية والتشغيل'],
            ['صافي الإيراد التشغيلي (Net Operating Revenue)', number_format($netRevenue, 2), 'المقبوضات بعد استقطاع التشغيل'],
            ['إجمالي الاستثمارات (Total Investments)', number_format($totalInvestments, 2), 'مجموع رؤوس الأموال المستثمرة'],
            ['المصروفات الاستثمارية (Capital Expenses)', number_format($totalCapitalExpenses, 2), 'المصروفات الرأسمالية من الاستثمار'],
            ['رصيد السيولة النقدية (Liquidity Balance)', number_format($liquidityBalance, 2), 'رصيد الاستثمارات المتاح'],
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
}
