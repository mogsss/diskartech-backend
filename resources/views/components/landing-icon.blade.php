@props(['name'])
@php
    $path = match ($name) {
        'arrow' => 'M5 12h14m-6-6 6 6-6 6',
        'location' => 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'sparkles' => 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3ZM20 2v4m-2-2h4',
        'shield' => 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-6',
        'calendar' => 'M8 2v4m8-4v4M4 10h16M5 4h14a1 1 0 0 1 1 1v15H4V5a1 1 0 0 1 1-1Zm3 10h2m4 0h2m-8 3h2',
        'briefcase' => 'M8 6V3h8v3M3 6h18v14H3V6Zm0 5 9 4 9-4m-9 2v4',
        'message' => 'M21 11a8 8 0 0 1-8 8H7l-4 3V7a4 4 0 0 1 4-4h6a8 8 0 0 1 8 8ZM7 8h10M7 12h7',
        'check' => 'm5 12 4 4L19 6',
        'bookmark' => 'M6 3h12v18l-6-4-6 4V3Z',
        'clock' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 7v5l3 2',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 12h4',
        'home' => 'm3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9',
        'user' => 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2',
        'android' => 'm7 4-2-3m12 3 2-3M4 10a8 8 0 0 1 16 0v8H4v-8Zm0 0h16M8 7h.01M16 7h.01M8 18v4m8-4v4M1 11v5m22-5v5',
    };
@endphp
<svg {{ $attributes->class(['shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $path }}" /></svg>
