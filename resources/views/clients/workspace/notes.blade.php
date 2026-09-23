<div id="tab-notes">
    <div class="notify-workspace-grid">
        <section class="notify-panel">
            <div class="notify-section-head">
                <div>
                    <p class="notify-eyebrow">Client Notes</p>
                    <h2>{{ __('notify.clients.notes_title') }}</h2>
                </div>
            </div>
            <div class="notify-note-box">
                {{ $clientWorkspaceViewModel->notes['client_note'] ?: __('notify.clients.notes_empty') }}
            </div>
        </section>

        <section class="notify-panel">
            <div class="notify-section-head">
                <div>
                    <p class="notify-eyebrow">Recent Contact Notes</p>
                    <h2>{{ __('notify.clients.recent_contact_notes') }}</h2>
                </div>
            </div>
            <div class="notify-list">
                @forelse($clientWorkspaceViewModel->notes['recent_attempts'] as $attempt)
                    <article class="notify-list-row">
                        <span class="notify-badge notify-badge--neutral">{{ $attempt['result'] }}</span>
                        <div>
                            <strong>{{ $attempt['method'] }}</strong>
                            <small>{{ $attempt['note'] }}</small>
                            @if($attempt['next_action'] || $attempt['next_follow_up_date'])
                                <small>التالي: {{ $attempt['next_action'] ?: 'غير محدد' }} @if($attempt['next_follow_up_date']) · {{ $attempt['next_follow_up_date'] }} @endif</small>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="notify-empty-state notify-empty-state--compact">
                        <h3>{{ __('notify.clients.recent_contact_empty') }}</h3>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
