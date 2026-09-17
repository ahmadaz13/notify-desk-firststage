@props([
    'client',
    'viewModel',
    'teamUsers' => collect(),
])

<div class="notify-contact-outcome" data-contact-outcome-root>
    <button type="button" class="notify-button notify-button--primary" data-contact-outcome-open>
        <span class="notify-button__label">تسجيل نتيجة التواصل</span>
    </button>

    <div class="notify-contact-outcome__overlay" data-contact-outcome-overlay hidden></div>
    <aside class="notify-contact-outcome__panel" data-contact-outcome-panel hidden aria-label="تسجيل نتيجة التواصل">
        <div class="notify-contact-outcome__head">
            <div>
                <p class="notify-eyebrow">CONTACT_01</p>
                <h2>تسجيل نتيجة التواصل</h2>
            </div>
            <button type="button" class="notify-icon-button" data-contact-outcome-close aria-label="إغلاق">×</button>
        </div>

        <form method="POST" action="{{ route('clients.contact-attempts.store', $client->id) }}" class="notify-contact-outcome__form">
            @csrf

            <div class="notify-form-grid">
                <label class="field">
                    <span>طريقة التواصل *</span>
                    <select name="method" required>
                        @foreach($viewModel->methods as $value => $label)
                            <option value="{{ $value }}" @selected($viewModel->defaultMethod === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span>الإجراء القادم</span>
                    <input name="next_action" value="{{ old('next_action') }}" placeholder="اتصال لاحق، إرسال عرض...">
                </label>
            </div>

            <fieldset class="notify-outcome-options">
                <legend>النتيجة *</legend>
                @foreach($viewModel->outcomes as $outcome)
                    <label class="notify-outcome-option" data-outcome-option="{{ $outcome['value'] }}">
                        <input
                            type="radio"
                            name="result"
                            value="{{ $outcome['value'] }}"
                            @checked(old('result', $viewModel->defaultOutcome) === $outcome['value'])
                            required
                        >
                        <span>
                            <strong>{{ $outcome['label'] }}</strong>
                            <small>{{ $outcome['summary'] }}</small>
                        </span>
                    </label>
                @endforeach
            </fieldset>

            <div class="notify-outcome-fields" data-outcome-fields="appointment" hidden>
                <div class="notify-form-grid">
                    <label class="field">
                        <span>تاريخ الموعد *</span>
                        <input type="date" name="appointment_date" value="{{ old('appointment_date', now()->addDay()->toDateString()) }}">
                    </label>
                    <label class="field">
                        <span>وقت الموعد *</span>
                        <input type="time" name="appointment_time" value="{{ old('appointment_time', '11:00') }}">
                    </label>
                    <label class="field">
                        <span>نوع الموعد</span>
                        <select name="appointment_type">
                            @foreach($viewModel->appointmentTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>الموقع</span>
                        <input name="location" value="{{ old('location', $client->location_text) }}">
                    </label>
                    <label class="field full">
                        <span>الفريق المكلف</span>
                        <span class="notify-checkbox-grid">
                            @foreach($teamUsers as $user)
                                <label>
                                    <input type="checkbox" name="attendees[]" value="{{ $user->id }}" @checked($user->id === auth()->id())>
                                    <span>{{ $user->name }}</span>
                                </label>
                            @endforeach
                        </span>
                    </label>
                    <label class="field full">
                        <span>ملاحظات الموعد</span>
                        <textarea name="appointment_notes">{{ old('appointment_notes') }}</textarea>
                    </label>
                </div>
            </div>

            <div class="notify-outcome-fields" data-outcome-fields="callback_later" hidden>
                <label class="field">
                    <span>وقت معاودة الاتصال *</span>
                    <input type="datetime-local" name="follow_up_date_time" value="{{ old('follow_up_date_time', now()->addDay()->setTime(10, 0)->format('Y-m-d\\TH:i')) }}">
                </label>
            </div>

            <div class="notify-outcome-review" data-outcome-fields="wrong_invalid" hidden>
                <strong>ستظهر هذه النتيجة كمراجعة تشغيلية.</strong>
                <span>سيبقى العميل نشطاً، ويحتاج الفريق إلى مراجعة بيانات التواصل قبل أي إغلاق صريح.</span>
            </div>

            <div class="notify-outcome-review" data-outcome-fields="not_interested" hidden>
                <strong>مراجعة عدم الاهتمام مطلوبة.</strong>
                <span>الملاحظة إلزامية هنا. لن يتم إغلاق العميل تلقائياً من نتيجة التواصل.</span>
            </div>

            <label class="field full">
                <span>ملاحظة التواصل <em data-note-required hidden>*</em></span>
                <textarea name="note" data-contact-note>{{ old('note') }}</textarea>
            </label>

            <div class="notify-contact-outcome__footer">
                <button type="button" class="notify-button notify-button--ghost" data-contact-outcome-close>
                    <span class="notify-button__label">إلغاء</span>
                </button>
                <button class="notify-button notify-button--primary" type="submit">
                    <span class="notify-button__label">حفظ النتيجة</span>
                </button>
            </div>
        </form>
    </aside>
</div>
