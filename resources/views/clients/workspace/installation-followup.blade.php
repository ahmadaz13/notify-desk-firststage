<div id="tab-installation">
    <div class="notify-section-stack">
<div class="notify-section-intro">
                <div>
                    <p class="notify-eyebrow">INSTALL_01</p>
                    <h2>التركيب والمتابعة</h2>
                    <small>{{ __('notify.installations.subtitle') }}</small>
                </div>
                <span class="notify-badge notify-badge--success">{{ $activeInstallationAppointments->count() }} نشطة</span>
            </div>
<div class="card" style="margin-bottom:16px">
                <div class="section-head" style="margin-top:0">
                    <h2>{{ __('notify.installations.free_install_badge') }}</h2>
                    <span class="muted">{{ $activeInstallationAppointments->count() }} مواعيد نشطة · {{ $installations->count() }} منجزة</span>
                </div>
                <div class="notify-installation-strip">
                    @forelse($installationAppointments as $appointment)
                        <article class="notify-action-row">
                            <span class="badge {{ in_array($appointment->status, \App\Support\AppointmentTypes::activeStatuses(), true) ? 'gold' : ($appointment->status === 'completed' ? 'green' : 'red') }}">
                                {{ $appointment->status }}
                            </span>
                            <div class="list-main">
                                <strong>{{ $appointment->appointment_date }} · {{ $appointment->appointment_time }}</strong>
                                <small>المسؤول: {{ $appointment->users->pluck('name')->join('، ') ?: 'غير محدد' }}</small>
                                <small>{{ $appointment->location ?: 'بدون موقع' }}@if($appointment->branch_name) · {{ $appointment->branch_name }} @endif</small>
                            </div>
                            @if(in_array($appointment->status, \App\Support\AppointmentTypes::activeStatuses(), true))
                                <form method="POST" action="{{ route('appointments.update', $appointment->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <input type="hidden" name="next_stage" value="contacting">
                                    <button class="btn btn-soft" type="submit">إلغاء التركيب</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="muted" style="padding:10px 0">لا يوجد موعد تركيب مجدول حالياً.</div>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('clients.installations.schedule', $client->id) }}" style="margin-bottom:16px">
                    @csrf
                    <h3 style="margin:0 0 10px 0;font-size:16px">جدولة تركيب مجاني</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label>تاريخ التركيب *</label>
                            <input type="date" name="appointment_date" value="{{ now()->addDay()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>وقت التركيب *</label>
                            <input type="time" name="appointment_time" value="11:00" required>
                        </div>
                        <div class="field">
                            <label>الفرع</label>
                            <input name="branch_name" placeholder="الفرع الرئيسي">
                        </div>
                        <div class="field">
                            <label>الموقع</label>
                            <input name="location" value="{{ $client->location_text }}" placeholder="موقع التركيب">
                        </div>
                        <div class="field full">
                            <label style="font-weight:700">الفريق المكلف</label>
                            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px">
                                @foreach($teamUsers as $u)
                                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                                        <input type="checkbox" name="attendees[]" value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'checked' : '' }} style="width:16px;height:16px;accent-color:var(--nd-primary)">
                                        <span>{{ $u->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="field full">
                            <label>ملاحظات</label>
                            <textarea name="notes" placeholder="أي تجهيزات أو تفاصيل موقع مهمة"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">جدولة التركيب</button>
                </form>

                <form method="POST" action="{{ route('clients.installations.complete', $client->id) }}" id="sec-complete-installation" style="border-top:1px solid var(--nd-border);padding-top:16px">
                    @csrf
                    <h3 style="margin:0 0 10px 0;font-size:16px">إكمال التركيب المجاني</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label>موعد التركيب</label>
                            <select name="appointment_id">
                                <option value="">بدون موعد مسبق</option>
                                @foreach($activeInstallationAppointments as $appointment)
                                    <option value="{{ $appointment->id }}">#{{ $appointment->id }} · {{ $appointment->appointment_date->toDateString() }} · {{ $appointment->appointment_time }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>وقت الإكمال *</label>
                            <input type="datetime-local" name="installed_at" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                        <div class="field">
                            <label>المنفذ *</label>
                            <select name="installed_by">
                                @foreach($teamUsers as $u)
                                    <option value="{{ $u->id }}" @selected($u->id === auth()->id())>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>الفرع</label>
                            <input name="branch_name" placeholder="اسم الفرع الذي تم تركيبه">
                        </div>
                        <div class="field full">
                            <label>العناصر المركبة من الكتالوج</label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:6px">
                                @foreach($catalogServices as $service)
                                    <label style="display:flex;align-items:center;gap:8px;border:1px solid var(--nd-border);border-radius:8px;padding:8px">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}">
                                        <span>{{ $service->name_ar }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="field full">
                            <label>عناصر مخصصة</label>
                            <textarea name="custom_item_names" placeholder="كل عنصر في سطر مستقل"></textarea>
                        </div>
                        <div class="field">
                            <label>تاريخ متابعة ما بعد التركيب</label>
                            <input type="date" name="next_follow_up_date" value="{{ now()->addDays(3)->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>الإجراء القادم</label>
                            <input name="next_action" value="متابعة قرار العميل بعد التركيب">
                        </div>
                        <div class="field full">
                            <label style="display:inline-flex;align-items:center;gap:8px">
                                <input type="checkbox" name="no_follow_up" value="1">
                                <span>لا توجد متابعة فورية</span>
                            </label>
                            <input name="no_follow_up_reason" placeholder="سبب عدم جدولة متابعة" style="margin-top:8px">
                        </div>
                        <div class="field full">
                            <label>ملاحظات التركيب</label>
                            <textarea name="notes"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:12px" type="submit">تسجيل اكتمال التركيب</button>
                </form>

                <div class="list" style="margin-top:16px">
                    @forelse($installations as $installation)
                        <div class="list-row">
                            <span class="badge green">منجز</span>
                            <div class="list-main">
                                <strong>{{ $installation->installed_at->format('Y-m-d H:i') }}@if($installation->branch_name) · {{ $installation->branch_name }} @endif</strong>
                                <small>المنفذ: {{ optional($installation->installedBy)->name ?: 'غير محدد' }}</small>
                                <small>العناصر: {{ $installation->items->pluck('service_name_snapshot')->join('، ') }}</small>
                                @if($installation->appointment_id)
                                    <small>مرتبط بموعد #{{ $installation->appointment_id }}</small>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="padding:10px 0">لا يوجد تركيب منجز بعد.</div>
                    @endforelse
                </div>
            </div>
<div class="grid grid-2">
        {{-- Follow-ups List --}}
        <div class="card">
            <div class="section-head" style="margin-top:0">
                <h2>سجل المتابعات الدورية</h2>
                <span class="muted">{{ $followUps->count() }} متابعات</span>
            </div>
            <div class="list">
                @forelse($followUps as $fu)
                    <div class="list-row">
                        <span class="badge {{ \Carbon\Carbon::parse($fu->next_follow_up_date)->isPast() ? 'gold' : 'green' }}">
                            {{ $fu->method }}
                        </span>
                        <div class="list-main">
                            <strong>{{ $fu->reason }}</strong>
                            <small style="color:var(--nd-ink)">الخطوة التالية: <b>{{ $fu->next_action }}</b></small>
                            <small class="muted">
                                تاريخ المتابعة القادمة: {{ $fu->next_follow_up_date }}
                                @if($fu->result) · النتيجة: {{ $fu->result }} @endif
                            </small>
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:20px 0">لا توجد متابعات مسجلة لهذا العميل.</div>
                @endforelse
            </div>
        </div>

        {{-- Add Follow-up Form --}}
        <div class="card form-card" id="sec-follow-ups">
            <h3 style="margin-top:0">تسجيل متابعة جديدة</h3>
            <form method="POST" action="{{ route('clients.follow-ups.store', $client->id) }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label>وسيلة المتابعة *</label>
                        <select name="method" required>
                            <option value="phone_call">مكالمة هاتفية</option>
                            <option value="whatsapp">رسالة واتساب</option>
                            <option value="physical_visit">زيارة شخصية</option>
                            <option value="email">بريد إلكتروني</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>سبب / موضوع المتابعة *</label>
                        <input name="reason" required placeholder="متابعة قرار، استفسار عن العرض...">
                    </div>
                    <div class="field">
                        <label>الخطوة التالية المحددة *</label>
                        <input name="next_action" required placeholder="معاودة الاتصال، إرسال تجربة...">
                    </div>
                    <div class="field">
                        <label>تاريخ المتابعة القادم *</label>
                        <input type="date" name="next_follow_up_date" value="{{ now()->addDays(3)->toDateString() }}" required>
                    </div>
                    <div class="field full">
                        <label>نتيجة المتابعة الحالية</label>
                        <textarea name="result" placeholder="ما دار في الاتصال أو المتابعة"></textarea>
                    </div>
                    <div class="field full">
                        <label>ملاحظات إضافية</label>
                        <textarea name="notes" placeholder="ملاحظات للفريق"></textarea>
                    </div>
                </div>
                <button class="btn btn-primary" style="margin-top:14px" type="submit">حفظ المتابعة</button>
            </form>
        </div>
    </div>
    </div>
</div>
