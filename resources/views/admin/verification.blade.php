<x-admin-layout>
    @push('header-title')
        Verifications
    @endpush

    <div class="mx-auto w-full max-w-7xl space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-red-700">Account verification</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Review with confidence.</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Check submitted documents and AI recommendations before making a decision.</p>
            </div>
            <a href="{{ route('admin.verification', request()->only('status', 'role', 'search', 'page')) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 self-start rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-stone-50 sm:self-auto">
                <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </a>
        </section>

        @if(session('success') || session('error'))
            <div role="status" class="rounded-2xl border px-4 py-3 text-sm {{ session('error') ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">{{ session('error') ?: session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        @php
            $states = [
                'pending' => ['label' => 'Pending review', 'detail' => 'Complete documents, awaiting a decision', 'tone' => 'bg-amber-50 text-amber-700', 'icon' => 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                'verified' => ['label' => 'Verified', 'detail' => 'Approved accounts', 'tone' => 'bg-emerald-50 text-emerald-700', 'icon' => 'M9 12l2 2 4-4m6-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                'rejected' => ['label' => 'Rejected', 'detail' => 'Documents need correction', 'tone' => 'bg-rose-50 text-rose-700', 'icon' => 'M9 9l6 6m0-6l-6 6m12-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                'incomplete' => ['label' => 'Incomplete', 'detail' => 'Required documents missing', 'tone' => 'bg-stone-100 text-slate-500', 'icon' => 'M9 12h6m-6 4h6M7 3h7l5 5v13H5V3h2m7 0v5h5'],
            ];
        @endphp
        <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" aria-label="Verification totals across all accounts">
            @foreach($states as $key => $state)
                <a href="{{ route('admin.verification', ['status' => $key, 'role' => $role, 'search' => $search]) }}" @if($status === $key) aria-current="page" @endif class="rounded-2xl border bg-white p-4 shadow-sm transition hover:border-red-200 sm:p-5 {{ $status === $key ? 'border-red-300 ring-1 ring-red-100' : 'border-stone-200/80' }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-medium text-slate-600 sm:text-sm">{{ $state['label'] }}</p>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl {{ $state['tone'] }}"><svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="{{ $state['icon'] }}"/></svg></span>
                    </div>
                    <p class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($statusCounts[$key]) }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $state['detail'] }}</p>
                </a>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-2xl border border-stone-200/80 bg-white shadow-sm" x-data="{ openModal: false, applicant: {}, reviewTrigger: null, showReview(data) { this.reviewTrigger = document.activeElement; this.applicant = data; this.openModal = true; } }" @keydown.escape.window="if (openModal) { openModal = false; reviewTrigger?.focus(); }">
            <div class="border-b border-stone-100 p-4 sm:p-6">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-slate-900">Verification queue <span class="ml-2 rounded-lg bg-stone-100 px-2 py-1 text-xs font-semibold text-slate-500">{{ number_format($users->total()) }}</span></h2>
                    <p class="text-xs text-slate-500">{{ number_format($totalAccounts) }} accounts overall</p>
                </div>
                <form method="GET" action="{{ route('admin.verification') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_180px_180px_auto]">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="verification-search" class="mb-2 block text-xs font-semibold text-slate-600">Search accounts</label>
                        <input id="verification-search" type="search" name="search" value="{{ $search }}" maxlength="120" placeholder="Name, email, school, or business" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-100">
                    </div>
                    <div>
                        <label for="verification-role" class="mb-2 block text-xs font-semibold text-slate-600">Account type</label>
                        <select id="verification-role" name="role" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                            @foreach(['all' => 'All types', 'student' => 'Student', 'employer' => 'Employer', 'household' => 'Household'] as $value => $label)
                                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="verification-status" class="mb-2 block text-xs font-semibold text-slate-600">Status</label>
                        <select id="verification-status" name="status" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                            <option value="all" @selected($status === 'all')>All statuses</option>
                            @foreach($states as $value => $state)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $state['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="min-h-[44px] self-end rounded-xl bg-red-700 px-5 text-sm font-semibold text-white transition hover:bg-red-800">Apply filters</button>
                </form>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <p>Newest registrations first. Summary cards show totals across all account types.</p>
                    <a href="{{ route('admin.verification', ['status' => 'all']) }}" class="font-semibold text-red-700 hover:underline">Show all / reset filters</a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <caption class="sr-only">Account documents and verification status, newest registrations first</caption>
                    <thead class="border-b border-stone-100 bg-stone-50/80 text-xs text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-4 font-semibold sm:pl-6">Account</th>
                            <th scope="col" class="px-4 py-4 font-semibold">Type</th>
                            <th scope="col" class="px-4 py-4 font-semibold">Documents</th>
                            <th scope="col" class="px-4 py-4 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-4 font-semibold">Registered</th>
                            <th scope="col" class="px-5 py-4 text-right font-semibold sm:pr-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse($users as $user)
                            @php
                                $profile = match ($user->role) { 'student' => $user->studentProfile, 'employer' => $user->employerProfile, 'household' => $user->householdProfile };
                                $name = match ($user->role) { 'student' => $profile?->student_name, 'employer' => $profile?->hirer_name, 'household' => $profile?->household_name } ?: $user->email;
                                $reference = match ($user->role) { 'student' => $profile?->student_school_name, 'employer' => $profile?->employer_name, 'household' => $profile?->household_name } ?: 'No reference provided';
                                $documents = match ($user->role) { 'student' => [$profile?->school_id, $profile?->coe, $profile?->student_resume], 'employer' => [$profile?->valid_id_path, $profile?->employer_certificate_path], 'household' => [$profile?->valid_id_path] };
                                $uploaded = count(array_filter($documents, fn ($value) => filled($value)));
                                $isComplete = $uploaded === count($documents);
                                $isVerified = (bool) $profile?->isVerified;
                                $reason = $profile?->rejection_reason;
                                $rowStatus = $isVerified ? 'verified' : (filled($reason) ? 'rejected' : ($isComplete ? 'pending' : 'incomplete'));
                                $fileUrl = fn ($path) => filled($path) ? asset('storage/'.$path) : '';
                                $reviewPayload = [
                                    'userId' => $user->id, 'name' => $name, 'role' => ucfirst($user->role), 'roleLower' => $user->role, 'reference' => $reference,
                                    'isValid' => $profile?->ai_is_valid, 'remarks' => $profile?->ai_remarks,
                                    'certIsValid' => $profile?->cert_ai_is_valid, 'certRemarks' => $profile?->cert_ai_remarks,
                                    'schoolIdAiIsValid' => $profile?->school_id_ai_is_valid, 'schoolIdAiRemarks' => $profile?->school_id_ai_remarks,
                                    'coeAiIsValid' => $profile?->coe_ai_is_valid, 'coeAiRemarks' => $profile?->coe_ai_remarks,
                                    'idUrl' => $fileUrl($profile?->valid_id_path), 'certUrl' => $fileUrl($profile?->employer_certificate_path),
                                    'schoolIdUrl' => $fileUrl($profile?->school_id), 'coeUrl' => $fileUrl($profile?->coe), 'resumeUrl' => $fileUrl($profile?->student_resume),
                                    'isComplete' => $isComplete, 'isVerified' => $isVerified, 'rejectionReason' => $reason,
                                ];
                            @endphp
                            <tr class="transition hover:bg-stone-50/60" data-account-id="{{ $user->id }}" data-verification-status="{{ $rowStatus }}">
                                <td class="px-5 py-5 sm:pl-6">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-sm font-bold text-red-700">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                                        <div class="min-w-0 max-w-[260px]">
                                            <p class="break-words font-semibold text-slate-900">{{ $name }}</p>
                                            <p class="mt-1 break-words text-xs text-slate-500">{{ $reference }}</p>
                                            <p class="mt-1 break-all text-xs text-slate-400">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-5 text-slate-600">{{ ucfirst($user->role) }}</td>
                                <td class="px-4 py-5">
                                    <p class="font-semibold text-slate-700">{{ $uploaded }} of {{ count($documents) }}</p>
                                    <p class="mt-1 text-xs {{ $isComplete ? 'text-emerald-700' : 'text-slate-400' }}">{{ $isComplete ? 'Complete' : 'Missing documents' }}</p>
                                </td>
                                <td class="px-4 py-5"><span class="inline-flex whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold {{ $states[$rowStatus]['tone'] }}">{{ $states[$rowStatus]['label'] }}</span></td>
                                <td class="whitespace-nowrap px-4 py-5 text-xs text-slate-500">{{ $user->created_at?->timezone('Asia/Manila')->format('M j, Y') ?: 'Not recorded' }}</td>
                                <td class="px-5 py-5 text-right sm:pr-6">
                                    <button type="button" @click="showReview(@js($reviewPayload))" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-stone-200 bg-white px-4 text-xs font-semibold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">{{ $isVerified ? 'View details' : 'Review' }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-14 text-center">
                                <svg class="mx-auto mb-4 h-10 w-10 text-stone-300" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="font-semibold text-slate-900">{{ $status === 'pending' && $search === '' && $role === 'all' ? 'No accounts waiting for review' : 'No matching accounts' }}</p>
                                <p class="mt-2 text-sm text-slate-500">Try another filter or search to view more accounts.</p>
                                <a href="{{ route('admin.verification', ['status' => 'all']) }}" class="mt-4 inline-block text-sm font-semibold text-red-700 hover:underline">View all accounts</a>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 border-t border-stone-100 px-5 py-4 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-slate-500">Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ number_format($users->total()) }} accounts</p>
                @if($users->hasPages())
                    <nav class="flex flex-wrap items-center gap-2" aria-label="Verification pages">
                        @if($users->onFirstPage())
                            <span aria-disabled="true" class="rounded-lg border border-stone-100 px-3 py-2 text-stone-300">Previous</span>
                        @else
                            <a rel="prev" href="{{ $users->previousPageUrl() }}" class="rounded-lg border border-stone-200 px-3 py-2 font-semibold text-slate-600 hover:bg-stone-50">Previous</a>
                        @endif
                        <span class="px-1 text-slate-500">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
                        @if($users->hasMorePages())
                            <a rel="next" href="{{ $users->nextPageUrl() }}" class="rounded-lg border border-stone-200 px-3 py-2 font-semibold text-slate-600 hover:bg-stone-50">Next</a>
                        @else
                            <span aria-disabled="true" class="rounded-lg border border-stone-100 px-3 py-2 text-stone-300">Next</span>
                        @endif
                    </nav>
                @endif
            </div>
            @include('admin.partials.verification-modal')
        </section>
    </div>
</x-admin-layout>
