{{-- Pagination numerik khusus TTE-Ticketing, tanpa dependensi Tailwind. --}}
@if ($paginator->hasPages())
    <nav class="tte-pagination" role="navigation" aria-label="Navigasi halaman">
        <p class="tte-pagination-summary">
            Menampilkan <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            dari <strong>{{ number_format($paginator->total(), 0, ',', '.') }}</strong> data
        </p>

        <div class="tte-pagination-pages">
            @if ($paginator->onFirstPage())
                <span class="tte-page-item tte-page-arrow" aria-disabled="true" aria-label="Halaman sebelumnya">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                </span>
            @else
                <a class="tte-page-item tte-page-arrow" href="{{ $paginator->previousPageUrl() }}"
                   rel="prev" aria-label="Halaman sebelumnya">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="tte-page-dots" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="tte-page-item is-active" aria-current="page"
                                  aria-label="Halaman {{ $page }} saat ini">{{ $page }}</span>
                        @else
                            <a class="tte-page-item" href="{{ $url }}"
                               aria-label="Buka halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="tte-page-item tte-page-arrow" href="{{ $paginator->nextPageUrl() }}"
                   rel="next" aria-label="Halaman berikutnya">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                </a>
            @else
                <span class="tte-page-item tte-page-arrow" aria-disabled="true" aria-label="Halaman berikutnya">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
