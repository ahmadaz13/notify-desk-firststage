@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">Commercial Catalog</div>
        <h1 class="page-title">إدارة الباقات والأسعار</h1>
    </div>
    <a class="btn btn-ghost" href="{{ route('settings.index') }}">العودة للإعدادات</a>
</div>

<div class="grid grid-2" style="align-items:start">
    <div class="card form-card">
        <h2 style="margin-top:0">إنشاء باقة جديدة</h2>
        <form method="POST" action="{{ route('commercial-catalog.plans.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>رمز الباقة *</label>
                    <input name="code" required placeholder="restaurant_package_custom">
                </div>
                <div class="field">
                    <label>اسم الباقة بالعربية *</label>
                    <input name="name_ar" required placeholder="باقة المطاعم المتقدمة">
                </div>
                <div class="field">
                    <label>اسم الباقة بالإنجليزية</label>
                    <input name="name_en" placeholder="Restaurant Advanced">
                </div>
                <div class="field full">
                    <label>الخدمات المشمولة</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px">
                        @foreach($services as $service)
                            <label style="display:flex;gap:8px;align-items:center;background:#f8fafc;border:1px solid var(--nd-border);border-radius:8px;padding:8px">
                                <input type="checkbox" name="services[]" value="{{ $service->id }}">
                                <span>{{ $service->name_ar }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="field full">
                    <label>وصف عربي</label>
                    <textarea name="description_ar" rows="2"></textarea>
                </div>
            </div>
            <button class="btn btn-primary" style="margin-top:12px" type="submit">إنشاء الباقة</button>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">مبادئ الأسعار الجديدة</h2>
        <div class="list">
            <div class="list-row"><span class="badge blue">JOD</span><div class="list-main">كل الأسعار الجديدة محفوظة بالفلس كأرقام صحيحة.</div></div>
            <div class="list-row"><span class="badge gold">History</span><div class="list-main">إنشاء سعر جديد ينهي السعر السابق ولا يغيّر الاشتراكات أو الفواتير التاريخية.</div></div>
            <div class="list-row"><span class="badge green">Invoice</span><div class="list-main">الفواتير تحفظ لقطات السطور والمبالغ وقت الإصدار.</div></div>
        </div>
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:18px;margin-top:18px">
@foreach($plans as $plan)
    <div class="card" style="border-right:4px solid {{ $plan->archived_at ? 'var(--nd-danger)' : 'var(--nd-primary)' }}">
        <div class="section-head" style="margin-top:0">
            <div>
                <h2 style="margin:0">{{ $plan->name_ar }}</h2>
                <div class="muted ltr" style="direction:ltr;text-align:right">{{ $plan->code }}</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <span class="badge {{ $plan->is_active && !$plan->archived_at ? 'green' : 'red' }}">
                    {{ $plan->is_active && !$plan->archived_at ? 'فعالة' : 'مؤرشفة/معطلة' }}
                </span>
                @if(!$plan->archived_at)
                    <form method="POST" action="{{ route('commercial-catalog.plans.archive', $plan) }}" onsubmit="return confirm('أرشفة الباقة؟ ستبقى الفواتير والاشتراكات التاريخية قابلة للقراءة.')">
                        @csrf
                        <button class="btn btn-ghost" type="submit">أرشفة</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid grid-2" style="align-items:start">
            <div>
                <form method="POST" action="{{ route('commercial-catalog.plans.update', $plan) }}">
                    @csrf
                    @method('PATCH')
                    <div class="form-grid">
                        <div class="field">
                            <label>اسم عربي</label>
                            <input name="name_ar" value="{{ $plan->name_ar }}" required>
                        </div>
                        <div class="field">
                            <label>اسم إنجليزي</label>
                            <input name="name_en" value="{{ $plan->name_en }}">
                        </div>
                        <div class="field">
                            <label>الحالة</label>
                            <select name="is_active">
                                <option value="1" {{ $plan->is_active ? 'selected' : '' }}>فعالة</option>
                                <option value="0" {{ !$plan->is_active ? 'selected' : '' }}>معطلة</option>
                            </select>
                        </div>
                        <div class="field full">
                            <label>الخدمات المشمولة</label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px">
                                @foreach($services as $service)
                                    <label style="display:flex;gap:8px;align-items:center;background:#f8fafc;border:1px solid var(--nd-border);border-radius:8px;padding:8px">
                                        <input type="checkbox" name="services[]" value="{{ $service->id }}" {{ $plan->services->contains('id', $service->id) ? 'checked' : '' }}>
                                        <span>{{ $service->name_ar }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">حفظ وصف الباقة</button>
                </form>
            </div>

            <div>
                <h3 style="margin-top:0">إضافة نسخة سعر</h3>
                <form method="POST" action="{{ route('commercial-catalog.prices.store', $plan) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>نوع الفوترة</label>
                            <select name="billing_interval" required>
                                <option value="monthly">شهري</option>
                                <option value="annual">سنوي</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>السعر (د.أ)</label>
                            <input name="amount_jod" required placeholder="15.000">
                        </div>
                        <div class="field">
                            <label>رسوم تأسيس (د.أ)</label>
                            <input name="setup_fee_jod" placeholder="0.000">
                        </div>
                        <div class="field">
                            <label>الفروع المشمولة</label>
                            <input type="number" name="included_branch_quantity" min="1" value="1" required>
                        </div>
                        <div class="field">
                            <label>سعر الفرع الإضافي (د.أ)</label>
                            <input name="additional_branch_price_jod" placeholder="5.000">
                        </div>
                        <div class="field">
                            <label>الضريبة بالنقاط الأساسية</label>
                            <input type="number" name="default_tax_rate_bps" min="0" max="10000" placeholder="1600">
                        </div>
                        <div class="field full">
                            <label>تاريخ الفعالية</label>
                            <input type="datetime-local" name="effective_from" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">إضافة السعر</button>
                </form>
            </div>
        </div>

        <div style="margin-top:16px">
            <h3>سجل الأسعار</h3>
            <div class="list">
                @forelse($plan->prices as $price)
                    <div class="list-row">
                        <span class="badge {{ $price->is_active ? 'green' : 'gray' }}">{{ $price->billing_interval }}</span>
                        <div class="list-main">
                            <strong>{{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ</strong>
                            <small class="muted">
                                من {{ $price->effective_from?->format('Y-m-d H:i') }}
                                @if($price->effective_until) حتى {{ $price->effective_until->format('Y-m-d H:i') }} @endif
                                · رسوم تأسيس {{ \App\Support\Money::fromMinorUnits($price->setup_fee_minor)->format() }} د.أ
                                @if($price->default_tax_rate_bps !== null) · ضريبة {{ $price->default_tax_rate_bps }} bps @endif
                            </small>
                        </div>
                    </div>
                @empty
                    <div class="muted">لا توجد أسعار بعد.</div>
                @endforelse
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection
