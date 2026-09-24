{{-- F. Contract (§8.3, §19.7): Preview and Download PDF; Issue on drafts for Founder (owner-level) only. --}}
@if($workspace->contracts)
<section class="notify-card notify-ws-card" aria-labelledby="client-contract-title" id="sec-contracts" data-client-contract>
    <header class="notify-ws-card__head">
        <h2 class="notify-ws-card__title" id="client-contract-title"><x-notify.icon name="file-chart" :size="18" /> {{ __('notify.client_hub.contract.title') }}</h2>
    </header>
    @foreach($workspace->contracts as $contract)
        <article class="notify-contract" data-contract="{{ $contract['id'] }}" data-contract-state="{{ $contract['is_draft'] ? 'draft' : $contract['status'] }}">
            <div class="notify-contract__top">
                @if($contract['is_draft'])
                    <span class="notify-status notify-status--warning"><x-notify.icon name="clipboard-list" :size="14" />{{ __('notify.client_hub.contract.draft') }}</span>
                @else
                    <strong class="notify-contract__number" dir="ltr">{{ $contract['number'] }}</strong>
                    <span class="notify-status notify-status--{{ $contract['status'] === 'issued' ? 'success' : 'neutral' }}">{{ $contract['status_label'] }}</span>
                @endif
            </div>
            <p class="notify-contract__meta">
                @if($contract['is_draft'])
                    {{ __('notify.client_hub.contract.draft_hint') }}
                @elseif($contract['issued_at'])
                    {{ __('notify.client_hub.contract.issued_on', ['date' => $contract['issued_at']]) }}
                @endif
            </p>
            <div class="notify-contract__actions">
                @if($contract['preview_url'])
                    <a class="notify-button notify-button--ghost notify-button--sm" href="{{ $contract['preview_url'] }}" target="_blank" rel="noopener">{{ __('notify.client_hub.actions.preview') }}</a>
                @endif
                @if($contract['pdf_url'])
                    <a class="notify-button notify-button--ghost notify-button--sm" href="{{ $contract['pdf_url'] }}">{{ __('notify.client_hub.actions.download_pdf') }}</a>
                @endif
                @if($contract['issue_url'])
                    <form method="POST" action="{{ $contract['issue_url'] }}" data-issue-contract>
                        @csrf
                        <button type="submit" class="notify-button notify-button--primary notify-button--sm">{{ __('notify.client_hub.actions.issue') }}</button>
                    </form>
                @endif
            </div>
        </article>
    @endforeach
</section>
@endif
