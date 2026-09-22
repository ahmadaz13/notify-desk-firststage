{{-- Financial Invariants Alert --}}
<div class="p4-callout-warning" style="margin-bottom: 20px;">
    <strong>حدود المعاملات الرأسمالية (Capital &amp; Financing Boundaries):</strong>
    <span>{{ __('notify.finance.capital_invariants_note') }}</span>
</div>

{{-- Capital & Assets KPIs --}}
<div class="p4-kpis" style="margin-bottom: 20px;">
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">تمويل رأسمالي وقروض نشطة</span>
        <span class="p4-kpi-value is-primary">{{ $money($capitalTotals['funding_minor']) }}</span>
        <span class="p4-kpi-meta">مساهمات مؤسسين، استثمارات، وقروض</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">أصول ممولة من الشركة</span>
        <span class="p4-kpi-value is-success">{{ $money($capitalTotals['company_asset_minor']) }}</span>
        <span class="p4-kpi-meta">تكلفة اقتناء تاريخية مسجلة</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">أصول ممولة شخصياً</span>
        <span class="p4-kpi-value is-warning">{{ $money($capitalTotals['personal_asset_minor']) }}</span>
        <span class="p4-kpi-meta">مستحقة للمؤسسين كالتزام</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">عدد الأصول الثابتة النشطة</span>
        <span class="p4-kpi-value">{{ $capitalTotals['active_asset_count'] }}</span>
        <span class="p4-kpi-meta">أصل ثابت في الخدمة</span>
    </div>
</div>

