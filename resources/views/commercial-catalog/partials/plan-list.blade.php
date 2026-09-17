<div style="display:flex;flex-direction:column;gap:14px">
    @forelse($plans as $plan)
        <x-notify.collapsible-section
            :title="$plan->name_ar"
            :subtitle="$plan->code"
            :badge="$plan->is_active && !$plan->archived_at ? 'باقة فعالة' : 'باقة مؤرشفة'"
            :badgeVariant="$plan->is_active && !$plan->archived_at ? 'success' : 'danger'"
            icon="package"
            :open="$loop->first && !$plan->archived_at"
        >
            <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
                @if(!$plan->archived_at)
                    <form method="POST" action="{{ route('commercial-catalog.plans.archive', $plan) }}" onsubmit="return confirm('أرشفة الباقة؟ ستبقى الفواتير والاشتراكات التاريخية قابلة للقراءة.')">
                        @csrf
                        <button class="p5-btn p5-btn-ghost p5-btn-sm" type="submit">أرشفة الباقة</button>
                    </form>
                @endif
            </div>

            <div class="p5-grid-2">
                <div>
                    <h3 style="font-size:14px;color:#0A1128;margin:0 0 10px">{{ __('notify.catalog.edit_plan') }}</h3>
                    <form method="POST" action="{{ route('commercial-catalog.plans.update', $plan) }}">
                        @csrf
                        @method('PATCH')
                        <div class="p5-form-grid">
                            <div class="p5-field">
                                <label>النظام / المنتج</label>
                                <select name="product_id" class="p5-select">
                                    <option value="">بدون نظام محدد</option>
                                    @foreach($productOptions as $product)
                                        <option value="{{ $product->id }}" {{ $plan->product_id === $product->id ? 'selected' : '' }}>{{ $product->name_ar }} · {{ $product->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.plan_name_ar') }} *</label>
                                <input name="name_ar" value="{{ $plan->name_ar }}" required class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.plan_name_en') }}</label>
                                <input name="name_en" value="{{ $plan->name_en }}" class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>ترتيب تجاري</label>
                                <input type="number" name="tier" min="0" max="255" value="{{ $plan->tier }}" class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>نوع العرض</label>
                                <select name="offer_type" class="p5-select">
                                    <option value="package" {{ ($plan->offer_type ?: 'package') === 'package' ? 'selected' : '' }}>باقة</option>
                                    <option value="standalone" {{ $plan->offer_type === 'standalone' ? 'selected' : '' }}>مستقل</option>
                                </select>
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.common.status') }}</label>
                                <select name="is_active" class="p5-select">
                                    <option value="1" {{ $plan->is_active ? 'selected' : '' }}>{{ __('notify.statuses.active') }}</option>
                                    <option value="0" {{ !$plan->is_active ? 'selected' : '' }}>{{ __('notify.statuses.inactive') }}</option>
                                </select>
                            </div>
                            <div class="p5-field" style="grid-column:1 / -1">
                                <label>{{ __('notify.catalog.included_services') }}</label>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:6px">
                                    @foreach($services as $service)
                                        <label style="display:flex;gap:6px;align-items:center;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:6px;cursor:pointer;font-size:12px">
                                            <input type="checkbox" name="services[]" value="{{ $service->id }}" {{ $plan->services->contains('id', $service->id) ? 'checked' : '' }} style="accent-color:#0055CC">
                                            <span>{{ $service->name_ar }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:10px">
                            <button class="p5-btn p5-btn-soft p5-btn-sm" type="submit">{{ __('notify.catalog.save_plan') }}</button>
                        </div>
                    </form>
                </div>

                <div>
                    <h3 style="font-size:14px;color:#0A1128;margin:0 0 10px">{{ __('notify.catalog.add_price_version') }}</h3>
                    <form method="POST" action="{{ route('commercial-catalog.prices.store', $plan) }}">
                        @csrf
                        <div class="p5-form-grid">
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.billing_interval') }} *</label>
                                <select name="billing_interval" required class="p5-select">
                                    <option value="monthly">{{ __('notify.catalog.monthly') }}</option>
                                    <option value="annual">{{ __('notify.catalog.annual') }}</option>
                                </select>
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.price_jod') }} *</label>
                                <input name="amount_jod" required placeholder="15.000" class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.setup_fee_jod') }}</label>
                                <input name="setup_fee_jod" placeholder="0.000" class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.included_branches') }} *</label>
                                <input type="number" name="included_branch_quantity" min="1" value="1" required class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.additional_branch_price') }}</label>
                                <input name="additional_branch_price_jod" placeholder="5.000" class="p5-input">
                            </div>
                            <div class="p5-field">
                                <label>{{ __('notify.catalog.tax_rate_bps') }}</label>
                                <input type="number" name="default_tax_rate_bps" min="0" max="10000" placeholder="1600" class="p5-input">
                            </div>
                            <div class="p5-field" style="grid-column:1 / -1">
                                <label>{{ __('notify.catalog.effective_from') }} *</label>
                                <input type="datetime-local" name="effective_from" value="{{ now()->format('Y-m-d\\TH:i') }}" required class="p5-input">
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:10px">
                            <button class="p5-btn p5-btn-primary p5-btn-sm" type="submit">{{ __('notify.catalog.add_price') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F1F5F9">
                <h3 style="font-size:13px;font-weight:700;color:#475569;margin:0 0 8px">{{ __('notify.catalog.price_history') }}</h3>
                <div class="p5-list">
                    @forelse($plan->prices as $price)
                        <div class="p5-list-item" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                            <div style="display:flex;gap:8px;align-items:center">
                                <span class="p5-badge {{ $price->is_active ? 'p5-badge-success' : 'p5-badge-neutral' }}">{{ $price->billing_interval }}</span>
                                <strong style="font-size:14px;color:#0A1128">{{ \App\Support\Money::fromMinorUnits($price->amount_minor)->format() }} د.أ</strong>
                            </div>
                            <span class="p5-kpi-meta">
                                من {{ $price->effective_from?->format('Y-m-d H:i') }}
                                @if($price->effective_until) حتى {{ $price->effective_until->format('Y-m-d H:i') }} @endif
                                · رسوم تأسيس {{ \App\Support\Money::fromMinorUnits($price->setup_fee_minor)->format() }} د.أ
                                @if($price->default_tax_rate_bps !== null) · ضريبة {{ $price->default_tax_rate_bps }} bps @endif
                            </span>
                        </div>
                    @empty
                        <span class="p5-kpi-meta">{{ __('notify.catalog.no_prices') }}</span>
                    @endforelse
                </div>
            </div>
        </x-notify.collapsible-section>
    @empty
        <div class="p5-list-item">
            <span class="p5-kpi-meta">لا توجد باقات تحت هذا النظام بعد.</span>
        </div>
    @endforelse
</div>
