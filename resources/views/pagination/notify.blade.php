{{--
    Pagination (P12): Previous / Next everywhere; numbered pages from 768px when the total is known.
    Phone shows "Page X of Y" instead of a long number row. URLs come from the paginator, so search and
    filter query strings are kept (controllers use withQueryString()). No infinite scroll.
--}}
@if($paginator->hasPages())
    <nav class="notify-pagination" aria-label="{{ __('notify.ui.pagination') }}" data-pagination>
        @if($paginator->onFirstPage())
            <span class="notify-button notify-button--ghost notify-pagination__step is-disabled" aria-disabled="true">
                <x-notify.icon name="chevron-left" :size="18" class="notify-icon--directional" /><span>{{ __('notify.ui.previous') }}</span>
            </span>
        @else
            <a class="notify-button notify-button--ghost notify-pagination__step" href="{{ $paginator->previousPageUrl() }}" rel="prev" data-page-prev>
                <x-notify.icon name="chevron-left" :size="18" class="notify-icon--directional" /><span>{{ __('notify.ui.previous') }}</span>
            </a>
        @endif

        @if(method_exists($paginator, 'lastPage'))
            <span class="notify-pagination__status" dir="auto">{{ __('notify.ui.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
            <ul class="notify-pagination__pages" data-page-numbers>
                @foreach($elements as $element)
                    @if(is_string($element))
                        <li><span class="notify-pagination__gap" aria-hidden="true">…</span></li>
                    @elseif(is_array($element))
                        @foreach($element as $page => $url)
                            <li>
                                @if($page == $paginator->currentPage())
                                    <span class="notify-pagination__page is-current" aria-current="page" dir="ltr">{{ $page }}</span>
                                @else
                                    <a class="notify-pagination__page" href="{{ $url }}" aria-label="{{ __('notify.ui.go_to_page', ['page' => $page]) }}" dir="ltr">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            </ul>
        @endif

        @if($paginator->hasMorePages())
            <a class="notify-button notify-button--ghost notify-pagination__step" href="{{ $paginator->nextPageUrl() }}" rel="next" data-page-next>
                <span>{{ __('notify.ui.next') }}</span><x-notify.icon name="chevron-right" :size="18" class="notify-icon--directional" />
            </a>
        @else
            <span class="notify-button notify-button--ghost notify-pagination__step is-disabled" aria-disabled="true">
                <span>{{ __('notify.ui.next') }}</span><x-notify.icon name="chevron-right" :size="18" class="notify-icon--directional" />
            </span>
        @endif
    </nav>
@endif
