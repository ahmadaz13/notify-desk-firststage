@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">لوحة الإدارة والمزيد</div>
        <h1 class="page-title">مركز التحكم والإعدادات (Admin Control Center)</h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <a class="btn btn-primary touch-btn" href="{{ route('settings.export') }}">
            📊 تصدير التقرير المالي
        </a>
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:24px;margin-top:10px">

    {{-- 1. Financial Settings Section --}}
    <div class="card" style="padding:22px">
        <div class="section-head" style="margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--nd-border)">
            <div>
                <h2 style="font-size:18px;font-weight:800;color:var(--nd-ink);margin:0">الإعدادات المالية (Financial Settings)</h2>
                <div class="muted" style="margin-top:4px">تحديد نسب التشغيل العامة ومضاعف التقييم المالي المعتمد في لوحة المؤشرات</div>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:18px">
                <div class="field">
                    <label style="font-weight:700">نسبة تكلفة التشغيل (%) *</label>
                    <input class="touch-input" type="number" step="0.1" min="0" max="100" name="operational_cost_percentage" value="{{ old('operational_cost_percentage', $operationalCostPercentage) }}" required placeholder="20">
                    <small class="muted" style="display:block;margin-top:4px">تستخدم لخصم تكلفة التشغيل من الإيراد الخام (الافتراضي 20%)</small>
                    @error('operational_cost_percentage') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label style="font-weight:700">مضاعف القيمة السوقية *</label>
                    <input class="touch-input" type="number" step="0.1" min="0.1" max="100" name="market_valuation_multiplier" value="{{ old('market_valuation_multiplier', $marketValuationMultiplier) }}" required placeholder="5">
                    <small class="muted" style="display:block;margin-top:4px">مضاعف الإيراد السنوي المتكرر ARR لحساب القيمة التقديرية (الافتراضي 5)</small>
                    @error('market_valuation_multiplier') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="margin-top:16px;padding:12px 16px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;color:var(--nd-ink)">
                    <input type="checkbox" name="allow_auto_transfer_clients" value="1" {{ $allowAutoTransferClients ? 'checked' : '' }} style="width:18px;height:18px;accent-color:var(--nd-primary)">
                    <span>السماح بنقل العملاء بين الشركاء تلقائياً</span>
                </label>
                <small class="muted" style="display:block;margin-top:4px;margin-right:28px">عند تفعيل هذا الخيار، يتم قبول طلبات المندوبين دون إنشاء تعارض يدوي يتطلب المراجعة</small>
            </div>

            <div style="margin-top:20px;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary touch-btn" style="min-height:44px;padding:10px 24px">
                    حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>

    {{-- 2. Partner Management Section --}}
    <div class="card" style="padding:22px">
        <div class="section-head" style="margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--nd-border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <h2 style="font-size:18px;font-weight:800;color:var(--nd-ink);margin:0">إدارة الشركاء (Partner Management)</h2>
                <div class="muted" style="margin-top:4px">التحكم في الشركاء، نسب الأرباح، وروابط تسجيل العملاء الخاصة بالمندوبين</div>
            </div>
            <a href="{{ route('partners.create') }}" class="btn btn-primary touch-btn" style="display:inline-flex;align-items:center;gap:6px">
                <span>＋</span>
                <span>إضافة شريك جديد</span>
            </a>
        </div>

        @if($partners->isEmpty())
            <div class="muted" style="padding:24px;text-align:center;background:#f8fafc;border-radius:12px;border:1px dashed var(--nd-border)">
                لا يوجد شركاء مسجلين حالياً في النظام. <a href="{{ route('partners.create') }}" style="color:var(--nd-primary);font-weight:700">أضف شريكاً جديداً الآن ←</a>
            </div>
        @else
            <div class="table-wrap" style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;text-align:right">
                    <thead>
                        <tr style="border-bottom:2px solid var(--nd-border);color:var(--nd-ink);font-size:13px">
                            <th style="padding:10px 12px">الشركة / الشريك</th>
                            <th style="padding:10px 12px">البريد الإلكتروني</th>
                            <th style="padding:10px 12px">نسبة الأرباح</th>
                            <th style="padding:10px 12px">عدد العملاء</th>
                            <th style="padding:10px 12px">رابط المندوب</th>
                            <th style="padding:10px 12px;text-align:center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($partners as $partner)
                            @php
                                $delegateLink = url('/p/' . $partner->public_uuid . '/client/create');
                            @endphp
                            <tr style="border-bottom:1px solid var(--nd-border);font-size:14px">
                                <td style="padding:12px">
                                    <strong style="color:var(--nd-ink)">{{ $partner->company_name }}</strong>
                                    @if($partner->phone)<div class="muted" style="font-size:12px">{{ $partner->phone }}</div>@endif
                                </td>
                                <td style="padding:12px;direction:ltr;text-align:right">{{ $partner->email }}</td>
                                <td style="padding:12px">
                                    @if($partner->profit_share_percentage !== null)
                                        <span class="badge" style="background:#ede9fe;color:#6d28d9;font-weight:700">
                                            {{ number_format($partner->profit_share_percentage, 1) }}%
                                        </span>
                                    @else
                                        <span class="muted" style="font-size:12px">غير محدد</span>
                                    @endif
                                </td>
                                <td style="padding:12px">
                                    <span class="badge blue">{{ $partner->clients_count ?? $partner->clients()->count() }}</span>
                                </td>
                                <td style="padding:12px">
                                    <div style="display:flex;align-items:center;gap:6px;max-width:280px">
                                        <input class="touch-input" type="text" readonly value="{{ $delegateLink }}" id="link-{{ $partner->id }}" style="font-size:11px;padding:4px 8px;direction:ltr;height:32px;background:#f1f5f9">
                                        <button type="button" class="btn btn-soft" style="padding:4px 8px;font-size:12px;height:32px;white-space:nowrap" onclick="navigator.clipboard.writeText('{{ $delegateLink }}'); alert('تم نسخ رابط المندوب بنجاح');">
                                            نسخ
                                        </button>
                                    </div>
                                </td>
                                <td style="padding:12px;text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center">
                                        <a href="{{ route('partners.edit', $partner->id) }}" class="btn btn-soft" style="padding:4px 10px;font-size:12px">
                                            تعديل
                                        </a>
                                        <form method="POST" action="{{ route('partners.destroy', $partner->id) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا الشريك والحساب المرتبط به؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost" style="padding:4px 8px;font-size:12px;color:var(--nd-danger)">
                                                حذف
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 3. Activity Log Section --}}
    <div class="card" style="padding:22px">
        <div class="section-head" style="margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--nd-border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <h2 style="font-size:18px;font-weight:800;color:var(--nd-ink);margin:0">سجل النشاطات (Activity Log)</h2>
                <div class="muted" style="margin-top:4px">عرض أحدث 50 حركة ونشاط مسجل في النظام مع إمكانية التصفية حسب النوع</div>
            </div>

            {{-- Filter Dropdown --}}
            <form method="GET" action="{{ route('settings.index') }}" style="display:flex;align-items:center;gap:8px">
                <label style="font-size:13px;font-weight:600;white-space:nowrap">تصفية حسب النوع:</label>
                <select name="type" class="touch-input" onchange="this.form.submit()" style="height:36px;padding:4px 10px;font-size:13px">
                    <option value="all" {{ $selectedType === 'all' ? 'selected' : '' }}>جميع النشاطات</option>
                    @foreach($activityTypes as $t)
                        <option value="{{ $t }}" {{ $selectedType === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
                @if($selectedType !== 'all')
                    <a href="{{ route('settings.index') }}" class="btn btn-ghost" style="font-size:12px;padding:4px 8px">إلغاء</a>
                @endif
            </form>
        </div>

        @if($activityLogs->isEmpty())
            <div class="muted" style="padding:24px;text-align:center;background:#f8fafc;border-radius:12px;border:1px dashed var(--nd-border)">
                لا توجد سجلات لعرضها
            </div>
        @else
            <div class="table-wrap" style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;text-align:right">
                    <thead>
                        <tr style="border-bottom:2px solid var(--nd-border);color:var(--nd-ink);font-size:13px">
                            <th style="padding:10px 12px;width:160px">الوقت</th>
                            <th style="padding:10px 12px;width:180px">النوع</th>
                            <th style="padding:10px 12px">الوصف</th>
                            <th style="padding:10px 12px">المستخدم / العميل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activityLogs as $log)
                            <tr style="border-bottom:1px solid var(--nd-border);font-size:13px">
                                <td style="padding:10px 12px;white-space:nowrap;color:var(--nd-muted)">
                                    {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i') }}
                                </td>
                                <td style="padding:10px 12px">
                                    <span class="badge {{ str_contains($log->type, 'conflict') ? 'red' : (str_contains($log->type, 'delegate') ? 'blue' : (str_contains($log->type, 'transferred') ? 'gold' : 'gray')) }}" style="font-size:11px">
                                        {{ $log->type }}
                                    </span>
                                </td>
                                <td style="padding:10px 12px;color:var(--nd-ink)">
                                    {{ $log->description ?: '—' }}
                                </td>
                                <td style="padding:10px 12px;color:var(--nd-ink)">
                                    @if(!empty($log->user_name))
                                        <div>👤 {{ $log->user_name }}</div>
                                    @endif
                                    @if(!empty($log->client_name))
                                        <div class="muted" style="font-size:11px">🏢 {{ $log->client_name }}</div>
                                    @endif
                                    @if(empty($log->user_name) && empty($log->client_name))
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 4. Data Export Section --}}
    <div class="card" style="padding:22px;background:linear-gradient(to left, #f8fafc 0%, #ffffff 100%)">
        <div class="section-head" style="margin-bottom:12px">
            <div>
                <h2 style="font-size:18px;font-weight:800;color:var(--nd-ink);margin:0">تصدير البيانات (Export Data)</h2>
                <div class="muted" style="margin-top:4px">تصدير كشوفات المؤشرات المالية بصيغة جدول Excel معتمد</div>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;background:#f1f5f9;padding:18px 20px;border-radius:12px;border:1px solid var(--nd-border)">
            <div>
                <div style="font-weight:700;color:var(--nd-ink);font-size:15px">الملخص المالي الشامل (Excel Report)</div>
                <div class="muted" style="font-size:13px;margin-top:4px">
                    يحتوي التقرير على: إجمالي المقبوضات، إجمالي المصروفات، الاستثمارات، المصروفات الرأسمالية، صافي الإيراد التشغيلي، ورصيد السيولة النقدية.
                </div>
            </div>
            <div>
                <a href="{{ route('settings.export') }}" class="btn btn-primary touch-btn" style="min-height:44px;padding:10px 22px;display:inline-flex;align-items:center;gap:8px">
                    <span>📥</span>
                    <span>تصدير التقرير المالي</span>
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
