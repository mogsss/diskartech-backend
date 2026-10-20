<x-admin-layout>
    @push('header-title') Users @endpush
    <style>[x-cloak] { display: none !important; }</style>
    <div class="mx-auto w-full max-w-7xl space-y-6"
        x-data="adminUserAccounts()"
        @keydown.escape.window="if (open) close()">
        <section>
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-red-700">Registered accounts</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Users</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">View student, employer, and household profiles and their verification status.</p>
        </section>
        @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4" aria-label="Overall account totals">
            @foreach(['all' => 'Total accounts', 'student' => 'Students', 'employer' => 'Employers', 'household' => 'Households'] as $type => $label)
                <a href="{{ route('admin.users', ['role' => $type]) }}" class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm hover:border-red-200 sm:p-5">
                    <span class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0zm-3 6a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/></svg>
                    </span>
                    <p class="text-xs font-medium text-slate-500 sm:text-sm">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($type === 'all' ? $totalAccounts : ($roleCounts[$type] ?? 0)) }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ $type === 'all' ? 'Across all account types' : 'View '.strtolower($label).' accounts' }}</p>
                </a>
            @endforeach
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('admin.users') }}" x-ref="userFilters"
                x-data="{ timer: null, submitFilters() { clearTimeout(this.timer); this.$refs.userFilters.requestSubmit(); }, scheduleSearch(event) { clearTimeout(this.timer); if (!event.isComposing) this.timer = setTimeout(() => this.submitFilters(), 600); }, destroy() { clearTimeout(this.timer); } }"
                @submit="clearTimeout(timer)" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_170px_180px]">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="user-search" class="mb-2 block text-xs font-semibold text-slate-600">Search accounts</label>
                    <input id="user-search" name="search" type="search" maxlength="120" value="{{ $search }}" @input="scheduleSearch($event)" @compositionend="scheduleSearch($event)" placeholder="Name, email, business, or school" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                </div>
                <div>
                    <label for="user-role" class="mb-2 block text-xs font-semibold text-slate-600">Account type</label>
                    <select id="user-role" name="role" @change="submitFilters()" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                        @foreach(['all' => 'All account types', 'student' => 'Student', 'employer' => 'Employer', 'household' => 'Household'] as $value => $label)<option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="user-verification" class="mb-2 block text-xs font-semibold text-slate-600">Verification</label>
                    <select id="user-verification" name="verification" @change="submitFilters()" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-700 focus:outline-red-700">
                        @foreach(['all' => 'All accounts', 'verified' => 'Verified', 'unverified' => 'Unverified'] as $value => $label)<option value="{{ $value }}" @selected($verification === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="min-h-[44px] rounded-xl bg-red-700 px-4 text-sm font-semibold text-white">Apply filters</button></noscript>
            </form>
        </section>
        <div class="flex flex-wrap items-center justify-between gap-2 px-1 text-xs text-slate-500">
            <p>{{ number_format($users->total()) }} matching accounts · newest first <span class="hidden sm:inline">· {{ number_format($verifiedAccounts) }} verified overall</span></p>
            <a href="{{ route('admin.users') }}" class="font-semibold text-red-700 hover:underline">Reset filters</a>
        </div>
        <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm" aria-label="Registered users">
                    <thead class="border-b border-stone-200 bg-stone-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th scope="col" class="px-5 py-4">Account</th><th scope="col" class="px-4 py-4">Type</th><th scope="col" class="px-4 py-4">Verification</th><th scope="col" class="px-4 py-4">Registered</th><th scope="col" class="px-5 py-4 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse($users as $user)
                            @php($details = $user->admin_details)
                            <tr data-user-id="{{ $user->id }}" class="hover:bg-stone-50/60">
                                <td class="px-5 py-5"><div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-sm font-bold text-red-700">{{ mb_strtoupper(mb_substr($details['name'], 0, 1)) }}</span>
                                    <div class="max-w-[320px]"><p class="break-words font-semibold text-slate-900">{{ $details['name'] }}</p>@if($details['reference'])<p class="mt-1 break-words text-xs text-slate-500">{{ $details['reference'] }}</p>@endif<p class="mt-1 break-all text-xs text-slate-400">{{ $user->email }}</p></div>
                                </div></td>
                                <td class="px-4 py-5 text-slate-600">{{ $details['role'] }}</td>
                                <td class="px-4 py-5"><span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold {{ $details['verified'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $details['verified'] ? 'Verified' : 'Unverified' }}</span>@if(!$details['hasProfile'])<p class="mt-1 text-xs text-slate-400">Profile not completed</p>@endif</td>
                                <td class="whitespace-nowrap px-4 py-5 text-xs text-slate-500">{{ $user->created_at?->timezone('Asia/Manila')->format('M j, Y') ?: 'Not recorded' }}</td>
                                <td class="px-5 py-5 text-right"><button type="button" @click="showDetails(@js($details), $el)" class="min-h-[40px] whitespace-nowrap rounded-xl border border-red-100 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100 focus:outline-red-700">View details</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-16 text-center"><p class="text-base font-semibold text-slate-900">{{ $totalAccounts ? 'No matching accounts' : 'No registered accounts yet' }}</p><p class="mt-2 text-sm text-slate-500">{{ $totalAccounts ? 'Try another name, account type, or verification filter.' : 'Students, employers, and households will appear here after registering.' }}</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.list-pagination', ['paginator' => $users, 'label' => 'User list pagination'])
        </section>
        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-3 sm:p-6" @click.self="close()">
            <section x-ref="userDialog" role="dialog" aria-modal="true" aria-labelledby="user-details-title" @keydown.tab="trap($event)" class="flex max-h-[90dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex shrink-0 items-center justify-between gap-4 border-b border-stone-200 p-5 sm:p-6">
                    <div><p class="mb-1 text-xs font-semibold uppercase tracking-widest text-red-700">Account information</p><h2 id="user-details-title" class="text-xl font-bold text-slate-900" x-text="deleteMode ? 'Delete account' : 'User details'">User details</h2></div>
                    <button x-ref="closeDetails" type="button" @click="close()" :disabled="submitting" aria-label="Close user details" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-stone-50 text-slate-700 hover:bg-stone-100 disabled:opacity-50"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
                </header>
                <div x-ref="userBody" class="min-h-0 overflow-y-auto overscroll-contain p-5 sm:p-6">
                    <template x-if="selected && !deleteMode">
                        <div>
                            <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl bg-stone-50 p-4"><div class="min-w-0"><h3 class="break-words text-lg font-bold text-slate-900" x-text="selected.name"></h3><p class="mt-2 text-xs text-slate-500"><span x-text="selected.role"></span> · #<span x-text="selected.id"></span></p></div><span class="rounded-lg px-2.5 py-1 text-xs font-semibold" :class="selected.verified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'" x-text="selected.verified ? 'Verified' : 'Unverified'"></span></div>
                            <p x-show="!selected.hasProfile" class="mt-4 rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm text-amber-800">This account has not completed its profile yet.</p>
                            <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                                <template x-for="field in selected.fields" :key="field.label"><div :class="field.label === 'Address' ? 'sm:col-span-2' : ''"><dt class="text-xs font-semibold text-slate-500" x-text="field.label"></dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800" x-text="field.value"></dd></div></template>
                                <div class="sm:col-span-2"><dt class="text-xs font-semibold text-slate-500">Registered</dt><dd class="mt-2 text-sm text-slate-800" x-text="selected.joined"></dd></div>
                            </dl>
                        </div>
                    </template>
                    <form id="delete-user-form" x-ref="deleteForm" x-show="deleteMode" x-cloak method="POST" :action="selected?.deletionUrl" @submit.prevent="submitDeletion()">
                        @csrf
                        @method('DELETE')
                        <fieldset :disabled="submitting" class="space-y-5">
                            <legend class="sr-only">Confirm account deletion</legend>
                            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-900">
                                <p class="font-semibold">Permanently delete <span class="break-words" x-text="selected?.name"></span> (account #<span x-text="selected?.id"></span>)?</p>
                                <p class="mt-2">This cannot be undone. All login sessions will be revoked, personal profile details removed, job posts closed, and active applications or engagements ended.</p>
                                <p class="mt-2">Shared work, payment, chat, review, and safety records are retained. Deletion does not issue a refund or settle outstanding obligations.</p>
                            </div>
                            <div x-show="deletionErrors.length" x-ref="deletionError" tabindex="-1" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                                <template x-for="(message, index) in deletionErrors" :key="index"><p x-text="message"></p></template>
                            </div>
                            <div>
                                <label for="deletion-reason" class="mb-2 block text-sm font-semibold text-slate-700">Reason for deletion</label>
                                <textarea id="deletion-reason" x-ref="deletionReason" name="reason" required minlength="5" maxlength="1000" rows="3" aria-describedby="deletion-reason-help" class="w-full rounded-xl border border-stone-300 p-3 text-sm focus:outline-red-700"></textarea>
                                <p id="deletion-reason-help" class="mt-1 text-xs text-slate-500">Recorded with your admin ID for accountability. Use plain text, 5–1,000 characters.</p>
                            </div>
                            <div>
                                <label for="deletion-confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Type DELETE to confirm</label>
                                <input id="deletion-confirmation" name="confirmation" type="text" required pattern="DELETE" autocomplete="off" spellcheck="false" class="min-h-[44px] w-full rounded-xl border border-stone-300 px-3 text-sm focus:outline-red-700">
                            </div>
                            <div>
                                <label for="deletion-password" class="mb-2 block text-sm font-semibold text-slate-700">Your admin password</label>
                                <input id="deletion-password" name="current_password" type="password" required autocomplete="current-password" class="min-h-[44px] w-full rounded-xl border border-stone-300 px-3 text-sm focus:outline-red-700">
                            </div>
                            <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-slate-700">
                                <input name="acknowledged" type="checkbox" value="1" required class="mt-1 h-4 w-4 shrink-0 accent-red-700">
                                <span>I understand the consequences and authorize permanent deletion of this account.</span>
                            </label>
                        </fieldset>
                    </form>
                </div>
                <footer class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-stone-200 bg-stone-50 p-4">
                    <button x-show="!deleteMode" x-ref="deleteButton" type="button" @click="confirmDeletion()" class="mr-auto min-h-[44px] rounded-xl border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Delete account</button>
                    <button x-show="!deleteMode" type="button" @click="close()" class="min-h-[44px] rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-stone-100">Close</button>
                    <a x-show="!deleteMode" :href="selected?.verificationUrl" class="inline-flex min-h-[44px] items-center rounded-xl bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800">View documents</a>
                    <button x-show="deleteMode" type="button" @click="cancelDeletion()" :disabled="submitting" class="min-h-[44px] rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-stone-100 disabled:opacity-50">Cancel</button>
                    <button x-show="deleteMode" type="submit" form="delete-user-form" :disabled="submitting" class="min-h-[44px] rounded-xl bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-50" x-text="submitting ? 'Deleting…' : 'Permanently delete account'">Permanently delete account</button>
                </footer>
            </section>
        </div>
    </div>
    <script>
        function adminUserAccounts() {
            return {
                open: false, selected: null, returnFocus: null, previousOverflow: '',
                deleteMode: false, submitting: false, deletionErrors: [],
                showDetails(data, button) {
                    this.selected = data;
                    this.returnFocus = button;
                    this.deleteMode = false;
                    this.deletionErrors = [];
                    this.$refs.deleteForm.reset();
                    this.previousOverflow = document.body.style.overflow;
                    document.body.style.overflow = 'hidden';
                    this.open = true;
                    this.$nextTick(() => requestAnimationFrame(() => {
                        if (!this.open || this.deleteMode) return;
                        this.$refs.userBody.scrollTop = 0;
                        this.$refs.closeDetails.focus();
                    }));
                },
                close() {
                    if (this.submitting) return;
                    this.open = false;
                    this.$refs.deleteForm.reset();
                    document.body.style.overflow = this.previousOverflow;
                    this.$nextTick(() => this.returnFocus?.focus());
                },
                confirmDeletion() {
                    this.deleteMode = true;
                    this.deletionErrors = [];
                    this.$nextTick(() => requestAnimationFrame(() => {
                        if (!this.open || !this.deleteMode) return;
                        this.$refs.userBody.scrollTop = 0;
                        this.$refs.deletionReason.focus({ preventScroll: true });
                    }));
                },
                cancelDeletion() {
                    if (this.submitting) return;
                    this.$refs.deleteForm.reset();
                    this.deleteMode = false;
                    this.deletionErrors = [];
                    this.$nextTick(() => requestAnimationFrame(() => {
                        if (this.open && !this.deleteMode) this.$refs.deleteButton.focus();
                    }));
                },
                async submitDeletion() {
                    if (this.submitting || !this.selected || !this.$refs.deleteForm.reportValidity()) return;
                    const body = new FormData(this.$refs.deleteForm);
                    this.submitting = true;
                    this.deletionErrors = [];
                    try {
                        const response = await fetch(this.selected.deletionUrl, {
                            method: 'POST', body, credentials: 'same-origin',
                            headers: { Accept: 'application/json' },
                        });
                        if (response.ok) {
                            window.location.reload();
                            return;
                        }
                        if (response.status === 422) {
                            const data = await response.json();
                            this.deletionErrors = Object.values(data.errors ?? {}).flat();
                        } else {
                            this.deletionErrors = [response.status === 401 || response.status === 419
                                ? 'Your admin session expired. Reload the page and sign in again.'
                                : response.status === 404
                                    ? 'This account is no longer available. Reload the user list.'
                                    : response.status === 429
                                        ? 'Too many deletion attempts. Wait a minute before trying again.'
                                        : 'Account deletion could not be completed. Please try again.'];
                        }
                    } catch {
                        this.deletionErrors = ['Could not confirm the result. Check your connection and reload the user list before retrying.'];
                    }
                    this.submitting = false;
                    this.$refs.deleteForm.elements.current_password.value = '';
                    this.$nextTick(() => this.$refs.deletionError.focus());
                },
                trap(event) {
                    const items = [...this.$refs.userDialog.querySelectorAll('button, a[href], input:not([type="hidden"]), textarea')]
                        .filter(el => !el.matches(':disabled') && el.getClientRects().length);
                    if (event.shiftKey && document.activeElement === items[0]) {
                        event.preventDefault(); items.at(-1)?.focus();
                    } else if (!event.shiftKey && document.activeElement === items.at(-1)) {
                        event.preventDefault(); items[0]?.focus();
                    }
                },
                destroy() { if (this.open) document.body.style.overflow = this.previousOverflow; },
            };
        }
    </script>
</x-admin-layout>
