<div id="tab-contacts">
    <div class="notify-workspace-grid">
        <section class="notify-panel">
            <div class="notify-section-head">
                <div>
                    <p class="notify-eyebrow">Contacts</p>
                    <h2>{{ __('notify.clients.contacts') }}</h2>
                </div>
                <span class="notify-muted">{{ count($clientWorkspaceViewModel->contacts) }} جهات</span>
            </div>

            <div class="notify-list">
                @forelse($clientWorkspaceViewModel->contacts as $contact)
                    <article class="notify-list-row">
                        <span class="notify-badge notify-badge--{{ $contact['is_primary'] ? 'success' : 'neutral' }}">
                            {{ $contact['is_primary'] ? __('notify.clients.contact_model.primary_badge') : __('notify.clients.contact_model.additional_badge') }}
                        </span>
                        <div>
                            <strong>{{ $contact['name'] }}</strong>
                            <small>
                                {{ $contact['role'] }}
                                @if($contact['primary_phone']) · <span class="ltr">{{ $contact['primary_phone'] }}</span>@endif
                                @if($contact['secondary_phone'] ?? null) · <span class="ltr">{{ $contact['secondary_phone'] }}</span>@endif
                                @if($contact['whatsapp_number']) · واتساب <span class="ltr">{{ $contact['whatsapp_number'] }}</span>@endif
                                @if($contact['email'] ?? null) · <span class="ltr">{{ $contact['email'] }}</span>@endif
                            </small>
                            @if($contact['preferred_contact_method'])
                                <small>المفضل: {{ $contact['preferred_contact_method'] }}</small>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="notify-empty-state notify-empty-state--compact">
                        <h3>{{ __('notify.clients.contact_model.no_primary_contact') }}</h3>
                        <a class="notify-button notify-button--soft notify-button--sm" href="{{ route('clients.edit', $client->id) }}">{{ __('notify.clients.contact_model.add_contact_details') }}</a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="notify-panel">
            <div class="notify-section-head">
                <div>
                    <p class="notify-eyebrow">Add Contact</p>
                    <h2>{{ __('notify.clients.add_contact') }}</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('clients.contacts.store', $client->id) }}" class="notify-workspace-form">
                @csrf
                <div class="notify-form-grid">
                    <label class="field">
                        <span>{{ __('notify.clients.contact_model.contact_name') }} <span class="notify-label-note">({{ __('notify.common.optional') }})</span></span>
                        <input name="name" maxlength="255" autocomplete="name">
                    </label>
                    <label class="field">
                        <span>{{ __('notify.clients.role') }}</span>
                        <input name="role" placeholder="مالك، صاحب قرار، مدير، أو صفة أخرى">
                    </label>
                    <label class="field">
                        <span>الجوال المباشر</span>
                        <input name="primary_phone" class="ltr">
                    </label>
                    <label class="field">
                        <span>واتساب</span>
                        <input name="whatsapp_number" class="ltr">
                    </label>
                    <label class="field">
                        <span>{{ __('notify.clients.contact_model.contact_email') }}</span>
                        <input name="email" type="email" inputmode="email" class="ltr" maxlength="255">
                    </label>
                    <label class="field">
                        <span>{{ __('notify.clients.preferred_method') }}</span>
                        <input name="preferred_contact_method" placeholder="اتصال، واتساب، بريد">
                    </label>
                    <label class="notify-inline-check">
                        <input type="checkbox" name="is_primary" value="1">
                        <span>جهة الاتصال الأساسية للتواصل التشغيلي</span>
                    </label>
                </div>
                <button class="notify-button notify-button--soft" type="submit">
                    <span class="notify-button__label">{{ __('notify.clients.add_contact') }}</span>
                </button>
            </form>
        </section>

        <section class="notify-panel notify-panel--wide">
            <div class="notify-section-head">
                <div>
                    <p class="notify-eyebrow">CONTACT_01</p>
                    <h2>نتائج التواصل</h2>
                </div>
                <span class="notify-muted">{{ $contactAttempts->count() }} محاولات</span>
            </div>

            <x-notify.contact-outcome :client="$client" :view-model="$contactOutcomeViewModel" :team-users="$teamUsers" />
        </section>
    </div>
</div>
