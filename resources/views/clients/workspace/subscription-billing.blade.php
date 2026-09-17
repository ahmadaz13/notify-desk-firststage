<div id="tab-billing" class="tab-pane" style="display:none">
    <div class="p3-wrap">
        {{-- Workspace Header --}}
        <header class="p3-header">
            <div class="p3-header-main">
                <div class="p3-eyebrow">Client Workspace / Sales & Billing</div>
                <h1 class="p3-title">الاشتراك والفوترة والتحصيل</h1>
                <p class="p3-subtitle">إدارة دورة حياة اشتراك العميل، إصدار الفواتير، تحصيل الدفعات، إشعارات الدائن، والعقود الرسمية المعتمدة.</p>
            </div>
            <div class="p3-header-actions">
                <span class="p3-badge p3-badge-primary" style="font-size:13px;padding:6px 12px">
                    {{ $client->business_name }}
                </span>
            </div>
        </header>

        {{-- Financial Summary KPI Strip --}}
        <section class="p3-kpis">
            <div class="p3-kpi-card">
                <span class="p3-kpi-label">{{ __('notify.client_workspace.outstanding') }}</span>
                <span class="p3-kpi-value is-danger">{{ \App\Support\Money::fromMinorUnits($receivableSummary['total_outstanding_minor'])->format() }} {{ __('notify.common.currency_jod') }}</span>
                <span class="p3-kpi-meta">إجمالي المتبقي على الفواتير</span>
            </div>
            <div class="p3-kpi-card">
                <span class="p3-kpi-label">{{ __('notify.statuses.overdue') }}</span>
                <span class="p3-kpi-value is-warning">{{ \App\Support\Money::fromMinorUnits($receivableSummary['overdue_outstanding_minor'])->format() }} {{ __('notify.common.currency_jod') }}</span>
                <span class="p3-kpi-meta">فواتير تجاوزت موعد الاستحقاق</span>
            </div>
            <div class="p3-kpi-card">
                <span class="p3-kpi-label">{{ __('notify.client_workspace.payment_credit') }}</span>
                <span class="p3-kpi-value is-success">{{ \App\Support\Money::fromMinorUnits($receivableSummary['unallocated_payment_credit_minor'])->format() }} {{ __('notify.common.currency_jod') }}</span>
                <span class="p3-kpi-meta">دفعات محصلة غير مخصصة</span>
            </div>
            <div class="p3-kpi-card">
                <span class="p3-kpi-label">{{ __('notify.client_workspace.credit_note_balance') }}</span>
                <span class="p3-kpi-value is-primary">{{ \App\Support\Money::fromMinorUnits($receivableSummary['available_credit_note_minor'])->format() }} {{ __('notify.common.currency_jod') }}</span>
                <span class="p3-kpi-meta">رصيد دائن متاح للاستخدام</span>
            </div>
            <div class="p3-kpi-card">
                <span class="p3-kpi-label">{{ __('notify.client_workspace.total_customer_credit') }}</span>
                <span class="p3-kpi-value is-success">{{ \App\Support\Money::fromMinorUnits($receivableSummary['total_customer_credit_minor'])->format() }} {{ __('notify.common.currency_jod') }}</span>
                <span class="p3-kpi-meta">إجمالي حقوق العميل المتاحة</span>
            </div>
        </section>

        {{-- Internal Quick Navigation --}}
        <nav class="p3-subnav">
            <a href="#sec-subscriptions" class="p3-subnav-item is-active">1. {{ __('notify.subscriptions.authorized') }}</a>
            <a href="#sec-invoices" class="p3-subnav-item">2. {{ __('notify.client_workspace.invoices') }}</a>
            <a href="#sec-payments" class="p3-subnav-item">3. {{ __('notify.collections.receipts') }}</a>
            <a href="#sec-credits" class="p3-subnav-item">4. {{ __('notify.client_workspace.credits_refunds') }}</a>
            <a href="#sec-contracts" class="p3-subnav-item">5. {{ __('notify.sales.contracts') }}</a>
            @can(\App\Support\FinancialPermissions::VIEW_ACCOUNTING)
                <a href="#sec-accounting" class="p3-subnav-item">6. {{ __('notify.accounting.accounting_trace') }}</a>
            @endcan
            <a href="#sec-offers" class="p3-subnav-item">{{ __('notify.sales.offers') }}</a>
        </nav>

        {{-- =========================================================================
             SECTION 1: SUBSCRIPTIONS & LIFECYCLE
             ========================================================================= --}}
        <x-notify.collapsible-section id="sec-subscriptions" :title="__('notify.subscriptions.authorized')" subtitle="خطط الاشتراك ودورة الفوترة والتجديد" :badge="isset($subscriptions) ? $subscriptions->count() : 0" :open="true">
            <div class="p3-grid-2">
                {{-- Active & Historical Subscriptions List --}}
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h2 class="p3-card-title">{{ __('notify.subscriptions.authorized') }}</h2>
                            <span class="p3-kpi-meta">خطط الاشتراك ودورة الفوترة والتجديد</span>
                        </div>
                        <span class="p3-badge p3-badge-primary">{{ isset($subscriptions) ? $subscriptions->count() : 0 }} اشتراك</span>
                    </div>

                    @if(isset($subscriptions) && $subscriptions->count() > 0)
                        <div class="p3-list">
                            @foreach($subscriptions as $sub)
                                @php
                                    $installmentProjection = $installmentScheduleProjections[$sub->id] ?? null;
                                @endphp
                                <div class="p3-list-item">
                                    <div class="p3-item-top">
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span class="p3-badge {{ $sub->status === 'active' ? 'p3-badge-success' : ($sub->status === 'cancelled' ? 'p3-badge-danger' : 'p3-badge-warning') }}">
                                                {{ $sub->status === 'active' ? 'نشط' : ($sub->status === 'cancelled' ? 'ملغي' : $sub->status) }}
                                            </span>
                                            <strong class="p3-item-title">
                                                {{ $sub->billing_interval_v2 === 'annual' ? ($installmentProjection ? 'سنوي بالتقسيط' : 'سنوي مدفوع بالكامل') : 'شهري' }}
                                                (إصدار v{{ $sub->version ?? 1 }})
                                            </strong>
                                        </div>
                                        <span class="p3-item-amount">{{ number_format($sub->grand_total ?? $sub->total_price, 3) }} د.أ</span>
                                    </div>

                                    <div class="p3-item-meta">
                                        الخطة: <strong>{{ $sub->plan_name_snapshot ?: 'غير محدد' }}</strong>
                                        · الفترة الحالية: {{ optional($sub->current_period_start)->format('Y-m-d') ?: $sub->start_date }} → {{ optional($sub->current_period_end)->format('Y-m-d') ?: 'غير محدد' }}
                                        @if($sub->next_billing_date) · التجديد القادم: {{ $sub->next_billing_date->format('Y-m-d') }} @endif
                                        · الكمية: {{ $sub->quantity ?: 1 }}
                                        @if($sub->setup_fee > 0) · رسوم التأسيس: {{ number_format($sub->setup_fee, 3) }} د.أ @endif
                                        @if($sub->discount_amount > 0) · الخصم: {{ number_format($sub->discount_amount, 3) }} د.أ ({{ $sub->annual_discount_percentage }}%) @endif
                                        @if($sub->tax_amount > 0) · الضريبة: {{ number_format($sub->tax_amount, 3) }} د.أ ({{ $sub->tax_percentage }}%) @endif
                                    </div>

                                    @if($sub->pending_plan_price_id)
                                        <div class="p3-callout-warning">
                                            ⏰ تغيير خطة مجدول للفترة القادمة: {{ $sub->pendingPlanPrice?->plan?->name_ar ?: 'خطة غير محددة' }}
                                            · {{ $sub->pendingPlanPrice?->billing_interval }}
                                            · كمية {{ $sub->pending_quantity }}
                                            · فعال في {{ optional($sub->pending_change_effective_at)->format('Y-m-d') }}
                                        </div>
                                    @endif

                                    @if($sub->cancel_at_period_end)
                                        <div class="p3-callout-danger">
                                            ⚠️ الإلغاء مجدول في نهاية الفترة الحالية: {{ optional($sub->current_period_end)->format('Y-m-d') }}
                                        </div>
                                    @endif

                                    @if($sub->billingPeriods->isNotEmpty())
                                        <div class="p3-box-nested">
                                            <strong style="font-size:12px;display:block;margin-bottom:4px">فترات الفوترة المرتبطة:</strong>
                                            @foreach($sub->billingPeriods->take(4) as $period)
                                                <div style="font-size:11px;color:#475569;margin-bottom:2px">
                                                    #{{ $period->period_number }} · {{ $period->period_start->format('Y-m-d') }} → {{ $period->period_end->format('Y-m-d') }}
                                                    · <span class="p3-badge p3-badge-neutral" style="padding:1px 5px;font-size:10px">{{ $period->status }}</span>
                                                    @if($period->invoice) · فاتورة: <strong class="ltr" style="direction:ltr">{{ $period->invoice->invoice_number }}</strong> @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($installmentProjection)
                                        <div class="p3-box-nested">
                                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-bottom:8px">
                                                <div><span class="p3-item-meta">الالتزام السنوي</span><strong class="p3-item-title" style="display:block">{{ \App\Support\Money::fromMinorUnits($installmentProjection['total_minor'])->format() }} د.أ</strong></div>
                                                <div><span class="p3-item-meta">المحصل والمخصص</span><strong class="p3-item-title" style="display:block">{{ \App\Support\Money::fromMinorUnits($installmentProjection['paid_minor'])->format() }} د.أ</strong></div>
                                                <div><span class="p3-item-meta">الرصيد المستحق</span><strong class="p3-item-title" style="display:block">{{ \App\Support\Money::fromMinorUnits($installmentProjection['remaining_minor'])->format() }} د.أ</strong></div>
                                                <div><span class="p3-item-meta">الاستحقاق القادم</span><strong class="p3-item-title" style="display:block">{{ $installmentProjection['next_due_date']?->format('Y-m-d') ?: 'مكتمل' }}</strong></div>
                                            </div>
                                            @foreach($installmentProjection['rows'] as $installment)
                                                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;font-size:11px;color:#475569;margin-top:4px">
                                                    <span>#{{ $installment['sequence'] }} · {{ $installment['due_date']->format('Y-m-d') }} · {{ \App\Support\Money::fromMinorUnits($installment['amount_minor'])->format() }} د.أ</span>
                                                    <span class="p3-badge {{ $installment['status'] === 'paid' ? 'p3-badge-success' : (in_array($installment['status'], ['overdue', 'partially_paid'], true) ? 'p3-badge-danger' : 'p3-badge-warning') }}">{{ $installment['status'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($sub->status === 'cancelled')
                                        <div class="p3-callout-danger">
                                            ⛔ تم الإلغاء بتاريخ {{ $sub->cancelled_at }}. السبب: {{ $sub->cancellation_reason ?: 'غير محدد' }}
                                        </div>
                                        @can(\App\Support\FinancialPermissions::MANAGE_SUBSCRIPTION_LIFECYCLE)
                                            <form method="POST" action="{{ route('subscriptions.reactivate', $sub->id) }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:8px">
                                                @csrf
                                                <select name="plan_price_id" required class="p3-select">
                                                    @foreach($sellableProducts as $product)
                                                        <optgroup label="{{ $product->name_ar }}">
                                                            @foreach($product->sellablePlans as $plan)
                                                                @foreach($plan->activePrices as $price)
                                                                    <option value="{{ $price->id }}">{{ $plan->name_ar }} · {{ $price->billing_interval }} · {{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ</option>
                                                                @endforeach
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                    @foreach($sellablePlans->whereNull('product_id') as $plan)
                                                        @foreach($plan->activePrices as $price)
                                                            <option value="{{ $price->id }}">{{ $plan->name_ar }} · {{ $price->billing_interval }} · {{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ</option>
                                                        @endforeach
                                                    @endforeach
                                                </select>
                                                <input type="number" name="quantity" min="1" value="{{ $sub->quantity ?: 1 }}" required class="p3-input">
                                                <input type="date" name="start_date" value="{{ now()->toDateString() }}" required class="p3-input">
                                                <button class="p3-btn p3-btn-primary p3-btn-sm" type="submit">إعادة تفعيل</button>
                                            </form>
                                        @endcan
                                    @elseif($sub->status === 'active')
                                        <div class="p3-item-actions" style="justify-content:space-between">
                                            @can('create', \App\Models\Contract::class)
                                                <form method="POST" action="{{ route('contracts.store', ['client' => $client->id, 'subscription' => $sub->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="p3-btn p3-btn-soft p3-btn-sm">
                                                        📄 توليد عقد رسمي
                                                    </button>
                                                </form>
                                            @endcan

                                            @can(\App\Support\FinancialPermissions::MANAGE_SUBSCRIPTION_LIFECYCLE)
                                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                                    <form method="POST" action="{{ route('subscriptions.plan-change.schedule', $sub->id) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                                                        @csrf
                                                        <select name="plan_price_id" required class="p3-select" style="min-width:180px;font-size:12px;padding:4px 8px">
                                                            @foreach($sellableProducts as $product)
                                                                <optgroup label="{{ $product->name_ar }}">
                                                                    @foreach($product->sellablePlans as $plan)
                                                                        @foreach($plan->activePrices as $price)
                                                                            <option value="{{ $price->id }}">{{ $plan->name_ar }} · {{ $price->billing_interval }} · {{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ</option>
                                                                        @endforeach
                                                                    @endforeach
                                                                </optgroup>
                                                            @endforeach
                                                            @foreach($sellablePlans->whereNull('product_id') as $plan)
                                                                @foreach($plan->activePrices as $price)
                                                                    <option value="{{ $price->id }}">{{ $plan->name_ar }} · {{ $price->billing_interval }} · {{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ</option>
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                        <input type="number" name="quantity" min="1" value="{{ $sub->quantity ?: 1 }}" required class="p3-input" style="width:60px;font-size:12px;padding:4px 6px">
                                                        <button type="submit" class="p3-btn p3-btn-soft p3-btn-sm">جدولة تغيير</button>
                                                    </form>

                                                    @if($sub->cancel_at_period_end)
                                                        <form method="POST" action="{{ route('subscriptions.cancel.undo', $sub->id) }}">
                                                            @csrf
                                                            <button type="submit" class="p3-btn p3-btn-soft p3-btn-sm">إلغاء جدولة الإلغاء</button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('subscriptions.cancel', $sub->id) }}" onsubmit="return confirm('سيتم الإلغاء في نهاية الفترة الحالية فقط. متابعة؟')" style="display:flex;gap:6px">
                                                            @csrf
                                                            <input name="cancellation_reason" placeholder="سبب الإلغاء" class="p3-input" style="min-width:130px;font-size:12px;padding:4px 8px">
                                                            <button type="submit" class="p3-btn p3-btn-danger p3-btn-sm">جدولة إلغاء</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endcan
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p3-empty">لا توجد اشتراكات معتمدة لهذا العميل حتى الآن.</div>
                    @endif
                </div>

                {{-- Start Paid Subscription Form (Admin Only) --}}
                <div style="display:flex;flex-direction:column;gap:16px">
                    @if(auth()->user()->isAdmin())
                        <div class="p3-card" id="paid-subscription-section" style="border:2px solid #0055CC">
                            <div class="p3-card-head">
                                <div>
                                    <h3 class="p3-card-title" style="color:#0055CC">{{ __('notify.subscriptions.start_paid') }}</h3>
                                    <p class="p3-subtitle" style="margin:2px 0 0 0">ينشئ اشتراك V2 وفاتورة أولى فقط، ولا يسجل أي دفعة مالية.</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('clients.paid-subscriptions.store', $client->id) }}" data-paid-subscription-form>
                                @csrf
                                <div class="p3-filter-grid" style="grid-template-columns:1fr;gap:10px">
                                    <div class="p3-field">
                                        <label>النظام ثم الباقة والسعر *</label>
                                        <div style="display:flex;flex-direction:column;gap:10px">
                                            @foreach($sellableProducts as $product)
                                                <div class="p3-box-nested">
                                                    <strong style="font-size:13px;color:#0A1128;display:block;margin-bottom:6px">{{ $product->name_ar }}</strong>
                                                    @foreach($product->sellablePlans as $plan)
                                                        <div style="border-top:1px solid #E2E8F0;padding-top:8px;margin-top:8px">
                                                            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                                                                <strong style="font-size:12px;color:#0A1128">{{ $plan->name_ar }}</strong>
                                                                @if($plan->description_ar)
                                                                    <span class="p3-item-meta" style="font-size:11px">{{ $plan->description_ar }}</span>
                                                                @endif
                                                            </div>
                                                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:6px;margin-top:6px">
                                                                @foreach($plan->activePrices as $price)
                                                                    <label style="display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #CBD5E1;border-radius:8px;padding:8px;cursor:pointer">
                                                                        <input type="radio" name="plan_price_id" value="{{ $price->id }}" data-billing-interval="{{ $price->billing_interval }}" @checked((string) old('plan_price_id') === (string) $price->id) required style="accent-color:#0055CC">
                                                                        <span style="font-size:12px;color:#0A1128">
                                                                            {{ $price->billing_interval === 'annual' ? 'سنوي' : 'شهري' }}
                                                                            · {{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ
                                                                            @if($price->setup_fee_minor > 0)
                                                                                · تأسيس {{ \App\Support\Money::fromMinorUnits($price->setup_fee_minor)->format() }} د.أ
                                                                            @endif
                                                                        </span>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                            @if($plan->services->isNotEmpty())
                                                                <details style="margin-top:6px">
                                                                    <summary class="p3-item-meta" style="cursor:pointer">الخدمات المشمولة</summary>
                                                                    <div class="p3-item-meta" style="margin-top:4px">{{ $plan->services->pluck('name_ar')->join(' · ') }}</div>
                                                                </details>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                            @foreach($sellablePlans->whereNull('product_id') as $plan)
                                                @foreach($plan->activePrices as $price)
                                                    <label style="display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #CBD5E1;border-radius:8px;padding:8px;cursor:pointer">
                                                        <input type="radio" name="plan_price_id" value="{{ $price->id }}" data-billing-interval="{{ $price->billing_interval }}" @checked((string) old('plan_price_id') === (string) $price->id) required style="accent-color:#0055CC">
                                                        <span style="font-size:12px;color:#0A1128">{{ $plan->name_ar }} · {{ $price->billing_interval }} · {{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ</span>
                                                    </label>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </div>

                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                                        <div class="p3-field">
                                            <label>عدد الفروع / الوحدات *</label>
                                            <input type="number" name="quantity" min="1" value="{{ old('quantity', $client->number_of_branches ?? 1) }}" required class="p3-input">
                                        </div>
                                        <div class="p3-field">
                                            <label>تاريخ بداية الاشتراك *</label>
                                            <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required class="p3-input">
                                        </div>
                                    </div>
                                    <div class="p3-box-nested" data-annual-payment-terms hidden>
                                        <div class="p3-field">
                                            <label>طريقة سداد الالتزام السنوي *</label>
                                            <select name="payment_terms" class="p3-select" data-payment-terms>
                                                <option value="full" @selected(old('payment_terms', 'full') === 'full')>دفعة سنوية كاملة</option>
                                                <option value="installments" @selected(old('payment_terms') === 'installments')>أقساط تحصيل ضمن اشتراك سنوي واحد</option>
                                            </select>
                                        </div>
                                        <div data-installment-fields hidden>
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
                                                <div class="p3-field">
                                                    <label>عدد الأقساط *</label>
                                                    <input type="number" name="installments_count" min="2" max="12" value="{{ old('installments_count', 3) }}" class="p3-input">
                                                </div>
                                                <div class="p3-field">
                                                    <label>يوم الاستحقاق الشهري *</label>
                                                    <select name="installment_due_day" class="p3-select">
                                                        @foreach([1, 5, 15, 30] as $dueDay)
                                                            <option value="{{ $dueDay }}" @selected((int) old('installment_due_day', 1) === $dueDay)>{{ $dueDay }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                                        <div class="p3-field">
                                            <label>خصم تفاوضي دقيق (د.أ)</label>
                                            <input name="discount_jod" placeholder="0.000" class="p3-input">
                                        </div>
                                        <div class="p3-field">
                                            <label>سبب الخصم</label>
                                            <input name="discount_reason" placeholder="مثال: عرض افتتاحي" class="p3-input">
                                        </div>
                                    </div>
                                    <div class="p3-field">
                                        <label>ملاحظات الفاتورة</label>
                                        <textarea name="notes" rows="2" class="p3-textarea">{{ old('notes') }}</textarea>
                                    </div>
                                    @if(session('billingTermsPreview'))
                                        @php
                                            $termsPreview = session('billingTermsPreview');
                                        @endphp
                                        <div class="p3-box-nested" style="border-color:#0055CC">
                                            <strong class="p3-item-title">معاينة شروط السداد: {{ $termsPreview['plan_name'] }}</strong>
                                            <div class="p3-item-meta" style="margin-top:4px">
                                                إجمالي الفاتورة المعتمد: {{ \App\Support\Money::fromMinorUnits($termsPreview['total_minor'])->format() }} د.أ
                                                · {{ $termsPreview['billing_interval'] === 'annual' ? 'التزام سنوي واحد' : 'اشتراك شهري' }}
                                            </div>
                                            @foreach($termsPreview['schedule'] as $installment)
                                                <div style="display:flex;justify-content:space-between;gap:8px;font-size:11px;color:#475569;margin-top:4px">
                                                    <span>القسط #{{ $installment['sequence'] }} · {{ $installment['due_date'] }}</span>
                                                    <strong>{{ $installment['amount_due'] }} د.أ</strong>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:14px">
                                    <button class="p3-btn p3-btn-soft" type="submit" name="preview_only" value="1">معاينة الالتزام والجدول</button>
                                    <button class="p3-btn p3-btn-primary" type="submit">بدء الاشتراك المدفوع وإصدار الفاتورة</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    {{-- Deprecated Convert to Subscriber Notice --}}
                    @if($client->status === 'prospect')
                        <div class="p3-card" id="convert-section" style="border:1px solid #CBD5E1;background:#F8FAFC">
                            <h3 class="p3-card-title" style="color:#475569">التحويل القديم متوقف</h3>
                            <p class="p3-subtitle">هذا المسار كان ينشئ اشتراكاً وجدول دفعات من إعدادات عامة. في V1 المعتمد يجب بدء الاشتراك من باقة وسعر PlanPrice صريحين، ثم إنشاء فترة فوترة وفاتورة V2.</p>
                            <a class="p3-btn p3-btn-primary p3-btn-sm" href="#paid-subscription-section" style="margin-top:10px;align-self:flex-start">استخدم اشتراك V2 المدفوع أعلاه</a>
                        </div>
                    @endif
                </div>
            </div>
        </x-notify.collapsible-section>

        {{-- =========================================================================
             SECTION 2: INVOICES & ONE-TIME WORK
             ========================================================================= --}}
        <x-notify.collapsible-section id="sec-invoices" :title="__('notify.billing.invoices')" subtitle="الفواتير المصدرة، بنود العمل الفردي، والفوترة اللحظية" :badge="$invoices->count()" :open="true">
            <div class="p3-grid-2">
                {{-- Issued Invoices List --}}
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h2 class="p3-card-title">{{ __('notify.billing.client_invoices') }}</h2>
                            <span class="p3-kpi-meta">فواتير الاشتراكات والأعمال الإضافية</span>
                        </div>
                        <span class="p3-badge p3-badge-primary">{{ $invoices->count() }} فاتورة</span>
                    </div>
                    <div class="p3-list">
                        @forelse($invoices as $invoice)
                            @php
                                $invoiceProjection = $invoiceReceivables[$invoice->id] ?? null;
                            @endphp
                            <div class="p3-list-item">
                                <div class="p3-item-top">
                                    <div>
                                        <span class="p3-badge {{ $invoice->status === 'issued' ? 'p3-badge-primary' : ($invoice->status === 'voided' ? 'p3-badge-danger' : 'p3-badge-warning') }}" style="margin-inline-end:6px">
                                            {{ $invoice->status }}
                                        </span>
                                        <strong class="ltr p3-item-title" style="direction:ltr">{{ $invoice->invoice_number ?? ('INV-DRAFT-'.$invoice->id) }}</strong>
                                    </div>
                                    <span class="p3-item-amount">{{ \App\Support\Money::fromMinorUnits($invoice->total_minor)->format() }} د.أ</span>
                                </div>
                                <div class="p3-item-meta">
                                    {{ $invoice->subscription_id ? 'فاتورة اشتراك' : 'عمل إضافي منفصل' }}
                                    · تاريخ الإصدار: {{ $invoice->issue_date?->format('Y-m-d') }}
                                    · تاريخ الاستحقاق: {{ $invoice->due_date?->format('Y-m-d') }}
                                </div>
                                @if($invoiceProjection)
                                    <div class="p3-item-meta" style="font-size:11px;color:#64748B">
                                        دفعات: {{ $invoiceProjection['allocated'] }} د.أ
                                        · رصيد دائن مطبق: {{ $invoiceProjection['credit_applied'] }} د.أ
                                        · مستحق قائم: <strong style="color:{{ $invoiceProjection['outstanding_minor'] > 0 ? '#B42318' : '#16A34A' }}">{{ $invoiceProjection['outstanding'] }} د.أ</strong>
                                        · الحالة: {{ $invoiceProjection['settlement_status'] }}
                                    </div>
                                @endif

                                {{-- Quick Pay Form --}}
                                @if($invoice->status === 'issued' && $invoiceProjection && $invoiceProjection['outstanding_minor'] > 0)
                                    <form method="POST" action="{{ route('clients.collections.payments.store', $client) }}" class="p3-box-nested" style="margin-top:6px">
                                        @csrf
                                        <input type="hidden" name="allocations[0][invoice_id]" value="{{ $invoice->id }}">
                                        <input type="hidden" name="allocations[0][amount]" value="{{ $invoiceProjection['outstanding'] }}">
                                        <strong style="font-size:12px;display:block;margin-bottom:6px;color:#0055CC">سداد سريع ومخصص لهذه الفاتورة:</strong>
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:6px;align-items:center">
                                            <input name="amount" value="{{ $invoiceProjection['outstanding'] }}" required class="p3-input" style="font-size:12px;padding:5px 8px">
                                            <select name="financial_account_id" required class="p3-select" style="font-size:12px;padding:5px 8px">
                                                @foreach($activeFinancialAccounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                                @endforeach
                                            </select>
                                            <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required class="p3-input" style="font-size:12px;padding:5px 8px">
                                            <select name="payment_method" required class="p3-select" style="font-size:12px;padding:5px 8px">
                                                @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                                    <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                                @endforeach
                                            </select>
                                            <button class="p3-btn p3-btn-primary p3-btn-sm" type="submit">دفع سريع</button>
                                        </div>
                                    </form>
                                @endif

                                {{-- Void Invoice Form or Reason --}}
                                @if($invoice->status === 'issued')
                                    <form method="POST" action="{{ route('invoices.void', $invoice) }}" style="display:flex;gap:6px;align-items:center;margin-top:6px" onsubmit="return confirm('هل تريد إلغاء هذه الفاتورة مع الحفاظ على السجل؟')">
                                        @csrf
                                        <input type="text" name="void_reason" placeholder="سبب إلغاء الفاتورة" required class="p3-input" style="max-width:240px;font-size:12px;padding:4px 8px">
                                        <button class="p3-btn p3-btn-danger p3-btn-sm" type="submit">إلغاء الفاتورة</button>
                                    </form>
                                @elseif($invoice->status === 'voided')
                                    <div class="p3-callout-danger" style="margin-top:4px">
                                        سبب الإلغاء: {{ $invoice->void_reason }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p3-empty">لا توجد فواتير منشأة لهذا العميل بعد.</div>
                        @endforelse
                    </div>
                </div>

                {{-- One-Time Invoice Form (Admin Only) --}}
                @if(auth()->user()->isAdmin())
                    <div class="p3-card">
                        <div class="p3-card-head">
                            <div>
                                <h3 class="p3-card-title">{{ __('notify.billing.one_time_work') }}</h3>
                                <p class="p3-subtitle" style="margin:2px 0 0 0">إصدار فاتورة V2 مستقلة لخدمات مخصصة أو تركيب أو استشارات.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('clients.one-time-invoices.store', $client->id) }}">
                            @csrf
                            <div class="p3-filter-grid" style="grid-template-columns:1fr 1fr;gap:10px">
                                <div class="p3-field">
                                    <label>تاريخ الإصدار *</label>
                                    <input type="date" name="issue_date" value="{{ now()->toDateString() }}" required class="p3-input">
                                </div>
                                <div class="p3-field">
                                    <label>تاريخ الاستحقاق *</label>
                                    <input type="date" name="due_date" value="{{ now()->toDateString() }}" required class="p3-input">
                                </div>
                            </div>
                            <div class="p3-field" style="margin-top:10px">
                                <label>وصف الفاتورة العام</label>
                                <input name="description" placeholder="مثال: تطوير موقع وهوية بصرية وحملة إعلانية" class="p3-input">
                            </div>

                            <div class="p3-box-nested" style="margin-top:12px">
                                <strong style="font-size:12px;color:#0A1128;display:block;margin-bottom:8px">السطر الأول (إلزامي) *</strong>
                                <input type="hidden" name="lines[0][line_type]" value="one_time_service">
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px">
                                    <div class="p3-field">
                                        <label>الوصف *</label>
                                        <input name="lines[0][description]" required placeholder="Website development" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>الكمية *</label>
                                        <input type="number" name="lines[0][quantity]" min="1" value="1" required class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>سعر الوحدة (د.أ) *</label>
                                        <input name="lines[0][unit_price_jod]" required placeholder="120.000" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>خصم السطر (د.أ)</label>
                                        <input name="lines[0][discount_jod]" placeholder="0.000" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>{{ __('notify.catalog.tax_rate_bps') }}</label>
                                        <input type="number" name="lines[0][tax_rate_bps]" min="0" max="10000" placeholder="1600" class="p3-input">
                                    </div>
                                </div>
                            </div>

                            <div class="p3-box-nested" style="margin-top:12px">
                                <strong style="font-size:12px;color:#0A1128;display:block;margin-bottom:8px">سطر إضافي (اختياري)</strong>
                                <input type="hidden" name="lines[1][line_type]" value="custom">
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px">
                                    <div class="p3-field">
                                        <label>الوصف</label>
                                        <input name="lines[1][description]" placeholder="Domain / training / hardware" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>الكمية</label>
                                        <input type="number" name="lines[1][quantity]" min="1" value="1" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>سعر الوحدة (د.أ)</label>
                                        <input name="lines[1][unit_price_jod]" placeholder="20.000" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>خصم السطر (د.أ)</label>
                                        <input name="lines[1][discount_jod]" placeholder="0.000" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>{{ __('notify.catalog.tax_rate_bps') }}</label>
                                        <input type="number" name="lines[1][tax_rate_bps]" min="0" max="10000" placeholder="1600" class="p3-input">
                                    </div>
                                </div>
                            </div>

                            <button class="p3-btn p3-btn-primary" style="margin-top:14px;width:100%" type="submit">إنشاء وإصدار فاتورة العمل الإضافي</button>
                        </form>
                    </div>
                @endif
            </div>
        </x-notify.collapsible-section>

        {{-- =========================================================================
             SECTION 3: PAYMENTS & COLLECTIONS
             ========================================================================= --}}
        <x-notify.collapsible-section id="sec-payments" :title="__('notify.collections.receipts')" subtitle="الدفعات المحصلة، التخصيص، والاسترداد" :badge="$payments->count()" :open="false">
            <div class="p3-grid-2">
                {{-- Recorded Payments List --}}
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h2 class="p3-card-title">{{ __('notify.collections.receipts') }}</h2>
                            <span class="p3-kpi-meta">الدفعات المحصلة، التخصيص، والاسترداد</span>
                        </div>
                        <span class="p3-badge p3-badge-success">{{ $payments->count() }} دفعات</span>
                    </div>
                    <div class="p3-list">
                        @forelse($payments as $payment)
                            @php
                                $paymentProjection = $paymentReceivables[$payment->id] ?? null;
                            @endphp
                            <div class="p3-list-item">
                                <div class="p3-item-top">
                                    <div>
                                        <span class="p3-badge {{ $paymentProjection && $paymentProjection['is_reversed'] ? 'p3-badge-danger' : ($payment->payment_engine_version === 'v2' ? 'p3-badge-primary' : 'p3-badge-success') }}" style="margin-inline-end:6px">
                                            {{ $paymentProjection && $paymentProjection['is_reversed'] ? 'Reversed' : ($payment->payment_engine_version === 'v2' ? 'V2' : 'محصل') }}
                                        </span>
                                        <strong class="p3-item-title">دفعة #{{ $payment->id }}</strong>
                                    </div>
                                    <span class="p3-item-amount">{{ $paymentProjection ? $paymentProjection['total'] : $payment->amount }} د.أ</span>
                                </div>
                                <div class="p3-item-meta">
                                    {{ $payment->received_at ?: $payment->paid_at }} · {{ $payment->payment_method }}
                                    @if($payment->reference) · مرجع: {{ $payment->reference }} @endif
                                </div>
                                @if($paymentProjection)
                                    <div class="p3-item-meta" style="font-size:11px;color:#64748B">
                                        مخصص: {{ $paymentProjection['allocated'] }} د.أ
                                        · مسترد: {{ $paymentProjection['refunded'] }} د.أ
                                        · رصيد غير مخصص: <strong style="color:{{ $paymentProjection['unallocated_minor'] > 0 ? '#16A34A' : '#475569' }}">{{ $paymentProjection['unallocated'] }} د.أ</strong>
                                    </div>
                                @endif
                                @if($payment->reversal)
                                    <div class="p3-callout-danger">
                                        تم عكس الدفعة: {{ $payment->reversal->reason }}
                                    </div>
                                @endif
                                @if($payment->notes)
                                    <div class="p3-item-meta">{{ $payment->notes }}</div>
                                @endif

                                {{-- Auto Allocate & Refund Forms if unallocated balance remains --}}
                                @if($paymentProjection && ! $paymentProjection['is_reversed'] && $paymentProjection['unallocated_minor'] > 0)
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                                        <form method="POST" action="{{ route('payments.auto-allocate', $payment) }}">
                                            @csrf
                                            <button class="p3-btn p3-btn-soft p3-btn-sm" type="submit">تخصيص تلقائي للأقدم</button>
                                        </form>
                                    </div>

                                    @can(\App\Support\FinancialPermissions::ISSUE_REFUNDS)
                                        <form method="POST" action="{{ route('payments.refunds.store', $payment) }}" class="p3-box-nested" style="margin-top:8px" onsubmit="return confirm('الاسترداد يمثل مالاً عائداً للعميل وليس عكس دفعة. هل تريد المتابعة؟')">
                                            @csrf
                                            <strong style="font-size:12px;color:#0055CC;display:block;margin-bottom:6px">استرداد مالي من رصيد الدفعة:</strong>
                                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:6px">
                                                <input name="amount" required placeholder="{{ $paymentProjection['unallocated'] }}" class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <select name="financial_account_id" required class="p3-select" style="font-size:12px;padding:4px 8px">
                                                    @foreach($activeFinancialAccounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="refund_method" required class="p3-select" style="font-size:12px;padding:4px 8px">
                                                    @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                                        <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="datetime-local" name="refunded_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <input name="reference" placeholder="مرجع الاسترداد" class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <input name="reason" required placeholder="سبب الاسترداد" class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <button class="p3-btn p3-btn-soft p3-btn-sm" type="submit">استرداد من الرصيد</button>
                                            </div>
                                        </form>
                                    @endcan
                                @endif

                                {{-- Allocations list & Reverse action --}}
                                @if($payment->allocations->isNotEmpty())
                                    <div class="p3-box-nested" style="margin-top:8px">
                                        <strong style="font-size:12px;display:block;margin-bottom:6px">التخصيصات المعتمدة على الفواتير:</strong>
                                        <div style="display:grid;gap:6px">
                                            @foreach($payment->allocations as $allocation)
                                                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;background:#fff;border:1px solid #E2E8F0;border-radius:6px;padding:6px 10px">
                                                    <div style="font-size:12px">
                                                        <span class="p3-badge {{ $allocation->reversal ? 'p3-badge-danger' : 'p3-badge-success' }}" style="padding:1px 6px;font-size:10px">
                                                            {{ $allocation->reversal ? 'Reversed' : 'Active' }}
                                                        </span>
                                                        <strong class="ltr" style="direction:ltr;margin-inline:4px">{{ optional($allocation->invoice)->invoice_number }}</strong>
                                                        · {{ \App\Support\Money::fromMinorUnits($allocation->amount_minor)->format() }} د.أ
                                                    </div>
                                                    @if($allocation->reversal)
                                                        <span style="font-size:11px;color:#B42318">سبب العكس: {{ $allocation->reversal->reason }}</span>
                                                    @else
                                                        @can(\App\Support\FinancialPermissions::MANAGE_COLLECTION_CORRECTIONS)
                                                            <form method="POST" action="{{ route('payment-allocations.reverse', $allocation) }}" style="display:flex;gap:6px" onsubmit="return confirm('عكس التخصيص سيعيد فتح رصيد الفاتورة. هذا ليس استرداداً للعميل. هل تريد المتابعة؟')">
                                                                @csrf
                                                                <input name="reason" required placeholder="سبب عكس التخصيص" class="p3-input" style="font-size:11px;padding:3px 6px;max-width:160px">
                                                                <button class="p3-btn p3-btn-danger p3-btn-sm" type="submit" style="padding:3px 8px;font-size:11px">عكس التخصيص</button>
                                                            </form>
                                                        @endcan
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Payment Reversal Form --}}
                                @if($paymentProjection && ! $paymentProjection['is_reversed'] && $payment->payment_engine_version === 'v2')
                                    @can(\App\Support\FinancialPermissions::MANAGE_COLLECTION_CORRECTIONS)
                                        <form method="POST" action="{{ route('payments.reverse', $payment) }}" style="display:flex;gap:6px;align-items:center;margin-top:8px" onsubmit="return confirm('عكس الدفعة هو تصحيح سجل فقط وليس استرداداً نقدياً للعميل. يجب عكس كل التخصيصات أولاً. هل تريد المتابعة؟')">
                                            @csrf
                                            <input name="reason" required placeholder="سبب عكس الدفعة بالكامل" class="p3-input" style="max-width:240px;font-size:12px;padding:4px 8px">
                                            <button class="p3-btn p3-btn-danger p3-btn-sm" type="submit">عكس الدفعة</button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        @empty
                            <div class="p3-empty">لا توجد دفعات محصلة بعد لهذا العميل.</div>
                        @endforelse
                    </div>

                    {{-- Installment Schedules if any --}}
                    @if(isset($schedules) && $schedules->count() > 0)
                        <div style="margin-top:16px">
                            <div class="p3-card-head">
                                <h3 class="p3-card-title">جدول الأقساط والاستحقاقات</h3>
                                <span class="p3-badge p3-badge-neutral">{{ $schedules->count() }} أقساط</span>
                            </div>
                            <div class="p3-list">
                                @foreach($schedules as $schedule)
                                    <div class="p3-list-item">
                                        <div class="p3-item-top">
                                            <div>
                                                <span class="p3-badge {{ $schedule->status === 'paid' ? 'p3-badge-success' : (\Carbon\Carbon::parse($schedule->due_date)->isPast() ? 'p3-badge-danger' : 'p3-badge-warning') }}" style="margin-inline-end:6px">
                                                    {{ $schedule->status }}
                                                </span>
                                                <strong class="p3-item-title">{{ number_format($schedule->amount_due, 2) }} د.أ</strong>
                                            </div>
                                            <span class="p3-item-meta">استحقاق: {{ $schedule->due_date }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Record Payment & Allocate Unused Credit Forms --}}
                <div style="display:flex;flex-direction:column;gap:16px">
                    {{-- Record Payment V2 Form --}}
                    <div class="p3-card">
                        <div class="p3-card-head">
                            <div>
                                <h3 class="p3-card-title">{{ __('notify.collections.record_payment') }}</h3>
                                <p class="p3-subtitle" style="margin:2px 0 0 0">تسجيل استلام نقد/تحويل لحساب مالي مع إمكانية التخصيص الفوري.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('clients.collections.payments.store', $client) }}">
                            @csrf
                            <div class="p3-filter-grid" style="grid-template-columns:1fr 1fr;gap:10px">
                                <div class="p3-field">
                                    <label>المبلغ المحصل (د.أ) *</label>
                                    <input name="amount" required placeholder="450.000" class="p3-input">
                                </div>
                                <div class="p3-field">
                                    <label>الحساب المالي المستلم *</label>
                                    <select name="financial_account_id" required class="p3-select">
                                        @foreach($activeFinancialAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="p3-field">
                                    <label>طريقة الدفع *</label>
                                    <select name="payment_method" required class="p3-select">
                                        @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                            <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="p3-field">
                                    <label>تاريخ ووقت التحصيل *</label>
                                    <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required class="p3-input">
                                </div>
                            </div>
                            <div class="p3-field" style="margin-top:10px">
                                <label>مرجع الدفعة / رقم الإيصال</label>
                                <input name="reference" placeholder="رقم إيصال / مرجع تحويل بنكي" class="p3-input">
                            </div>
                            <div class="p3-field" style="margin-top:10px">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                                    <input type="checkbox" name="auto_allocate_oldest" value="1">
                                    <span>تخصيص تلقائي فوري لأقدم الفواتير المستحقة</span>
                                </label>
                            </div>
                            <div class="p3-box-nested" style="margin-top:12px">
                                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px">أو تخصيص يدوي مباشر على فاتورة محددة:</label>
                                <div style="display:grid;grid-template-columns:minmax(0,1fr) 130px;gap:8px">
                                    <select name="allocations[0][invoice_id]" class="p3-select">
                                        <option value="">بدون تخصيص فوري (رصيد متاح)</option>
                                        @foreach($invoices as $invoice)
                                            @php
                                                $projection = $invoiceReceivables[$invoice->id] ?? null;
                                            @endphp
                                            @if($invoice->status === 'issued' && $projection && $projection['outstanding_minor'] > 0)
                                                <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · مستحق {{ $projection['outstanding'] }} د.أ</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <input name="allocations[0][amount]" placeholder="0.000" class="p3-input">
                                </div>
                            </div>
                            <button class="p3-btn p3-btn-primary" style="margin-top:14px;width:100%" type="submit">تسجيل دفعة V2</button>
                        </form>
                    </div>

                    {{-- Allocate Unused Payment Credit Form --}}
                    @if($paymentReceivables->where('unallocated_minor', '>', 0)->isNotEmpty())
                        <div class="p3-card">
                            <div class="p3-card-head">
                                <div>
                                    <h3 class="p3-card-title">{{ __('notify.collections.allocate_credit') }}</h3>
                                    <p class="p3-subtitle" style="margin:2px 0 0 0">ربط مبالغ الدفعات الزائدة بفواتير قائمة غير مسددة.</p>
                                </div>
                            </div>
                            @foreach($payments as $payment)
                                @php
                                    $paymentProjection = $paymentReceivables[$payment->id] ?? null;
                                @endphp
                                @if($paymentProjection && ! $paymentProjection['is_reversed'] && $paymentProjection['unallocated_minor'] > 0)
                                    <form method="POST" action="{{ route('payments.allocations.store', $payment) }}" class="p3-box-nested" style="margin-bottom:10px">
                                        @csrf
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                            <strong style="font-size:12px">دفعة #{{ $payment->id }}</strong>
                                            <span class="p3-badge p3-badge-success">متاح {{ $paymentProjection['unallocated'] }} د.أ</span>
                                        </div>
                                        <div style="display:grid;grid-template-columns:minmax(0,1fr) 110px;gap:8px">
                                            <select name="invoice_id" required class="p3-select">
                                                @foreach($invoices as $invoice)
                                                    @php
                                                        $projection = $invoiceReceivables[$invoice->id] ?? null;
                                                    @endphp
                                                    @if($invoice->status === 'issued' && $projection && $projection['outstanding_minor'] > 0)
                                                        <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · {{ $projection['outstanding'] }} د.أ</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <input name="amount" required placeholder="{{ $paymentProjection['unallocated'] }}" class="p3-input">
                                        </div>
                                        <button class="p3-btn p3-btn-soft p3-btn-sm" style="margin-top:8px" type="submit">تخصيص الرصيد</button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </x-notify.collapsible-section>

        {{-- =========================================================================
             SECTION 4: CREDITS & REFUNDS
             ========================================================================= --}}
        <x-notify.collapsible-section id="sec-credits" :title="__('notify.client_workspace.credits_refunds')" subtitle="إشعارات الخصم والتصحيح وتطبيقها على الفواتير" :badge="$creditNotes->count()" :open="false">
            <div class="p3-grid-2">
                {{-- Credit Notes List --}}
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h2 class="p3-card-title">{{ __('notify.collections.credit_notes') }}</h2>
                            <span class="p3-kpi-meta">إشعارات الخصم والتصحيح وتطبيقها على الفواتير</span>
                        </div>
                        <span class="p3-badge p3-badge-primary">{{ $creditNotes->count() }} إشعار</span>
                    </div>

                    {{-- Issue Credit Note Form --}}
                    @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                        <form method="POST" action="{{ route('clients.credit-notes.store', $client) }}" class="p3-box-nested" style="margin-bottom:14px">
                            @csrf
                            <strong style="font-size:13px;display:block;margin-bottom:8px;color:#0055CC">إصدار إشعار دائن جديد:</strong>
                            <div class="p3-filter-grid" style="grid-template-columns:1fr 1fr;gap:8px">
                                <div class="p3-field">
                                    <label>تاريخ الإصدار *</label>
                                    <input type="date" name="issue_date" value="{{ now()->toDateString() }}" required class="p3-input">
                                </div>
                                <div class="p3-field">
                                    <label>فاتورة أصلية اختيارية</label>
                                    <select name="original_invoice_id" class="p3-select">
                                        <option value="">رصيد عام / Goodwill</option>
                                        @foreach($invoices as $invoice)
                                            @if($invoice->status === 'issued')
                                                <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · {{ \App\Support\Money::fromMinorUnits($invoice->total_minor)->format() }} د.أ</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="p3-field" style="margin-top:8px">
                                <label>السبب *</label>
                                <input name="reason" required placeholder="خصم تجاري / تصحيح استحقاق / رصيد حسن نية" class="p3-input">
                            </div>
                            <div class="p3-box-nested" style="margin-top:8px;background:#fff">
                                <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px">تفاصيل سطر إشعار الدائن *</label>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:6px">
                                    <div class="p3-field">
                                        <label>الوصف</label>
                                        <input name="lines[0][description]" required placeholder="Credit adjustment" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>المبلغ قبل الضريبة</label>
                                        <input name="lines[0][subtotal_jod]" required placeholder="20.000" class="p3-input">
                                    </div>
                                    <div class="p3-field">
                                        <label>الضريبة</label>
                                        <input name="lines[0][tax_jod]" value="0.000" class="p3-input">
                                    </div>
                                </div>
                            </div>
                            <button class="p3-btn p3-btn-primary p3-btn-sm" style="margin-top:10px" type="submit">إنشاء وإصدار إشعار دائن</button>
                        </form>
                    @endcan

                    <div class="p3-list">
                        @forelse($creditNotes as $creditNote)
                            @php
                                $creditProjection = $creditNoteReceivables[$creditNote->id] ?? null;
                            @endphp
                            <div class="p3-list-item">
                                <div class="p3-item-top">
                                    <div>
                                        <span class="p3-badge {{ $creditNote->status === 'issued' ? 'p3-badge-primary' : ($creditNote->status === 'voided' ? 'p3-badge-danger' : 'p3-badge-warning') }}" style="margin-inline-end:6px">
                                            {{ $creditNote->status }}
                                        </span>
                                        <strong class="ltr p3-item-title" style="direction:ltr">{{ $creditNote->credit_note_number }}</strong>
                                    </div>
                                    @if($creditProjection)
                                        <span class="p3-item-amount" style="color:#16A34A">{{ $creditProjection['available'] }} د.أ متاح</span>
                                    @endif
                                </div>
                                <div class="p3-item-meta">
                                    {{ $creditNote->issue_date?->format('Y-m-d') }} · السبب: {{ $creditNote->reason }}
                                    @if($creditNote->originalInvoice)
                                        · الفاتورة الأصلية: <strong class="ltr" style="direction:ltr">{{ $creditNote->originalInvoice->invoice_number }}</strong>
                                    @endif
                                </div>
                                @if($creditProjection)
                                    <div class="p3-item-meta" style="font-size:11px;color:#64748B">
                                        الإجمالي: {{ $creditProjection['total'] }} د.أ
                                        · مطبق: {{ $creditProjection['applied'] }} د.أ
                                        · مسترد: {{ $creditProjection['refunded'] }} د.أ
                                    </div>
                                @endif

                                {{-- Apply Credit Note to Invoice Form --}}
                                @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                                    @if($creditNote->status === 'issued' && $creditProjection && $creditProjection['available_minor'] > 0)
                                        <form method="POST" action="{{ route('credit-notes.applications.store', $creditNote) }}" class="p3-box-nested" style="margin-top:6px">
                                            @csrf
                                            <strong style="font-size:12px;color:#0055CC;display:block;margin-bottom:6px">تطبيق الرصيد على فاتورة مستحقة:</strong>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                                <select name="invoice_id" required class="p3-select" style="min-width:200px">
                                                    @foreach($invoices as $invoice)
                                                        @php
                                                            $projection = $invoiceReceivables[$invoice->id] ?? null;
                                                        @endphp
                                                        @if($invoice->status === 'issued' && $projection && $projection['outstanding_minor'] > 0)
                                                            <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} · مستحق {{ $projection['outstanding'] }} د.أ</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <input name="amount" required placeholder="{{ $creditProjection['available'] }}" class="p3-input" style="max-width:110px">
                                                <button class="p3-btn p3-btn-soft p3-btn-sm" type="submit">تطبيق الرصيد</button>
                                            </div>
                                        </form>
                                    @endif
                                @endcan

                                {{-- Applications List with Reversal --}}
                                @if($creditNote->applications->isNotEmpty())
                                    <div class="p3-box-nested" style="margin-top:6px">
                                        <strong style="font-size:12px;display:block;margin-bottom:6px">التطبيقات على الفواتير:</strong>
                                        <div style="display:grid;gap:6px">
                                            @foreach($creditNote->applications as $application)
                                                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;background:#fff;border:1px solid #E2E8F0;border-radius:6px;padding:6px 10px">
                                                    <div style="font-size:12px">
                                                        <span class="p3-badge {{ $application->reversal ? 'p3-badge-danger' : 'p3-badge-success' }}" style="padding:1px 6px;font-size:10px">
                                                            {{ $application->reversal ? 'Reversed' : 'Active' }}
                                                        </span>
                                                        <strong class="ltr" style="direction:ltr;margin-inline:4px">{{ optional($application->invoice)->invoice_number }}</strong>
                                                        · {{ \App\Support\Money::fromMinorUnits($application->amount_minor)->format() }} د.أ
                                                    </div>
                                                    @if($application->reversal)
                                                        <span style="font-size:11px;color:#B42318">سبب العكس: {{ $application->reversal->reason }}</span>
                                                    @else
                                                        @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                                                            <form method="POST" action="{{ route('credit-note-applications.reverse', $application) }}" style="display:flex;gap:6px" onsubmit="return confirm('عكس تطبيق الرصيد يعيد فتح ذمة الفاتورة ويعيد الرصيد المتاح. هل تريد المتابعة؟')">
                                                                @csrf
                                                                <input name="reason" required placeholder="سبب عكس التطبيق" class="p3-input" style="font-size:11px;padding:3px 6px;max-width:160px">
                                                                <button class="p3-btn p3-btn-danger p3-btn-sm" type="submit" style="padding:3px 8px;font-size:11px">عكس التطبيق</button>
                                                            </form>
                                                        @endcan
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Refund from Credit Note Form --}}
                                @can(\App\Support\FinancialPermissions::ISSUE_REFUNDS)
                                    @if($creditNote->status === 'issued' && $creditProjection && $creditProjection['available_minor'] > 0)
                                        <form method="POST" action="{{ route('credit-notes.refunds.store', $creditNote) }}" class="p3-box-nested" style="margin-top:6px" onsubmit="return confirm('الاسترداد من إشعار دائن يمثل مالاً عائداً للعميل وليس عكس تطبيق. هل تريد المتابعة؟')">
                                            @csrf
                                            <strong style="font-size:12px;color:#0055CC;display:block;margin-bottom:6px">استرداد مالي من رصيد إشعار الدائن:</strong>
                                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:6px">
                                                <input name="amount" required placeholder="{{ $creditProjection['available'] }}" class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <select name="financial_account_id" required class="p3-select" style="font-size:12px;padding:4px 8px">
                                                    @foreach($activeFinancialAccounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->name_ar }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="refund_method" required class="p3-select" style="font-size:12px;padding:4px 8px">
                                                    @foreach($paymentMethodOptions as $methodValue => $methodLabel)
                                                        <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="datetime-local" name="refunded_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <input name="reference" placeholder="مرجع الاسترداد" class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <input name="reason" required placeholder="سبب الاسترداد" class="p3-input" style="font-size:12px;padding:4px 8px">
                                                <button class="p3-btn p3-btn-soft p3-btn-sm" type="submit">استرداد مالي</button>
                                            </div>
                                        </form>
                                    @endif
                                @endcan

                                {{-- Void Credit Note Form --}}
                                @can(\App\Support\FinancialPermissions::MANAGE_CREDIT_NOTES)
                                    @if($creditNote->status === 'issued' && $creditProjection && $creditProjection['applied_minor'] <= 0 && $creditProjection['refunded_minor'] <= 0)
                                        <form method="POST" action="{{ route('credit-notes.void', $creditNote) }}" style="display:flex;gap:6px;align-items:center;margin-top:6px" onsubmit="return confirm('سيتم إلغاء إشعار الدائن دون حذف سطوره. هل تريد المتابعة؟')">
                                            @csrf
                                            <input name="void_reason" required placeholder="سبب إلغاء إشعار الدائن" class="p3-input" style="max-width:240px;font-size:12px;padding:4px 8px">
                                            <button class="p3-btn p3-btn-danger p3-btn-sm" type="submit">إلغاء إشعار الدائن</button>
                                        </form>
                                    @elseif($creditNote->status === 'voided')
                                        <div class="p3-callout-danger" style="margin-top:4px">
                                            سبب الإلغاء: {{ $creditNote->void_reason }}
                                        </div>
                                    @endif
                                @endcan
                            </div>
                        @empty
                            <div class="p3-empty">لا توجد إشعارات دائن لهذا العميل بعد.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Refunds History List --}}
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h2 class="p3-card-title">{{ __('notify.collections.refunds') }}</h2>
                            <span class="p3-kpi-meta">المبالغ المستردة نقدياً أو بنكياً للعميل</span>
                        </div>
                        <span class="p3-badge p3-badge-neutral">{{ $refunds->count() }} استرداد</span>
                    </div>
                    <div class="p3-list">
                        @forelse($refunds as $refund)
                            <div class="p3-list-item">
                                <div class="p3-item-top">
                                    <div>
                                        <span class="p3-badge p3-badge-success" style="margin-inline-end:6px">Refund</span>
                                        <strong class="ltr p3-item-title" style="direction:ltr">{{ $refund->refund_number }}</strong>
                                    </div>
                                    <span class="p3-item-amount" style="color:#B42318">{{ \App\Support\Money::fromMinorUnits($refund->amount_minor)->format() }} د.أ</span>
                                </div>
                                <div class="p3-item-meta">
                                    طريقة الاسترداد: {{ $refund->refund_method }} · التاريخ: {{ $refunded_at = $refund->refunded_at?->format('Y-m-d H:i') }}
                                </div>
                                <div class="p3-item-meta" style="font-size:11px;color:#64748B">
                                    المصدر: {{ $refund->payment_id ? ('دفعة #'.$refund->payment_id) : ('إشعار دائن '.optional($refund->creditNote)->credit_note_number) }}
                                    · السبب: {{ $refund->reason }}
                                </div>
                            </div>
                        @empty
                            <div class="p3-empty">لا يوجد سجل استردادات لهذا العميل.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-notify.collapsible-section>

        {{-- =========================================================================
             SECTION 5: OFFICIAL CONTRACTS
             ========================================================================= --}}
        <x-notify.collapsible-section id="sec-contracts" :title="__('notify.sales.contracts')" subtitle="عقود تشغيلية تفصيلية تشمل 22 بنداً قانونياً وتشغيلياً" :badge="$contracts->count()" :open="false">
            <div class="p3-card">
                <div class="p3-card-head">
                    <div>
                        <h2 class="p3-card-title">{{ __('notify.sales.contracts') }}</h2>
                        <span class="p3-kpi-meta">عقود تشغيلية تفصيلية تشمل 22 بنداً قانونياً وتشغيلياً مع الملاحق الثلاثة وحفظ اللقطات التاريخية</span>
                    </div>
                    <span class="p3-badge p3-badge-primary">{{ $contracts->count() }} عقد</span>
                </div>

                <div class="p3-list">
                    @forelse($contracts as $contract)
                        <div class="p3-list-item">
                            <div class="p3-item-top">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <strong class="ltr p3-item-title" style="direction:ltr;font-size:15px">{{ $contract->contract_number }}</strong>
                                    @if($contract->status === 'issued')
                                        <span class="p3-badge p3-badge-success">معتمد ورسمي</span>
                                    @elseif($contract->status === 'voided')
                                        <span class="p3-badge p3-badge-danger">{{ __('notify.contracts.voided') }}</span>
                                    @elseif($contract->status === 'superseded')
                                        <span class="p3-badge p3-badge-neutral">مستبدل</span>
                                    @else
                                        <span class="p3-badge p3-badge-warning">مسودة قيد المراجعة</span>
                                    @endif
                                    <span class="p3-badge p3-badge-neutral">نسخة v{{ $contract->template_version }}</span>
                                </div>
                                <span class="p3-item-meta">
                                    تاريخ الإنشاء: {{ $contract->created_at->format('Y-m-d') }}
                                    @if($contract->issued_at) · الاعتماد: {{ $contract->issued_at->format('Y-m-d') }} @endif
                                </span>
                            </div>

                            <div class="p3-item-actions" style="justify-content:space-between">
                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                    <a href="{{ route('contracts.preview', $contract->id) }}" target="_blank" class="p3-btn p3-btn-soft p3-btn-sm">
                                        👁️ معاينة العقد
                                    </a>
                                    <a href="{{ route('contracts.print', $contract->id) }}" target="_blank" class="p3-btn p3-btn-soft p3-btn-sm">
                                        🖨️ طباعة / حفظ PDF
                                    </a>
                                    <a href="{{ route('contracts.download', $contract->id) }}" class="p3-btn p3-btn-ghost p3-btn-sm">
                                        💾 تحميل المستند
                                    </a>
                                </div>

                                @if(auth()->user()->isAdmin())
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        @if($contract->status === 'draft')
                                            <form method="POST" action="{{ route('contracts.issue', $contract->id) }}" onsubmit="return confirm('هل أنت متأكد من اعتماد وإصدار هذا العقد رسمياً؟')">
                                                @csrf
                                                <button type="submit" class="p3-btn p3-btn-success p3-btn-sm">
                                                    ✓ اعتماد وإصدار
                                                </button>
                                            </form>
                                        @endif

                                        @if($contract->status === 'issued')
                                            <form method="POST" action="{{ route('contracts.supersede', $contract->id) }}" onsubmit="return confirm('هل تريد استبدال هذا العقد بإصدار جديد محدث؟ سيتم نقل الحالة الحالية إلى مستبدل.')">
                                                @csrf
                                                <button type="submit" class="p3-btn p3-btn-soft p3-btn-sm">
                                                    🔄 {{ __('notify.contracts.supersede') }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('contracts.void', $contract->id) }}" onsubmit="var r = prompt('أدخل سبب إلغاء هذا العقد:'); if(r){ this.reason.value = r; return true; } return false;">
                                                @csrf
                                                <input type="hidden" name="reason" value="">
                                                <button type="submit" class="p3-btn p3-btn-danger p3-btn-sm">
                                                    ⛔ {{ __('notify.contracts.void_action') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p3-empty">
                            لا توجد عقود صادرة لهذا العميل بعد. يمكنك توليد مسودة عقد رسمي من أي اشتراك نشط في القسم الأول أعلاه.
                        </div>
                    @endforelse
                </div>
            </div>
        </x-notify.collapsible-section>

        {{-- =========================================================================
             SECTION 6: ACCOUNTING TRACE
             ========================================================================= --}}
        @can(\App\Support\FinancialPermissions::VIEW_ACCOUNTING)
            <x-notify.collapsible-section id="sec-accounting" :title="__('notify.accounting.accounting_trace')" subtitle="القيود اليومية التلقائية المرتبطة بفوترة وتحصيل هذا العميل" :badge="$accountingTrace->count()" :open="false">
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h2 class="p3-card-title">{{ __('notify.accounting.accounting_trace') }}</h2>
                            <span class="p3-kpi-meta">القيود اليومية التلقائية المرتبطة بفوترة وتحصيل هذا العميل</span>
                        </div>
                        <span class="p3-badge p3-badge-neutral">{{ $accountingTrace->count() }} قيد</span>
                    </div>
                    <div class="p3-list">
                        @forelse($accountingTrace as $trace)
                            @php
                                $entry = $trace['entry'];
                            @endphp
                            <div class="p3-list-item">
                                <div class="p3-item-top">
                                    <div>
                                        <span class="p3-badge {{ $entry->status === 'reversed' ? 'p3-badge-danger' : 'p3-badge-primary' }}" style="margin-inline-end:6px">
                                            {{ $entry->status }}
                                        </span>
                                        <strong class="ltr p3-item-title" style="direction:ltr">{{ $entry->journal_number }}</strong>
                                    </div>
                                    <span class="p3-item-meta">{{ $entry->entry_date?->format('Y-m-d') }}</span>
                                </div>
                                <div class="p3-item-meta">
                                    المصدر: {{ $trace['source_label'] }} · نوع الحدث: {{ $entry->event_type }}
                                </div>
                                <div class="p3-item-meta" style="font-size:11px;color:#64748B">
                                    {{ $entry->description }}
                                </div>
                            </div>
                        @empty
                            <div class="p3-empty">لا توجد قيود محاسبية مرتبطة بعناصر الفوترة لهذا العميل بعد.</div>
                        @endforelse
                    </div>
                </div>
            </x-notify.collapsible-section>
        @endcan

        {{-- =========================================================================
             COMMERCIAL OFFERS SECTION
             ========================================================================= --}}
        <x-notify.collapsible-section id="sec-offers" :title="__('notify.sales.offers')" subtitle="عروض الأسعار والخصومات المتفاوض عليها" :badge="$offers->count()" :open="false">
            <div class="p3-grid-2">
                {{-- Commercial Offers List --}}
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h2 class="p3-card-title">{{ __('notify.sales.offers') }}</h2>
                            <span class="p3-kpi-meta">عروض الأسعار والخصومات المتفاوض عليها</span>
                        </div>
                        <span class="p3-badge p3-badge-success">{{ $offers->count() }} عروض</span>
                    </div>
                    <div class="p3-list">
                        @forelse($offers as $offer)
                            <div class="p3-list-item">
                                <div class="p3-item-top">
                                    <div>
                                        <span class="p3-badge p3-badge-success" style="margin-inline-end:6px">{{ $offer->billing_period }}</span>
                                        <strong class="p3-item-title">{{ $offer->package }}</strong>
                                    </div>
                                    <span class="p3-item-amount">{{ number_format($offer->final_agreed_price, 2) }} د.أ</span>
                                </div>
                                <div class="p3-item-meta">
                                    السعر الأصلي: {{ number_format($offer->price, 2) }} د.أ
                                    @if($offer->discount > 0) · الخصم: {{ number_format($offer->discount, 2) }} د.أ @endif
                                    · تاريخ العرض: {{ $offer->offer_date }}
                                </div>
                                @if($offer->decision_deadline)
                                    <div class="p3-item-meta" style="color:#D97706">⏰ مهلة القرار حتى: {{ $offer->decision_deadline }}</div>
                                @endif
                                @if($offer->notes)
                                    <div class="p3-item-meta">{{ $offer->notes }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="p3-empty">لا توجد عروض تجارية مسجلة لهذا العميل.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Add Offer Form --}}
                <div class="p3-card">
                    <div class="p3-card-head">
                        <div>
                            <h3 class="p3-card-title">{{ __('notify.sales.new_offer') }}</h3>
                            <p class="p3-subtitle" style="margin:2px 0 0 0">تسجيل عرض أسعار تفاوضي مع تحديد مهلة اتخاذ القرار.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('clients.offers.store', $client->id) }}">
                        @csrf
                        <div class="p3-filter-grid" style="grid-template-columns:1fr 1fr;gap:10px">
                            <div class="p3-field">
                                <label>اسم الباقة *</label>
                                <input name="package" required placeholder="الباقة الأساسية / باقة المتاجر" class="p3-input">
                            </div>
                            <div class="p3-field">
                                <label>دورة الفوترة *</label>
                                <select name="billing_period" required class="p3-select">
                                    <option value="monthly">شهري</option>
                                    <option value="annual">سنوي</option>
                                    <option value="installment">تقسيط</option>
                                </select>
                            </div>
                            <div class="p3-field">
                                <label>السعر الأساسي (د.أ) *</label>
                                <input type="number" step="0.01" name="price" id="offer_price" required placeholder="500" oninput="calculateOfferFinal()" class="p3-input">
                            </div>
                            <div class="p3-field">
                                <label>قيمة الخصم (د.أ)</label>
                                <input type="number" step="0.01" name="discount" id="offer_discount" value="0" oninput="calculateOfferFinal()" class="p3-input">
                            </div>
                            <div class="p3-field">
                                <label>السعر الصافي المتفق عليه (د.أ)</label>
                                <input type="number" step="0.01" name="final_agreed_price" id="offer_final" placeholder="450" class="p3-input">
                            </div>
                            <div class="p3-field">
                                <label>تاريخ العرض *</label>
                                <input type="date" name="offer_date" value="{{ now()->toDateString() }}" required class="p3-input">
                            </div>
                            <div class="p3-field" style="grid-column:1 / -1">
                                <label>مهلة اتخاذ القرار</label>
                                <input type="date" name="decision_deadline" value="{{ now()->addDays(7)->toDateString() }}" class="p3-input">
                            </div>
                            <div class="p3-field" style="grid-column:1 / -1">
                                <label>ملاحظات وشروط العرض</label>
                                <textarea name="notes" placeholder="أي شروط خاصة، تدريب، أو ملحقات مجانية" class="p3-textarea"></textarea>
                            </div>
                        </div>
                        <button class="p3-btn p3-btn-primary" style="margin-top:14px;width:100%" type="submit">حفظ العرض التجاري</button>
                    </form>
                </div>
            </div>
        </x-notify.collapsible-section>
    </div>
</div>
