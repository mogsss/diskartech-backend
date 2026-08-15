<x-admin-layout>
    <!-- Top Header Title Change -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const headerTitle = document.querySelector('header h2');
            if(headerTitle) headerTitle.textContent = "Users";
        });
    </script>

    <!-- Accounts Container -->
    <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h3 class="font-bold text-slate-900 text-base">Accounts</h3>
            
            <!-- Filter Buttons -->
            <div class="flex items-center space-x-2 text-xs font-semibold">
                <button onclick="filterUsers('all')" id="btn-all" class="px-3.5 py-1.5 rounded-xl bg-red-700 text-white transition shadow-sm">All</button>
                <button onclick="filterUsers('student')" id="btn-student" class="px-3.5 py-1.5 rounded-xl bg-[#F2EDE4] text-slate-700 hover:bg-stone-200 transition">Students</button>
                <button onclick="filterUsers('employer')" id="btn-employer" class="px-3.5 py-1.5 rounded-xl bg-[#F2EDE4] text-slate-700 hover:bg-stone-200 transition">Employers</button>
                <button onclick="filterUsers('household')" id="btn-household" class="px-3.5 py-1.5 rounded-xl bg-[#F2EDE4] text-slate-700 hover:bg-stone-200 transition">Household</button>
            </div>
        </div>

        <div class="divide-y divide-stone-100 text-xs" id="users-list">
            <!-- Row 1: Student -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition user-row" data-category="student">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Junnyl P. Bautista</h4>
                    <p class="text-slate-500 mt-0.5">Student</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Active</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Suspend</button>
                </div>
            </div>

            <!-- Row 2: Student -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition user-row" data-category="student">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Marites A. Dela Cruz</h4>
                    <p class="text-slate-500 mt-0.5">Student</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Active</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Suspend</button>
                </div>
            </div>

            <!-- Row 3: Employer -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition user-row" data-category="employer">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Kape Marfrancisco</h4>
                    <p class="text-slate-500 mt-0.5">Employer</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Active</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Suspend</button>
                </div>
            </div>

            <!-- Row 4: Employer -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition user-row" data-category="employer">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Lutong Bahay PH</h4>
                    <p class="text-slate-500 mt-0.5">Employer</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-rose-50 text-rose-600 font-semibold px-3 py-1 rounded-lg">Suspended</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Reactivate</button>
                </div>
            </div>

            <!-- Row 5: Household (Dagdag sample para sa bagong filter) -->
            <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition user-row" data-category="household">
                <div>
                    <h4 class="font-bold text-slate-900 text-sm">Aling Nena</h4>
                    <p class="text-slate-500 mt-0.5">Household</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Active</span>
                    <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">Suspend</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript para sa Filter Buttons -->
    <script>
        function filterUsers(category) {
            // I-update ang active button styles
            const buttons = ['all', 'student', 'employer', 'household'];
            buttons.forEach(cat => {
                const btn = document.getElementById('btn-' + cat);
                if (cat === category) {
                    btn.className = "px-3.5 py-1.5 rounded-xl bg-red-700 text-white transition shadow-sm";
                } else {
                    btn.className = "px-3.5 py-1.5 rounded-xl bg-[#F2EDE4] text-slate-700 hover:bg-stone-200 transition";
                }
            });

            // I-filter ang mga rows
            const rows = document.querySelectorAll('.user-row');
            rows.forEach(row => {
                if (category === 'all' || row.getAttribute('data-category') === category) {
                    row.style.display = 'flex';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</x-admin-layout>