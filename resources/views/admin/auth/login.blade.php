@php
    $landingUrl = config('admin.domain') ? config('app.url') : url('/');
    $loginError = $loginThrottleMessage ?? $errors->first();
    $emailValue = $loginEmail ?? old('email', '');
    $emailValue = is_string($emailValue) ? $emailValue : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiskarTech</title>
    <meta name="theme-color" content="#FDFBF7">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen min-h-[100svh] flex-col bg-[#FDFBF7] font-['Poppins',sans-serif] text-slate-900 antialiased selection:bg-red-100 selection:text-red-900">
    <a href="#login-form" class="sr-only rounded-xl bg-white px-4 py-3 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">Skip to sign in</a>

    <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-6 sm:px-8">
        <a href="{{ $landingUrl }}" aria-label="DiskarTech home" class="inline-flex shrink-0 items-center gap-2.5 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700">
            <img src="{{ asset('logo.png') }}" alt="" width="36" height="36" class="h-9 w-9 rounded-xl object-cover">
            <span class="text-base font-bold tracking-tight sm:text-lg">DiskarTech<span class="text-red-700">.</span></span>
        </a>
        <a href="{{ $landingUrl }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg text-xs font-medium text-slate-600 transition hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700 sm:text-sm">
            <x-landing-icon name="arrow" class="h-4 w-4 rotate-180" />
            <span><span class="hidden sm:inline">Back to </span>website</span>
        </a>
    </header>

    <main class="mx-auto flex w-full max-w-6xl flex-1 items-center px-4 py-3 sm:px-8 sm:py-6">
        <div class="grid w-full overflow-hidden rounded-[28px] border border-stone-200/80 bg-white shadow-[0_20px_80px_-40px_rgba(15,23,42,0.2)] lg:grid-cols-[0.95fr_1fr] lg:rounded-[32px]">
            <aside class="relative m-2 hidden min-h-[590px] flex-col justify-between overflow-hidden rounded-[24px] bg-slate-900 p-10 text-white lg:flex" aria-labelledby="community-title">
                <div aria-hidden="true" class="pointer-events-none absolute -right-36 -top-28 h-[380px] w-[380px] rounded-full border border-white/10"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-16 h-[280px] w-[280px] rounded-full border border-white/5"></div>
                <div class="relative">
                    <span class="inline-flex items-center gap-2 text-[10px] font-medium uppercase tracking-[0.18em] text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-red-400"></span> The people behind the platform</span>
                    <div class="mb-7 mt-14 flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-red-400"><x-landing-icon name="shield" class="h-6 w-6" /></div>
                    <h2 id="community-title" class="max-w-sm text-[38px] font-semibold leading-[1.15] tracking-[-0.045em]">A stronger<br>community.<br><span class="text-red-400">Starts with you.</span></h2>
                    <p class="mt-5 max-w-xs text-sm leading-7 text-slate-400">Help students and hirers connect with confidence, one opportunity at a time.</p>
                </div>
                <div class="relative mt-10">
                    <ul class="space-y-4 text-xs font-medium text-slate-300">
                        <li class="flex items-center gap-3"><x-landing-icon name="user" class="h-4 w-4 text-slate-500" /> Account verification</li>
                        <li class="flex items-center gap-3"><x-landing-icon name="briefcase" class="h-4 w-4 text-slate-500" /> Job moderation</li>
                        <li class="flex items-center gap-3"><x-landing-icon name="message" class="h-4 w-4 text-slate-500" /> Community reports</li>
                    </ul>
                    <div class="mt-8 border-t border-white/10 pt-5 text-[10px] leading-5 text-slate-500">Built for our community.<br><span class="text-slate-400">Pinamalayan, Oriental Mindoro</span></div>
                </div>
            </aside>

            <section class="flex min-w-0 flex-col justify-center px-6 py-10 sm:px-10 sm:py-14 lg:px-12" aria-labelledby="login-title">
                <div class="mx-auto w-full max-w-sm">
                    <p class="mb-5 flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-red-700"><span class="h-px w-5 bg-red-700"></span> Admin console</p>
                    <h1 id="login-title" class="text-3xl font-semibold leading-tight tracking-[-0.045em] sm:text-4xl">Welcome back.</h1>
                    <p id="login-help" class="mt-3 text-sm leading-7 text-slate-500">Sign in to manage the DiskarTech community.</p>

                    @if($loginError)
                        <div id="login-error" role="alert" class="mt-6 flex items-start gap-3 rounded-2xl border border-red-100 bg-red-50 px-4 py-3.5 text-red-800">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v6m0 4h.01" /></svg>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold">{{ isset($loginThrottleMessage) ? 'Please wait a moment' : 'Unable to sign in' }}</p>
                                <p class="mt-1 break-words text-xs leading-6">{{ $loginError }}</p>
                            </div>
                        </div>
                    @endif

                    <form id="login-form" action="{{ route('admin.login.submit') }}" method="POST" class="mt-8 space-y-5" aria-describedby="login-help{{ $loginError ? ' login-error' : '' }}">
                        @csrf
                        <div>
                            <label for="email" class="mb-2 block text-xs font-semibold text-slate-700">Email address</label>
                            <input id="email" type="email" name="email" value="{{ $emailValue }}" maxlength="255" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required placeholder="you@example.com" @if($errors->has('email')) aria-invalid="true" aria-describedby="login-error" @endif class="min-h-[52px] w-full rounded-xl border border-stone-200 bg-stone-50/70 px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-stone-400 focus:border-red-700 focus:bg-white focus:ring-2 focus:ring-red-700/10 sm:text-sm">
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-xs font-semibold text-slate-700">Password</label>
                            <div class="relative">
                                <input id="password" type="password" name="password" maxlength="255" autocomplete="current-password" required placeholder="Enter your password" @if($errors->has('password')) aria-invalid="true" aria-describedby="login-error" @endif class="min-h-[52px] w-full rounded-xl border border-stone-200 bg-stone-50/70 py-3 pl-4 pr-14 text-base text-slate-900 outline-none transition placeholder:text-stone-400 focus:border-red-700 focus:bg-white focus:ring-2 focus:ring-red-700/10 sm:text-sm">
                                <button id="toggle-password" type="button" aria-label="Show password" aria-controls="password" aria-pressed="false" class="absolute right-1 top-1 hidden h-11 w-11 items-center justify-center rounded-lg text-slate-400 transition hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700">
                                    <svg data-eye-open class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                                    <svg data-eye-closed class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3 3.8M6 6.5A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.5-1.5" /></svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center gap-3 rounded-xl bg-red-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700">
                            Sign in <x-landing-icon name="arrow" class="h-4 w-4" />
                        </button>
                    </form>

                    <p class="mt-7 border-t border-stone-100 pt-5 text-center text-[11px] leading-6 text-slate-400">For authorized DiskarTech administrators.</p>
                </div>
            </section>
        </div>
    </main>

    <footer class="px-5 py-6 text-center text-[10px] leading-5 text-stone-400">&copy; {{ now()->year }} DiskarTech. Local work. Your kind of schedule.</footer>

    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('toggle-password');

        if (passwordInput && passwordToggle) {
            passwordToggle.classList.replace('hidden', 'inline-flex');
            passwordToggle.addEventListener('click', () => {
                const showPassword = passwordInput.type === 'password';
                passwordInput.type = showPassword ? 'text' : 'password';
                passwordToggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
                passwordToggle.setAttribute('aria-pressed', String(showPassword));
                passwordToggle.querySelector('[data-eye-open]').classList.toggle('hidden', showPassword);
                passwordToggle.querySelector('[data-eye-closed]').classList.toggle('hidden', !showPassword);
            });
        }
    </script>
</body>
</html>
