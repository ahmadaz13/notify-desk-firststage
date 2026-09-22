{{-- Advanced Accounting Header --}}
<div class="p4-card" style="margin-bottom: 20px; border-inline-start: 4px solid #475569;">
    <div class="p4-card-head">
        <div>
            <span class="p4-badge" style="background:#E2E8F0; color:#334155; margin-bottom: 6px;">Technical Operations Console</span>
            <h2 class="p4-card-title" style="font-size: 18px;">وحدة الأدوات المحاسبية والعمليات المتقدمة (Advanced Accounting Console)</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">الحسابات المالية، دفتر الأستاذ العام، محرك إطفاء الإيراد، إقفال الفترات، ومطابقة الدفاتر.</p>
        </div>
    </div>
</div>

{{-- Advanced Action Cards Grid --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
    {{-- Card 1: Financial Accounts & Cash Subledger --}}
    <div class="p4-card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                <strong style="font-size: 16px; color: #0F172A;">الحسابات المالية ودفتر حركة النقد</strong>
                <span class="p4-badge p4-badge-primary">Cash Subledger</span>
            </div>
            <p style="font-size: 13px; color: #475569; line-height: 1.5; margin: 0 0 12px 0;">
                إدارة الحسابات البنكية، الصناديق النقدية، التحويلات الداخلية، مطابقة الأرصدة النقدية وتعيين الدفعات التاريخية.
            </p>
        </div>
        <a href="{{ route('financial-accounts.index') }}" class="p4-btn p4-btn-soft" style="width: 100%;">
            فتح إدارة الحسابات والنقد
        </a>
    </div>

    {{-- Card 2: General Ledger & Chart of Accounts --}}
    <div class="p4-card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                <strong style="font-size: 16px; color: #0F172A;">شجرة الحسابات ودفتر الأستاذ العام</strong>
                <span class="p4-badge p4-badge-success">General Ledger</span>
            </div>
            <p style="font-size: 13px; color: #475569; line-height: 1.5; margin: 0 0 12px 0;">
                استعراض دليل الحسابات الموحد، ميزان المراجعة، قيود اليومية المحاسبية المزدوجة، وحركة كل حساب في الأستاذ.
            </p>
        </div>
        <a href="{{ route('accounting.index') }}" class="p4-btn p4-btn-soft" style="width: 100%;">
            فتح الأستاذ العام وقيود اليومية
        </a>
    </div>

    {{-- Card 3: Revenue Recognition Engine --}}
    <div class="p4-card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                <strong style="font-size: 16px; color: #0F172A;">محرك الاعتراف بالإيراد (Revenue Recognition)</strong>
                <span class="p4-badge p4-badge-warning">ASC 606</span>
            </div>
            <p style="font-size: 13px; color: #475569; line-height: 1.5; margin: 0 0 12px 0;">
                إدارة جداول إطفاء الإيراد اليومية والشهرية، تشغيل دورة الاعتراف التلقائية، ومطابقة الإيراد المؤجل مع الأستاذ.
            </p>
        </div>
        <form method="POST" action="{{ route('accounting.revenue-recognition.run') }}" style="display: flex; gap: 8px;">
            @csrf
            <input type="date" name="through" value="{{ now('Asia/Amman')->toDateString() }}" class="p4-input" style="flex: 1;">
            <button type="submit" class="p4-btn p4-btn-soft p4-btn-sm">تشغيل الإطفاء</button>
        </form>
    </div>

    {{-- Card 4: Accounting Periods & Closing --}}
    <div class="p4-card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                <strong style="font-size: 16px; color: #0F172A;">الفترات المحاسبية والإقفال الدوري</strong>
                <span class="p4-badge p4-badge-primary">Period Close</span>
            </div>
            <p style="font-size: 13px; color: #475569; line-height: 1.5; margin: 0 0 12px 0;">
                قفل الفترات المحاسبية الشهرية لمنع التعديلات الرجعية، وإعادة فتح الفترات بضوابط التدقيق عند الضرورة.
            </p>
        </div>
        <a href="{{ route('accounting.index') }}#periods" class="p4-btn p4-btn-soft" style="width: 100%;">
            إدارة الفترات والإقفال
        </a>
    </div>
</div>

{{-- Live Reconciliation & Audit Matrix --}}
<div class="p4-card">
    <div class="p4-card-head" style="margin-bottom: 16px;">
        <div>
            <h2 class="p4-card-title" style="font-size: 17px;">مصفوفة المطابقة والتدقيق بين الدفاتر والأستاذ (Reconciliation Status)</h2>
            <p class="p4-subtitle" style="margin: 2px 0 0 0;">فحص فوري لأي فروقات بين الحسابات التشغيلية ودفتر الأستاذ العام.</p>
        </div>
        <span class="p4-badge {{ $reports['reconciliation']['ok'] ? 'p4-badge-success' : 'p4-badge-danger' }}">
            {{ $reports['reconciliation']['ok'] ? 'جميع الدفاتر مطابقة' : 'توجد فروقات تحتاج مراجعة' }}
        </span>
    </div>

    <div class="p4-table-wrap">
        <table class="p4-table">
            <thead>
                <tr>
                    <th>بند الفحص والمطابقة</th>
                    <th>فارق المطابقة</th>
                    <th>التقييم المحاسبي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reports['reconciliation']['checks'] as $check => $difference)
                    <tr>
                        <td>
                            <strong style="font-size: 14px;">{{ str_replace('_', ' ', $check) }}</strong>
                        </td>
                        <td>
                            <span class="p4-badge {{ $difference === 0 ? 'p4-badge-success' : 'p4-badge-danger' }}">
                                {{ $money($difference) }}
                            </span>
                        </td>
                        <td>
                            @if($difference === 0)
                                <span style="color: #10B981; font-weight: 600; font-size: 13px;">✓ سليم ومطابق تماماً</span>
                            @else
                                <span style="color: #DC2626; font-weight: 600; font-size: 13px;">⚠ يتطلب فحص القيود</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
