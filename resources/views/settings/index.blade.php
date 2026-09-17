@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    {{-- Header --}}
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">Administration &amp; Configuration</div>
            <h1 class="p5-title">{{ __('notify.settings.title') }}</h1>
            <p class="p5-subtitle">إدارة المحددات التشغيلية، مراجع الشركاء التاريخية، سجل الأنشطة والتدقيق، وتصدير التقارير الإدارية المعتمدة.</p>
        </div>
        <div class="p5-header-actions">
            <a class="p5-btn p5-btn-primary" href="{{ route('settings.export') }}">{{ __('notify.settings.export_financial_excel') }}</a>
        </div>
    </header>

    {{-- 1. Financial Configuration --}}
    <x-notify.collapsible-section
        :title="__('notify.settings.parameters_title')"
        subtitle="القيم الافتراضية المعتمدة لجدولة الفواتير والضرائب العامة"
        icon="sliders"
        :open="true"
    >
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            <div class="p5-form-grid">
                <div class="p5-field">
                    <label>نسبة تكلفة التشغيل القديمة</label>
                    <input type="text" value="{{ $operationalCostPercentage }}%" disabled class="p5-input" style="background:#F8FAFC;color:#64748B">
                    <span class="p5-kpi-meta">Deprecated: لا تؤثر على P&amp;L أو Finance أو Executive</span>
                </div>

                <div class="p5-field">
                    <label>مضاعف القيمة السوقية القديم</label>
                    <input type="text" value="{{ $marketValuationMultiplier }}" disabled class="p5-input" style="background:#F8FAFC;color:#64748B">
                    <span class="p5-kpi-meta">Deprecated: لا توجد قيمة سوقية تقديرية في V1</span>
                </div>

                <div class="p5-field">
                    <label>خصم الدفع السنوي الافتراضي (%)</label>
                    <input type="number" step="0.5" min="0" max="100" name="annual_discount_percentage" value="{{ old('annual_discount_percentage', $annualDiscountPercentage) }}" placeholder="10.0" class="p5-input">
                    <span class="p5-kpi-meta">الأسعار السنوية المعتمدة في V2 هي PlanPrice صريحة</span>
                    @error('annual_discount_percentage') <span style="color:#B42318;font-size:12px">{{ $message }}</span> @enderror
                </div>

                <div class="p5-field">
                    <label>ضريبة المبيعات العامة (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="sales_tax_percentage" value="{{ old('sales_tax_percentage', $salesTaxPercentage) }}" placeholder="16.0" class="p5-input">
                    <span class="p5-kpi-meta">Review only: لا تتجاوز لقطة الضريبة على PlanPrice</span>
                    @error('sales_tax_percentage') <span style="color:#B42318;font-size:12px">{{ $message }}</span> @enderror
                </div>

                <div class="p5-field">
                    <label>يوم استحقاق الأقساط الشهرية الافتراضي *</label>
                    <select name="monthly_due_day" class="p5-select">
                        <option value="1" {{ old('monthly_due_day', $monthlyDueDay) == 1 ? 'selected' : '' }}>1 من كل شهر (موصى به)</option>
                        <option value="5" {{ old('monthly_due_day', $monthlyDueDay) == 5 ? 'selected' : '' }}>5 من كل شهر</option>
                        <option value="15" {{ old('monthly_due_day', $monthlyDueDay) == 15 ? 'selected' : '' }}>15 من كل شهر</option>
                        <option value="30" {{ old('monthly_due_day', $monthlyDueDay) == 30 ? 'selected' : '' }}>30 من كل شهر (نهاية الشهر)</option>
                    </select>
                    <span class="p5-kpi-meta">اليوم المعتمد لجدولة تنبيهات الأقساط الشهرية</span>
                    @error('monthly_due_day') <span style="color:#B42318;font-size:12px">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="margin-top:14px;padding:12px 14px;background:#F8FAFC;border-radius:8px;border:1px solid #E2E8F0">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;font-weight:700;color:#0A1128">
                    <input type="checkbox" name="allow_auto_transfer_clients" value="1" {{ $allowAutoTransferClients ? 'checked' : '' }} style="accent-color:#0055CC;width:16px;height:16px">
                    <span>السماح بنقل العملاء بين الشركاء تلقائياً</span>
                </label>
                <span class="p5-kpi-meta" style="margin-top:4px;display:block">عند التفعيل، يتم قبول طلبات المندوبين دون إنشاء تعارض يدوي يتطلب مراجعة المشرفين.</span>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:14px">
                <button type="submit" class="p5-btn p5-btn-primary">حفظ الإعدادات</button>
            </div>
        </form>
    </x-notify.collapsible-section>

    {{-- Authoritative Source Notice --}}
    <div class="p5-list-item" style="border-inline-start:4px solid #0055CC;background:#EFF8FF;margin-block-end:16px">
        <strong style="font-size:13px;color:#0055CC">المصادر المالية المعتمدة في النظام:</strong>
        <span class="p5-kpi-meta" style="color:#1E3A8A">
            تم استبدال سلاسل المعادلات القديمة بالخدمات التخصصية المعتمدة:
            <a href="{{ route('finance.index') }}" style="color:#0055CC;font-weight:700">Finance</a> للقوائم الإدارية والإيراد المعترف به،
            <a href="{{ route('saas-metrics.index') }}" style="color:#0055CC;font-weight:700">SaaS Metrics</a> لمقاييس MRR و ARR،
            <a href="{{ route('capital-management.index') }}" style="color:#0055CC;font-weight:700">Capital Management</a> للتمويل والأصول،
            و <a href="{{ route('commercial-catalog.index') }}" style="color:#0055CC;font-weight:700">Commercial Catalog</a> للباقات وإصدارات الأسعار.
        </span>
    </div>

    {{-- 2. Partner Management --}}
    <x-notify.collapsible-section
        :title="__('notify.settings.partners_title')"
        subtitle="بيانات إحالة وتاريخ فقط؛ لا توجد حسابات دخول أو صلاحيات شريك نشطة في V1"
        :badge="$partners->count()"
        icon="users"
        :open="true"
    >
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
            <a href="{{ Route::has('partners.create') ? route('partners.create') : '#' }}" class="p5-btn p5-btn-primary p5-btn-sm">＋ {{ __('notify.settings.add_partner') }}</a>
        </div>

        @if($partners->isEmpty())
            <div class="p5-kpi-meta" style="padding:20px;text-align:center">
                {{ __('notify.settings.no_partners') }} <a href="{{ Route::has('partners.create') ? route('partners.create') : '#' }}" style="color:#0055CC;font-weight:700">إضافة شريك جديد ←</a>
            </div>
        @else
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th>{{ __('notify.settings.company_or_partner') }}</th>
                            <th>{{ __('notify.settings.email') }}</th>
                            <th>{{ __('notify.settings.profit_share') }}</th>
                            <th>{{ __('notify.settings.clients_count') }}</th>
                            <th>{{ __('notify.settings.delegate_link') }}</th>
                            <th style="text-align:center">{{ __('notify.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($partners as $partner)
                            @php
                                $delegateLink = url('/p/' . $partner->public_uuid . '/client/create');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $partner->company_name }}</strong>
                                    @if($partner->phone)<span class="p5-kpi-meta">{{ $partner->phone }}</span>@endif
                                </td>
                                <td dir="ltr" style="text-align:right">{{ $partner->email }}</td>
                                <td>
                                    @if($partner->profit_share_percentage !== null)
                                        <span class="p5-badge p5-badge-primary">{{ number_format($partner->profit_share_percentage, 1) }}%</span>
                                    @else
                                        <span class="p5-kpi-meta">غير محدد</span>
                                    @endif
                                </td>
                                <td><span class="p5-badge p5-badge-neutral">{{ $partner->clients_count ?? $partner->clients()->count() }}</span></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;max-width:260px">
                                        <input type="text" readonly value="{{ $delegateLink }}" class="p5-input" style="font-size:11px;padding:4px 6px;direction:ltr;background:#F1F5F9">
                                        <button type="button" class="p5-btn p5-btn-soft p5-btn-sm" style="white-space:nowrap" onclick="navigator.clipboard.writeText('{{ $delegateLink }}'); alert('تم نسخ رابط المندوب بنجاح');">{{ __('notify.actions.copy') }}</button>
                                    </div>
                                </td>
                                <td style="text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center">
                                        <a href="{{ Route::has('partners.edit') ? route('partners.edit', $partner->id) : '#' }}" class="p5-btn p5-btn-ghost p5-btn-sm">{{ __('notify.actions.edit') }}</a>
                                        <form method="POST" action="{{ Route::has('partners.destroy') ? route('partners.destroy', $partner->id) : '#' }}" onsubmit="return confirm('هل أنت متأكد من أرشفة مرجع الشريك؟ سيبقى السجل التاريخي محفوظاً.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p5-btn p5-btn-danger p5-btn-sm">{{ __('notify.actions.archive') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-notify.collapsible-section>

    {{-- 3. Activity Log --}}
    <x-notify.collapsible-section
        :title="__('notify.settings.activity_log_title')"
        :subtitle="__('notify.settings.activity_log_subtitle')"
        :badge="$activityLogs->count()"
        icon="file-text"
        :open="$selectedType !== 'all'"
    >
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
            <form method="GET" action="{{ route('settings.index') }}" style="display:flex;align-items:center;gap:8px">
                <label style="font-size:12px;font-weight:700;color:#0A1128">{{ __('notify.settings.filter_activity') }}</label>
                <select name="type" class="p5-select" onchange="this.form.submit()" style="padding:4px 8px;font-size:12px;min-width:140px">
                    <option value="all" {{ $selectedType === 'all' ? 'selected' : '' }}>{{ __('notify.settings.all_activities') }}</option>
                    @foreach($activityTypes as $t)
                        <option value="{{ $t }}" {{ $selectedType === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
                @if($selectedType !== 'all')
                    <a href="{{ route('settings.index') }}" class="p5-btn p5-btn-ghost p5-btn-sm">{{ __('notify.actions.cancel') }}</a>
                @endif
            </form>
        </div>

        @if($activityLogs->isEmpty())
            <div class="p5-kpi-meta" style="padding:20px;text-align:center">{{ __('notify.settings.no_activity_logs') }}</div>
        @else
            <div class="p5-table-wrap">
                <table class="p5-table">
                    <thead>
                        <tr>
                            <th style="width:140px">{{ __('notify.common.time') }}</th>
                            <th style="width:150px">{{ __('notify.common.description') }}</th>
                            <th>{{ __('notify.common.description') }}</th>
                            <th>{{ __('notify.settings.user_or_client') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activityLogs as $log)
                            <tr>
                                <td style="white-space:nowrap;color:#64748B;font-size:12px">
                                    {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i') }}
                                </td>
                                <td>
                                    <span class="p5-badge {{ str_contains($log->type, 'conflict') ? 'p5-badge-danger' : (str_contains($log->type, 'delegate') ? 'p5-badge-primary' : (str_contains($log->type, 'transferred') ? 'p5-badge-warning' : 'p5-badge-neutral')) }}">
                                        {{ $log->type }}
                                    </span>
                                </td>
                                <td>{{ $log->description ?: '—' }}</td>
                                <td>
                                    @if(!empty($log->user_name))
                                        <div>👤 {{ $log->user_name }}</div>
                                    @endif
                                    @if(!empty($log->client_name))
                                        <span class="p5-kpi-meta">🏢 {{ $log->client_name }}</span>
                                    @endif
                                    @if(empty($log->user_name) && empty($log->client_name))
                                        <span class="p5-kpi-meta">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-notify.collapsible-section>
</div>
@endsection
