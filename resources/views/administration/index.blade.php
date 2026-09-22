@extends('layouts.app')

@section('content')
<div class="p5-wrap">
    <header class="p5-header">
        <div class="p5-header-main">
            <div class="p5-eyebrow">{{ __('notify.navigation.administration') }}</div>
            <h1 class="p5-title">الإدارة</h1>
            <p class="p5-subtitle">إدارة المنتجات والأسعار، الشركاء، الفريق، الاستيراد، والإعدادات الموحدة.</p>
        </div>
    </header>

    {{-- 6 Section Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-top:8px;">

        {{-- 1. Products & Pricing --}}
        @can('manage_commercial_catalog')
        <div class="admin-section-card">
            <div class="admin-section-card__icon">
                <x-notify.icon name="package" :size="24" />
            </div>
            <div class="admin-section-card__body">
                <h2 class="admin-section-card__title">المنتجات والتسعير</h2>
                <p class="admin-section-card__desc">إدارة الأنظمة التجارية، الباقات، وإصدارات الأسعار المعتمدة.</p>
                <div class="admin-section-card__meta">
                    <span class="p5-badge p5-badge--success">{{ $activeProducts }} نظام فعال</span>
                    <span class="p5-badge p5-badge--neutral">{{ $totalProducts }} إجمالي</span>
                </div>
            </div>
            <div class="admin-section-card__footer">
                <a href="{{ route('commercial-catalog.index') }}" class="p5-btn p5-btn-primary p5-btn-sm">
                    إدارة الكتالوج
                </a>
            </div>
        </div>
        @endcan

        {{-- 2. Partners --}}
        @if($isAdmin)
        <div class="admin-section-card">
            <div class="admin-section-card__icon">
                <x-notify.icon name="building" :size="24" />
            </div>
            <div class="admin-section-card__body">
                <h2 class="admin-section-card__title">الشركاء</h2>
                <p class="admin-section-card__desc">إدارة شركاء الإحالة، معدلات العمولة، وسجلات الإسناد.</p>
                <div class="admin-section-card__meta">
                    <span class="p5-badge p5-badge--success">{{ $activePartners }} شريك فعال</span>
                    @if($pendingConflicts > 0)
                        <span class="p5-badge p5-badge--warning">{{ $pendingConflicts }} تعارض معلق</span>
                    @endif
                </div>
            </div>
            <div class="admin-section-card__footer">
                <a href="{{ route('partners.index') }}" class="p5-btn p5-btn-primary p5-btn-sm">
                    إدارة الشركاء
                </a>
            </div>
        </div>
        @endif

        {{-- 3. Team & Permissions --}}
        @if($isAdmin)
        <div class="admin-section-card">
            <div class="admin-section-card__icon">
                <x-notify.icon name="users" :size="24" />
            </div>
            <div class="admin-section-card__body">
                <h2 class="admin-section-card__title">الفريق والصلاحيات</h2>
                <p class="admin-section-card__desc">إدارة أعضاء الفريق الداخلي، الأدوار، وإعادة تعيين كلمات المرور.</p>
                <div class="admin-section-card__meta">
                    <span class="p5-badge p5-badge--success">{{ $activeTeamMembers }} عضو فعال</span>
                </div>
            </div>
            <div class="admin-section-card__footer">
                <a href="{{ route('administration.team') }}" class="p5-btn p5-btn-primary p5-btn-sm">
                    إدارة الفريق
                </a>
            </div>
        </div>
        @endif

        {{-- 4. Import --}}
        @can('create', App\Models\Client::class)
        <div class="admin-section-card">
            <div class="admin-section-card__icon">
                <x-notify.icon name="clipboard-list" :size="24" />
            </div>
            <div class="admin-section-card__body">
                <h2 class="admin-section-card__title">الاستيراد</h2>
                <p class="admin-section-card__desc">استيراد العملاء والمشترقين من ملفات CSV دفعةً واحدة.</p>
                <div class="admin-section-card__meta">
                    <span class="p5-badge p5-badge--neutral">عملاء محتملون + مشتركون</span>
                </div>
            </div>
            <div class="admin-section-card__footer">
                <a href="{{ route('clients.import') }}" class="p5-btn p5-btn-primary p5-btn-sm">
                    استيراد البيانات
                </a>
            </div>
        </div>
        @endcan

        {{-- 5. Conflicts / Reviews --}}
        @if($isAdmin)
        <div class="admin-section-card {{ $pendingConflicts > 0 ? 'admin-section-card--alert' : '' }}">
            <div class="admin-section-card__icon">
                <x-notify.icon name="activity" :size="24" />
            </div>
            <div class="admin-section-card__body">
                <h2 class="admin-section-card__title">التعارضات والمراجعات</h2>
                <p class="admin-section-card__desc">معالجة تعارضات الإحالة وطلبات إعادة التعيين.</p>
                <div class="admin-section-card__meta">
                    @if($pendingConflicts > 0)
                        <span class="p5-badge p5-badge--warning">{{ $pendingConflicts }} بحاجة مراجعة</span>
                    @else
                        <span class="p5-badge p5-badge--success">لا توجد تعارضات معلقة</span>
                    @endif
                </div>
            </div>
            <div class="admin-section-card__footer">
                <a href="{{ route('conflicts.index') }}" class="p5-btn p5-btn-primary p5-btn-sm">
                    عرض التعارضات
                </a>
            </div>
        </div>
        @endif

        {{-- 6. Settings --}}
        @can('manage_financial_settings')
        <div class="admin-section-card">
            <div class="admin-section-card__icon">
                <x-notify.icon name="settings" :size="24" />
            </div>
            <div class="admin-section-card__body">
                <h2 class="admin-section-card__title">الإعدادات</h2>
                <p class="admin-section-card__desc">إعدادات التشغيل: الخصم السنوي، ضريبة المبيعات، نقل العملاء.</p>
                <div class="admin-section-card__meta">
                    <span class="p5-badge p5-badge--neutral">خصم سنوي: {{ $annualDiscountPercentage }}%</span>
                    <span class="p5-badge p5-badge--neutral">ضريبة: {{ $salesTaxPercentage }}%</span>
                </div>
            </div>
            <div class="admin-section-card__footer">
                <a href="{{ route('settings.index') }}" class="p5-btn p5-btn-primary p5-btn-sm">
                    الإعدادات
                </a>
            </div>
        </div>
        @endcan

    </div>
</div>

<style>
.admin-section-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.admin-section-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    border-color: #CBD5E0;
}
.admin-section-card--alert {
    border-color: #F59E0B;
    background: #FFFBEB;
}
.admin-section-card__icon {
    width: 44px;
    height: 44px;
    background: #F0F4FF;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0055CC;
    flex-shrink: 0;
}
.admin-section-card__title {
    font-size: 15px;
    font-weight: 700;
    color: #0A1128;
    margin: 0 0 4px;
}
.admin-section-card__desc {
    font-size: 13px;
    color: #4A5568;
    line-height: 1.5;
    margin: 0;
}
.admin-section-card__meta {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.admin-section-card__body {
    flex: 1;
}
.admin-section-card__footer {
    border-top: 1px solid #F0F4FF;
    padding-top: 12px;
}
.p5-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.6;
}
.p5-badge--success { background: #D1FAE5; color: #065F46; }
.p5-badge--warning { background: #FEF3C7; color: #92400E; }
.p5-badge--neutral { background: #F1F5F9; color: #374151; }
.p5-badge--danger  { background: #FEE2E2; color: #991B1B; }
</style>
@endsection
