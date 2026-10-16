@php
    $policy = config('legal.'.$policyKey);
    $homeUrl = rtrim(config('app.url'), '/');
    $pages = ['privacy' => ['Privacy Policy', '/privacy-policy'], 'terms' => ['Terms and Conditions', '/terms-and-conditions'], 'cookies' => ['Cookie Policy', '/cookie-policy']];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $policy['title'] }} | DiskarTech</title>
    <meta name="description" content="{{ $policy['introduction'] }}">
    <link rel="canonical" href="{{ $homeUrl.$pages[$policyKey][1] }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#FDFBF7] font-['Poppins',sans-serif] text-slate-900 antialiased selection:bg-red-100 selection:text-red-900">
    <a href="#main-content" class="sr-only bg-white px-4 py-3 font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">Skip to content</a>
    <header class="border-b border-stone-200/70">
        <div class="mx-auto flex min-h-[88px] max-w-6xl items-center justify-between gap-4 px-5 sm:px-8">
            <a href="{{ $homeUrl }}" aria-label="DiskarTech home" class="flex items-center gap-2.5">
                <img src="{{ asset('logo.png') }}" alt="" width="38" height="38" class="h-[38px] w-[38px] rounded-xl object-cover">
                <span class="text-base font-bold tracking-tight sm:text-lg">DiskarTech<span class="text-red-700">.</span></span>
            </a>
            <a href="{{ $homeUrl }}" class="inline-flex min-h-11 items-center gap-2 rounded-full px-3 text-xs font-semibold text-slate-600 hover:text-red-700 sm:text-sm"><span aria-hidden="true">&larr;</span> Back to home</a>
        </div>
    </header>

    <main id="main-content" class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-16">
        <div class="mb-10 max-w-3xl sm:mb-14">
            <p class="mb-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-red-700">DiskarTech &middot; Policies</p>
            <h1 class="text-3xl font-bold leading-tight tracking-tight sm:text-5xl">{{ $policy['title'] }}</h1>
            <p class="mt-5 text-sm leading-7 text-slate-600 sm:text-base sm:leading-8">{{ $policy['introduction'] }}</p>
            <p class="mt-5 text-xs text-stone-500">Updated {{ $policy['updated'] }}</p>
        </div>

        <div class="flex flex-col gap-8 lg:flex-row lg:gap-16">
            <aside class="lg:w-60 lg:shrink-0">
                <details class="rounded-2xl border border-stone-200 px-4 py-3 lg:hidden">
                    <summary class="cursor-pointer text-sm font-semibold">On this page</summary>
                    <nav aria-label="On this page" class="mt-3 flex flex-col gap-1">
                        @foreach($policy['sections'] as $section)
                            <a href="#section-{{ $loop->index }}" class="py-2 text-xs leading-5 text-slate-600 hover:text-red-700">{{ $section['title'] }}</a>
                        @endforeach
                    </nav>
                </details>
                <nav aria-label="On this page" class="sticky top-8 hidden lg:block">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-stone-500">On this page</p>
                    @foreach($policy['sections'] as $section)
                        <a href="#section-{{ $loop->index }}" class="block border-l border-stone-200 py-2 pl-4 text-xs leading-6 text-slate-600 hover:border-red-700 hover:text-red-700">{{ $section['title'] }}</a>
                    @endforeach
                </nav>
            </aside>

            <div class="min-w-0 flex-1">
                @foreach($policy['sections'] as $section)
                    <section id="section-{{ $loop->index }}" aria-labelledby="heading-{{ $loop->index }}" class="scroll-mt-8 border-t border-stone-200 py-7 first:border-t-0 first:pt-0 sm:py-9">
                        <h2 id="heading-{{ $loop->index }}" class="text-lg font-bold leading-7 tracking-tight sm:text-xl">{{ $section['title'] }}</h2>
                        @foreach($section['paragraphs'] as $paragraph)
                            <p class="mt-4 text-sm leading-7 text-slate-600 break-words">{{ $paragraph }}</p>
                        @endforeach
                        @if(!empty($section['bullets']))
                            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-7 text-slate-600 marker:text-red-700">
                                @foreach($section['bullets'] as $bullet)
                                    <li class="pl-1 break-words">{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach

                <section aria-labelledby="related-title" class="mt-2 border-t border-stone-200 pt-7">
                    <h2 id="related-title" class="text-base font-bold">Related information</h2>
                    <nav aria-label="Related policies" class="mt-4 flex flex-wrap gap-x-5 gap-y-3 text-sm font-medium">
                        @foreach($pages as $key => [$title, $path])
                            @if($key !== $policyKey)
                                <a href="{{ $homeUrl.$path }}" class="text-red-700 underline decoration-red-200 underline-offset-4 hover:decoration-red-700">{{ $title }}</a>
                            @endif
                        @endforeach
                        @if($policyKey === 'privacy')
                            <a href="https://privacy.gov.ph/data-subject-rights/" class="text-red-700 underline decoration-red-200 underline-offset-4 hover:decoration-red-700">NPC data subject rights</a>
                        @endif
                    </nav>
                    <p class="mt-6 text-sm leading-7 text-slate-600">For questions about these policies or retained account information, use the Help Center in the DiskarTech app or email <a href="mailto:diskartech.official@gmail.com" class="break-all font-medium text-red-700 underline underline-offset-4">diskartech.official@gmail.com</a>.</p>
                </section>
            </div>
        </div>
    </main>

    <footer class="mt-6 border-t border-stone-200/70">
        <div class="mx-auto flex max-w-6xl flex-col gap-4 px-5 py-8 text-xs text-slate-500 sm:px-8 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} DiskarTech &middot; Pinamalayan, Oriental Mindoro</p>
            <a href="{{ $homeUrl }}" class="font-medium hover:text-red-700">Visit DiskarTech</a>
        </div>
    </footer>
</body>
</html>
