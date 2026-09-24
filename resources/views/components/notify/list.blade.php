@props([
    'columns' => [],
    'label' => null,
    'empty' => false,
])

{{--
    Business list (P12, §21): rows are written once in the feature view as <tr>/<td>; from 768px they
    render as a real table (≤5 content columns + actions), below 768px each row becomes a card.
    Cell roles (classes on <td>):
      notify-list__primary  title cell, first line of the card
      notify-list__end      status / amount, top end of the card
      notify-list__actions  one primary action + optional "…" menu, card footer
      (other cells)         secondary details; give them data-label="…" so the card shows the label
    Columns: ['Label', ['label' => 'Amount', 'class' => 'is-num'], ['label' => 'Actions', 'hidden' => true]].
    Slots: toolbar, empty (shown when `empty` is true), footer (pagination).
--}}
<div {{ $attributes->merge(['class' => 'notify-list']) }} data-list>
    @isset($toolbar)
        <div class="notify-list__toolbar">{{ $toolbar }}</div>
    @endisset

    @if($empty)
        {{ $emptyState ?? '' }}
    @else
        <table class="notify-list__table" @if($label) aria-label="{{ $label }}" @endif>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        @php($column = is_array($column) ? $column : ['label' => $column])
                        <th scope="col" @class([$column['class'] ?? null])>
                            @if($column['hidden'] ?? false)
                                <span class="notify-visually-hidden">{{ $column['label'] }}</span>
                            @else
                                {{ $column['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    @endif

    @isset($footer)
        <div class="notify-list__footer">{{ $footer }}</div>
    @endisset
</div>
