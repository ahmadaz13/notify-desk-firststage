<div id="tab-timeline" class="tab-pane" style="display:none">
    <section class="notify-panel">
        <div class="notify-section-head">
            <div>
                <p class="notify-eyebrow">Unified Timeline</p>
                <h2>{{ __('notify.clients.timeline_title') }}</h2>
            </div>
            <span class="notify-muted">{{ count($clientWorkspaceViewModel->timeline) }} أحداث ظاهرة</span>
        </div>

        <div class="notify-timeline">
            @forelse($clientWorkspaceViewModel->timeline as $event)
                <article class="notify-timeline__item notify-timeline__item--{{ $event['variant'] }}">
                    <span aria-hidden="true"></span>
                    <div>
                        <strong>{{ $event['description'] }}</strong>
                        <small>{{ $event['at'] }} · {{ $event['type'] }}</small>
                    </div>
                </article>
            @empty
                <div class="notify-empty-state notify-empty-state--compact">
                    <h3>{{ __('notify.clients.timeline_empty') }}</h3>
                </div>
            @endforelse
        </div>
    </section>
</div>
