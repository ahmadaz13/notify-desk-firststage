<div id="tab-appointments" class="tab-pane" style="display:none">
    <div class="notify-section-intro">
        <div>
            <p class="notify-eyebrow">APPOINTMENTS_01</p>
            <h2>{{ __('notify.appointments.title') }}</h2>
            <small>{{ __('notify.appointments.subtitle') }}</small>
        </div>
        <span class="notify-badge notify-badge--info">{{ $appointments->count() }} مواعيد</span>
    </div>

    <div class="grid grid-2">
        {{-- Appointments List --}}
        <div class="card">
            <div class="section-head" style="margin-top:0">
                <h2>{{ __('notify.appointments.history') }}</h2>
                <span class="muted">{{ $appointments->count() }} مواعيد</span>
            </div>
            <div class="list">
                @forelse($appointments as $appointment)
                    @php
                        $isInstallationAppointment = $appointment->appointment_type === \App\Support\AppointmentTypes::INSTALLATION;
                    @endphp
                    <div class="list-row notify-action-row">
                        <span class="badge {{ $appointment->status === 'completed' ? 'green' : ($appointment->status === 'confirmed' ? 'green' : ($appointment->status === 'cancelled' ? 'red' : 'gold')) }}" @if($isInstallationAppointment) style="background:#e0f2fe;color:#0369a1" @endif>
                            {{ $appointment->status }}
                        </span>
                        <div class="list-main">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <strong>{{ $appointment->appointment_date }} · {{ $appointment->appointment_time }}</strong>
                                @if(isset($appointment->users) && $appointment->users->isNotEmpty())
                                    <div style="display:flex;gap:4px">
                                        @foreach($appointment->users as $attendee)
                                            <span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:11px">{{ $attendee->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <small>العميل: {{ $appointment->client?->business_name ?? $client->business_name }}</small>
                            <small>{{ $appointmentTypeLabels[$appointment->appointment_type] ?? $appointment->appointment_type }} · {{ $appointment->location ?: 'عن بُعد' }}@if($appointment->branch_name) · {{ $appointment->branch_name }} @endif</small>
                            @if($appointment->notes)
                                <small style="color:var(--nd-ink)">{{ $appointment->notes }}</small>
                            @endif
                            @if($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                                <form method="POST" action="{{ route('appointments.reschedule', $appointment->id) }}" class="form-grid" style="margin-top:10px">
                                    @csrf
                                    @method('PATCH')
                                    <div class="field">
                                        <label>تاريخ جديد</label>
                                        <input type="date" name="appointment_date" value="{{ $appointment->appointment_date->toDateString() }}" required>
                                    </div>
                                    <div class="field">
                                        <label>وقت جديد</label>
                                        <input type="time" name="appointment_time" value="{{ substr($appointment->appointment_time, 0, 5) }}" required>
                                    </div>
                                    <div class="field">
                                        <label>الموقع</label>
                                        <input name="location" value="{{ $appointment->location }}">
                                    </div>
                                    <div class="field">
                                        <label>الفرع</label>
                                        <input name="branch_name" value="{{ $appointment->branch_name }}">
                                    </div>
                                    <div class="field full">
                                        <button class="btn btn-soft" type="submit">إعادة الجدولة</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                        <div class="notify-action-row__actions">
                            @if($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                                @if($isInstallationAppointment)
                                    <form method="POST" action="{{ route('appointments.update', $appointment->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="cancelled">
                                        <select name="next_stage" style="min-width:130px;margin-bottom:6px">
                                            <option value="">المرحلة التالية</option>
                                            <option value="contacting">قيد التواصل</option>
                                            <option value="appointment">موعد</option>
                                            <option value="decision_pending">بانتظار القرار</option>
                                            <option value="prospect">فرصة جديدة</option>
                                        </select>
                                        <button class="btn btn-soft" type="submit">إلغاء التركيب</button>
                                    </form>
                                @else
                                    <a class="btn btn-primary" href="{{ route('appointments.outcome.create', $appointment->id) }}">
                                        تسجيل النتيجة
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="muted" style="padding:20px 0">لا توجد مواعيد مسجلة.</div>
                @endforelse
            </div>
        </div>

        {{-- Schedule New Appointment --}}
        <div class="card form-card">
            <h3 style="margin-top:0">جدولة موعد جديد</h3>
            <form method="POST" action="{{ route('appointments.store') }}">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <div class="form-grid">
                    <div class="field">
                        <label>تاريخ الموعد *</label>
                        <input type="date" name="appointment_date" value="{{ now()->addDay()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label>وقت الموعد *</label>
                        <input type="time" name="appointment_time" value="11:00" required>
                    </div>
                    <div class="field">
                        <label>نوع الموعد *</label>
                        <select name="appointment_type" required>
                            @foreach($appointmentTypeLabels as $type => $typeLabel)
                                <option value="{{ $type }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>المكان / الرابط</label>
                        <input name="location" placeholder="مقر العميل أو رابط الاجتماع">
                    </div>
                    <div class="field">
                        <label>الفرع</label>
                        <input name="branch_name" placeholder="اسم الفرع عند الحاجة">
                    </div>
                    <div class="field full">
                        <label style="font-weight:700">المسؤولون عن الموعد (الحضور)</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px">
                            @foreach($teamUsers ?? [] as $u)
                                <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                                    <input type="checkbox" name="attendees[]" value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'checked' : '' }} style="width:16px;height:16px;accent-color:var(--nd-primary)">
                                    <span>{{ $u->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="field full">
                        <label>ملاحظات الموعد</label>
                        <textarea name="notes" placeholder="هدف الاجتماع والمواضيع المراد مناقشتها"></textarea>
                    </div>
                </div>
                <button class="btn btn-primary" style="margin-top:14px" type="submit">حفظ وجدولة الموعد</button>
            </form>
        </div>
    </div>
</div>
