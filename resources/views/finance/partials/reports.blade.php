{{-- 1. GL-Backed Financial Statements --}}
<div class="p4-card" style="margin-bottom: 20px;">
    <div class="p4-card-head" style="margin-bottom: 16px;">
        <div>
            <h2 class="p4-card-title" style="font-size: 18px;">القوائم المالية الإدارية (GL-Backed Financial Statements)</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">تقارير الأداء المالي والمركز المالي المستخرجة من قيود دفتر الأستاذ العام وحركات النقد.</p>
        </div>
        <span class="p4-badge p4-badge-primary">General Ledger Authority</span>
    </div>

    <div class="p4-grid-2">
        {{-- Profit & Loss --}}
        <div class="p4-card" style="background: #F8FAFC;">
            <div class="p4-card-head">
                <h3 class="p4-card-title" style="font-size: 16px;">{{ __('notify.finance.profit_loss') }}</h3>
                <a class="p4-btn p4-btn-soft p4-btn-sm" href="{{ route('finance.export', ['report' => 'profit-and-loss'] + request()->query()) }}">تصدير CSV</a>
            </div>
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <tbody>
                        <tr><th>{{ __('notify.finance.saas_revenue') }}</th><td>{{ $money($reports['profit_and_loss']['saas_revenue_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.finance.onetime_revenue') }}</th><td>{{ $money($reports['profit_and_loss']['one_time_revenue_minor']) }}</td></tr>
                        <tr><th>إجمالي الإيراد المعترف به</th><td><strong>{{ $money($reports['profit_and_loss']['total_revenue_minor']) }}</strong></td></tr>
                        <tr><th>إجمالي المصاريف التشغيلية</th><td>{{ $money($reports['profit_and_loss']['total_expenses_minor']) }}</td></tr>
                        <tr><th>صافي الدخل الإداري</th><td><strong style="color: {{ $reports['profit_and_loss']['net_income_minor'] >= 0 ? '#10B981' : '#DC2626' }};">{{ $money($reports['profit_and_loss']['net_income_minor']) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Balance Sheet --}}
        <div class="p4-card" style="background: #F8FAFC;">
            <div class="p4-card-head">
                <h3 class="p4-card-title" style="font-size: 16px;">{{ __('notify.finance.balance_sheet') }}</h3>
                <a class="p4-btn p4-btn-soft p4-btn-sm" href="{{ route('finance.export', ['report' => 'balance-sheet'] + request()->query()) }}">تصدير CSV</a>
            </div>
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <tbody>
                        <tr><th>{{ __('notify.finance.total_assets') }}</th><td>{{ $money($reports['balance_sheet']['total_assets_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.finance.total_liabilities') }}</th><td>{{ $money($reports['balance_sheet']['total_liabilities_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.finance.equity') }}</th><td>{{ $money($reports['balance_sheet']['total_equity_minor']) }}</td></tr>
                        <tr>
                            <th>فارق معادلة الميزانية</th>
                            <td>
                                <span class="p4-badge {{ $reports['balance_sheet']['equation_difference_minor'] === 0 ? 'p4-badge-success' : 'p4-badge-danger' }}">
                                    {{ $money($reports['balance_sheet']['equation_difference_minor']) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cash Flow --}}
        <div class="p4-card" style="background: #F8FAFC;">
            <div class="p4-card-head">
                <h3 class="p4-card-title" style="font-size: 16px;">{{ __('notify.finance.cash_flow') }}</h3>
                <a class="p4-btn p4-btn-soft p4-btn-sm" href="{{ route('finance.export', ['report' => 'cash-flow'] + request()->query()) }}">تصدير CSV</a>
            </div>
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <tbody>
                        <tr><th>النقد الافتتاحي</th><td>{{ $money($reports['cash_flow']['opening_cash_minor']) }}</td></tr>
                        <tr><th>التدفقات التشغيلية</th><td>{{ $money($reports['cash_flow']['operating_cash_flow_minor']) }}</td></tr>
                        <tr><th>التدفقات الاستثمارية</th><td>{{ $money($reports['cash_flow']['investing_cash_flow_minor']) }}</td></tr>
                        <tr><th>التدفقات التمويلية</th><td>{{ $money($reports['cash_flow']['financing_cash_flow_minor']) }}</td></tr>
                        <tr><th>النقد الختامي</th><td><strong>{{ $money($reports['cash_flow']['closing_cash_minor']) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- AR Aging --}}
        <div class="p4-card" style="background: #F8FAFC;">
            <div class="p4-card-head">
                <h3 class="p4-card-title" style="font-size: 16px;">{{ __('notify.collections.aging') }}</h3>
                <a class="p4-btn p4-btn-soft p4-btn-sm" href="{{ route('finance.export', ['report' => 'ar-aging'] + request()->query()) }}">تصدير CSV</a>
            </div>
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <tbody>
                        @foreach($reports['ar_aging']['summary']['buckets'] as $bucket => $amount)
                            <tr><th>{{ str_replace('_', ' ', $bucket) }}</th><td>{{ $money($amount) }}</td></tr>
                        @endforeach
                        <tr><th>إجمالي الذمم</th><td><strong>{{ $money($reports['ar_aging']['summary']['total_ar_minor']) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Deferred Revenue --}}
        <div class="p4-card" style="background: #F8FAFC;">
            <div class="p4-card-head">
                <h3 class="p4-card-title" style="font-size: 16px;">{{ __('notify.finance.deferred_revenue') }}</h3>
                <a class="p4-btn p4-btn-soft p4-btn-sm" href="{{ route('finance.export', ['report' => 'deferred-revenue'] + request()->query()) }}">تصدير CSV</a>
            </div>
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <tbody>
                        @foreach($reports['deferred_revenue']['summary'] as $label => $amount)
                            <tr><th>{{ str_replace('_', ' ', $label) }}</th><td>{{ $money((int) $amount) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Customer Credits & Tax --}}
        <div class="p4-card" style="background: #F8FAFC;">
            <div class="p4-card-head">
                <h3 class="p4-card-title" style="font-size: 16px;">أرصدة العملاء وضريبة المبيعات</h3>
            </div>
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <tbody>
                        <tr><th>{{ __('notify.finance.unallocated_payments') }}</th><td>{{ $money($reports['customer_credits']['unallocated_payment_credit_minor']) }}</td></tr>
                        <tr><th>{{ __('notify.finance.credit_note_balances') }}</th><td>{{ $money($reports['customer_credits']['credit_note_credit_minor']) }}</td></tr>
                        <tr><th>ضريبة الفواتير</th><td>{{ $money($reports['sales_tax']['invoice_tax_minor']) }}</td></tr>
                        <tr><th>صافي ضريبة المبيعات المستحقة</th><td><strong>{{ $money($reports['sales_tax']['net_sales_tax_payable_minor']) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- 2. Commercial SaaS Reports (Decoupled from Accounting P&L) --}}
<div class="p4-card" style="border: 2px solid #10B981;">
    <div class="p4-card-head" style="margin-bottom: 16px;">
        <div>
            <span class="p4-badge p4-badge-success" style="margin-bottom: 6px;">Commercial Contract Analytics</span>
            <h2 class="p4-card-title" style="font-size: 18px; color: #065F46;">تقارير الاشتراكات التجارية (Commercial SaaS Reports)</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">تقارير دورية لاقتصاديات الاشتراكات من محرك SaaS Metrics، مستقلة تماماً عن قائمة الدخل المحاسبية.</p>
        </div>
        <a href="{{ route('saas-metrics.index') }}" class="p4-btn p4-btn-soft p4-btn-sm">لوحة المقاييس الكاملة</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
        <div style="padding: 16px; border: 1px solid #E2E8F0; border-radius: 10px; background: #FFFFFF; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
            <div>
                <strong style="font-size: 15px; color: #0F172A; display: block; margin-bottom: 4px;">ملخص مقاييس SaaS</strong>
                <p style="font-size: 12px; color: #64748B; margin: 0;">ملخص MRR و ARR وصافي النمو ونسب الاستبقاء.</p>
            </div>
            <a href="{{ route('saas-metrics.export', ['report' => 'summary'] + request()->query()) }}" class="p4-btn p4-btn-soft p4-btn-sm" style="width: 100%;">
                تصدير ملخص SaaS (CSV)
            </a>
        </div>

        <div style="padding: 16px; border: 1px solid #E2E8F0; border-radius: 10px; background: #FFFFFF; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
            <div>
                <strong style="font-size: 15px; color: #0F172A; display: block; margin-bottom: 4px;">حركة MRR الشهرية</strong>
                <p style="font-size: 12px; color: #64748B; margin: 0;">تفصيل شهري للنمو، التوسع، الانكماش، وفقدان الاشتراكات.</p>
            </div>
            <a href="{{ route('saas-metrics.export', ['report' => 'monthly-movement'] + request()->query()) }}" class="p4-btn p4-btn-soft p4-btn-sm" style="width: 100%;">
                تصدير الحركة الشهرية (CSV)
            </a>
        </div>

        <div style="padding: 16px; border: 1px solid #E2E8F0; border-radius: 10px; background: #FFFFFF; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
            <div>
                <strong style="font-size: 15px; color: #0F172A; display: block; margin-bottom: 4px;">سجل الاشتراكات النشطة</strong>
                <p style="font-size: 12px; color: #64748B; margin: 0;">قائمة بجميع الاشتراكات النشطة مع قيمة MRR و ARR لكل اشتراك.</p>
            </div>
            <a href="{{ route('saas-metrics.export', ['report' => 'active-subscriptions'] + request()->query()) }}" class="p4-btn p4-btn-soft p4-btn-sm" style="width: 100%;">
                تصدير الاشتراكات النشطة (CSV)
            </a>
        </div>

        <div style="padding: 16px; border: 1px solid #E2E8F0; border-radius: 10px; background: #FFFFFF; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
            <div>
                <strong style="font-size: 15px; color: #0F172A; display: block; margin-bottom: 4px;">توزيع MRR حسب الباقات</strong>
                <p style="font-size: 12px; color: #64748B; margin: 0;">توزيع الإيراد المتكرر وعدد المشتركين لكل باقة تجارية.</p>
            </div>
            <a href="{{ route('saas-metrics.export', ['report' => 'mrr-by-plan'] + request()->query()) }}" class="p4-btn p4-btn-soft p4-btn-sm" style="width: 100%;">
                تصدير توزيع الباقات (CSV)
            </a>
        </div>
    </div>
</div>
