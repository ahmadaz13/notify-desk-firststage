{{-- Expenses KPIs --}}
<div class="p4-kpis" style="margin-bottom: 20px;">
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">مصاريف اليوم</span>
        <span class="p4-kpi-value is-primary">{{ $money($expenseTotals['today_minor']) }}</span>
        <span class="p4-kpi-meta">مصاريف مسجلة اليوم</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">مصاريف هذا الشهر</span>
        <span class="p4-kpi-value">{{ $money($expenseTotals['month_minor']) }}</span>
        <span class="p4-kpi-meta">إجمالي الشهر الحالي</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">ممولة من حساب الشركة</span>
        <span class="p4-kpi-value is-success">{{ $money($expenseTotals['company_minor']) }}</span>
        <span class="p4-kpi-meta">نقد خارج من البنك/الصندوق</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">ممولة شخصياً (Due to Parties)</span>
        <span class="p4-kpi-value is-warning">{{ $money($expenseTotals['personal_minor']) }}</span>
        <span class="p4-kpi-meta">التزامات مستحقة للمؤسسين</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">التزامات دورية قادمة</span>
        <span class="p4-kpi-value">{{ $upcomingObligations->count() }}</span>
        <span class="p4-kpi-meta">مستحقة للدفع قريباً</span>
    </div>
    <div class="p4-kpi-card">
        <span class="p4-kpi-label">التزامات دورية متأخرة</span>
        <span class="p4-kpi-value is-danger">{{ $overdueObligations->count() }}</span>
        <span class="p4-kpi-meta">تجاوزت تاريخ الاستحقاق</span>
    </div>
</div>

