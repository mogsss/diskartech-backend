<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiskarTech | Student & Worker Gigs Platform</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#FDFBF7] text-slate-800 font-sans min-h-screen flex flex-col justify-between">

    <!-- Navigation Bar -->
    <header class="max-w-7xl w-full mx-auto px-8 py-6 flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-red-600 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-sm">
                D
            </div>
            <h1 class="font-bold text-slate-900 text-lg">DiskarTech</h1>
        </div>
        <div class="flex items-center space-x-4">
            <a href="{{ route('admin.login') }}" class="text-xs font-semibold text-slate-600 hover:text-red-700 transition">Admin Portal</a>
            <a href="#download" class="bg-red-700 hover:bg-red-800 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-sm transition">Download App</a>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="max-w-7xl w-full mx-auto px-8 py-12 grid grid-cols-1 md:grid-cols-2 gap-12 items-center my-auto">
        <div>
            <span class="bg-red-50 text-red-700 text-xs font-semibold px-3 py-1 rounded-lg">Pinamalayan, Oriental Mindoro</span>
            <h2 class="text-4xl md:text-5xl font-black text-slate-900 tracking-tight mt-4 leading-tight">
                Hanapbuhay para sa mga estudyante at manggagawa.
            </h2>
            <p class="text-slate-600 text-sm mt-4 leading-relaxed">
                Subaybayan ang mga part-time gigs, weekend shifts, at mga ligtas na oportunidad na tugma sa iyong oras sa klase. Ginawa para sa mga taga-Mindoro.
            </p>
            <div class="mt-8 flex items-center space-x-4">
                <a href="#download" class="bg-red-700 hover:bg-red-800 text-white font-semibold text-xs px-6 py-3 rounded-xl shadow-sm transition">
                    Kunin ang App
                </a>
                <a href="{{ route('admin.login') }}" class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold text-xs px-6 py-3 rounded-xl transition">
                    Admin Sign In
                </a>
            </div>
        </div>

        <!-- Hero Card / Illustration mockup -->
        <div class="bg-white p-8 rounded-3xl border border-stone-200/80 shadow-sm relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-red-50 rounded-full blur-2xl"></div>
            <div class="flex items-center space-x-3 mb-6">
                <div class="w-12 h-12 bg-red-600 text-white rounded-2xl flex items-center justify-center font-bold text-xl">D</div>
                <div>
                    <h4 class="font-bold text-slate-900">DiskarTech Mobile</h4>
                    <p class="text-xs text-slate-500">Class-safe match system active</p>
                </div>
            </div>
            <div class="space-y-3 text-xs">
                <div class="p-3 bg-stone-50 rounded-xl border border-stone-100 flex justify-between items-center">
                    <span class="font-medium text-slate-700">Weekend Barista Assistant</span>
                    <span class="text-emerald-600 font-bold">₱450/day</span>
                </div>
                <div class="p-3 bg-stone-50 rounded-xl border border-stone-100 flex justify-between items-center">
                    <span class="font-medium text-slate-700">Math Tutor (Grade 7)</span>
                    <span class="text-emerald-600 font-bold">₱200/hr</span>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="max-w-7xl w-full mx-auto px-8 py-6 border-t border-stone-200/60 flex flex-col sm:flex-row justify-between items-center text-xs text-slate-500">
        <p>© 2026 DiskarTech Capstone Project. All rights reserved.</p>
        <p class="mt-2 sm:mt-0">Pinamalayan, Oriental Mindoro</p>
    </footer>

</body>
</html>