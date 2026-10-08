<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiskarTech - Super Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#FDFBF7] min-h-screen text-slate-800 flex font-sans antialiased overflow-x-hidden" x-data="adminNotificationCenter()" x-init="init()">

    <!-- Floating Real-Time Incident Toast Alert -->
    <div 
        x-show="toast.visible" 
        x-cloak
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-4 scale-95"
        class="fixed top-5 right-4 sm:right-6 z-[100] max-w-sm sm:max-w-md w-full bg-slate-900 text-white rounded-2xl shadow-2xl p-4 border border-slate-800 flex items-start space-x-3.5"
    >
        <div class="w-10 h-10 rounded-xl bg-red-600 text-white flex items-center justify-center flex-shrink-0 text-base shadow-sm">
            🚨
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold tracking-wide uppercase text-red-400">New Incident Report Filed</h4>
                <button @click="toast.visible = false" class="text-slate-400 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <p class="text-sm font-semibold text-white mt-0.5 truncate" x-text="toast.subject"></p>
            <p class="text-xs text-slate-300 mt-0.5 truncate" x-text="'Reported by ' + toast.reporter + (toast.job ? ' · ' + toast.job : '')"></p>
            <div class="mt-2.5 flex items-center space-x-2">
                <a :href="'{{ route('admin.reports') }}?search=' + toast.reportId" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg transition">
                    Review Incident
                </a>
                <button @click="toast.visible = false" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg transition">
                    Dismiss
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Off-Canvas Sidebar Drawer (Phone & Small Tablet) -->
    <div 
        x-show="sidebarOpen" 
        x-cloak 
        class="fixed inset-0 z-50 md:hidden flex"
        role="dialog" 
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div 
            x-show="sidebarOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="sidebarOpen = false" 
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
        ></div>

        <!-- Drawer Content -->
        <div 
            x-show="sidebarOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative flex-1 flex flex-col max-w-xs w-full bg-[#FDFBF7] h-full p-5 shadow-2xl justify-between z-10"
        >
            <div class="overflow-y-auto">
                <!-- Header with Close Button -->
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-stone-200">
                    <div class="flex items-center space-x-3">
                        <img src="{{ asset('logo.png') }}" alt="DiskarTech Logo" class="w-10 h-10 rounded-2xl shadow-sm object-cover">
                        <div>
                            <h1 class="font-bold text-slate-900 leading-tight">DiskarTech</h1>
                            <p class="text-xs text-slate-500 font-medium">Super Admin</p>
                        </div>
                    </div>
                    <button 
                        @click="sidebarOpen = false" 
                        class="w-9 h-9 rounded-xl bg-stone-100 text-slate-600 flex items-center justify-center hover:bg-stone-200 transition"
                        aria-label="Close Sidebar"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Navigation Links -->
                <nav class="space-y-1.5">
                    <a href="{{ route('admin.dashboard') }}" @click="sidebarOpen = false" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.dashboard') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span>Overview</span>
                    </a>
                    <a href="{{ route('admin.messages') }}" @click="sidebarOpen = false" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.messages*') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                        <span>Messages</span>
                    </a>
                    <a href="{{ route('admin.verification') }}" @click="sidebarOpen = false" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.verification') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span>Verifications</span>
                    </a>
                    <a href="{{ route('admin.job-moderation') }}" @click="sidebarOpen = false" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.job-moderation') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span>Job moderation</span>
                    </a>
                    <a href="{{ route('admin.users') }}" @click="sidebarOpen = false" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.users') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span>Users</span>
                    </a>
                    <a href="{{ route('admin.reports') }}" @click="sidebarOpen = false" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.reports*') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            <span>Reports</span>
                        </div>
                        <span 
                            x-show="pendingCount > 0" 
                            x-cloak 
                            x-text="pendingCount" 
                            class="bg-red-600 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-xs"
                        ></span>
                    </a>
                    <a href="{{ route('admin.settings') }}" @click="sidebarOpen = false" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.settings') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                        <span>Platform settings</span>
                    </a>
                </nav>
            </div>

            <!-- Root Access Box -->
            <div class="bg-[#F5EFE6] p-3.5 rounded-2xl border border-stone-200/60 mt-4">
                <div class="flex items-center space-x-2 text-red-700 font-semibold text-xs mb-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span>Root access</span>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed">All actions are logged to the audit trail.</p>
            </div>
        </div>
    </div>

    <!-- Desktop & Tablet Sidebar Navigation -->
    <aside class="w-64 lg:w-72 bg-[#FDFBF7] border-r border-stone-200 p-5 lg:p-6 flex flex-col justify-between hidden md:flex flex-shrink-0">
        <div>
            <!-- Logo & Brand -->
            <div class="flex items-center space-x-3 mb-8">
                <img src="{{ asset('logo.png') }}" alt="DiskarTech Logo" class="w-11 h-11 lg:w-12 lg:h-12 rounded-2xl shadow-sm object-cover">
                <div>
                    <h1 class="font-bold text-slate-900 leading-tight text-base lg:text-lg">DiskarTech</h1>
                    <p class="text-xs text-slate-500 font-medium">Super Admin</p>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="space-y-1.5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3 px-3.5 lg:px-4 py-2.5 lg:py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.dashboard') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    <span class="truncate">Overview</span>
                </a>
                <a href="{{ route('admin.messages') }}" class="flex items-center space-x-3 px-3.5 lg:px-4 py-2.5 lg:py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.messages*') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    <span class="truncate">Messages</span>
                </a>
                <a href="{{ route('admin.verification') }}" class="flex items-center space-x-3 px-3.5 lg:px-4 py-2.5 lg:py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.verification') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span class="truncate">Verifications</span>
                </a>
                <a href="{{ route('admin.job-moderation') }}" class="flex items-center space-x-3 px-3.5 lg:px-4 py-2.5 lg:py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.job-moderation') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span class="truncate">Job moderation</span>
                </a>
                <a href="{{ route('admin.users') }}" class="flex items-center space-x-3 px-3.5 lg:px-4 py-2.5 lg:py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.users') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span class="truncate">Users</span>
                </a>
                <a href="{{ route('admin.reports') }}" class="flex items-center justify-between px-3.5 lg:px-4 py-2.5 lg:py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.reports*') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <div class="flex items-center space-x-3 min-w-0">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <span class="truncate">Reports</span>
                    </div>
                    <span 
                        x-show="pendingCount > 0" 
                        x-cloak 
                        x-text="pendingCount" 
                        class="bg-red-600 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-xs"
                    ></span>
                </a>
                <a href="{{ route('admin.settings') }}" class="flex items-center space-x-3 px-3.5 lg:px-4 py-2.5 lg:py-3 rounded-xl font-medium text-sm transition {{ request()->routeIs('admin.settings') ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                    <span class="truncate">Platform settings</span>
                </a>
            </nav>
        </div>

        <!-- Root Access Box -->
        <div class="bg-[#F5EFE6] p-4 rounded-2xl border border-stone-200/60 mt-4">
            <div class="flex items-center space-x-2 text-red-700 font-semibold text-xs mb-1">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <span>Root access</span>
            </div>
            <p class="text-[11px] text-slate-500 leading-relaxed">All actions on this console are logged to the audit trail.</p>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
        <!-- Top Bar Header -->
        <header class="h-16 md:h-20 px-4 sm:px-6 lg:px-8 flex items-center justify-between border-b border-stone-200/60 bg-[#FDFBF7] flex-shrink-0 z-20">
            <div class="flex items-center min-w-0 mr-3">
                <!-- Mobile Hamburger Button -->
                <button 
                    @click="sidebarOpen = true" 
                    class="mr-3 p-2 rounded-xl bg-[#F2EDE4] text-slate-700 hover:bg-stone-200 md:hidden flex-shrink-0 active:opacity-75 transition"
                    aria-label="Open Sidebar Menu"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>

                <div class="min-w-0">
                    <h2 class="text-base sm:text-lg md:text-xl font-bold text-slate-900 leading-tight truncate">@stack('header-title', 'Overview')</h2>
                    <p class="text-[10px] sm:text-xs text-slate-500 font-medium truncate hidden sm:block">Pinamalayan, Oriental Mindoro · updated just now</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-2 sm:space-x-3 md:space-x-4 flex-shrink-0">
                <!-- Search (Responsive) -->
                <div class="relative hidden sm:block w-36 md:w-56 lg:w-72">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" placeholder="Search..." class="w-full bg-[#F2EDE4] text-xs rounded-xl pl-9 pr-3 py-2 sm:py-2.5 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 placeholder-slate-400 border-0">
                </div>

                <!-- Interactive Notification Bell & Dropdown -->
                <div class="relative" @click.away="openNotifications = false">
                    <button 
                        @click="openNotifications = !openNotifications"
                        class="w-9 h-9 sm:w-10 sm:h-10 bg-[#F2EDE4] rounded-full flex items-center justify-center text-slate-600 hover:bg-stone-200 transition relative"
                        title="Incident & Moderation Alerts"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span 
                            x-show="pendingCount > 0" 
                            x-cloak
                            class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-black w-4 h-4 sm:w-5 sm:h-5 rounded-full flex items-center justify-center border-2 border-[#FDFBF7] shadow-sm animate-pulse"
                            x-text="pendingCount > 99 ? '99+' : pendingCount"
                        ></span>
                    </button>

                    <!-- Dropdown Menu -->
                    <div 
                        x-show="openNotifications" 
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2.5 w-72 sm:w-80 md:w-96 bg-white rounded-2xl shadow-2xl border border-stone-200/90 py-3 z-50 overflow-hidden"
                    >
                        <div class="px-4 pb-2.5 border-b border-stone-100 flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Incident Alerts</h4>
                                <p class="text-[11px] text-slate-400">Reports requiring safety review</p>
                            </div>
                            <div class="flex items-center space-x-1.5">
                                <span 
                                    class="text-[11px] font-bold px-2 py-0.5 rounded-full"
                                    :class="pendingCount > 0 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"
                                    x-text="pendingCount > 0 ? pendingCount + ' Pending' : 'All Clear'"
                                ></span>
                            </div>
                        </div>

                        <!-- Notification Items Container -->
                        <div class="max-h-80 overflow-y-auto divide-y divide-stone-100">
                            <template x-if="recentReports.length === 0">
                                <div class="py-8 text-center text-slate-400 px-4">
                                    <svg class="w-8 h-8 mx-auto text-emerald-500 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="text-xs font-bold text-slate-800">No pending reports</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">All community safety reports have been moderated.</p>
                                </div>
                            </template>

                            <template x-for="report in recentReports" :key="report.id">
                                <a :href="'{{ route('admin.reports') }}?search=' + report.id" class="p-3.5 hover:bg-red-50/50 transition flex items-start space-x-3 block">
                                    <div class="w-8 h-8 rounded-xl bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">
                                        🚨
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <p class="text-xs font-bold text-slate-900 truncate" x-text="report.subject"></p>
                                            <span class="text-[10px] text-slate-400" x-text="report.created_at_human"></span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 truncate mt-0.5">
                                             By <span class="font-semibold text-slate-700" x-text="report.reporter_name"></span>
                                            <template x-if="report.job_title">
                                                <span>· <span x-text="report.job_title"></span></span>
                                            </template>
                                        </p>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="w-9 h-9 sm:w-10 sm:h-10 bg-slate-900 text-white rounded-full flex items-center justify-center font-bold text-xs tracking-wider flex-shrink-0">
                    SA
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-3.5 sm:p-5 md:p-6 lg:p-8 overflow-y-auto min-w-0">
            {{ $slot }}
        </main>
    </div>

    <!-- Alpine.js Real-time Admin Notification Engine -->
    <script>
        function adminNotificationCenter() {
            return {
                sidebarOpen: false,
                openNotifications: false,
                pendingCount: 0,
                recentReports: [],
                lastKnownLatestId: null,
                toast: {
                    visible: false,
                    subject: '',
                    reporter: '',
                    job: '',
                    reportId: null,
                },

                init() {
                    // Initial poll
                    this.poll();

                    // Poll every 5 seconds for real-time responsiveness
                    setInterval(() => {
                        this.poll();
                    }, 5000);
                },

                async poll() {
                    try {
                        const res = await fetch("{{ route('admin.notifications.poll') }}", {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (!res.ok) return;
                        const data = await res.json();

                        if (data.status === 'success') {
                            this.pendingCount = data.pending_count || 0;
                            this.recentReports = data.reports || [];

                            // Check if a new report was created since last poll
                            if (this.lastKnownLatestId !== null && data.latest_id > this.lastKnownLatestId) {
                                const newReport = this.recentReports[0];
                                if (newReport) {
                                    this.triggerAlert(newReport);
                                }
                            }

                            this.lastKnownLatestId = data.latest_id;
                        }
                    } catch (e) {
                        // Silent fail on network hiccups
                    }
                },

                triggerAlert(report) {
                    // Show floating in-app toast
                    this.toast = {
                        visible: true,
                        subject: report.subject,
                        reporter: report.reporter_name,
                        job: report.job_title,
                        reportId: report.id,
                    };

                    // Auto-hide toast after 8 seconds
                    setTimeout(() => {
                        this.toast.visible = false;
                    }, 8000);

                    // Play subtle audio chime
                    this.playChime();

                    // Trigger browser desktop notification if permitted
                    if ("Notification" in window && Notification.permission === "granted") {
                        try {
                            new Notification("🚨 New Incident Report Filed", {
                                body: report.subject + " reported by " + report.reporter_name,
                            });
                        } catch (err) {}
                    }
                },

                playChime() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const ctx = new AudioCtx();
                        const now = ctx.currentTime;
                        
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();

                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(587.33, now); // D5
                        osc.frequency.setValueAtTime(880.00, now + 0.12); // A5

                        gain.gain.setValueAtTime(0.2, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.55);

                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(now);
                        osc.stop(now + 0.55);
                    } catch (e) {}
                },

                requestDesktopNotifications() {
                    if ("Notification" in window) {
                        Notification.requestPermission().then((permission) => {
                            if (permission === 'granted') {
                                alert('Desktop notifications enabled! You will now receive desktop popups for critical reports even when on another tab.');
                            } else {
                                alert('Notification permission was not granted.');
                            }
                        });
                    } else {
                        alert('Your browser does not support desktop notifications.');
                    }
                }
            };
        }
    </script>
</body>
</html>