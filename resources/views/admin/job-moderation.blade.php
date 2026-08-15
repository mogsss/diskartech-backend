<x-admin-layout>
    <!-- Top Header Title Change -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const headerTitle = document.querySelector('header h2');
            if(headerTitle) headerTitle.textContent = "Job moderation";
        });
    </script>

    <!-- Job Posts Container -->
    <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
        <h3 class="font-bold text-slate-900 text-base mb-6">Job posts</h3>

        <div class="divide-y divide-stone-100 text-xs">
            <!-- Row 1 -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Weekend barista assistant</h4>
                    <p class="text-slate-500 mt-0.5">Kape Marfrancisco · ₱450/day</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Live</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Hide post</button>
                </div>
            </div>

            <!-- Row 2 -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Math tutor (Grade 7)</h4>
                    <p class="text-slate-500 mt-0.5">Dela Cruz Family · ₱200/hr</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Live</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Hide post</button>
                </div>
            </div>

            <!-- Row 3 -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Sari-sari store helper</h4>
                    <p class="text-slate-500 mt-0.5">Aling Nena Store · ₱380/day</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-stone-100 text-stone-600 font-semibold px-3 py-1 rounded-lg">Hidden</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Publish</button>
                </div>
            </div>

            <!-- Row 4 -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Delivery errands (bike)</h4>
                    <p class="text-slate-500 mt-0.5">Lutong Bahay PH · ₱120/trip</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Live</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Hide post</button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>