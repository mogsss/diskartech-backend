@php
    $downloadUrl = 'https://expo.dev/accounts/mogsssss/projects/DiskarTech/builds/383a624e-80d4-4962-a98f-c87b33f90f54';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiskarTech</title>
    <meta name="description" content="Discover local work in Pinamalayan with DiskarTech. Explore nearby jobs, AI recommendations based on your skills and schedule, and opportunities from employers and household hirers.">
    <meta name="theme-color" content="#FDFBF7">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .landing-phone-stage {
            perspective: 1100px;
        }

        .landing-phone-shadow {
            position: absolute;
            bottom: 26px;
            left: 50%;
            width: 220px;
            height: 32px;
            border-radius: 50%;
            background: rgb(15 23 42 / 22%);
            filter: blur(16px);
            transform: translateX(-50%) rotate(-6deg);
        }

        .landing-phone {
            --phone-tilt-x: 0deg;
            --phone-tilt-y: 0deg;
            position: relative;
            z-index: 10;
            width: 272px;
            max-width: 100%;
            padding: 8px;
            border: 1px solid #64748b;
            border-radius: 42px;
            background: linear-gradient(135deg, #a0aaba 0%, #334155 12%, #0f172a 44%, #475569 90%, #94a3b8 100%);
            box-shadow: inset 0 0 0 2px #0f172a, 1px 1px 0 #cbd5e1;
            transform: rotateX(calc(7deg + var(--phone-tilt-x))) rotateY(calc(-16deg + var(--phone-tilt-y))) rotateZ(-5deg);
            transform-style: preserve-3d;
            transition: transform 350ms cubic-bezier(.2, .7, .2, 1);
        }

        .landing-phone::before {
            content: "";
            position: absolute;
            inset: -1px;
            border: 1px solid #64748b;
            border-radius: inherit;
            background: linear-gradient(120deg, #64748b, #0f172a 42%, #334155);
            box-shadow: 1px 1px #64748b, 2px 2px #475569, 3px 3px #475569,
                4px 4px #334155, 5px 5px #334155, 6px 6px #1e293b,
                7px 7px #1e293b, 8px 8px #0f172a,
                20px 28px 40px -12px rgb(15 23 42 / 35%);
            transform: translateZ(-14px);
        }

        .landing-phone-screen {
            position: relative;
            border: 1px solid rgb(148 163 184 / 20%);
            box-shadow: inset 0 0 0 2px #020617;
            transform: translateZ(1px);
        }

        .landing-phone-screen::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(115deg, rgb(255 255 255 / 11%), transparent 32%, transparent 68%, rgb(255 255 255 / 3%));
            pointer-events: none;
        }

        .landing-phone-button {
            position: absolute;
            left: -4px;
            top: 112px;
            width: 4px;
            height: 38px;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            background: linear-gradient(90deg, #0f172a, #64748b);
            transform: translateZ(-5px);
        }

        .landing-phone-button + .landing-phone-button {
            top: 160px;
        }

        .landing-phone-button.landing-phone-power {
            left: auto;
            right: -4px;
            top: 136px;
            height: 54px;
        }

        @media (min-width: 640px) {
            .landing-phone { width: 286px; }
        }

        @media (max-width: 639px) {
            .landing-phone {
                transform: rotateX(5deg) rotateY(-10deg) rotateZ(-4deg);
            }
        }

        @media (max-width: 399px) {
            .landing-phone {
                width: min(272px, calc(100% - 24px));
                min-width: 0;
                transform: rotateX(4deg) rotateY(-8deg) rotateZ(-2deg);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .landing-phone { transition: none; }
        }
    </style>
</head>
<body class="min-h-screen bg-[#FDFBF7] font-['Poppins',sans-serif] text-slate-900 antialiased selection:bg-red-100 selection:text-red-900">
    <a href="#main-content" class="sr-only rounded-xl bg-white px-4 py-3 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">Skip to content</a>

    <header class="border-b border-stone-200/70">
        <div class="mx-auto flex min-h-[88px] max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 lg:px-10">
            <a href="#" aria-label="DiskarTech home" class="flex shrink-0 items-center gap-2.5 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700">
                <img src="{{ asset('logo.png') }}" alt="" width="38" height="38" class="h-[38px] w-[38px] rounded-xl object-cover">
                <span class="text-base font-bold tracking-tight sm:text-lg">DiskarTech<span class="text-red-700">.</span></span>
            </a>
            <nav class="hidden items-center gap-7 text-sm font-medium text-slate-600 md:flex" aria-label="Main navigation">
                <a href="features" class="rounded-sm hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700">Features</a>
                <a href="how-it-works" class="rounded-sm hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700">How it works</a>
                <a href="for-hirers" class="rounded-sm hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700">For hirers</a>
            </nav>
            <a href="{{ $downloadUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-slate-900 px-4 text-xs font-semibold text-white transition hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900 sm:px-5 sm:text-sm">Get the app <span class="sr-only">(opens in a new tab)</span><x-landing-icon name="arrow" class="hidden h-4 w-4 sm:block" /></a>
        </div>
    </header>

    <main id="main-content">
        <section class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-14 pt-12 sm:px-8 sm:py-20 lg:grid-cols-[1.08fr_1fr] lg:gap-10 lg:px-10 lg:py-20" aria-labelledby="hero-title">
            <div class="min-w-0">
                <h1 id="hero-title" class="text-[clamp(2rem,10vw,2.5rem)] font-bold leading-[1.05] tracking-[-0.055em] lg:text-[clamp(2.5rem,5.5vw,4.7rem)]">
                    Your skills.<br>Your schedule.<br><span class="text-red-700">Your next move.</span>
                </h1>
                <p class="mt-6 max-w-[450px] text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">Find local work that fits your student life. Connect with employers and household hirers, and make room for what matters.</p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="#features" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-full bg-red-700 px-6 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700">Explore DiskarTech <x-landing-icon name="arrow" class="h-4 w-4" /></a>
                    <a href="#how-it-works" class="inline-flex min-h-12 items-center justify-center rounded-full border border-stone-300 px-6 text-sm font-semibold transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900">How it works</a>
                </div>
                <p class="mt-4 flex items-center gap-2 text-xs text-slate-500"><x-landing-icon name="android" class="h-4 w-4" /> Android app &middot; Download via Expo</p>
                <div class="mt-9 flex flex-wrap gap-x-5 gap-y-3 border-t border-stone-200 pt-6 text-xs font-medium text-slate-600">
                    <span class="inline-flex items-center gap-1.5"><x-landing-icon name="sparkles" class="h-4 w-4 text-red-700" /> AI recommendations</span>
                    <span class="inline-flex items-center gap-1.5"><x-landing-icon name="location" class="h-4 w-4 text-red-700" /> Nearby opportunities</span>
                    <span class="inline-flex items-center gap-1.5"><x-landing-icon name="calendar" class="h-4 w-4 text-red-700" /> Your availability</span>
                </div>
            </div>

            <figure class="min-w-0">
                <div class="landing-phone-stage relative flex min-h-[560px] items-center justify-center overflow-hidden px-4 py-10 sm:min-h-[600px]" data-phone-stage aria-hidden="true">
                    <span class="absolute left-6 top-6 text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-500">Opportunity, closer.</span>

                    <div class="landing-phone-shadow"></div>
                    <div class="landing-phone" data-phone>
                        <span class="landing-phone-button"></span>
                        <span class="landing-phone-button"></span>
                        <span class="landing-phone-button landing-phone-power"></span>
                        <div class="landing-phone-screen overflow-hidden rounded-[33px] bg-[#111827] px-3.5 pb-3 pt-2 text-white">
                            <div class="mx-auto mb-4 h-1 w-16 rounded-full bg-slate-700"></div>
                            <div class="mb-5 flex items-center justify-between"><x-landing-icon name="menu" class="h-4 w-4 text-slate-300" /><span class="text-sm font-bold tracking-tight">DiskarTech<span class="text-red-500">.</span></span><x-landing-icon name="bell" class="h-4 w-4 text-slate-300" /></div>
                            <p class="text-[20px] font-bold leading-6 tracking-tight">Find work that fits<br>your schedule.</p>
                            <p class="mt-1.5 text-[9px] leading-4 text-slate-400">Your next opportunity could be around the corner.</p>
                            <div class="my-4 flex flex-wrap gap-1.5 text-[9px] font-medium"><span class="rounded-full border border-slate-700 px-3 py-1.5 text-slate-300">All jobs</span><span class="rounded-full border border-slate-700 px-3 py-1.5 text-slate-300">Nearby</span><span class="flex items-center gap-1 rounded-full bg-red-700 px-3 py-1.5"><x-landing-icon name="sparkles" class="h-2.5 w-2.5" /> For You</span></div>
                            <div class="mb-2 flex items-center justify-between"><span class="text-[11px] font-semibold">Recommended for you</span><span class="text-[8px] text-slate-400">AI powered</span></div>
                            <div class="rounded-2xl border border-slate-700 bg-slate-800 p-3.5">
                                <div class="flex items-start gap-2.5"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E9D6BE] text-[11px] font-bold text-[#694B35]">MC</div><div class="min-w-0 flex-1"><p class="text-[12px] font-semibold leading-4">Weekend Café Assistant</p><p class="mt-1 text-[9px] text-slate-400">Mindoro Coffee House</p></div><x-landing-icon name="bookmark" class="h-4 w-4 text-slate-400" /></div>
                                <div class="mt-3 flex flex-wrap gap-1.5 text-[8px]"><span class="rounded-md bg-amber-400/10 px-2 py-1 font-semibold text-amber-300">Featured</span><span class="rounded-md bg-slate-700/60 px-2 py-1 text-slate-300">Food Service</span></div>
                                <p class="mb-3 mt-3 text-xl font-bold">₱450 <span class="text-[10px] font-normal text-slate-400">/ day</span></p>
                                <p class="flex items-center gap-1.5 text-[9px] text-slate-300"><x-landing-icon name="location" class="h-3 w-3 text-slate-400" /> Pinamalayan &middot; 1.2 km away</p>
                                <p class="mt-2 flex items-center gap-1.5 text-[9px] text-slate-300"><x-landing-icon name="clock" class="h-3 w-3 text-slate-400" /> Sat, Sun &middot; Morning</p>
                                <div class="mt-3 flex flex-wrap gap-1.5 text-[8px] text-slate-400"><span class="rounded-md border border-slate-600 px-2 py-1">Customer service</span><span class="rounded-md border border-slate-600 px-2 py-1">Teamwork</span></div>
                                <div class="mt-3 flex items-center justify-end border-t border-slate-700 pt-2.5 text-[9px] font-semibold text-red-400">View job <x-landing-icon name="arrow" class="ml-1 h-3 w-3" /></div>
                            </div>
                            <div class="mt-3 flex items-center justify-around border-t border-slate-800 pt-3 text-slate-500"><x-landing-icon name="home" class="h-4 w-4 text-red-500" /><x-landing-icon name="briefcase" class="h-4 w-4" /><x-landing-icon name="message" class="h-4 w-4" /><x-landing-icon name="user" class="h-4 w-4" /></div>
                            <div class="mx-auto mt-3 h-1 w-20 rounded-full bg-slate-600"></div>
                        </div>
                    </div>
                    <div class="absolute bottom-12 right-4 z-20 hidden max-w-[190px] items-center gap-3 rounded-2xl border border-stone-200/80 bg-white px-4 py-3 shadow-lg lg:flex">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700"><x-landing-icon name="sparkles" class="h-5 w-5" /></span>
                        <div><p class="text-xs font-semibold">Skills + schedule</p><p class="mt-1 text-[10px] text-slate-500">The basis of your AI matches.</p></div>
                    </div>
                </div>
                <figcaption class="mt-3 text-center text-[11px] text-stone-500">Illustrative app preview &middot; Sample job details</figcaption>
            </figure>
        </section>

        <section id="features" class="scroll-mt-8 border-y border-stone-200/70 bg-white/60" aria-labelledby="features-title">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20 lg:px-10">
                <div class="mb-9 flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                    <div><p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-red-700">Built around your day</p><h2 id="features-title" class="max-w-lg text-3xl font-bold leading-tight tracking-tight sm:text-4xl">Less searching.<br>More possibilities.</h2></div>
                    <p class="max-w-sm text-sm leading-7 text-slate-500">From finding a nearby role to starting a conversation, take your next step with more context.</p>
                </div>
                @php
                    $features = [
                        ['icon' => 'sparkles', 'label' => '01 / FOR YOU', 'title' => 'Your skills. Your schedule.', 'body' => 'AI job recommendations consider your skills, available days, and preferred time slots to help you discover opportunities.'],
                        ['icon' => 'location', 'label' => '02 / NEARBY', 'title' => 'Keep opportunity close.', 'body' => 'Explore jobs near your profile location and see their distance, so you can factor the commute into your day.'],
                        ['icon' => 'shield', 'label' => '03 / VERIFICATION', 'title' => 'Know who you connect with.', 'body' => 'See profile details and verification badges. Document checks are assisted by AI, with final account approval by admins.'],
                    ];
                @endphp
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($features as $feature)
                        <article class="rounded-3xl border border-stone-200/80 bg-white p-6 sm:p-7">
                            <div class="mb-8 flex items-center justify-between gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#F5F0E9] text-slate-700"><x-landing-icon :name="$feature['icon']" class="h-5 w-5" /></span><span class="text-[9px] font-semibold tracking-[0.12em] text-stone-500">{{ $feature['label'] }}</span></div>
                            <h3 class="text-lg font-bold leading-7 tracking-tight">{{ $feature['title'] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-slate-500">{{ $feature['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="how-it-works" class="mx-auto max-w-7xl scroll-mt-8 px-5 py-14 sm:px-8 sm:py-20 lg:px-10" aria-labelledby="steps-title">
            <div class="mb-10 text-center"><p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-red-700">For working students</p><h2 id="steps-title" class="text-3xl font-bold tracking-tight sm:text-4xl">A small step. A new opportunity.</h2></div>
            @php
                $steps = [
                    ['title' => 'Make it yours.', 'body' => 'Build your profile. Add your skills and the days and times you are available.'],
                    ['title' => 'Find your fit.', 'body' => 'Explore All jobs, browse Nearby, or discover AI recommendations in For You.'],
                    ['title' => 'Start a conversation.', 'body' => 'Apply for a role, message the hirer, and keep track of interviews and application updates.'],
                ];
            @endphp
            <ol class="grid gap-8 md:grid-cols-3 md:gap-10">
                @foreach($steps as $step)
                    <li class="flex items-start gap-4 md:block"><span class="mb-4 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-stone-300 text-xs font-semibold text-slate-500">0{{ $loop->iteration }}</span><div><h3 class="text-lg font-bold">{{ $step['title'] }}</h3><p class="mt-2 max-w-sm text-sm leading-7 text-slate-500">{{ $step['body'] }}</p></div></li>
                @endforeach
            </ol>
        </section>

        <section id="for-hirers" class="mx-auto max-w-7xl scroll-mt-8 px-5 pb-14 sm:px-8 sm:pb-20 lg:px-10" aria-labelledby="hirers-title">
            <div class="grid gap-10 rounded-[32px] bg-[#EEEAE3] p-6 sm:p-10 lg:grid-cols-2 lg:gap-16 lg:p-12">
                <div>
                    <p class="mb-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">For employers &amp; household hirers</p>
                    <h2 id="hirers-title" class="max-w-md text-3xl font-bold leading-tight tracking-tight sm:text-4xl">Good help starts with<br>a good connection.</h2>
                    <p class="mt-5 max-w-md text-sm leading-7 text-slate-600">From a weekend shift at your business to a helping hand at home, connect with students in your community.</p>
                    <a href="#download" class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-full text-sm font-semibold text-red-800 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700">Meet your next working student <x-landing-icon name="arrow" class="h-4 w-4" /></a>
                </div>
                <div class="min-w-0">
                    @foreach([['briefcase', 'Post an opportunity', 'Set the role, requirements, schedule, and compensation.'], ['user', 'Get to know applicants', 'Review student profiles and resumes, then arrange an interview.'], ['message', 'Keep everything connected', 'Coordinate through chat, manage contracts, and issue employment certificates.']] as [$icon, $title, $description])
                        <div class="flex items-start gap-4 {{ !$loop->last ? 'mb-5' : '' }}"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/70 text-slate-700"><x-landing-icon :name="$icon" class="h-5 w-5" /></span><div class="min-w-0"><h3 class="text-sm font-bold">{{ $title }}</h3><p class="mt-1 text-xs leading-6 text-slate-600">{{ $description }}</p></div></div>
                    @endforeach
                </div>
                <div class="flex flex-col gap-3 border-t border-stone-300/70 pt-5 text-xs leading-6 lg:col-span-2 sm:flex-row sm:items-start"><span class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-white/80 px-3 py-1 text-[10px] font-bold text-red-800"><x-landing-icon name="sparkles" class="h-3.5 w-3.5" /> PRO EMPLOYERS</span><p class="text-slate-600">Added visibility for your opportunities. Active jobs from employers on Pro are included in For You with a Featured badge.</p></div>
            </div>
        </section>

        <section id="download" class="mx-auto max-w-7xl scroll-mt-8 px-5 pb-14 sm:px-8 sm:pb-20 lg:px-10" aria-labelledby="download-title">
            <div class="relative overflow-hidden rounded-[32px] bg-slate-900 px-6 py-12 text-center text-white sm:px-12 sm:py-16">
                <div aria-hidden="true" class="pointer-events-none absolute -left-28 -top-24 h-80 w-80 rounded-full border border-white/5"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-48 -right-16 h-96 w-96 rounded-full border border-white/5"></div>
                <div class="relative">
                    <span class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/15 px-3.5 py-1.5 text-[11px] text-slate-300"><span class="h-1.5 w-1.5 rounded-full bg-red-400"></span> Get DiskarTech for Android</span>
                    <h2 id="download-title" class="text-3xl font-bold leading-tight tracking-tight sm:text-5xl">Your next move<br>starts here.</h2>
                    <p class="mx-auto mt-5 max-w-md text-sm leading-7 text-slate-400">Find your next opportunity with DiskarTech. Open our Expo build page for download and installation options.</p>
                    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener noreferrer" class="mt-7 inline-flex min-h-12 items-center justify-center gap-2.5 rounded-full bg-red-700 px-6 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"><x-landing-icon name="android" class="h-5 w-5" /> Download for Android <span class="sr-only">(opens in a new tab)</span></a>
                    <p class="mt-5 text-xs text-slate-400">For students. For hirers. For our community.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-stone-200/70">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-8 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-10">
            <div><a href="#" class="text-base font-bold tracking-tight">DiskarTech<span class="text-red-700">.</span></a><p class="mt-2 text-xs leading-6 text-slate-500">Local work. Your kind of schedule.<br>Pinamalayan, Oriental Mindoro</p></div>
            <div class="flex flex-wrap gap-x-5 gap-y-3 text-xs font-medium text-slate-600"><a href="#features" class="hover:text-red-700">Features</a><a href="#how-it-works" class="hover:text-red-700">How it works</a><a href="{{ route('legal.privacy') }}" class="hover:text-red-700">Privacy Policy</a><a href="{{ route('legal.terms') }}" class="hover:text-red-700">Terms</a><a href="{{ route('legal.cookies') }}" class="hover:text-red-700">Cookies</a></div>
            <p class="text-[11px] text-stone-500">&copy; {{ now()->year }} DiskarTech &middot; Capstone Project</p>
        </div>
    </footer>
    <script>
        (() => {
            const stage = document.querySelector('[data-phone-stage]');
            const phone = stage?.querySelector('[data-phone]');
            if (!phone) return;

            const pointerQuery = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 640px)');
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            let frame = null;

            const resetTilt = () => {
                if (frame !== null) window.cancelAnimationFrame(frame);
                frame = null;
                phone.style.removeProperty('--phone-tilt-x');
                phone.style.removeProperty('--phone-tilt-y');
            };

            stage.addEventListener('pointermove', (event) => {
                if (!pointerQuery.matches || reducedMotion.matches || event.pointerType !== 'mouse') return;
                if (frame !== null) window.cancelAnimationFrame(frame);
                frame = window.requestAnimationFrame(() => {
                    const bounds = stage.getBoundingClientRect();
                    const x = Math.max(-.5, Math.min(.5, (event.clientX - bounds.left) / bounds.width - .5));
                    const y = Math.max(-.5, Math.min(.5, (event.clientY - bounds.top) / bounds.height - .5));
                    phone.style.setProperty('--phone-tilt-x', `${-y * 10}deg`);
                    phone.style.setProperty('--phone-tilt-y', `${x * 14}deg`);
                    frame = null;
                });
            });

            stage.addEventListener('pointerleave', resetTilt);
            stage.addEventListener('pointercancel', resetTilt);
            window.addEventListener('blur', resetTilt);
            pointerQuery.addEventListener('change', resetTilt);
            reducedMotion.addEventListener('change', resetTilt);
        })();
    </script>
</body>
</html>
