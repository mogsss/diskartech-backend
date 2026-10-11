<div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 px-5 py-4 text-xs text-slate-500">
    <p>Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}</p>
    @if($paginator->hasPages())
        <nav class="flex flex-wrap items-center gap-3" aria-label="{{ $label }}">
            @if($paginator->onFirstPage())
                <span aria-disabled="true" class="text-stone-300">Previous</span>
            @else
                <a rel="prev" href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-stone-200 px-3 py-2 font-semibold hover:bg-stone-50">Previous</a>
            @endif
            <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
            @if($paginator->hasMorePages())
                <a rel="next" href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-stone-200 px-3 py-2 font-semibold hover:bg-stone-50">Next</a>
            @else
                <span aria-disabled="true" class="text-stone-300">Next</span>
            @endif
        </nav>
    @endif
</div>
