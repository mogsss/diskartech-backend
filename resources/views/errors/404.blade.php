@php
    $homeUrl = config('admin.domain') && request()->getHost() === config('admin.domain')
        ? route('admin.home')
        : (config('app.url') ?: url('/'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FDFBF7">
    <title>Page not found | DiskarTech</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100svh; display: flex; flex-direction: column; background: #FDFBF7; color: #0F172A; font-family: Poppins, system-ui, sans-serif; }
        a { color: inherit; text-decoration: none; }
        a:focus-visible { outline: 3px solid #B91C1C; outline-offset: 6px; }
        .shell { width: min(1080px, calc(100% - 64px)); margin-inline: auto; }
        header { padding-block: 30px; }
        .brand { display: inline-flex; align-items: center; gap: 11px; border-radius: 8px; font-size: 21px; font-weight: 700; letter-spacing: -.7px; }
        .brand img { width: 40px; height: 40px; border-radius: 12px; object-fit: contain; }
        .brand span { color: #B91C1C; }
        main { flex: 1; display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 64px; padding-block: 48px 80px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 9px; margin: 0 0 22px; color: #B91C1C; font-size: 11px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase; }
        .eyebrow::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        h1 { margin: 0; font-size: clamp(34px, 4.6vw, 56px); line-height: 1.18; font-weight: 600; letter-spacing: -2px; }
        .description { max-width: 420px; margin: 22px 0 30px; color: #64748B; font-size: 14px; line-height: 1.9; }
        .home-button { display: inline-flex; min-height: 50px; align-items: center; justify-content: center; gap: 18px; padding: 14px 24px; border-radius: 999px; background: #B91C1C; color: #FFF; font-size: 13px; font-weight: 600; transition: background-color .15s; }
        .home-button:hover { background: #991B1B; }
        .home-button svg { width: 18px; height: 18px; }
        .illustration { position: relative; display: grid; place-items: center; min-height: 360px; border: 1px solid #E9E3DA; border-radius: 32px; background: radial-gradient(#E7E2D9 1px, transparent 1px) 0 0 / 20px 20px, #F7F3EC; overflow: hidden; }
        .route { position: absolute; inset: 0; width: 100%; height: 100%; }
        .error-number { position: relative; margin-top: -25px; color: #B91C1C; font-size: clamp(100px, 12vw, 156px); line-height: 1; font-weight: 700; letter-spacing: -10px; }
        .map-note { position: absolute; bottom: 28px; display: flex; align-items: center; gap: 8px; padding: 10px 17px; border: 1px solid #EEE7DD; border-radius: 999px; background: #FDFBF7; color: #78716C; font-size: 11px; }
        .map-note svg { width: 16px; height: 16px; color: #B91C1C; }
        footer { padding-block: 22px; border-top: 1px solid #EDE8DF; color: #78716C; font-size: 11px; line-height: 1.8; }
        footer .shell { display: flex; justify-content: space-between; gap: 12px; }
        @media (max-width: 700px) {
            .shell { width: calc(100% - 40px); }
            header { padding-block: 22px; }
            main { grid-template-columns: 1fr; gap: 32px; padding-block: 20px 48px; text-align: center; }
            .illustration { grid-row: 1; min-height: 230px; width: min(100%, 400px); justify-self: center; border-radius: 26px; }
            .error-number { font-size: 112px; margin-top: -18px; }
            .map-note { bottom: 18px; font-size: 10px; padding: 8px 14px; }
            .eyebrow { margin-bottom: 16px; }
            h1 { letter-spacing: -1.2px; }
            .description { margin: 16px auto 24px; }
            footer .shell { flex-direction: column; align-items: center; gap: 3px; text-align: center; }
        }
        @media (prefers-reduced-motion: reduce) { .home-button { transition: none; } }
    </style>
</head>
<body>
    <header class="shell">
        <a class="brand" href="{{ $homeUrl }}" aria-label="DiskarTech home">
            <img src="{{ asset('logo.png') }}" alt="" width="40" height="40">
            <div>DiskarTech<span>.</span></div>
        </a>
    </header>
    <main class="shell">
        <section aria-labelledby="error-title">
            <p class="eyebrow">Error 404</p>
            <h1 id="error-title">Page not found.</h1>
            <p class="description">The page you're looking for may have moved or doesn't exist. Let's get you back to DiskarTech.</p>
            <a class="home-button" href="{{ $homeUrl }}">
                Back to home
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" /></svg>
            </a>
        </section>
        <div class="illustration" aria-hidden="true">
            <svg class="route" viewBox="0 0 460 360" fill="none" preserveAspectRatio="xMidYMid slice">
                <path d="M-20 280H85a28 28 0 0 0 28-28V108a28 28 0 0 1 28-28h178a28 28 0 0 1 28 28v72a28 28 0 0 0 28 28h105" stroke="#E3DCD1" stroke-width="20" />
                <path d="M-20 280H85a28 28 0 0 0 28-28V108a28 28 0 0 1 28-28h178a28 28 0 0 1 28 28v72a28 28 0 0 0 28 28h105" stroke="#FDFBF7" stroke-width="17" />
                <path d="M-20 280H85a28 28 0 0 0 28-28V108a28 28 0 0 1 28-28h86" stroke="#D9A5A0" stroke-width="2" stroke-linecap="round" stroke-dasharray="5 7" />
                <circle cx="231" cy="80" r="7" fill="#B91C1C" stroke="#FDFBF7" stroke-width="3" />
            </svg>
            <div class="error-number">404</div>
            <div class="map-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></svg>
                A fresh start is one click away.
            </div>
        </div>
    </main>
    <footer><div class="shell"><span>&copy; {{ now()->year }} DiskarTech</span><span>Smart Jobs for Working Students</span></div></footer>
</body>
</html>
