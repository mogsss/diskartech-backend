<x-admin-layout>
    @push('header-title')
        Ratings & reviews
    @endpush
    <div class="mx-auto w-full max-w-7xl space-y-6">
        <section>
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-red-700">Community feedback</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Ratings & reviews</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">See feedback submitted by students, employers, and households for their job contracts.</p>
        </section>
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif
        <section class="grid gap-3 sm:grid-cols-3 sm:gap-4" aria-label="Overall review totals">
            <a href="{{ route('admin.reviews') }}" class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm hover:border-red-200">
                <p class="text-sm text-slate-500">Total reviews</p>
                <p class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($totalReviews) }}</p>
                <p class="mt-2 text-xs text-slate-500">Official contract feedback</p>
            </a>
            <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Average rating</p>
                <p class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ $averageRating === null ? '—' : number_format($averageRating, 1) }} <span class="text-base font-medium text-slate-400">/ 5</span></p>
                <p class="mt-2 text-xs text-slate-500">{{ $totalReviews ? 'Across all submitted reviews' : 'No ratings submitted yet' }}</p>
            </div>
            <a href="{{ route('admin.reviews', ['rating' => 'low']) }}" class="rounded-2xl border border-rose-100 bg-rose-50/50 p-5 shadow-sm hover:border-rose-300">
                <p class="text-sm text-rose-700">Low ratings</p>
                <p class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($lowRatingCount) }}</p>
                <p class="mt-2 text-xs text-slate-500">1–2 stars · view feedback</p>
            </a>
        </section>
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_260px]">
            <div class="min-w-0 space-y-4">
                <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-5">
                    <form method="GET" action="{{ route('admin.reviews') }}" x-ref="reviewFilters"
                        x-data="{ searchTimer: null, submitFilters() { clearTimeout(this.searchTimer); this.$refs.reviewFilters.requestSubmit(); }, scheduleSearch(event) { clearTimeout(this.searchTimer); if (!event.isComposing) this.searchTimer = setTimeout(() => this.submitFilters(), 600); }, destroy() { clearTimeout(this.searchTimer); } }"
                        @submit="clearTimeout(searchTimer)" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_150px_160px]">
                        <div class="sm:col-span-2 lg:col-span-1">
                            <label for="review-search" class="mb-2 block text-xs font-semibold text-slate-600">Search feedback</label>
                            <input id="review-search" name="search" type="search" maxlength="120" value="{{ $search }}" @input="scheduleSearch($event)" @compositionend="scheduleSearch($event)" placeholder="Name, job, or feedback" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                        </div>
                        <div>
                            <label for="review-rating" class="mb-2 block text-xs font-semibold text-slate-600">Rating</label>
                            <select id="review-rating" name="rating" @change="submitFilters()" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                                @foreach(['all' => 'All ratings', 'low' => '1–2 stars', '5' => '5 stars', '4' => '4 stars', '3' => '3 stars', '2' => '2 stars', '1' => '1 star'] as $value => $label)
                                    <option value="{{ $value }}" @selected((string) $rating === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="review-role" class="mb-2 block text-xs font-semibold text-slate-600">Rated account</label>
                            <select id="review-role" name="role" @change="submitFilters()" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                                @foreach(['all' => 'All account types', 'student' => 'Student', 'employer' => 'Employer', 'household' => 'Household'] as $value => $label)
                                    <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <noscript><button type="submit" class="min-h-[44px] rounded-xl bg-red-700 px-4 text-sm font-semibold text-white">Apply filters</button></noscript>
                    </form>
                </section>
                <div class="flex flex-wrap items-center justify-between gap-2 px-1 text-xs text-slate-500">
                    <p>{{ number_format($reviews->total()) }} matching reviews · newest first</p>
                    <a href="{{ route('admin.reviews') }}" class="font-semibold text-red-700 hover:underline">Reset filters</a>
                </div>
                @php
                    $accountName = function ($user) {
                        if (!$user) return 'Account no longer available';
                        return match ($user->role) {
                            'student' => $user->studentProfile?->student_name,
                            'employer' => $user->employerProfile?->employer_name ?: $user->employerProfile?->hirer_name,
                            'household' => $user->householdProfile?->household_name,
                            default => null,
                        } ?: $user->email;
                    };
                @endphp
                @forelse($reviews as $review)
                    <article data-review-id="{{ $review->id }}" class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex gap-0.5 text-amber-500" role="img" aria-label="{{ $review->rating }} out of 5 stars">
                                        @for($star = 1; $star <= 5; $star++)
                                            <svg class="h-4 w-4 {{ $star <= $review->rating ? 'fill-current' : 'fill-stone-200 text-stone-200' }}" aria-hidden="true" viewBox="0 0 24 24"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                        @endfor
                                    </span>
                                    <span class="text-sm font-bold text-slate-900">{{ $review->rating }}/5</span>
                                    @if($review->rating <= 2)<span class="rounded-lg bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">Low rating</span>@endif
                                </div>
                                <h2 class="mt-3 break-words text-base font-bold text-slate-900">{{ $review->application?->job?->title ?: 'Job no longer available' }}</h2>
                                <p class="mt-1 text-xs text-slate-400">Contract #{{ $review->application_id }} · Review #{{ $review->id }}</p>
                            </div>
                            <time datetime="{{ $review->created_at?->toIso8601String() }}" class="text-xs text-slate-500">{{ $review->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') ?: 'Date not recorded' }} PHT</time>
                        </div>
                        <div class="mt-5 grid gap-4 rounded-xl bg-stone-50 p-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold text-slate-400">Reviewed by</p>
                                <p class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $accountName($review->reviewer) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ ucfirst($review->reviewer?->role ?? 'Unavailable') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-400">Rated account</p>
                                <p class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $accountName($review->reviewee) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ ucfirst($review->reviewee?->role ?? 'Unavailable') }}</p>
                            </div>
                        </div>
                        <p class="mt-5 whitespace-pre-wrap break-words text-sm leading-7 {{ filled($review->feedback) ? 'text-slate-700' : 'text-slate-400' }}">{{ filled($review->feedback) ? $review->feedback : 'No written feedback provided.' }}</p>
                        @if(is_array($review->tags) && count($review->tags))
                            <div class="mt-4 flex flex-wrap gap-2" aria-label="Feedback tags">
                                @foreach($review->tags as $tag)
                                    @if(is_string($tag) && filled($tag))<span class="max-w-full break-words rounded-lg border border-stone-200 px-2.5 py-1 text-xs text-slate-600">{{ $tag }}</span>@endif
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-stone-200 bg-white px-5 py-14 text-center">
                        <p class="text-base font-semibold text-slate-900">{{ $totalReviews ? 'No matching reviews' : 'No reviews yet' }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ $totalReviews ? 'Try another rating, account type, or search.' : 'Reviews will appear here when someone submits feedback in the app.' }}</p>
                    </div>
                @endforelse
                <div class="flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-500">
                    <p>Showing {{ $reviews->firstItem() ?? 0 }}–{{ $reviews->lastItem() ?? 0 }} of {{ number_format($reviews->total()) }}</p>
                    @if($reviews->hasPages())
                        <nav class="flex items-center gap-3" aria-label="Review pages">
                            @if($reviews->previousPageUrl())<a rel="prev" href="{{ $reviews->previousPageUrl() }}" class="rounded-lg border border-stone-200 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-stone-50">Previous</a>@endif
                            <span>Page {{ $reviews->currentPage() }} of {{ $reviews->lastPage() }}</span>
                            @if($reviews->nextPageUrl())<a rel="next" href="{{ $reviews->nextPageUrl() }}" class="rounded-lg border border-stone-200 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-stone-50">Next</a>@endif
                        </nav>
                    @endif
                </div>
            </div>
            <aside class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900">Rating distribution</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Across all submitted reviews</p>
                <div class="mt-5 space-y-4">
                    @for($stars = 5; $stars >= 1; $stars--)
                        @php($count = (int) ($distribution[$stars] ?? 0))
                        <a href="{{ route('admin.reviews', ['rating' => $stars, 'role' => $role, 'search' => $search]) }}" class="flex items-center gap-3 text-xs hover:text-red-700" aria-label="{{ $stars }} stars: {{ $count }} reviews">
                            <span class="w-8 shrink-0 text-slate-600">{{ $stars }} ★</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-stone-100"><span class="block h-full rounded-full bg-amber-400" style="width: {{ $totalReviews ? round($count / $totalReviews * 100, 2) : 0 }}%"></span></span>
                            <span class="w-6 text-right tabular-nums text-slate-500">{{ $count }}</span>
                        </a>
                    @endfor
                </div>
            </aside>
        </div>
    </div>
</x-admin-layout>
