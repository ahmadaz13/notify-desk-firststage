@props([
    'dailyNote' => null,
])

{{--
    Personal Daily Notes (P11): one note per user per operational day (Asia/Amman), auto-saved
    ~500ms after typing stops. Only the signed-in user's note is ever loaded or written.
--}}
@php
    $businessNow = \App\Support\OperationalTime::now();
    $todayDate = $businessNow->toDateString();
    $userId = auth()->id() ?? 0;
    $initialContent = $dailyNote?->content ?? '';
    $storageKey = 'notify_daily_notes_' . $userId . '_' . $todayDate;
@endphp

<section
    class="notify-card notify-daily-notes"
    id="daily-notes"
    data-daily-notes
    x-data="{
        content: {{ Js::from($initialContent) }},
        status: 'idle',
        savedAt: '',
        errorMessage: '',
        pendingSave: false,
        storageKey: '{{ $storageKey }}',
        date: '{{ $todayDate }}',

        readDraft() {
            try { return window.localStorage.getItem(this.storageKey); } catch (e) { return null; }
        },
        writeDraft(value) {
            try { value === null ? window.localStorage.removeItem(this.storageKey) : window.localStorage.setItem(this.storageKey, value); } catch (e) {}
        },

        init() {
            const cached = this.readDraft();
            if (cached !== null && cached !== this.content) {
                this.content = cached;
                this.status = 'error';
                this.errorMessage = {{ Js::from(__('notify.daily_notes.restored_unsaved')) }};
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
                if (!data.success) {
                    throw new Error(data.message || 'Save failed');
                }
                this.status = 'saved';
                this.savedAt = data.saved_at_formatted || data.saved_at || '';
                this.writeDraft(null);
            } catch (err) {
                this.status = 'error';
                this.errorMessage = {{ Js::from(__('notify.daily_notes.save_failed')) }};
                this.writeDraft(this.content);
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
            <h2 id="daily-notes-title" class="notify-daily-notes__title">
                <x-notify.icon name="book-open" :size="18" />
                <span>{{ __('notify.daily_notes.title') }}</span>
            </h2>
            <p class="notify-daily-notes__hint" id="daily-notes-hint">{{ __('notify.daily_notes.personal') }}</p>
        </div>

        <div class="notify-daily-notes__status" aria-live="polite" aria-atomic="true">
            <template x-if="status === 'saving'">
                <span class="notify-notes-badge notify-notes-badge--saving" data-notes-state="saving">
                    <span class="notify-notes-spinner" aria-hidden="true"></span>
                    <span>{{ __('notify.daily_notes.saving') }}</span>
                </span>
            </template>

            <template x-if="status === 'saved'">
                <span class="notify-notes-badge notify-notes-badge--saved" data-notes-state="saved">
                    <x-notify.icon name="check" :size="13" />
                    <span>{{ __('notify.daily_notes.saved') }} <time dir="ltr" x-text="savedAt"></time></span>
                </span>
            </template>

            <template x-if="status === 'error'">
                <span class="notify-notes-badge notify-notes-badge--error" data-notes-state="error" role="alert">
                    <x-notify.icon name="alert-circle" :size="13" />
                    <span x-text="errorMessage"></span>
                    <button type="button" class="notify-daily-notes__retry-btn" @click="save()">
                        {{ __('notify.actions.retry') }}
                    </button>
                </span>
            </template>
        </div>
    </header>

    <textarea
        x-model="content"
        @input.debounce.500ms="save()"
        class="notify-daily-notes__textarea"
        placeholder="{{ __('notify.daily_notes.placeholder') }}"
        rows="3"
        maxlength="5000"
        aria-labelledby="daily-notes-title"
        aria-describedby="daily-notes-hint"
        data-notes-editor
    >{{ $initialContent }}</textarea>
</section>
