<x-admin-layout>
    @push('header-title') Account @endpush
    <style>[x-cloak] { display: none !important; }</style>
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
                x-data="{ ...@js($profileState), get emailChanged() { return this.email.trim().toLowerCase() !== this.originalEmail; }, get changed() { return this.name.trim() !== this.originalName || this.emailChanged; }, start() { this.editing = true; this.$nextTick(() => requestAnimationFrame(() => this.$refs.nameInput.focus())); }, cancel() { this.name = this.originalName; this.email = this.originalEmail; this.$refs.emailPassword.value = ''; this.editing = false; this.showErrors = false; this.$nextTick(() => this.$refs.editDetails.focus()); } }">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="text-base font-bold text-slate-900">Personal details</h2><p class="mt-1 text-xs leading-6 text-slate-500">This name appears in your admin account.</p></div>
                    <button x-ref="editDetails" type="button" x-show="!editing" @click="start()" :aria-expanded="editing" aria-controls="personal-details-form" class="min-h-[40px] shrink-0 rounded-xl border border-red-100 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100">Edit</button>
                </div>
                <dl x-show="!editing" class="mt-5 space-y-4">
                    <div><dt class="text-xs font-semibold text-slate-500">Name</dt><dd class="mt-2 break-words text-sm text-slate-800">{{ $admin->name }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Email address</dt><dd class="mt-2 break-all text-sm text-slate-800">{{ $admin->email }}</dd></div>
                </dl>
                @if($errors->profile->any())<div x-show="editing && showErrors" role="alert" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->profile->first() }}</div>@endif
                <form id="personal-details-form" x-ref="profileForm" x-show="editing" x-cloak method="POST" action="{{ route('admin.account.update') }}" @submit="if (!editing || !changed) $event.preventDefault()" class="mt-5 space-y-4">
                    @csrf @method('PATCH')
                    <div>
                        <label for="admin-name" class="mb-2 block text-xs font-semibold text-slate-600">Name</label>
                        <input x-ref="nameInput" id="admin-name" name="name" x-model="name" value="{{ $profileState['name'] }}" :disabled="!editing" type="text" required maxlength="100" autocomplete="name" pattern="[^<>]+"
                            @blur="name = name.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f-\u009f\u202a-\u202e\u2066-\u2069\ufeff]/g, '').trim()"
                            class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                    </div>
                    <div>
                        <label for="admin-email" class="mb-2 block text-xs font-semibold text-slate-600">Email address</label>
                        <input id="admin-email" name="email" x-model="email" value="{{ $profileState['email'] }}" :disabled="!editing" type="email" required maxlength="255" autocomplete="username"
                            @blur="email = email.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f-\u009f\u202a-\u202e\u2066-\u2069\ufeff]/g, '').trim().toLowerCase()"
                            class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                    </div>
                    <div x-show="emailChanged">
                        <label for="profile-current-password" class="mb-2 block text-xs font-semibold text-slate-600">Current password</label>
                        <input x-ref="emailPassword" id="profile-current-password" name="current_password" type="password" :disabled="!editing || !emailChanged" :required="editing && emailChanged" autocomplete="current-password" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Confirm your current password to change your email address.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="cancel()" class="min-h-[44px] rounded-xl border border-stone-200 px-4 text-sm font-semibold text-slate-700 hover:bg-stone-50">Cancel</button>
                        <button type="submit" :disabled="!editing || !changed" class="min-h-[44px] rounded-xl bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40">Save details</button>
                    </div>
                </form>
            </section>
            <section id="password-details" class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6"
                x-data="{ editing: @js($errors->password->any()), showErrors: @js($errors->password->any()), start() { this.editing = true; this.$nextTick(() => requestAnimationFrame(() => this.$refs.currentPassword.focus())); }, cancel() { this.$refs.passwordForm.reset(); this.$refs.passwordForm.querySelectorAll('input[type=password]').forEach(input => input.value = ''); this.editing = false; this.showErrors = false; this.$nextTick(() => this.$refs.editPassword.focus()); } }">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="text-base font-bold text-slate-900">Change password</h2><p class="mt-1 text-xs leading-6 text-slate-500">Choose a new password with at least 8 characters.</p></div>
                    <button x-ref="editPassword" type="button" x-show="!editing" @click="start()" :aria-expanded="editing" aria-controls="password-details-form" class="min-h-[40px] shrink-0 rounded-xl border border-red-100 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100">Edit</button>
                </div>
                <p x-show="!editing" class="mt-5 rounded-xl bg-stone-50 p-4 text-sm leading-6 text-slate-500">Your password is kept private. Select Edit to update it.</p>
                @if($errors->password->any())<div x-show="editing && showErrors" role="alert" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->password->first() }}</div>@endif
                <form id="password-details-form" x-ref="passwordForm" x-show="editing" x-cloak method="POST" action="{{ route('admin.account.password') }}" @submit="if (!editing) $event.preventDefault()" class="mt-5 space-y-4">
                    @csrf @method('PUT')
                    <div><label for="password-current" class="mb-2 block text-xs font-semibold text-slate-600">Current password</label><input x-ref="currentPassword" id="password-current" name="current_password" type="password" :disabled="!editing" required autocomplete="current-password" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700"></div>
                    <div><label for="password-new" class="mb-2 block text-xs font-semibold text-slate-600">New password</label><input id="password-new" name="password" type="password" :disabled="!editing" required minlength="8" maxlength="255" autocomplete="new-password" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700"></div>
                    <div><label for="password-confirm" class="mb-2 block text-xs font-semibold text-slate-600">Confirm new password</label><input id="password-confirm" name="password_confirmation" type="password" :disabled="!editing" required minlength="8" maxlength="255" autocomplete="new-password" class="min-h-[44px] w-full rounded-xl border border-stone-200 bg-stone-50 px-3 text-sm text-slate-900 focus:outline-red-700"></div>
                    <div class="flex flex-wrap gap-2"><button type="button" @click="cancel()" class="min-h-[44px] rounded-xl border border-stone-200 px-4 text-sm font-semibold text-slate-700 hover:bg-stone-50">Cancel</button><button type="submit" :disabled="!editing" class="min-h-[44px] rounded-xl bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-40">Update password</button></div>
                </form>
            </section>
        </div>
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
            <div><h2 class="text-sm font-semibold text-slate-900">Sign out</h2><p class="mt-1 text-xs text-slate-500">End your current admin session.</p></div>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="min-h-[44px] rounded-xl border border-rose-200 bg-rose-50 px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100">Sign out</button></form>
        </section>
    </div>
</x-admin-layout>
