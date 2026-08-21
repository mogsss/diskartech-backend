<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiskarTech Super Admin Console</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#FDFBF7] min-h-screen text-slate-800 flex font-sans">

    <!-- Sidebar Navigation -->
    <aside class="w-72 bg-[#FDFBF7] border-r border-stone-200 p-6 flex flex-col justify-between hidden md:flex">
        <div>
            <!-- Logo & Brand -->
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-12 h-12 bg-red-600 rounded-2xl flex items-center justify-center text-white font-black text-2xl shadow-sm">
                    D
                </div>
                <div>
                    <h1 class="font-bold text-slate-900 leading-tight">DiskarTech</h1>
                    <p class="text-xs text-slate-500 font-medium">Super Admin</p>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="space-y-1.5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.dashboard') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    <span>Overview</span>
                </a>
                
                {{-- Inayos ang route name mula admin.verifications patungong admin.verification --}}
                <a href="{{ route('admin.verification') }}" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.verification') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span>Verifications</span>
                </a>

                <a href="{{ route('admin.job-moderation') }}" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.job-moderation') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span>Job moderation</span>
                </a>
                <a href="{{ route('admin.users') }}" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.users') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>Users</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm text-slate-600 hover:bg-stone-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span>Reports</span>
                </a>
                <a href="{{ route('admin.settings') }}" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.settings') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                    <span>Platform settings</span>
                </a>
            </nav>
        </div>

        <!-- Root Access Box -->
        <div class="bg-[#F5EFE6] p-4 rounded-2xl border border-stone-200/60">
            <div class="flex items-center space-x-2 text-red-700 font-semibold text-xs mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <span>Root access</span>
            </div>
            <p class="text-[11px] text-slate-500 leading-relaxed">All actions on this console are logged to the audit trail.</p>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Bar -->
        <header class="h-20 px-8 flex items-center justify-between border-b border-stone-200/60 bg-[#FDFBF7]">
            <div>
                {{-- Dito natin ginawang dynamic ang Header Title gamit ang Stack --}}
                <h2 class="text-xl font-bold text-slate-900">@stack('header-title', 'Overview')</h2>
                <p class="text-xs text-slate-500 font-medium">Pinamalayan, Oriental Mindoro · updated just now</p>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="relative w-72">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" placeholder="Search users, jobs, reports" class="w-full bg-[#F2EDE4] text-xs rounded-xl pl-9 pr-4 py-2.5 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 placeholder-slate-400 border-0">
                </div>
                <button class="w-10 h-10 bg-[#F2EDE4] rounded-full flex items-center justify-center text-slate-600 hover:bg-stone-200 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </button>
                <div class="w-10 h-10 bg-slate-900 text-white rounded-full flex items-center justify-center font-bold text-xs tracking-wider">
                    SA
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-8 overflow-y-auto">
            {{ $slot }}
        </main>
    </div>

</body>
</html>