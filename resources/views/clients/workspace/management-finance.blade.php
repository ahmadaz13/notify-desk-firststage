<div class="notify-workspace-management-content">
    {{-- Reopen Client Section (Targeted by Reopen Action) --}}
    @if($client->stage === \App\Support\ClientLifecycle::CLOSED || $client->status === 'archived')
        <section id="sec-reopen-client" class="card" style="margin-bottom:20px;border:2px solid var(--notify-primary)">
            <div class="section-head" style="margin-top:0">
                <h2>{{ __('notify.client_workspace.action_reopen') }}</h2>
            </div>
            <form method="POST" action="{{ route('clients.reopen', $client->id) }}" class="form-grid">
                @csrf
                <div class="field full">
                    <label>سبب إعادة فتح الملف *</label>
                    <input name="reason" required placeholder="اذكر سبب إعادة فتح ملف العميل للمتابعة">
                </div>
                <div class="field">
                    <label>المرحلة المستهدفة</label>
                    <select name="stage">
                        <option value="prospect">فرصة جديدة</option>
                        <option value="contacting">قيد التواصل</option>
                        <option value="appointment">موعد</option>
                        <option value="decision_pending">بانتظار القرار</option>
                    </select>
                </div>
                <div class="field full">
                    <button class="btn btn-primary" type="submit">{{ __('notify.client_workspace.action_reopen') }}</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Pending Review Items --}}
    @php
        $pendingReviews = $client->relationLoaded('reviewItems')
            ? $client->reviewItems->where('status', \App\Models\ClientReviewItem::STATUS_PENDING)
            : \App\Models\ClientReviewItem::where('client_id', $client->id)->where('status', \App\Models\ClientReviewItem::STATUS_PENDING)->get();
    @endphp
    @if($pendingReviews->isNotEmpty())
        <section class="card" style="margin-bottom:20px;border:2px solid var(--notify-warning)">
            <div class="section-head" style="margin-top:0">
                <h2>بنود المراجعة المعلقة</h2>
                <span class="badge gold">{{ $pendingReviews->count() }} معلق</span>
            </div>
            <div class="list">
                @foreach($pendingReviews as $reviewItem)
                    <div class="list-row" id="sec-review-item-{{ $reviewItem->id }}">
                        <div class="list-main">
                            <span class="badge gold">{{ $reviewItem->type }}</span>
                            <small>{{ $reviewItem->created_at }}</small>
                            <p style="margin:4px 0">{{ $reviewItem->notes ?: 'بدون تفاصيل إضافية' }}</p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <form method="POST" action="{{ route('client-review-items.resolve', $reviewItem->id) }}">
                                @csrf
                                <input type="hidden" name="resolution_note" value="تمت المراجعة والحل">
                                <button class="btn btn-primary" type="submit">حل وتأكيد</button>
                            </form>
                            <form method="POST" action="{{ route('client-review-items.dismiss', $reviewItem->id) }}">
                                @csrf
                                <input type="hidden" name="resolution_note" value="تم التجاهل والمتابعة">
                                <button class="btn btn-soft" type="submit">تجاهل</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Preserved Stage Update / Close Section --}}
    <div id="sec-close-client" style="margin-bottom:20px">
        {{-- Handled by stage update form inside overview/management --}}
    </div>

    {{-- Billing, Payments, Invoices, Contracts, and Accounting Traces (Gated) --}}
    @if(auth()->user()?->isAdmin() || \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::MANAGE_SUBSCRIPTION_BILLING) || \Illuminate\Support\Facades\Gate::allows(\App\Support\FinancialPermissions::RECORD_PAYMENT))
        @include('clients.workspace.subscription-billing')
    @endif
</div>
