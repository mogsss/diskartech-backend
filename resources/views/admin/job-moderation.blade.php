<x-admin-layout>
    @push('header-title') Job Moderation @endpush
    <script>
        function jobModeration() {
            return {
                open: false, loading: false, error: '', job: null, applicants: [], pagination: null,
                detailUrl: '', lastUrl: '', controller: null, revision: 0, opener: null, previousOverflow: '',
                async show(url, button) {
                    this.opener = button; this.detailUrl = url; this.job = null; this.applicants = []; this.pagination = null;
                    this.previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; this.open = true;
                    this.$nextTick(() => requestAnimationFrame(() => this.$refs.dialogClose.focus()));
                    await this.load(url);
                },
                async load(url) {
                    if (!url) return;
                    this.controller?.abort();
                    const controller = new AbortController(); this.controller = controller;
                    const revision = ++this.revision; this.loading = true; this.error = ''; this.lastUrl = url;
                    const timeout = setTimeout(() => controller.abort(), 15000);
                    try {
                        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
                        if (!response.ok) throw new Error(response.status === 401 || response.status === 403 ? 'Your session has expired. Refresh the page and sign in again.' : response.status === 404 ? 'This job is no longer available. Refresh the job list.' : 'Unable to load job details. Please try again.');
                        const data = await response.json();
                        if (revision !== this.revision || !this.open) return;
                        if (!data.job || !Array.isArray(data.applicants) || !data.pagination) throw new Error('Unable to load job details. Please refresh the page.');
                        this.job = data.job; this.applicants = data.applicants; this.pagination = data.pagination;
                        this.$nextTick(() => { this.$refs.dialogBody.scrollTop = 0; });
                    } catch (error) {
                        if (revision === this.revision && this.open) this.error = error.name === 'AbortError' ? 'The request timed out. Please try again.' : (error instanceof SyntaxError || error instanceof TypeError ? 'Unable to load job details. Check your connection and try again.' : error.message);
                    } finally {
                        clearTimeout(timeout);
                        if (revision === this.revision) this.loading = false;
                    }
                },
                close() {
                    this.open = false; ++this.revision; this.controller?.abort(); this.loading = false;
                    document.body.style.overflow = this.previousOverflow;
                    this.$nextTick(() => requestAnimationFrame(() => this.opener?.focus()));
                },
                trap(event) {
                    const elements = [...this.$refs.dialog.querySelectorAll('button:not([disabled]), a[href], input:not([type=hidden]), select, textarea, [tabindex="0"]')].filter(el => el.getClientRects().length);
                    const first = elements[0], last = elements.at(-1);
                    if (!first) return;
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                },
                statusLabel(status) { return ({ active: 'Live', hidden: 'Hidden', closed: 'Closed' })[status] || 'Unknown'; },
                statusClass(status) { return ({ active: 'bg-emerald-50 text-emerald-700', hidden: 'bg-amber-50 text-amber-800', closed: 'bg-slate-100 text-slate-600' })[status] || 'bg-slate-100 text-slate-600'; },
                destroy() { this.controller?.abort(); if (this.open) document.body.style.overflow = this.previousOverflow; }
            };
        }
    </script>
    <div class="mx-auto w-full max-w-7xl space-y-6" x-data="jobModeration()" @keydown.escape.window="if (open) close()">
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-red-700">Manage listings</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Job Moderation</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Review job posts, check hirer details, and manage what students can see.</p>
            </div>
            <a href="{{ url()->full() }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-stone-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5M4 17v-5h5m-4-1a7 7 0 0 1 12-5l3 3M4 15l3 3a7 7 0 0 0 12-5"/></svg>
                Refresh
            </a>
        </section>
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error') || $errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') ?: $errors->first() }}</div>
        @endif
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4" aria-label="Overall job totals">
            @foreach([
                ['all', 'Total job posts', $stats['total'], 'All listings', 'bg-red-50 text-red-700'],
                ['active', 'Live jobs', $stats['live'], 'Visible to students', 'bg-emerald-50 text-emerald-700'],
                ['hidden', 'Hidden jobs', $stats['hidden'], 'Removed from browsing', 'bg-amber-50 text-amber-800'],
                ['closed', 'Closed jobs', $stats['closed'], 'No longer accepting applicants', 'bg-slate-100 text-slate-600'],
            ] as [$value, $label, $count, $description, $tone])
                <a href="{{ route('admin.job-moderation', ['status' => $value]) }}" class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm hover:border-red-200 sm:p-5">
                    <div class="mb-3 inline-flex h-9 w-9 items-center justify-center rounded-xl {{ $tone }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M16 6V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M4 6h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1zm-1 6h18m-11 0v2h4v-2"/></svg>
                    </div>
                    <p class="text-xs font-medium text-slate-500 sm:text-sm">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($count) }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-400">{{ $description }}</p>
                </a>
            @endforeach
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('admin.job-moderation') }}" x-ref="jobFilters"
                x-data="{ timer: null, submitFilters() { clearTimeout(this.timer); this.$refs.jobFilters.requestSubmit(); }, scheduleSearch(event) { clearTimeout(this.timer); if (!event.isComposing) this.timer = setTimeout(() => this.submitFilters(), 600); }, destroy() { clearTimeout(this.timer); } }"
                @submit="clearTimeout(timer)" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_160px_200px]">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="job-search" class="mb-2 block text-xs font-semibold text-slate-600">Search job posts</label>
                    <input id="job-search" type="search" name="search" maxlength="120" value="{{ $search }}" @input="scheduleSearch($event)" @compositionend="scheduleSearch($event)" placeholder="Job title, hirer, email, or description" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                </div>
                <div>
                    <label for="job-status" class="mb-2 block text-xs font-semibold text-slate-600">Status</label>
                    <select id="job-status" name="status" @change="submitFilters()" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                        @foreach(['all' => 'All statuses', 'active' => 'Live', 'hidden' => 'Hidden', 'closed' => 'Closed'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="job-category" class="mb-2 block text-xs font-semibold text-slate-600">Category</label>
                    <select id="job-category" name="category" @change="submitFilters()" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                        <option value="all" @selected($category === 'all')>All categories</option>
                        @foreach($categories as $item)<option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>@endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="min-h-[44px] rounded-xl bg-red-700 px-4 text-sm font-semibold text-white">Apply filters</button></noscript>
            </form>
        </section>
        <div class="flex flex-wrap items-center justify-between gap-2 px-1 text-xs text-slate-500">
            <p>{{ number_format($jobs->total()) }} matching posts · newest first <span class="hidden sm:inline">· {{ number_format($stats['applications']) }} total applications</span></p>
            <a href="{{ route('admin.job-moderation') }}" class="font-semibold text-red-700 hover:underline">Reset filters</a>
        </div>
        <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm" aria-label="Job listings">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left">
                    <thead class="border-b border-stone-200 bg-stone-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th scope="col" class="px-5 py-4">Job post</th><th scope="col" class="px-4 py-4">Hirer</th><th scope="col" class="px-4 py-4">Rate</th><th scope="col" class="px-4 py-4 text-center">Applicants</th><th scope="col" class="px-4 py-4">Status</th><th scope="col" class="px-5 py-4 text-right">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse($jobs as $job)
                            @php
                                $poster = $job->moderation_poster;
                                $tone = match ($job->status) { 'active' => 'bg-emerald-50 text-emerald-700', 'hidden' => 'bg-amber-50 text-amber-800', default => 'bg-slate-100 text-slate-600' };
                            @endphp
                            <tr data-job-id="{{ $job->id }}" class="hover:bg-stone-50/60">
                                <td class="max-w-[300px] px-5 py-5">
                                    <p class="break-words text-sm font-semibold leading-6 text-slate-900">{{ $job->title }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-500">{{ $job->category ?: 'Uncategorized' }} <span class="text-stone-300">·</span> #{{ $job->id }}</p>
                                    <p class="mt-2 text-xs text-slate-400">{{ $job->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</p>
                                </td>
                                <td class="max-w-[240px] px-4 py-5">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $poster['name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $poster['role'] }} @if($poster['verified'])<span class="ml-1 text-emerald-700">· Verified</span>@endif</p>
                                    <p class="mt-1 break-all text-xs text-slate-400">{{ $poster['email'] }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-5 text-sm font-semibold text-slate-800">₱{{ number_format((float) $job->salary, 2) }} <span class="text-xs font-normal text-slate-500">/ {{ $job->salary_type === 'hour' ? 'hr' : 'day' }}</span></td>
                                <td class="px-4 py-5 text-center text-sm font-semibold tabular-nums text-slate-700">{{ $job->applications_count }}</td>
                                <td class="px-4 py-5"><span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ $job->status === 'active' ? 'Live' : ucfirst($job->status) }}</span></td>
                                <td class="px-5 py-5 text-right"><button type="button" @click="show(@js(route('admin.job-moderation.show', $job->id, false)), $el)" class="min-h-[40px] whitespace-nowrap rounded-xl border border-red-100 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100 focus:outline-red-700">View details</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-16 text-center"><p class="text-base font-semibold text-slate-900">{{ $stats['total'] ? 'No matching job posts' : 'No job posts yet' }}</p><p class="mt-2 text-sm text-slate-500">{{ $stats['total'] ? 'Try a different search, status, or category.' : 'Job posts will appear here when employers or households publish them.' }}</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.list-pagination', ['paginator' => $jobs, 'label' => 'Job list pagination'])
        </section>

        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-3 sm:p-6" @click.self="close()">
            <section x-ref="dialog" role="dialog" aria-modal="true" aria-labelledby="job-review-title" @keydown.tab="trap($event)" :aria-busy="loading" class="flex max-h-[90dvh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-stone-200 p-5 sm:p-6">
                    <div class="min-w-0">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-red-700">Review listing</p>
                        <h2 id="job-review-title" class="text-xl font-bold text-slate-900">Job details</h2>
                        <p class="mt-1 text-xs text-slate-500">Review this post before changing its visibility.</p>
                    </div>
                    <button x-ref="dialogClose" type="button" @click="close()" aria-label="Close job details" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-stone-50 text-slate-700 hover:bg-stone-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <div x-ref="dialogBody" class="min-h-0 overflow-y-auto overscroll-contain p-5 sm:p-6">
                    <p x-show="loading" role="status" class="mb-4 rounded-xl bg-stone-50 p-4 text-sm text-slate-500">Loading job details…</p>
                    <div x-show="error" role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        <p x-text="error"></p><button type="button" @click="load(lastUrl)" :disabled="loading" class="mt-3 font-semibold underline disabled:opacity-50">Try again</button>
                    </div>
                    <template x-if="job">
                        <div class="space-y-6">
                            <div>
                                <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="min-w-0 break-words text-lg font-bold text-slate-900" x-text="job.title"></h3><span class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold" :class="statusClass(job.status)" x-text="statusLabel(job.status)"></span></div>
                                <p class="mt-2 text-xs text-slate-500"><span x-text="job.category"></span> · #<span x-text="job.id"></span> · <span x-text="job.posted_at"></span></p>
                                <div class="mt-4 grid gap-4 rounded-xl bg-stone-50 p-4 sm:grid-cols-2"><div><p class="text-xs font-semibold text-slate-500">Posted rate</p><p class="mt-2 text-lg font-bold text-slate-900" x-text="job.rate"></p></div><div><p class="text-xs font-semibold text-slate-500">Work schedule</p><p class="mt-2 break-words text-sm font-medium text-slate-800" x-text="job.available_days.join(', ') || 'Days not specified'"></p><p class="mt-1 break-words text-xs text-slate-500" x-text="job.time_slot"></p></div></div>
                            </div>
                            <div><h4 class="text-sm font-semibold text-slate-900">Job description</h4><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600" x-text="job.description || 'No description provided.'"></p></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><h4 class="text-sm font-semibold text-slate-900">Requirements</h4><div class="mt-3 flex flex-wrap gap-2"><template x-for="(item, index) in job.requirements" :key="index"><span class="max-w-full break-words rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1 text-xs text-slate-600" x-text="item"></span></template><p x-show="!job.requirements.length" class="text-sm text-slate-400">None specified</p></div></div>
                                <div><h4 class="text-sm font-semibold text-slate-900">Preferred skills</h4><div class="mt-3 flex flex-wrap gap-2"><template x-for="(item, index) in job.skills" :key="index"><span class="max-w-full break-words rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1 text-xs text-slate-600" x-text="item"></span></template><p x-show="!job.skills.length" class="text-sm text-slate-400">None specified</p></div></div>
                            </div>
                            <section class="rounded-xl border border-stone-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="text-sm font-semibold text-slate-900">Hirer information</h4><span x-show="job.poster.verified" class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Verified account</span></div>
                                <p class="mt-3 break-words text-sm font-semibold text-slate-800" x-text="job.poster.name"></p><p class="mt-1 text-xs text-slate-500" x-text="job.poster.role"></p>
                                <dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Email</dt><dd class="mt-1 break-all text-sm text-slate-700" x-text="job.poster.email"></dd></div><div><dt class="text-xs text-slate-500">Contact number</dt><dd class="mt-1 text-sm text-slate-700" x-text="job.poster.phone"></dd></div><div class="sm:col-span-2"><dt class="text-xs text-slate-500">Workplace address</dt><dd class="mt-1 break-words text-sm leading-6 text-slate-700" x-text="job.poster.location"></dd></div></dl>
                            </section>
                            <section>
                                <div class="flex items-center justify-between gap-3"><h4 class="text-sm font-semibold text-slate-900">Student applicants</h4><span class="rounded-lg bg-stone-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-600" x-text="pagination.total"></span></div>
                                <div class="mt-3 divide-y divide-stone-100 rounded-xl border border-stone-200">
                                    <template x-for="applicant in applicants" :key="applicant.id">
                                        <div class="flex flex-wrap items-start justify-between gap-3 p-4"><div class="min-w-0 flex-1"><p class="break-words text-sm font-semibold text-slate-800" x-text="applicant.name"></p><p class="mt-1 break-words text-xs leading-5 text-slate-500" x-text="applicant.school + ' · ' + applicant.course"></p><p class="mt-2 text-xs text-slate-400" x-text="applicant.date"></p></div><span class="rounded-lg bg-stone-100 px-2 py-1 text-xs font-semibold text-slate-600" x-text="applicant.status"></span></div>
                                    </template>
                                    <p x-show="!applicants.length" class="p-4 text-sm text-slate-400">No students have applied yet.</p>
                                </div>
                                <nav x-show="pagination.last > 1" class="mt-3 flex items-center justify-between gap-2 text-xs text-slate-500" aria-label="Applicant pagination">
                                    <button type="button" @click="load(pagination.previous)" :disabled="loading || !pagination.previous" class="min-h-[40px] rounded-lg border border-stone-200 px-3 font-semibold disabled:opacity-40">Previous</button><span>Page <span x-text="pagination.current"></span> of <span x-text="pagination.last"></span></span><button type="button" @click="load(pagination.next)" :disabled="loading || !pagination.next" class="min-h-[40px] rounded-lg border border-stone-200 px-3 font-semibold disabled:opacity-40">Next</button>
                                </nav>
                            </section>
                        </div>
                    </template>
                </div>
                <footer class="shrink-0 border-t border-stone-200 bg-stone-50 p-4 sm:px-6">
                    <template x-if="job">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <form :action="job.delete_url" method="POST" @submit="if (loading || error || !confirm('Permanently delete this job and all its applications? This cannot be undone.')) $event.preventDefault()">
                                @csrf @method('DELETE')
                                <button type="submit" :disabled="loading || !!error" class="min-h-[44px] rounded-xl border border-rose-200 bg-white px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-40">Delete post</button>
                            </form>
                            <div class="flex flex-wrap gap-2">
                                <form x-show="job.status !== 'closed'" :action="job.status_url" method="POST" @submit="if (loading || error || !confirm('Close this job? Students will no longer be able to apply.')) $event.preventDefault()">
                                    @csrf @method('PATCH') <input type="hidden" name="status" value="closed">
                                    <button type="submit" :disabled="loading || !!error" class="min-h-[44px] rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-stone-100 disabled:opacity-40">Close job</button>
                                </form>
                                <form :action="job.status_url" method="POST" @submit="if (loading || error || !confirm(job.status === 'active' ? 'Hide this post from students?' : job.status === 'closed' ? 'Reopen this job and allow students to apply again?' : 'Publish this post to students?')) $event.preventDefault()">
                                    @csrf @method('PATCH') <input type="hidden" name="status" :value="job.status === 'active' ? 'hidden' : 'active'">
                                    <button type="submit" :disabled="loading || !!error" class="min-h-[44px] rounded-xl bg-red-700 px-4 text-xs font-semibold text-white hover:bg-red-800 disabled:opacity-40" x-text="job.status === 'active' ? 'Hide post' : job.status === 'closed' ? 'Reopen job' : 'Publish post'"></button>
                                </form>
                            </div>
                        </div>
                    </template>
                </footer>
            </section>
        </div>
    </div>
</x-admin-layout>