{{-- Two Consolidated Forms Grid --}}
<div class="p4-grid-2" style="margin-bottom: 20px;">
    {{-- Form 1: Capital Funding (Equity & Debt) --}}
    <div class="p4-card">
        <div class="p4-card-head" style="margin-bottom: 14px;">
            <div>
                <h2 class="p4-card-title" style="font-size: 17px;">تسجيل تمويل رأسمالي أو قرض</h2>
                <p class="p4-subtitle" style="margin: 2px 0 0 0;">مساهمة شريك، استثمار خارجي، أو قرض مؤسس (لا يعتبر إيراداً).</p>
            </div>
            <span class="p4-badge p4-badge-primary">حقوق ملكية / التزام</span>
        </div>

        <form method="POST" action="{{ route('capital-funding-transactions.store') }}">
            @csrf
            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <div class="p4-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="p4-field">
                    <label style="font-weight: 700;">نوع المعاملة *</label>
                    <select name="funding_type" required class="p4-select">
                        <option value="founder_contribution">مساهمة مؤسس (رأس مال / Equity)</option>
                        <option value="owner_contribution">مساهمة مالك (رأس مال / Equity)</option>
                        <option value="external_investment">استثمار ملكية خارجي (External Equity)</option>
                        <option value="loan_funding">قرض مؤسس / تمويل دَين (Loan Payable)</option>
                        <option value="other_funding">تمويل رأسمالي آخر</option>
                    </select>
                </div>

                <div class="p4-field">
                    <label style="font-weight: 700;">القيمة (د.أ) *</label>
                    <input type="number" step="0.001" min="0.001" name="amount" required placeholder="0.000" class="p4-input" style="font-weight: 700;">
                </div>

                <div class="p4-field">
                    <label style="font-weight: 700;">الحساب المالي المستلم *</label>
                    <select name="financial_account_id" required class="p4-select">
                        <option value="">اختر الحساب</option>
                        @foreach($activeFinancialAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="p4-field">
                    <label style="font-weight: 700;">تاريخ الاستلام *</label>
                    <input type="date" name="received_at" value="{{ now('Asia/Amman')->toDateString() }}" required class="p4-input">
                </div>

                <div class="p4-field">
                    <label>المصدر المحفوظ</label>
                    <select name="funding_source_id" class="p4-select">
                        <option value="">بدون مصدر محفوظ</option>
                        @foreach($activeFundingSources as $source)
                            <option value="{{ $source->id }}">{{ $source->name }} ({{ $source->type }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="p4-field">
                    <label>اسم المصدر (حر)</label>
                    <input type="text" name="source_name" placeholder="اسم الممول أو المستثمر" class="p4-input">
                </div>

                <div class="p4-field" style="grid-column: 1 / -1;">
                    <label>المرجع والملاحظات</label>
                    <input type="text" name="notes" placeholder="ملاحظات أو رقم التحويل البنكي" class="p4-input">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="p4-btn p4-btn-primary">حفظ التمويل وترحيله</button>
            </div>
        </form>
    </div>

    {{-- Form 2: Fixed Asset Acquisition --}}
    <div class="p4-card">
        <div class="p4-card-head" style="margin-bottom: 14px;">
            <div>
                <h2 class="p4-card-title" style="font-size: 17px;">تسجيل اقتناء أصل ثابت (Fixed Asset)</h2>
                <p class="p4-subtitle" style="margin: 2px 0 0 0;">شراء أجهزة، أثاث، أو معدات بتكلفة تاريخية (أصل ثابت وليس مصروفاً تشغيلياً).</p>
            </div>
            <span class="p4-badge p4-badge-success">أصول ثابتة (1500)</span>
        </div>

        <form method="POST" action="{{ route('fixed-assets.store') }}">
            @csrf
            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <div class="p4-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="p4-field">
                    <label style="font-weight: 700;">اسم الأصل *</label>
                    <input type="text" name="name" required placeholder="مثال: لابتوب ماك بوك برو" class="p4-input">
                </div>

                <div class="p4-field">
                    <label style="font-weight: 700;">تصنيف الأصل *</label>
                    <select name="asset_category_id" required class="p4-select">
                        <option value="">اختر التصنيف</option>
                        @foreach($activeAssetCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="p4-field">
                    <label style="font-weight: 700;">تكلفة الاقتناء (د.أ) *</label>
                    <input type="number" step="0.001" min="0.001" name="purchase_cost" required placeholder="0.000" class="p4-input" style="font-weight: 700;">
                </div>

                <div class="p4-field">
                    <label style="font-weight: 700;">تاريخ الاقتناء *</label>
                    <input type="date" name="acquired_at" value="{{ now('Asia/Amman')->toDateString() }}" required class="p4-input">
                </div>

                <div class="p4-field">
                    <label style="font-weight: 700;">مصدر التمويل *</label>
                    <select name="funding_source" id="asset_funding_source" required class="p4-select" onchange="toggleAssetFunding(this.value)">
                        <option value="{{ \App\Models\FixedAsset::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة (Company Account)</option>
                        <option value="{{ \App\Models\FixedAsset::FUNDING_PERSONAL }}">دفع شخصي / شريك (Personal)</option>
                    </select>
                </div>

                <div class="p4-field" id="asset_comp_account_wrap">
                    <label style="font-weight: 700;">الحساب المالي *</label>
                    <select name="financial_account_id" id="asset_financial_account_id" class="p4-select">
                        <option value="">اختر الحساب</option>
                        @foreach($activeFinancialAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="p4-field" id="asset_pers_user_wrap" style="display: none;">
                    <label style="font-weight: 700;">الدافع الشخصي *</label>
                    <select name="paid_by_user_id" id="asset_paid_by_user_id" class="p4-select">
                        <option value="">اختر الدافع</option>
                        @foreach($internalUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="p4-field" style="grid-column: 1 / -1;">
                    <label>الرقم التسلسلي / المرجع</label>
                    <input type="text" name="serial_number" placeholder="الرقم التسلسلي أو رقم الفاتورة" class="p4-input">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="p4-btn p4-btn-primary">حفظ الأصل الثابت</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAssetFunding(val) {
    const compWrap = document.getElementById('asset_comp_account_wrap');
    const persWrap = document.getElementById('asset_pers_user_wrap');
    if (val === '{{ \App\Models\FixedAsset::FUNDING_PERSONAL }}') {
        compWrap.style.display = 'none';
        persWrap.style.display = 'block';
    } else {
        compWrap.style.display = 'block';
        persWrap.style.display = 'none';
    }
}
</script>

{{-- Recent Funding & Asset Registers --}}
<div class="p4-grid-2">
    {{-- Recent Capital Funding --}}
    <div class="p4-card">
        <div class="p4-card-head" style="margin-bottom: 14px;">
            <h2 class="p4-card-title" style="font-size: 16px;">سجل معاملات التمويل الرأسمالي</h2>
            <span class="p4-badge p4-badge-primary">{{ $fundingTransactions->count() }}</span>
        </div>
        @if($fundingTransactions->isEmpty())
            <p style="color: #64748B; font-size: 13px; text-align: center; padding: 20px;">لا توجد معاملات تمويل مسجلة.</p>
        @else
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>المصدر</th>
                            <th>القيمة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fundingTransactions as $txn)
                            <tr>
                                <td>{{ $txn->received_at?->toDateString() }}</td>
                                <td>
                                    @if(in_array($txn->funding_type, ['founder_contribution', 'owner_contribution', 'external_investment']))
                                        <span class="p4-badge p4-badge-success">رأس مال (Equity)</span>
                                    @elseif($txn->funding_type === 'loan_funding')
                                        <span class="p4-badge p4-badge-warning">قرض (Loan)</span>
                                    @else
                                        <span class="p4-badge p4-badge-primary">{{ $txn->funding_type }}</span>
                                    @endif
                                </td>
                                <td>{{ $txn->source_name_snapshot }}</td>
                                <td><strong>{{ $money($txn->amount_minor) }}</strong></td>
                                <td>
                                    @if($txn->reversal)
                                        <span class="p4-badge p4-badge-danger">معكوس</span>
                                    @else
                                        <span class="p4-badge p4-badge-success">نشط</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Fixed Assets Register --}}
    <div class="p4-card">
        <div class="p4-card-head" style="margin-bottom: 14px;">
            <h2 class="p4-card-title" style="font-size: 16px;">سجل الأصول الثابتة (Fixed Assets)</h2>
            <span class="p4-badge p4-badge-success">{{ $fixedAssets->count() }}</span>
        </div>
        @if($fixedAssets->isEmpty())
            <p style="color: #64748B; font-size: 13px; text-align: center; padding: 20px;">لا توجد أصول ثابتة مسجلة.</p>
        @else
            <div class="p4-table-wrap">
                <table class="p4-table">
                    <thead>
                        <tr>
                            <th>الأصل</th>
                            <th>التصنيف</th>
                            <th>التكلفة التاريخية</th>
                            <th>المصدر</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fixedAssets as $fa)
                            <tr>
                                <td><strong>{{ $fa->name }}</strong></td>
                                <td>{{ $fa->category?->name_ar ?: '-' }}</td>
                                <td><strong>{{ $money($fa->purchase_cost_minor) }}</strong></td>
                                <td>
                                    @if($fa->funding_source === \App\Models\FixedAsset::FUNDING_COMPANY_ACCOUNT)
                                        <span class="p4-badge p4-badge-success">الشركة</span>
                                    @else
                                        <span class="p4-badge p4-badge-warning">شخصي</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="p4-badge {{ $fa->status === 'in_service' ? 'p4-badge-success' : 'p4-badge-warning' }}">
                                        {{ $fa->status === 'in_service' ? 'في الخدمة' : $fa->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
