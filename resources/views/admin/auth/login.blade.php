<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | DiskarTech</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#FDFBF7] flex items-center justify-center h-screen font-sans text-slate-800">

    <div class="bg-white p-8 rounded-3xl border border-stone-200/80 shadow-sm w-full max-w-md mx-4 relative overflow-hidden">
        <!-- Background Glow Accent -->
        <div class="absolute -right-12 -top-12 w-32 h-32 bg-red-50 rounded-full blur-2xl pointer-events-none"></div>

        <!-- Logo & Header -->
        <div class="flex items-center space-x-3 mb-8">
            <div class="w-12 h-12 bg-red-600 rounded-2xl flex items-center justify-center text-white font-black text-2xl shadow-sm">
                D
            </div>
            <div>
                <h1 class="font-bold text-slate-900 text-lg leading-tight">DiskarTech</h1>
                <p class="text-xs text-slate-500 font-medium">Super admin console</p>
            </div>
        </div>

        <h2 class="text-xl font-bold text-slate-900 mb-2">Sign in to console</h2>
        <p class="text-xs text-slate-500 mb-6">Ilagay ang iyong admin credentials para makapasok.</p>
        
        @if($errors->any())
            <div class="mb-4 bg-rose-50 border border-rose-100 text-rose-600 text-xs p-3 rounded-xl font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-slate-700 text-xs font-semibold mb-1.5">Email address</label>
                <input type="email" name="email" required placeholder="admin@diskartech.com" class="w-full bg-[#F2EDE4]/50 border border-stone-200 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-800 placeholder-slate-400 transition">
            </div>

            <div>
                <label class="block text-slate-700 text-xs font-semibold mb-1.5">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full bg-[#F2EDE4]/50 border border-stone-200 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-800 placeholder-slate-400 transition">
            </div>

            <button type="submit" class="w-full bg-red-700 hover:bg-red-800 text-white text-xs font-semibold py-3 rounded-xl shadow-sm transition mt-2">
                Sign in
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ url('/') }}" class="text-xs text-slate-500 hover:text-red-700 font-medium transition">← Bumalik sa landing page</a>
        </div>
    </div>

</body>
</html>