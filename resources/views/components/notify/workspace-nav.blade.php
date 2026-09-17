@props([
    'sections' => [],
])

<nav {{ $attributes->merge(['class' => 'notify-workspace-nav', 'aria-label' => 'Client workspace sections']) }}>
    @foreach($sections as $section)
        <button
            type="button"
            class="notify-workspace-nav__item tab-btn {{ $loop->first ? 'is-active btn-primary' : 'btn-ghost' }}"
            id="tab-btn-{{ $section['id'] }}"
            data-tab-target="{{ $section['id'] }}"
            onclick="switchTab('{{ $section['id'] }}')"
        >
            <span>{{ $section['label'] }}</span>
            @if(($section['count'] ?? null) !== null)
                <small>{{ $section['count'] }}</small>
            @endif
        </button>
    @endforeach
</nav>
