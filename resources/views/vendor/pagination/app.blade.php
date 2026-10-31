@if ($paginator->total() > 0)
    <div class="pagination">
        <span>Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}</span>
        @if ($paginator->hasPages())
            <nav class="pages" aria-label="Navigasi halaman">
                @if ($paginator->onFirstPage())
                    <button class="pg" disabled aria-label="Sebelumnya">‹</button>
                @else
                    <a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya">‹</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pg" aria-disabled="true">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pg active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pg" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya">›</a>
                @else
                    <button class="pg" disabled aria-label="Berikutnya">›</button>
                @endif
            </nav>
        @endif
    </div>
@endif