{{-- Simple Expense Entry Form --}}
<div class="p4-card" style="margin-bottom: 20px;">
    <div class="p4-card-head" style="margin-bottom: 16px;">
        <div>
            <h2 class="p4-card-title" style="font-size: 18px;">تسجيل مصروف تشغيلي (Quick Expense Entry)</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">المدخلات الأساسية فقط: القيمة، التصنيف، وجهة الدفع، مع خيارات إضافية عند الحاجة.</p>
        </div>
        <span class="p4-badge p4-badge-primary">V2 Authoritative Expense</span>
    </div>

    <form method="POST" action="{{ route('operating-expenses.store') }}">
        @csrf
        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        {{-- Normal Inputs --}}
        <div class="p4-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 14px;">
            <div class="p4-field">
                <label style="font-weight: 700;">القيمة (د.أ) *</label>
                <input type="number" step="0.001" min="0.001" name="amount" required placeholder="0.000" class="p4-input" style="font-size: 16px; font-weight: 700;">
            </div>

            <div class="p4-field">
                <label style="font-weight: 700;">التصنيف *</label>
                <select name="category_id" required class="p4-select">
                    <option value="">اختر التصنيف</option>
                    @foreach($activeCategories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->displayName() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="p4-field">
                <label style="font-weight: 700;">مصدر الدفع (Paid From) *</label>
                <select name="funding_source" id="exp_funding_source" required class="p4-select" onchange="toggleFundingFields(this.value)">
                    <option value="{{ \App\Models\Expense::FUNDING_COMPANY_ACCOUNT }}">حساب الشركة (Company Account)</option>
                    <option value="{{ \App\Models\Expense::FUNDING_PERSONAL }}">دفع شخصي (Personal / Founder)</option>
                </select>
            </div>

            <div class="p4-field" id="exp_company_account_wrap">
                <label style="font-weight: 700;">الحساب المالي للشركة *</label>
                <select name="financial_account_id" id="exp_financial_account_id" class="p4-select">
                    <option value="">اختر الحساب البنكي / الصندوق</option>
                    @foreach($activeFinancialAccounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name_ar }}</option>
                    @endforeach
                </select>
            </div>

            <div class="p4-field" id="exp_personal_user_wrap" style="display: none;">
                <label style="font-weight: 700;">الشخص الدافع (Founder / User) *</label>
                <select name="paid_by_user_id" id="exp_paid_by_user_id" class="p4-select">
                    <option value="">اختر الشخص</option>
                    @foreach($internalUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Collapsible Optional Inputs --}}
        <details style="border: 1px dashed #CBD5E1; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; background: #F8FAFC;">
            <summary style="font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; user-select: none;">
                خيارات إضافية (المورد، التاريخ، المرجع، الشرح) — اختياري
            </summary>
            <div class="p4-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px;">
                <div class="p4-field">
                    <label>المورد المحفوظ</label>
                    <select name="vendor_id" class="p4-select">
                        <option value="">بدون مورد محفوظ</option>
                        @foreach($activeVendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="p4-field">
                    <label>اسم المستفيد (حر)</label>
                    <input type="text" name="payee_name" placeholder="اسم المستفيد إذا لم يكن في القائمة" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>تاريخ السداد</label>
                    <input type="date" name="paid_at" value="{{ now('Asia/Amman')->toDateString() }}" class="p4-input">
                </div>
                <div class="p4-field">
                    <label>المرجع / رقم الفاتورة</label>
                    <input type="text" name="reference" placeholder="رقم الإيصال أو الفاتورة" class="p4-input">
                </div>
                <div class="p4-field" style="grid-column: 1 / -1;">
                    <label>الوصف / ملاحظات</label>
                    <input type="text" name="description" placeholder="تفاصيل المصروف التشغيلي" class="p4-input">
                </div>
            </div>
        </details>

        <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="p4-btn p4-btn-primary" style="padding: 10px 24px;">حفظ المصروف وترحيله</button>
        </div>
    </form>
</div>

<script>
function toggleFundingFields(val) {
    const compWrap = document.getElementById('exp_company_account_wrap');
    const persWrap = document.getElementById('exp_personal_user_wrap');
    if (val === '{{ \App\Models\Expense::FUNDING_PERSONAL }}') {
        compWrap.style.display = 'none';
        persWrap.style.display = 'block';
    } else {
        compWrap.style.display = 'block';
        persWrap.style.display = 'none';
    }
}
</script>

{{-- Recent Expenses Workspace --}}
<div class="p4-card">
    <div class="p4-card-head" style="margin-bottom: 16px;">
        <h2 class="p4-card-title" style="font-size: 18px;">آخر المصاريف التشغيلية المسجلة</h2>
        <a href="{{ route('operating-expenses.index') }}" class="p4-btn p4-btn-ghost p4-btn-sm">عرض كل المصاريف</a>
    </div>

    @if($recentExpenses->isEmpty())
        <p style="color: #64748B; font-size: 13px; text-align: center; padding: 20px;">لا توجد مصاريف تشغيلية مسجلة بعد.</p>
    @else
        <div class="p4-table-wrap">
            <table class="p4-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>التصنيف</th>
                        <th>المستفيد / المورد</th>
                        <th>مصدر التمويل</th>
                        <th>القيمة</th>
                        <th>المرجع</th>
                        <th>الحالة / الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentExpenses as $exp)
                        <tr>
                            <td>{{ $exp->paid_at?->toDateString() ?: $exp->date }}</td>
                            <td><strong>{{ $exp->category_name_snapshot }}</strong></td>
                            <td>{{ $exp->payee_name_snapshot ?: '-' }}</td>
                            <td>
                                @if($exp->funding_source === \App\Models\Expense::FUNDING_COMPANY_ACCOUNT)
                                    <span class="p4-badge p4-badge-success">{{ $exp->financialAccount?->name_ar ?: 'حساب الشركة' }}</span>
                                @else
                                    <span class="p4-badge p4-badge-warning">دفع شخصي: {{ $exp->personalPayer?->name ?: 'شريك' }}</span>
                                @endif
                            </td>
                            <td><strong>{{ $money($exp->amount_minor) }}</strong></td>
                            <td><span style="font-size: 12px; color: #64748B;">{{ $exp->reference ?: '-' }}</span></td>
                            <td>
                                @if($exp->reversal)
                                    <span class="p4-badge p4-badge-danger">معكوس</span>
                                @else
                                    <form method="POST" action="{{ route('operating-expenses.reverse', $exp) }}" onsubmit="return confirm('هل أنت متأكد من عكس هذا المصروف؟');" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <input type="hidden" name="reason" value="عكس تصحيحي من لوحة المالية">
                                        <button type="submit" class="p4-btn p4-btn-ghost p4-btn-xs" style="color: #DC2626;">عكس</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
