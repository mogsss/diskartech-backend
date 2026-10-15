<x-admin-layout>
    @push('header-title')
        Dashboard
    @endpush

    <div class="mx-auto w-full max-w-7xl space-y-6 sm:space-y-8">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" aria-labelledby="overview-title">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-red-700">Platform overview</p>
                <h1 id="overview-title" class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Your community at a glance.</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Manage accounts, review documents, and keep DiskarTech safe.</p>
            </div>
            <div class="flex shrink-0 items-center gap-3 sm:flex-col sm:items-end">
                <p class="text-xs text-slate-500">As of {{ $generatedAt->format('M j, Y · g:i A') }} PHT</p>
                <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-stone-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700" aria-label="Refresh dashboard">
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Refresh
                </a>
            </div>
        </section>

        @if(session('success') || session('error'))
            <div role="status" class="rounded-2xl border px-4 py-3 text-sm {{ session('error') ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">{{ session('error') ?: session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        @php
            $stats = [
                ['label' => 'Students', 'count' => $studentsCount, 'detail' => 'Registered student accounts', 'url' => route('admin.users', ['role' => 'student']), 'icon' => 'M12 14l9-5-9-5-9 5 9 5zm0 0v7m-6-9.5v5a12 12 0 0012 0v-5M21 9v6'],
                ['label' => 'Households', 'count' => $householdCount, 'detail' => 'Registered household accounts', 'url' => route('admin.users', ['role' => 'household']), 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
                ['label' => 'Employers', 'count' => $employerCount, 'detail' => 'Registered business accounts', 'url' => route('admin.users', ['role' => 'employer']), 'icon' => 'M4 21h16M6 21V4h12v17M9 7h1m4 0h1M9 11h1m4 0h1M10 21v-6h4v6'],
                ['label' => 'Open jobs', 'count' => $openJobsCount, 'detail' => 'Active job listings', 'url' => route('admin.job-moderation', ['status' => 'active']), 'icon' => 'M9 6V4h6v2M3 10v10h18V10M3 10V6h18v4M3 10l9 4 9-4m-9 1v4'],
            ];
        @endphp
        <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" aria-label="Platform totals">
            @foreach($stats as $stat)
                <a href="{{ $stat['url'] }}" class="group rounded-2xl border border-stone-200/80 bg-white p-4 shadow-sm transition hover:border-red-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-medium text-slate-500 sm:text-sm">{{ $stat['label'] }}</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 sm:h-10 sm:w-10"><svg class="h-4 w-4 sm:h-5 sm:w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $stat['icon'] }}"/></svg></span>
                    </div>
                    <p class="mt-4 text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($stat['count']) }}</p>
                    <div class="mt-2 flex items-center justify-between gap-2"><p class="text-xs leading-5 text-slate-500">{{ $stat['detail'] }}</p><span aria-hidden="true" class="text-slate-300 transition group-hover:text-red-700">&rarr;</span></div>
                </a>
            @endforeach
        </section>

        <section class="flex flex-col gap-4 rounded-2xl border border-red-100 bg-red-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" aria-label="Review workload">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-red-700"><svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 5H5v16h14V5h-4M9 3h6v4H9zM9 12h6m-6 4h4"/></svg></span>
                <div><p class="text-sm font-semibold text-slate-900">{{ $pendingVerificationCount + $flaggedCount > 0 ? 'Ready for your review' : 'You’re all caught up' }}</p><p class="mt-1 text-xs leading-5 text-slate-600">{{ number_format($pendingVerificationCount) }} awaiting verification &middot; {{ number_format($flaggedCount) }} open {{ $flaggedCount === 1 ? 'report' : 'reports' }}</p></div>
            </div>
            <div class="flex flex-wrap gap-2"><a href="{{ route('admin.verification') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-semibold text-white transition hover:bg-red-800">Review accounts</a><a href="{{ route('admin.reports', ['status' => 'pending']) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-red-200 bg-white px-4 text-xs font-semibold text-red-700 transition hover:bg-red-50">View reports</a></div>
        </section>

        <div x-data="{ openModal: false, applicant: {}, showReview(data) { this.applicant = data; this.openModal = true; } }" @keydown.escape.window="openModal = false" class="grid grid-cols-1 items-start gap-6 xl:grid-cols-12">
            <div class="space-y-6 xl:col-span-8">
                @include('admin.partials.job-status-chart')

                <section class="overflow-hidden rounded-2xl border border-stone-200/80 bg-white shadow-sm" aria-labelledby="queue-title">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 p-5 sm:px-6">
                        <div><div class="flex items-center gap-2"><h2 id="queue-title" class="text-base font-bold text-slate-900">Verification queue</h2><span class="rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-amber-700">{{ number_format($pendingVerificationCount) }}</span></div><p class="mt-1 text-xs leading-5 text-slate-500">Complete document submissions awaiting a decision.</p></div>
                        <a href="{{ route('admin.verification') }}" class="inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-red-700 hover:text-red-800">View all <span aria-hidden="true">&rarr;</span></a>
                    </div>
                    @if($users->isEmpty())
                        <div class="px-6 py-14 text-center"><span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-50 text-slate-400"><svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12l2 2 4-4m5-6a20 20 0 01-8-2 20 20 0 01-8 2v7c0 5 8 9 8 9s8-4 8-9V4z"/></svg></span><p class="text-sm font-semibold text-slate-800">No accounts waiting for review</p><p class="mx-auto mt-2 max-w-xs text-xs leading-5 text-slate-500">New complete submissions will appear here. View all accounts to check incomplete or rejected documents.</p></div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-left">
                                <caption class="sr-only">Five newest complete verification submissions</caption>
                                <thead class="border-b border-stone-100 bg-stone-50/60 text-[11px] uppercase tracking-wider text-slate-500"><tr><th scope="col" class="px-5 py-3 font-medium sm:pl-6">Account</th><th scope="col" class="px-3 py-3 font-medium">Type</th><th scope="col" class="px-3 py-3 font-medium">Documents</th><th scope="col" class="px-3 py-3 font-medium">Registered</th><th scope="col" class="px-5 py-3 text-right font-medium sm:pr-6">Action</th></tr></thead>
                                <tbody class="divide-y divide-stone-100">
                                    @foreach($users as $user)
                                        @php
                                            $profile = match ($user->role) { 'student' => $user->studentProfile, 'employer' => $user->employerProfile, 'household' => $user->householdProfile };
                                            $name = match ($user->role) { 'student' => $profile->student_name, 'employer' => $profile->hirer_name, 'household' => $profile->household_name } ?: $user->email;
                                            $reference = match ($user->role) { 'student' => $profile->student_school_name, 'employer' => $profile->employer_name, 'household' => $profile->household_name } ?: 'Not provided';
                                            $documentUrl = fn ($path) => app(\App\Services\UploadStorage::class)->url($path) ?? '';
                                            $reviewPayload = [
                                                'userId' => $user->id, 'name' => $name, 'role' => ucfirst($user->role), 'roleLower' => $user->role, 'reference' => $reference,
                                                'isValid' => $profile->ai_is_valid, 'remarks' => $profile->ai_remarks ?? 'No remarks.',
                                                'certIsValid' => $profile->cert_ai_is_valid, 'certRemarks' => $profile->cert_ai_remarks ?? 'No remarks.',
                                                'schoolIdAiIsValid' => $profile->school_id_ai_is_valid, 'schoolIdAiRemarks' => $profile->school_id_ai_remarks ?? 'No remarks.',
                                                'coeAiIsValid' => $profile->coe_ai_is_valid, 'coeAiRemarks' => $profile->coe_ai_remarks ?? 'No remarks.',
                                                'idUrl' => $documentUrl($profile->valid_id_path), 'certUrl' => $documentUrl($profile->employer_certificate_path),
                                                'schoolIdUrl' => $documentUrl($profile->school_id), 'resumeUrl' => $documentUrl($profile->student_resume), 'coeUrl' => $documentUrl($profile->coe),
                                                'isComplete' => true, 'isVerified' => false,
                                            ];
                                        @endphp
                                        <tr class="transition hover:bg-stone-50/60">
                                            <td class="px-5 py-4 sm:pl-6"><div class="flex items-center gap-3"><span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-50 text-xs font-bold text-red-700">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span><div class="min-w-0"><p class="max-w-[210px] truncate text-sm font-semibold text-slate-900" title="{{ $name }}">{{ $name }}</p><p class="mt-1 max-w-[210px] truncate text-xs text-slate-500" title="{{ $reference }}">{{ $reference }}</p></div></div></td>
                                            <td class="px-3 py-4"><span class="inline-flex rounded-lg bg-stone-100 px-2 py-1 text-xs font-medium text-slate-600">{{ ucfirst($user->role) }}</span></td>
                                            <td class="px-3 py-4"><p class="text-xs font-medium text-slate-700">{{ $profile->docs_count }}</p><p class="mt-1 text-[11px] text-emerald-700">Complete</p></td>
                                            <td class="px-3 py-4 text-xs text-slate-500"><time datetime="{{ $user->created_at->toIso8601String() }}">{{ $user->created_at->copy()->timezone('Asia/Manila')->format('M j') }}</time></td>
                                            <td class="px-5 py-4 text-right sm:pr-6"><button type="button" @click="showReview(@js($reviewPayload))" class="min-h-9 rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700" aria-label="Review documents for {{ $name }}">Review</button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-stone-100 px-5 py-3 text-xs text-slate-500 sm:px-6">Showing {{ $users->count() }} of {{ number_format($pendingVerificationCount) }} awaiting review &middot; Newest registrations first</div>
                    @endif
                </section>

                <section class="grid gap-3 sm:grid-cols-2" aria-label="Account and job management">
                    <a href="{{ route('admin.users') }}" class="group flex items-center justify-between gap-4 rounded-2xl border border-stone-200/80 bg-white p-5 transition hover:border-red-200"><div><h2 class="text-sm font-semibold text-slate-900">Manage users</h2><p class="mt-1 text-xs leading-5 text-slate-500">Find accounts and view profile details.</p></div><span aria-hidden="true" class="text-slate-400 group-hover:text-red-700">&rarr;</span></a>
                    <a href="{{ route('admin.job-moderation') }}" class="group flex items-center justify-between gap-4 rounded-2xl border border-stone-200/80 bg-white p-5 transition hover:border-red-200"><div><h2 class="text-sm font-semibold text-slate-900">Job moderation</h2><p class="mt-1 text-xs leading-5 text-slate-500">Review listings and manage visibility.</p></div><span aria-hidden="true" class="text-slate-400 group-hover:text-red-700">&rarr;</span></a>
                </section>
            </div>

            <div class="space-y-6 xl:col-span-4">
                <section class="rounded-2xl border border-stone-200/80 bg-white shadow-sm" aria-labelledby="reports-title">
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 p-5"><div><h2 id="reports-title" class="text-base font-bold text-slate-900">Recent reports</h2><p class="mt-1 text-xs text-slate-500">Open incidents requiring attention.</p></div><span class="rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold tabular-nums text-red-700">{{ number_format($flaggedCount) }} open</span></div>
                    <div class="divide-y divide-stone-100">
                        @forelse($flaggedReports as $report)
                            @php
                                $reporter = $report->reporter;
                                $reporterName = $reporter?->studentProfile?->student_name ?? $reporter?->employerProfile?->hirer_name ?? $reporter?->householdProfile?->household_name ?? $reporter?->email ?? 'Unknown reporter';
                                $generalReport = !$report->job_id && !$report->reported_user_id;
                            @endphp
                            <a href="{{ route('admin.reports', ['search' => $report->id]) }}" class="group block px-5 py-4 transition hover:bg-stone-50">
                                <div class="flex items-center justify-between gap-2"><p class="text-[11px] font-medium text-slate-500">REP-{{ str_pad($report->id, 5, '0', STR_PAD_LEFT) }}</p><span class="rounded-md px-2 py-0.5 text-[10px] font-semibold {{ $report->status === 'investigating' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700' }}">{{ ucfirst($report->status) }}</span></div>
                                <h3 class="mt-2 line-clamp-2 text-sm font-semibold leading-5 text-slate-900 group-hover:text-red-700">{{ $report->subject ?: ucfirst(str_replace('_', ' ', $report->report_type)) }}</h3>
                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $report->description }}</p>
                                <p class="mt-2 text-[11px] text-slate-500">{{ $generalReport ? 'General incident' : ($report->job?->title ?? 'Account report') }}</p>
                                <div class="mt-3 flex items-center justify-between gap-2 text-[11px] text-slate-400"><span class="truncate">By {{ $reporterName }}</span><time class="shrink-0" datetime="{{ $report->created_at->toIso8601String() }}">{{ $report->created_at->diffForHumans() }}</time></div>
                            </a>
                        @empty
                            <div class="px-5 py-10 text-center"><p class="text-sm font-semibold text-slate-800">No open incidents</p><p class="mt-2 text-xs leading-5 text-slate-500">New reports will appear here for review.</p></div>
                        @endforelse
                    </div>
                    <a href="{{ route('admin.reports') }}" class="flex min-h-12 items-center justify-center gap-2 border-t border-stone-100 text-xs font-semibold text-red-700 transition hover:bg-red-50">Manage all reports <span aria-hidden="true">&rarr;</span></a>
                </section>

                @php $weeklyTotal = $weeklyActivity->sum('count'); $weeklyMax = max(1, $weeklyActivity->max('count')); @endphp
                <section class="rounded-2xl border border-stone-200/80 bg-white p-5 shadow-sm" aria-labelledby="activity-title">
                    <div class="flex items-start justify-between gap-3"><div><h2 id="activity-title" class="text-base font-bold text-slate-900">New accounts</h2><p class="mt-1 text-xs text-slate-500">Past 7 days &middot; Philippine time</p></div><span class="rounded-lg bg-stone-50 px-2 py-1 text-xs font-medium text-slate-500">{{ $weeklyActivity->first()['date'] }}–{{ $weeklyActivity->last()['date'] }}</span></div>
                    <p class="mt-5 text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($weeklyTotal) }} <span class="text-xs font-normal text-slate-500">registrations</span></p>
                    <figure class="mt-5">
                        <div class="flex h-36 items-end justify-between gap-2 border-b border-stone-100" aria-hidden="true">
                            @foreach($weeklyActivity as $day)
                                <div class="flex h-full flex-1 flex-col justify-end gap-2 text-center"><span class="text-[10px] font-medium tabular-nums text-slate-500">{{ $day['count'] }}</span><div class="mx-auto w-full max-w-[32px] rounded-t-md {{ $loop->last ? 'bg-red-700' : 'bg-red-200' }}" style="height: {{ round($day['count'] / $weeklyMax * 80, 2) }}%"></div></div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex justify-between gap-2 text-center text-[10px] font-medium text-slate-400" aria-hidden="true">@foreach($weeklyActivity as $day)<span class="flex-1">{{ $day['label'] }}</span>@endforeach</div>
                        <figcaption class="sr-only">New registrations: @foreach($weeklyActivity as $day){{ $day['date'] }}: {{ $day['count'] }}{{ $loop->last ? '.' : '; ' }}@endforeach</figcaption>
                    </figure>
                    <p class="mt-4 text-[11px] leading-5 text-slate-500">{{ $weeklyTotal ? 'Includes student, household, and employer accounts.' : 'No new registrations during this period.' }}</p>
                </section>
            </div>
            @include('admin.partials.verification-modal')
        </div>
    </div>
</x-admin-layout>
