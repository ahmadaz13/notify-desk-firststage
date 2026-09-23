@props([
    'dailyNote' => null,
])

@php
    $businessNow = \Carbon\Carbon::now('Asia/Amman');
    $todayDate = $businessNow->toDateString();
    $userId = auth()->id() ?? 0;
    $initialContent = $dailyNote?->content ?? '';
    $storageKey = 'notify_daily_notes_' . $userId . '_' . $todayDate;
@endphp

<section
    class="notify-daily-notes"
    data-daily-notes
    x-data="{
        content: {{ Js::from($initialContent) }},
        status: 'idle',
        savedAt: '',
        errorMessage: '',
        pendingSave: false,
        storageKey: '{{ $storageKey }}',
        date: '{{ $todayDate }}',

        init() {
            const cached = localStorage.getItem(this.storageKey);
            if (cached && cached !== this.content) {
                this.content = cached;
                this.status = 'error';
                this.errorMessage = '{{ __('notify.daily_notes.restored_unsaved') ?: 'تم استرجاع مسودة غير محفوظة' }}';
            }
        },

        async save() {
            if (this.status === 'saving') {
                this.pendingSave = true;
                return;
            }
            this.status = 'saving';
            this.errorMessage = '';

            try {
                const response = await fetch('{{ route('daily-notes.save') }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        content: this.content,
                        date: this.date
                    })
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const data = await response.json();
                if (data.success) {
                    this.status = 'saved';
                    this.savedAt = data.saved_at_formatted || data.saved_at || '';
                    localStorage.removeItem(this.storageKey);
                } else {
                    throw new Error(data.message || 'Save failed');
                }
            } catch (err) {
                this.status = 'error';
                this.errorMessage = '{{ __('notify.daily_notes.save_failed') ?: 'فشل الحفظ. المسودة محفوظة محلياً.' }}';
                localStorage.setItem(this.storageKey, this.content);
            } finally {
                if (this.pendingSave) {
                    this.pendingSave = false;
                    this.save();
                }
            }
        }
    }"
    aria-labelledby="daily-notes-title"
>
    <header class="notify-daily-notes__header">
        <div class="notify-daily-notes__heading">
            <div class="notify-daily-notes__icon-title">
                <span class="notify-daily-notes__icon">
                    <x-notify.icon name="clipboard-list" :size="16" />
                </span>
                <h2 id="daily-notes-title" class="notify-daily-notes__title">{{ __('notify.daily_notes.title') ?: 'ملاحظات اليوم' }}</h2>
            </div>
            <span class="notify-daily-notes__date">{{ $businessNow->translatedFormat('l، d F') }}</span>
        </div>

        <div class="notify-daily-notes__status" aria-live="polite">
            <template x-if="status === 'saving'">
                <span class="notify-notes-badge notify-notes-badge--saving" data-notes-state="saving">
                    <span class="notify-notes-spinner" aria-hidden="true"></span>
                    <span>{{ __('notify.daily_notes.saving') ?: 'جاري الحفظ...' }}</span>
                </span>
            </template>

            <template x-if="status === 'saved'">
                <span class="notify-notes-badge notify-notes-badge--saved" data-notes-state="saved">
                    <x-notify.icon name="check" :size="13" />
                    <span>{{ __('notify.daily_notes.saved') ?: 'تم الحفظ' }} <time x-text="savedAt"></time></span>
                </span>
            </template>

            <template x-if="status === 'error'">
                <span class="notify-notes-badge notify-notes-badge--error" data-notes-state="error">
                    <x-notify.icon name="alert-circle" :size="13" />
                    <span x-text="errorMessage"></span>
                    <button type="button" class="notify-daily-notes__retry-btn" @click="save()" aria-label="{{ __('notify.actions.retry') ?: 'إعادة المحاولة' }}">
                        {{ __('notify.actions.retry') ?: 'إعادة المحاولة' }}
                    </button>
                </span>
            </template>
        </div>
    </header>

    <div class="notify-daily-notes__editor">
        <textarea
            x-model="content"
            @input.debounce.500ms="save()"
            class="notify-daily-notes__textarea"
            placeholder="{{ __('notify.daily_notes.placeholder') ?: 'اكتب ملاحظاتك اليومية هنا...' }}"
            rows="3"
            aria-label="{{ __('notify.daily_notes.title') ?: 'ملاحظات اليوم' }}"
            data-notes-editor
        >{{ $initialContent }}</textarea>
    </div>
</section>
