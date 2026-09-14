@if($paginator->hasPages())
    <nav class="finance-pagination" aria-label="Páginas dos lançamentos">
        @if($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="Página anterior">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior">‹</a>
        @endif
        @foreach($elements as $element)
            @if(is_string($element))
                <span aria-hidden="true">{{ $element }}</span>
            @else
                @foreach($element as $page => $url)
                    @if($page == $paginator->currentPage())
                        <span aria-current="page" aria-label="Página {{ $page }}">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="Ir para a página {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Próxima página">›</a>
        @else
            <span aria-disabled="true" aria-label="Próxima página">›</span>
        @endif
    </nav>
@endif
