<div id="tab-overview">
    <div class="notify-workspace-grid">
        <section class="notify-panel">
            <div class="notify-section-head">
                <div>
                    <p class="notify-eyebrow">CLIENT_DETAIL_01</p>
                    <h2>{{ __('notify.clients.overview') }}</h2>
                </div>
                <span class="notify-badge notify-badge--{{ $clientWorkspaceViewModel->header['stage_variant'] }}">
                    {{ $clientWorkspaceViewModel->header['stage_label'] }}
                </span>
            </div>

            <dl class="notify-fact-grid">
                @foreach($clientWorkspaceViewModel->overview as $fact)
                    <div>
                        <dt>{{ $fact['label'] }}</dt>
                        <dd @class(['ltr' => $fact['ltr'] ?? false])>{{ $fact['value'] ?: 'غير محدد' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="notify-panel" id="sec-close-client">
            <div class="notify-section-head">
                <div>
                    <p class="notify-eyebrow">Lifecycle</p>
                    <h2>{{ __('notify.clients.stage') }}</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('clients.stage.update', $client->id) }}" class="notify-workspace-form">
                @csrf
                @method('PATCH')
                <div class="notify-form-grid">
                    <label class="field">
                        <span>{{ __('notify.clients.current_stage') }}</span>
                        <select name="stage" required>
                            @foreach($lifecycleStages as $stageOption)
                                <option value="{{ $stageOption }}" @selected($clientWorkspaceViewModel->header['stage'] === $stageOption)>{{ $lifecycleLabels[$stageOption] ?? $stageOption }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>{{ __('notify.clients.closed_reason') }}</span>
                        <input name="closed_reason" value="{{ old('closed_reason', $client->closed_reason) }}" placeholder="{{ __('notify.clients.closed_reason_placeholder') }}">
                    </label>
                </div>
                <button class="notify-button notify-button--primary" type="submit">
                    <span class="notify-button__label">{{ __('notify.clients.update_stage') }}</span>
                </button>
            </form>
        </section>
    </div>
</div>
