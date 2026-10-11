<x-admin-layout>
    @push('header-title') Account @endpush
    <style>[x-cloak] { display: none !important; }</style>
    <script>
        function adminProfileEditor(state, checkUrl) {
            return {
                ...state, emailStatus: 'idle', emailMessage: '', checkedEmail: '', emailTimer: null, emailController: null, emailRevision: 0,
                get normalizedEmail() { return this.email.trim().toLowerCase(); },
                get emailChanged() { return this.normalizedEmail !== this.originalEmail.toLowerCase(); },
                get changed() { return this.name.trim() !== this.originalName || this.emailChanged; },
                get canSave() { return this.editing && this.changed && this.name.trim().length > 0 && this.$refs.nameInput.checkValidity() && (!this.emailChanged || (this.emailStatus === 'available' && this.checkedEmail === this.normalizedEmail)); },
                init() { if (this.editing && this.emailChanged) this.$nextTick(() => this.queueEmailCheck()); },
                start() { this.editing = true; this.$nextTick(() => requestAnimationFrame(() => this.$refs.nameInput.focus())); },
                stopEmailCheck() { clearTimeout(this.emailTimer); ++this.emailRevision; this.emailController?.abort(); },
                queueEmailCheck(event) {
                    if (!event?.isComposing && this.checkedEmail === this.normalizedEmail && ['available', 'taken'].includes(this.emailStatus)) return;
                    this.stopEmailCheck(); this.checkedEmail = ''; this.emailStatus = 'idle'; this.emailMessage = '';
                    if (!this.editing || !this.emailChanged || event?.isComposing) return;
                    if (!this.normalizedEmail || !this.$refs.emailInput.checkValidity()) { this.emailStatus = 'invalid'; this.emailMessage = 'Enter a valid email address.'; return; }
                    this.emailStatus = 'checking'; this.emailMessage = 'Checking email availability…';
                    this.emailTimer = setTimeout(() => this.checkEmail(), 600);
                },
                async checkEmail() {
                    this.stopEmailCheck();
                    if (!this.editing || !this.emailChanged || !this.$refs.emailInput.checkValidity()) return;
                    const email = this.normalizedEmail, revision = this.emailRevision;
                    const controller = new AbortController(); this.emailController = controller;
                    this.emailStatus = 'checking'; this.emailMessage = 'Checking email availability…';
                    const timeout = setTimeout(() => controller.abort(), 10000);
                    try {
                        const response = await fetch(checkUrl, {
                            method: 'POST', credentials: 'same-origin', signal: controller.signal,
                            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.$refs.profileForm.querySelector('[name=_token]').value },
                            body: JSON.stringify({ email })
                        });
                        let message = 'Unable to check email. Please try again.';
                        if (response.status === 429) message = 'Too many checks. Please wait a minute and try again.';
                        if ([401, 403, 419].includes(response.status)) message = 'Your session has expired. Refresh the page and sign in again.';
                        if (!response.ok && response.status !== 422) throw new Error(message);
                        const data = await response.json();
                        if (revision !== this.emailRevision || !this.editing || email !== this.normalizedEmail) return;
                        if (response.status === 422) {
                            this.emailStatus = 'invalid'; this.emailMessage = data.errors?.email?.[0] || 'Enter a valid email address.'; return;
                        }
                        if (typeof data.available !== 'boolean') throw new Error(message);
                        this.checkedEmail = email; this.emailStatus = data.available ? 'available' : 'taken';
                        this.emailMessage = data.available ? 'This email address is available.' : 'This email address is already used by another admin.';
                    } catch (error) {
                        if (revision !== this.emailRevision || !this.editing || email !== this.normalizedEmail) return;
                        this.emailStatus = 'error'; this.emailMessage = error.name === 'AbortError' ? 'Email check timed out. Please try again.' : (error instanceof TypeError || error instanceof SyntaxError ? 'Unable to check email. Please try again.' : error.message);
                    } finally { clearTimeout(timeout); }
                },
                cancel() {
                    this.stopEmailCheck(); this.name = this.originalName; this.email = this.originalEmail;
                    this.$refs.emailPassword.value = ''; this.emailStatus = 'idle'; this.emailMessage = ''; this.checkedEmail = '';
                    this.editing = false; this.showErrors = false; this.$nextTick(() => this.$refs.editDetails.focus());
                },
                destroy() { this.stopEmailCheck(); }
            };
        }
        function adminPasswordEditor(editing, checks) {
            return {
                editing, showErrors: editing, checks, current: '', password: '', confirmation: '', confirmationTouched: false,
                get lengthValid() { return this.password.length >= 8 && this.password.length <= 255; },
                meets(pattern) { return new RegExp(pattern).test(this.password); },
                get matches() { return !!this.password && this.password === this.confirmation; },
                get different() { return !!this.password && this.password !== this.current; },
                get canSave() { return this.editing && !!this.current && this.lengthValid && this.checks.every(check => this.meets(check.pattern)) && this.matches && this.different; },
                start() { this.editing = true; this.$nextTick(() => requestAnimationFrame(() => this.$refs.currentPassword.focus())); },
                cancel() {
                    this.current = ''; this.password = ''; this.confirmation = ''; this.confirmationTouched = false;
                    this.$refs.passwordForm.querySelectorAll('input[type=password]').forEach(input => input.value = '');
                    this.editing = false; this.showErrors = false; this.$nextTick(() => this.$refs.editPassword.focus());
                }
            };
        }
    </script>

    <div class="mx-auto w-full max-w-5xl space-y-6">
        <section>
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-red-700">Your admin account</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Account</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Manage your personal details and sign-in password.</p>
        </section>
        @if(session('success'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <section class="grid grid-cols-[56px_minmax(0,1fr)] items-center gap-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:flex sm:flex-wrap sm:p-6">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-xl font-bold text-red-700">{{ mb_strtoupper(mb_substr($admin->name ?: 'Admin', 0, 1)) }}</span>
            <div class="min-w-0 flex-1"><h2 class="break-words text-lg font-bold text-slate-900">{{ $admin->name }}</h2><p class="mt-1 break-all text-sm text-slate-500">{{ $admin->email }}</p></div>
            <span class="col-span-2 justify-self-start rounded-lg bg-stone-100 px-3 py-1.5 text-xs font-semibold text-slate-600 sm:ml-auto">Administrator</span>
        </section>
        @php
            $profileState = [
                'editing' => $errors->profile->any(), 'showErrors' => $errors->profile->any(),
                'name' => $errors->profile->any() && is_string(old('name')) ? old('name') : $admin->name,
                'email' => $errors->profile->any() && is_string(old('email')) ? old('email') : $admin->email,
                'originalName' => $admin->name, 'originalEmail' => $admin->email,
            ];
        @endphp
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <section id="personal-details" class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6"
                x-data="adminProfileEditor(@js($profileState), @js(route('admin.account.check-email', [], false)))">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="text-base font-bold text-slate-900">Personal details</h2><p class="mt-1 text-xs leading-6 text-slate-500">This name appears in your admin account.</p></div>
                    <button x-ref="editDetails" type="button" x-show="!editing" @click="start()" :aria-expanded="editing" aria-controls="personal-details-form" class="min-h-[40px] shrink-0 rounded-xl border border-red-100 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100">Edit</button>
                </div>
                <dl x-show="!editing" class="mt-5 space-y-4">
                    <div><dt class="text-xs font-semibold text-slate-500">Name</dt><dd class="mt-2 break-words text-sm text-slate-800">{{ $admin->name }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Email address</dt><dd class="mt-2 break-all text-sm text-slate-800">{{ $admin->email }}</dd></div>
                </dl>
                @if($errors->profile->any())<div x-show="editing && showErrors" role="alert" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->profile->first() }}</div>@endif
                <form id="personal-details-form" x-ref="profileForm" x-show="editing" x-cloak method="POST" action="{{ route('admin.account.update') }}" @submit="if (!canSave) $event.preventDefault()" class="mt-5 space-y-4">
                    @csrf @method('PATCH')
                    <div>
                        <label for="admin-name" class="mb-2 block text-xs font-semibold text-slate-600">Name</label>
                        <input x-ref="nameInput" id="admin-name" name="name" x-model="name" value="{{ $profileState['name'] }}" :disabled="!editing" type="text" required maxlength="100" autocomplete="name" pattern="[^<>]+"
                            @blur="name = name.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f-\u009f\u202a-\u202e\u2066-\u2069\ufeff]/g, '').trim()"
                            class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                    </div>
                    <div>
                        <label for="admin-email" class="mb-2 block text-xs font-semibold text-slate-600">Email address</label>
                        <input x-ref="emailInput" id="admin-email" name="email" x-model="email" value="{{ $profileState['email'] }}" :disabled="!editing" type="email" required maxlength="255" autocomplete="username" aria-describedby="email-live-message" :aria-invalid="['invalid', 'taken'].includes(emailStatus)" @input="queueEmailCheck($event)" @compositionend="queueEmailCheck($event)"
                            @blur="email = email.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f-\u009f\u202a-\u202e\u2066-\u2069\ufeff]/g, '').trim().toLowerCase(); queueEmailCheck()"
                            class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                        <p id="email-live-message" x-show="emailMessage" x-text="emailMessage" aria-live="polite" class="mt-2 text-xs leading-5" :class="emailStatus === 'available' ? 'text-emerald-700' : ['invalid', 'taken', 'error'].includes(emailStatus) ? 'text-rose-700' : 'text-slate-500'"></p>
                        <button x-show="emailStatus === 'error'" type="button" @click="checkEmail()" class="mt-2 text-xs font-semibold text-red-700 underline">Retry email check</button>
                    </div>
                    <div x-show="emailChanged">
                        <label for="profile-current-password" class="mb-2 block text-xs font-semibold text-slate-600">Current password</label>
                        <input x-ref="emailPassword" id="profile-current-password" name="current_password" type="password" :disabled="!editing || !emailChanged" :required="editing && emailChanged" autocomplete="current-password" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Confirm your current password to change your email address.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="cancel()" class="min-h-[44px] rounded-xl border border-stone-200 px-4 text-sm font-semibold text-slate-700 hover:bg-stone-50">Cancel</button>
                        <button type="submit" :disabled="!canSave" class="min-h-[44px] rounded-xl bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40">Save details</button>
                    </div>
                </form>
            </section>
            <section id="password-details" class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6"
                x-data="adminPasswordEditor(@js($errors->password->any()), @js($passwordChecks))">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="text-base font-bold text-slate-900">Change password</h2><p class="mt-1 text-xs leading-6 text-slate-500">Use at least 8 characters with an uppercase letter, number, and special character.</p></div>
                    <button x-ref="editPassword" type="button" x-show="!editing" @click="start()" :aria-expanded="editing" aria-controls="password-details-form" class="min-h-[40px] shrink-0 rounded-xl border border-red-100 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100">Edit</button>
                </div>
                <p x-show="!editing" class="mt-5 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-slate-500">Your password is kept private. Select Edit to update it.</p>
                @if($errors->password->any())<div x-show="editing && showErrors" role="alert" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->password->first() }}</div>@endif
                <form id="password-details-form" x-ref="passwordForm" x-show="editing" x-cloak method="POST" action="{{ route('admin.account.password') }}" @submit="if (!canSave) $event.preventDefault()" class="mt-5 space-y-4">
                    @csrf @method('PUT')
                    <div><label for="password-current" class="mb-2 block text-xs font-semibold text-slate-600">Current password</label><input x-ref="currentPassword" id="password-current" name="current_password" x-model="current" type="password" :disabled="!editing" required autocomplete="current-password" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700"></div>
                    <div><label for="password-new" class="mb-2 block text-xs font-semibold text-slate-600">New password</label><input id="password-new" name="password" x-model="password" type="password" :disabled="!editing" required minlength="8" maxlength="255" autocomplete="new-password" aria-describedby="password-rules" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                        <ul id="password-rules" class="mt-3 space-y-1.5 rounded-xl bg-stone-50 p-3 text-xs leading-5">
                            <li :class="lengthValid ? 'text-emerald-700' : 'text-slate-500'"><span aria-hidden="true" x-text="lengthValid ? '✓ ' : '○ '"></span>8–255 characters <span class="sr-only" x-text="lengthValid ? '(met)' : '(not met)'"></span></li>
                            <template x-for="check in checks" :key="check.pattern"><li :class="meets(check.pattern) ? 'text-emerald-700' : 'text-slate-500'"><span aria-hidden="true" x-text="meets(check.pattern) ? '✓ ' : '○ '"></span><span x-text="check.label"></span><span class="sr-only" x-text="meets(check.pattern) ? ' (met)' : ' (not met)'"></span></li></template>
                        </ul>
                        <p x-show="password && current && !different" class="mt-2 text-xs text-rose-700" role="status">Your new password must be different from your current password.</p>
                    </div>
                    <div><label for="password-confirm" class="mb-2 block text-xs font-semibold text-slate-600">Confirm new password</label><input id="password-confirm" name="password_confirmation" x-model="confirmation" @input="confirmationTouched = true" @blur="confirmationTouched = true" type="password" :disabled="!editing" required minlength="8" maxlength="255" autocomplete="new-password" aria-describedby="password-match-message" :aria-invalid="confirmationTouched && !matches" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                        <p id="password-match-message" x-show="confirmationTouched" x-text="matches ? 'Passwords match.' : 'Passwords do not match.'" aria-live="polite" class="mt-2 text-xs" :class="matches ? 'text-emerald-700' : 'text-rose-700'"></p>
                    </div>
                    <div class="flex flex-wrap gap-2"><button type="button" @click="cancel()" class="min-h-[44px] rounded-xl border border-stone-200 px-4 text-sm font-semibold text-slate-700 hover:bg-stone-50">Cancel</button><button type="submit" :disabled="!canSave" class="min-h-[44px] rounded-xl bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40">Update password</button></div>
                </form>
            </section>
        </div>
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
            <div><h2 class="text-sm font-semibold text-slate-900">Sign out</h2><p class="mt-1 text-xs text-slate-500">End your current admin session.</p></div>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="min-h-[44px] rounded-xl border border-rose-200 bg-rose-50 px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100">Sign out</button></form>
        </section>
    </div>
</x-admin-layout>
