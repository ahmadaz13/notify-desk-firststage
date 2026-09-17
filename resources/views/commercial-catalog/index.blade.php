@extends('layouts.app')

@section('content')
@php
    $productOptions = $products;
@endphp

<div class="p5-wrap">
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">Commercial Catalog</div>
            <h1 class="p5-title">{{ __('notify.catalog.title') }}</h1>
            <p class="p5-subtitle">إدارة الأنظمة البرمجية، الباقات التابعة لها، الخدمات المشمولة، وإصدارات الأسعار المعتمدة من PlanPrice.</p>
        </div>
        <div class="p5-header-actions">
            <a class="p5-btn p5-btn-ghost" href="{{ route('settings.index') }}">العودة للإعدادات</a>
        </div>
    </header>

    <div class="p5-grid-2" style="margin-bottom:16px">
        <x-notify.collapsible-section title="إنشاء نظام / منتج" subtitle="النظام البرمجي التجاري الذي تباع تحته الباقات" icon="box" :open="false">
            <form method="POST" action="{{ route('commercial-catalog.products.store') }}">
                @csrf
                <div class="p5-form-grid">
                    <div class="p5-field">
                        <label>رمز النظام *</label>
                        <input name="code" required placeholder="restaurant_system" class="p5-input">
                    </div>
                    <div class="p5-field">
                        <label>اسم النظام بالعربية *</label>
                        <input name="name_ar" required placeholder="نظام المطاعم" class="p5-input">
                    </div>
                    <div class="p5-field">
                        <label>اسم النظام بالإنجليزية</label>
                        <input name="name_en" placeholder="Restaurant System" class="p5-input">
                    </div>
                    <div class="p5-field" style="grid-column:1 / -1">
                        <label>وصف عربي</label>
                        <textarea name="description_ar" rows="2" class="p5-textarea" placeholder="وصف مختصر للنظام"></textarea>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:14px">
                    <button class="p5-btn p5-btn-primary" type="submit">إنشاء النظام</button>
                </div>
            </form>
        </x-notify.collapsible-section>

        <x-notify.collapsible-section :title="__('notify.catalog.create_plan')" subtitle="تعريف باقة وربطها بنظام وخدمات مشمولة" icon="plus-circle" :open="false">
            <form method="POST" action="{{ route('commercial-catalog.plans.store') }}">
                @csrf
                <div class="p5-form-grid">
                    <div class="p5-field">
                        <label>النظام / المنتج</label>
                        <select name="product_id" class="p5-select">
                            <option value="">بدون نظام محدد</option>
                            @foreach($productOptions as $product)
                                <option value="{{ $product->id }}">{{ $product->name_ar }} · {{ $product->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="p5-field">
                        <label>رمز الباقة *</label>
                        <input name="code" required placeholder="restaurant_advanced" class="p5-input">
                    </div>
                    <div class="p5-field">
                        <label>اسم الباقة بالعربية *</label>
                        <input name="name_ar" required placeholder="باقة المطاعم المتقدمة" class="p5-input">
                    </div>
                    <div class="p5-field">
                        <label>اسم الباقة بالإنجليزية</label>
                        <input name="name_en" placeholder="Restaurant Advanced" class="p5-input">
                    </div>
                    <div class="p5-field">
                        <label>ترتيب تجاري</label>
                        <input type="number" name="tier" min="0" max="255" placeholder="2" class="p5-input">
                    </div>
                    <div class="p5-field">
                        <label>نوع العرض</label>
                        <select name="offer_type" class="p5-select">
                            <option value="package">باقة</option>
                            <option value="standalone">مستقل</option>
                        </select>
                    </div>
                    <div class="p5-field" style="grid-column:1 / -1">
                        <label>{{ __('notify.catalog.included_services') }}</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px">
                            @foreach($services as $service)
                                <label style="display:flex;gap:8px;align-items:center;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px;cursor:pointer">
                                    <input type="checkbox" name="services[]" value="{{ $service->id }}" style="accent-color:#0055CC">
                                    <span style="font-size:13px;color:#0A1128">{{ $service->name_ar }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="p5-field" style="grid-column:1 / -1">
                        <label>وصف عربي</label>
                        <textarea name="description_ar" rows="2" class="p5-textarea" placeholder="وصف مميزات الباقة"></textarea>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:14px">
                    <button class="p5-btn p5-btn-primary" type="submit">إنشاء الباقة</button>
                </div>
            </form>
        </x-notify.collapsible-section>

        <x-notify.collapsible-section title="مبادئ تسعير V2 المعتمدة" subtitle="قواعد الفوترة والدقة بالفلس" icon="info" :open="false">
            <div class="p5-list">
                <div class="p5-list-item">
                    <strong style="font-size:13px;color:#0A1128">PlanPrice هو مصدر السعر الوحيد.</strong>
                    <span class="p5-kpi-meta" style="margin-top:4px">النظام والباقة ينظمان الكتالوج فقط، ولا يغيران حسابات السعر أو الفواتير.</span>
                </div>
                <div class="p5-list-item">
                    <strong style="font-size:13px;color:#0A1128">الخدمات تبقى مرتبطة بالباقات.</strong>
                    <span class="p5-kpi-meta" style="margin-top:4px">علاقة plan_service الحالية محفوظة كما هي.</span>
                </div>
            </div>
        </x-notify.collapsible-section>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
        @foreach($products as $product)
            <x-notify.collapsible-section
                :title="$product->name_ar"
                :subtitle="$product->code"
                :badge="$product->is_active && !$product->archived_at ? 'نظام فعال' : 'نظام مؤرشف'"
                :badgeVariant="$product->is_active && !$product->archived_at ? 'success' : 'danger'"
                icon="box"
                :open="$loop->first && !$product->archived_at"
            >
                <div class="p5-grid-2" style="margin-bottom:16px">
                    <form method="POST" action="{{ route('commercial-catalog.products.update', $product) }}">
                        @csrf
                        @method('PATCH')
                        <div class="p5-form-grid">
                            <div class="p5-field">
                                <label>اسم النظام بالعربية *</label>
                                <input name="name_ar" value="{{ $product->name_ar }}" required class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>اسم النظام بالإنجليزية</label>
                                <input name="name_en" value="{{ $product->name_en }}" class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.common.status') }}</label>
                                <select name="is_active" class="p5-select">
                                    <option value="1" {{ $product->is_active ? 'selected' : '' }}>{{ __('notify.statuses.active') }}</option>
                                    <option value="0" {{ !$product->is_active ? 'selected' : '' }}>{{ __('notify.statuses.inactive') }}</option>
                                </select>
                            </div>
                            <div class="p5-field" style="grid-column:1 / -1">
                                <label>وصف عربي</label>
                                <textarea name="description_ar" rows="2" class="p5-textarea">{{ $product->description_ar }}</textarea>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:10px">
                            <button class="p5-btn p5-btn-soft p5-btn-sm" type="submit">حفظ النظام</button>
                        </div>
                    </form>

                    <div class="p5-list-item">
                        <strong style="font-size:13px;color:#0A1128">الباقات التابعة: {{ $product->plans->count() }}</strong>
                        <span class="p5-kpi-meta" style="margin-top:4px">أرشفة النظام تمنعه من الظهور في اختيار الاشتراكات الجديدة، مع بقاء السجل التاريخي قابلاً للقراءة.</span>
                        @if(!$product->archived_at)
                            <form method="POST" action="{{ route('commercial-catalog.products.archive', $product) }}" onsubmit="return confirm('أرشفة النظام؟ ستبقى الباقات والأسعار والفواتير التاريخية قابلة للقراءة.')" style="margin-top:10px">
                                @csrf
                                <button class="p5-btn p5-btn-ghost p5-btn-sm" type="submit">أرشفة النظام</button>
                            </form>
                        @endif
                    </div>
                </div>

                @include('commercial-catalog.partials.plan-list', ['plans' => $product->plans, 'services' => $services, 'productOptions' => $productOptions])
            </x-notify.collapsible-section>
        @endforeach

        @if($unassignedPlans->isNotEmpty())
            <x-notify.collapsible-section title="باقات غير مرتبطة بنظام" subtitle="باقات تاريخية أو انتقالية تحتاج ربطاً تجارياً" badge="مراجعة" badgeVariant="warning" icon="package" :open="true">
                @include('commercial-catalog.partials.plan-list', ['plans' => $unassignedPlans, 'services' => $services, 'productOptions' => $productOptions])
            </x-notify.collapsible-section>
        @endif
    </div>
</div>
@endsection
