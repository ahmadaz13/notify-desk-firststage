@props([
    'client',
])

@php
    $canManageBilling = \App\Support\FinancialPermissions::allows(auth()->user(), \App\Support\FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING);
    $clientBranches = max(1, (int) ($client->number_of_branches ?: 1));
@endphp

@if($canManageBilling)
<div class="notify-modal-backdrop" id="modal-start-subscription" hidden>
    <div class="notify-modal-card notify-action-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-start-subscription-title" style="max-width:680px">
        <div class="notify-modal-header">
            <div>
                <span class="notify-eyebrow">COMMERCIAL_GUIDED_01</span>
                <h3 id="modal-start-subscription-title">{{ __('notify.client_workspace.guided_subscription.title') }}</h3>
                <p style="font-size:12px;color:var(--nd-muted);margin:2px 0 0">{{ __('notify.client_workspace.guided_subscription.subtitle') }}</p>
            </div>
            <button type="button" class="notify-icon-button" data-close-action-modal aria-label="{{ __('notify.client_workspace.cancel') }}">×</button>
        </div>

        <form method="POST" action="{{ route('clients.guided-subscription.store', $client->id) }}" class="notify-action-form" id="guided-subscription-form" data-preview-url="{{ route('clients.guided-subscription.preview', $client->id) }}" data-catalog-url="{{ route('clients.guided-subscription.catalog', $client->id) }}">
            @csrf
            <input type="hidden" name="_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            {{-- Step 1: Product and Plan --}}
            <div class="notify-form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                <div class="notify-form-group">
                    <label class="notify-field-label" for="guided-sub-product"><strong>{{ __('notify.client_workspace.guided_subscription.product_label') }}</strong></label>
                    <select name="product_id" id="guided-sub-product" class="notify-form-select" required>
                        <option value="">{{ __('notify.client_workspace.guided_subscription.select_product') }}</option>
                    </select>
                </div>

                <div class="notify-form-group">
                    <label class="notify-field-label" for="guided-sub-plan"><strong>{{ __('notify.client_workspace.guided_subscription.plan_label') }}</strong></label>
                    <select name="plan_id" id="guided-sub-plan" class="notify-form-select" required disabled>
                        <option value="">{{ __('notify.client_workspace.guided_subscription.select_plan') }}</option>
                    </select>
                </div>
            </div>

            {{-- Step 2: Billing Interval --}}
            <div class="notify-form-group" style="margin-top:12px">
                <label class="notify-field-label" for="guided-sub-interval"><strong>{{ __('notify.client_workspace.guided_subscription.billing_interval_label') }}</strong></label>
                <select name="billing_interval" id="guided-sub-interval" class="notify-form-select" required disabled>
                    <option value="">{{ __('notify.client_workspace.guided_subscription.select_interval') }}</option>
                    <option value="monthly">{{ __('notify.client_workspace.guided_subscription.monthly') }}</option>
                    <option value="annual">{{ __('notify.client_workspace.guided_subscription.annual') }}</option>
                </select>
            </div>

            {{-- Step 3: Annual Payment Terms (Conditional, shown for Annual) --}}
            <div id="guided-annual-terms-container" style="margin-top:12px;display:none">
                <div class="p3-box-nested" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px">
                    <div class="notify-form-group">
                        <label class="notify-field-label" for="guided-sub-payment-terms"><strong>{{ __('notify.client_workspace.guided_subscription.annual_payment_terms') }}</strong></label>
                        <select name="payment_terms" id="guided-sub-payment-terms" class="notify-form-select">
                            <option value="full">{{ __('notify.client_workspace.guided_subscription.full_payment') }}</option>
                            <option value="installments">{{ __('notify.client_workspace.guided_subscription.installments') }}</option>
                        </select>
                    </div>

                    <div id="guided-installments-config" style="display:none;margin-top:12px" class="notify-form-grid">
                        <div class="notify-form-group">
                            <label class="notify-field-label" for="guided-sub-installments-count"><strong>{{ __('notify.client_workspace.guided_subscription.installments_count_label') }}</strong></label>
                            <select name="installments_count" id="guided-sub-installments-count" class="notify-form-select">
                                <option value="2">قسطان (2)</option>
                                <option value="3">3 أقساط</option>
                                <option value="4" selected>4 أقساط</option>
                                <option value="6">6 أقساط</option>
                                <option value="12">12 قسطاً</option>
                            </select>
                        </div>
                        <div class="notify-form-group">
                            <label class="notify-field-label" for="guided-sub-due-day"><strong>{{ __('notify.client_workspace.guided_subscription.installment_due_day_label') }}</strong></label>
                            <select name="installment_due_day" id="guided-sub-due-day" class="notify-form-select">
                                <option value="1">1 من الشهر</option>
                                <option value="5" selected>5 من الشهر</option>
                                <option value="15">15 من الشهر</option>
                                <option value="30">30 من الشهر</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- More Options (Collapsible) --}}
            <details style="margin-top:14px;border:1px dashed #CBD5E1;border-radius:6px;padding:8px 12px">
                <summary style="cursor:pointer;font-size:12px;font-weight:700;color:var(--nd-muted, #64748B)">{{ __('notify.client_workspace.more_options') ?? 'خيارات إضافية' }}</summary>
                <div class="notify-form-group" style="margin-top:10px">
                    <label class="notify-field-label" for="guided-sub-start-date">{{ __('notify.client_workspace.guided_subscription.start_date_label') }}</label>
                    <input type="date" name="start_date" id="guided-sub-start-date" value="{{ \Carbon\Carbon::now('Asia/Amman')->toDateString() }}" class="notify-form-input">
                    <small style="display:block;color:var(--nd-muted, #64748B);margin-top:3px;font-size:11px">الافتراضي هو تاريخ اليوم بتوقيت عمان.</small>
                </div>
                <div class="notify-form-group" style="margin-top:10px">
                    <label class="notify-field-label" for="guided-sub-notes">{{ __('notify.client_workspace.guided_subscription.notes_label') }}</label>
                    <textarea name="notes" id="guided-sub-notes" class="notify-form-textarea" rows="2" placeholder="{{ __('notify.client_workspace.guided_subscription.notes_placeholder') }}"></textarea>
                </div>
            </details>

            {{-- Authoritative Preview Card --}}
            <div id="guided-sub-preview-card" style="margin-top:14px;display:none;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:8px;padding:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #E2E8F0;padding-bottom:8px;margin-bottom:10px">
                    <span style="font-size:12px;font-weight:800;color:#0F172A;text-transform:uppercase;letter-spacing:0.04em">{{ __('notify.client_workspace.guided_subscription.preview_title') }}</span>
                    <span id="guided-preview-badge" style="font-size:11px;padding:2px 8px;border-radius:4px;background:#E0E7FF;color:#3730A3;font-weight:700">معتمد من الخادم</span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:10px;font-size:12px">
                    <div>
                        <span style="color:#64748B;display:block">{{ __('notify.client_workspace.guided_subscription.recurring_price') }}</span>
                        <strong id="preview-unit-price" style="color:#0F172A;font-size:14px">-</strong>
                    </div>
                    <div id="preview-setup-fee-container">
                        <span style="color:#64748B;display:block">{{ __('notify.client_workspace.guided_subscription.setup_fee') }}</span>
                        <strong id="preview-setup-fee" style="color:#0F172A;font-size:14px">-</strong>
                    </div>
                    <div>
                        <span style="color:#64748B;display:block">{{ __('notify.client_workspace.guided_subscription.tax') }}</span>
                        <strong id="preview-tax" style="color:#0F172A;font-size:14px">-</strong>
                    </div>
                    <div style="background:#EFF6FF;padding:6px 10px;border-radius:6px;border:1px solid #BFDBFE">
                        <span style="color:#1E40AF;display:block;font-weight:700">{{ __('notify.client_workspace.guided_subscription.total_obligation') }}</span>
                        <strong id="preview-total" style="color:#1E3A8A;font-size:16px">-</strong>
                    </div>
                </div>

                {{-- Installment Schedule Table --}}
                <div id="preview-schedule-container" style="margin-top:12px;display:none;border-top:1px solid #E2E8F0;padding-top:10px">
                    <span style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:6px">{{ __('notify.client_workspace.guided_subscription.installment_schedule') }}:</span>
                    <table style="width:100%;font-size:11px;text-align:right;border-collapse:collapse" dir="rtl">
                        <thead>
                            <tr style="color:#64748B;border-bottom:1px solid #E2E8F0">
                                <th style="padding:4px">#</th>
                                <th style="padding:4px">تاريخ الاستحقاق</th>
                                <th style="padding:4px">قيمة القسط</th>
                            </tr>
                        </thead>
                        <tbody id="preview-schedule-tbody"></tbody>
                    </table>
                </div>
            </div>

            <div id="guided-sub-error" style="display:none;margin-top:10px;padding:8px 12px;border-radius:6px;background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;font-size:12px"></div>

            <div class="notify-modal-footer" style="margin-top:16px">
                <button type="button" class="notify-button-secondary" data-close-action-modal>{{ __('notify.client_workspace.guided_subscription.cancel_btn') }}</button>
                <button type="submit" class="notify-button-primary" id="guided-sub-submit-btn" disabled>
                    {{ __('notify.client_workspace.guided_subscription.confirm_btn') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif
