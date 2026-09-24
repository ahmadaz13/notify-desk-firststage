@extends('layouts.app')

{{--
    Import (§15.2, P13): owner-only CSV prospect import with preview → confirm. Nothing is written until
    the owner confirms the previewed valid rows. It never creates subscriptions, invoices or payments;
    the production import of existing subscribers is a separate controlled launch task (§33a).
--}}
@php
    $preview ??= null;
    $validCount = $preview ? count($preview['valid']) : 0;
    $duplicateCount = $preview ? count($preview['duplicates']) : 0;
    $invalidCount = $preview ? count($preview['invalid']) : 0;
    $csv = app(\App\Services\CsvImportService::class);
@endphp

@section('content')
<div class="notify-admin notify-admin--medium" data-admin-page="import">
    <x-notify.page-header :title="__('notify.import.title')" :description="__('notify.import.subtitle')">
        <x-slot:actions>
            <x-notify.button :href="route('clients.import.template', 'prospects')" variant="secondary" icon="upload" data-import-template>{{ __('notify.import.download_template') }}</x-notify.button>
        </x-slot:actions>
    </x-notify.page-header>

    <section class="notify-admin-card" aria-labelledby="import-upload-title" data-import-upload>
        <div class="notify-admin-card__head">
            <div>
                <h2 id="import-upload-title" class="notify-admin-card__title">{{ __('notify.import.upload_title') }}</h2>
                <p class="notify-admin-card__desc">{{ __('notify.import.upload_desc') }}</p>
            </div>
        </div>
        <ol class="notify-admin-steps">
            <li>{{ __('notify.import.steps.template') }}</li>
            <li>{{ __('notify.import.steps.fill') }}</li>
            <li>{{ __('notify.import.steps.preview') }}</li>
            <li>{{ __('notify.import.steps.confirm') }}</li>
        </ol>
        <form method="POST" action="{{ route('clients.import.preview') }}" enctype="multipart/form-data" class="notify-admin-form">
            @csrf
            <input type="hidden" name="type" value="prospect">
            <x-notify.form-field :label="__('notify.import.file_label')" for="import-file" name="csv_file" :required="true" :hint="__('notify.import.file_hint')">
                <input id="import-file" class="notify-input notify-admin-file" type="file" name="csv_file" accept=".csv,text/csv" required @invalid('csv_file', 'import-file', 'default', true)>
            </x-notify.form-field>
            <div class="notify-form-actions">
                <button class="notify-button notify-button--primary" type="submit" data-import-preview>{{ __('notify.import.preview_action') }}</button>
            </div>
        </form>
    </section>

    <details class="notify-admin-card notify-admin-details" @if(! $preview) open @endif>
        <summary class="notify-admin-details__summary">{{ __('notify.import.format_title') }}</summary>
        <ul class="notify-admin-bullets">
            <li>{!! __('notify.import.format.required', ['columns' => '<code dir="ltr">business_name, phone, city_area, business_category</code>']) !!}</li>
            <li>{!! __('notify.import.format.phone_type', ['column' => '<code dir="ltr">primary_phone_type</code>', 'values' => '<code dir="ltr">business · owner · manager</code>']) !!}</li>
            <li>{!! __('notify.import.format.contact', ['columns' => '<code dir="ltr">contact_name, contact_role, contact_phone, contact_whatsapp, contact_email</code>']) !!}</li>
            <li>{{ __('notify.import.format.duplicates') }}</li>
            <li>{{ __('notify.import.format.scope') }}</li>
        </ul>
    </details>

    @if($preview)
        <section class="notify-admin-card" aria-labelledby="import-preview-title" data-import-result>
            <div class="notify-admin-card__head">
                <div>
                    <h2 id="import-preview-title" class="notify-admin-card__title">{{ __('notify.import.preview_title') }}</h2>
                    <p class="notify-admin-card__desc">{{ trans_choice('notify.import.rows_read', $preview['total'], ['count' => $preview['total']]) }}</p>
                </div>
            </div>
            <div class="notify-admin-summary" role="list">
                <span class="notify-status notify-status--success" role="listitem" data-import-count="valid"><x-notify.icon name="check-circle" :size="14" />{{ trans_choice('notify.import.summary.valid', $validCount, ['count' => $validCount]) }}</span>
                <span class="notify-status notify-status--warning" role="listitem" data-import-count="duplicates">{{ trans_choice('notify.import.summary.duplicates', $duplicateCount, ['count' => $duplicateCount]) }}</span>
                <span class="notify-status notify-status--danger" role="listitem" data-import-count="invalid"><x-notify.icon name="alert-circle" :size="14" />{{ trans_choice('notify.import.summary.invalid', $invalidCount, ['count' => $invalidCount]) }}</span>
            </div>

            @if($validCount > 0)
                <div class="notify-admin-confirm">
                    <p>{{ trans_choice('notify.import.confirm_hint', $validCount, ['count' => $validCount]) }}</p>
                    <form method="POST" action="{{ route('clients.import.confirm') }}">
                        @csrf
                        <button class="notify-button notify-button--primary" type="submit" data-import-confirm>{{ trans_choice('notify.import.confirm_action', $validCount, ['count' => $validCount]) }}</button>
                    </form>
                </div>

                <h3 class="notify-admin-subtitle">{{ __('notify.import.valid_title') }}</h3>
                <x-notify.list :label="__('notify.import.valid_title')"
                    :columns="[__('notify.import.columns.business'), __('notify.import.columns.phone'), __('notify.import.columns.area'), __('notify.import.columns.category'), __('notify.import.columns.row')]">
                    @foreach($preview['valid'] as $row)
                        <tr data-import-row="valid">
                            <td class="notify-list__primary">
                                <span class="notify-list__title">{{ $row['data']['business_name'] ?? '' }}</span>
                                @php $contact = ($row['data']['contact_name'] ?? '') ?: ($row['data']['contact_person'] ?? ''); @endphp
                                <span class="notify-list__sub">{{ $contact !== '' ? $contact.' · ' : '' }}{{ __('notify.clients.contact_model.phone_types.'.($csv->primaryPhoneType($row['data']) ?? 'business')) }}</span>
                            </td>
                            <td data-label="{{ __('notify.import.columns.phone') }}"><span dir="ltr">{{ $row['data']['phone'] ?? '' }}</span></td>
                            <td data-label="{{ __('notify.import.columns.area') }}">{{ $row['data']['city_area'] ?? '' }}</td>
                            <td data-label="{{ __('notify.import.columns.category') }}">{{ $row['data']['business_category'] ?? '' }}</td>
                            <td class="notify-list__end"><span class="notify-admin-muted" dir="ltr">#{{ $row['row'] }}</span></td>
                        </tr>
                    @endforeach
                </x-notify.list>
            @else
                <x-notify.empty-state :compact="true" icon="alert-circle" :title="__('notify.import.nothing_valid')" />
            @endif

            @foreach(['duplicates' => 'warning', 'invalid' => 'danger'] as $group => $tone)
                @if(count($preview[$group]) > 0)
                    <h3 class="notify-admin-subtitle">{{ __('notify.import.'.$group.'_title') }}</h3>
                    <x-notify.list :label="__('notify.import.'.$group.'_title')"
                        :columns="[__('notify.import.columns.business'), __('notify.import.columns.phone'), __('notify.import.columns.reason'), __('notify.import.columns.row')]">
                        @foreach($preview[$group] as $row)
                            <tr data-import-row="{{ $group }}">
                                <td class="notify-list__primary"><span class="notify-list__title">{{ ($row['data']['business_name'] ?? '') ?: __('notify.import.not_set') }}</span></td>
                                <td data-label="{{ __('notify.import.columns.phone') }}"><span dir="ltr">{{ ($row['data']['phone'] ?? '') ?: '—' }}</span></td>
                                <td data-label="{{ __('notify.import.columns.reason') }}"><span class="notify-status notify-status--{{ $tone }}">{{ $row['reason'] }}</span></td>
                                <td class="notify-list__end"><span class="notify-admin-muted" dir="ltr">#{{ $row['row'] }}</span></td>
                            </tr>
                        @endforeach
                    </x-notify.list>
                @endif
            @endforeach
        </section>
    @endif
</div>
@endsection
