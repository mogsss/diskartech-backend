<x-admin-layout>
    <!-- Top Header Title Change -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const headerTitle = document.querySelector('header h2');
            if(headerTitle) headerTitle.textContent = "Platform settings";
        });
    </script>

    <!-- Settings Container -->
    <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 max-w-4xl">
        <div class="mb-6 pb-4 border-b border-stone-100">
            <h3 class="font-bold text-slate-900 text-base">Global platform controls</h3>
            <p class="text-xs text-slate-500 mt-0.5">Manage automated compliance, user restrictions, and system-wide operational rules.</p>
        </div>

        <div class="space-y-6">
            <!-- Setting Item 1 -->
            <div class="flex items-center justify-between py-3 border-b border-stone-50">
                <div class="pr-4">
                    <h4 class="font-bold text-slate-900 text-sm">Auto-verify complete student credentials</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Automatically approve student verification requests when all 4 required validation documents are uploaded and formatted correctly.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="checkbox" class="sr-only peer" onchange="toggleSetting(this)">
                    <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-700"></div>
                </label>
            </div>

            <!-- Setting Item 2 -->
            <div class="flex items-center justify-between py-3 border-b border-stone-50">
                <div class="pr-4">
                    <h4 class="font-bold text-slate-900 text-sm">Enforce minimum pay floor on job listings</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Automatically flag and reject job posts that fall below the regional standard minimum wage or hourly compensation guidelines.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="checkbox" checked class="sr-only peer" onchange="toggleSetting(this)">
                    <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-700"></div>
                </label>
            </div>

            <!-- Setting Item 3 -->
            <div class="flex items-center justify-between py-3 border-b border-stone-50">
                <div class="pr-4">
                    <h4 class="font-bold text-slate-900 text-sm">Block gigs overlapping active class hours</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Prevent student accounts from accepting or applying to shifts and part-time tasks that conflict with their registered academic schedules.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="checkbox" checked class="sr-only peer" onchange="toggleSetting(this)">
                    <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-700"></div>
                </label>
            </div>

            <!-- Setting Item 4 -->
            <div class="flex items-center justify-between py-3">
                <div class="pr-4">
                    <h4 class="font-bold text-slate-900 text-sm">System maintenance mode</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Temporarily restrict access to mobile application endpoints and display a scheduled maintenance advisory to standard users.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="checkbox" class="sr-only peer" onchange="toggleSetting(this)">
                    <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-700"></div>
                </label>
            </div>
        </div>

        <!-- Save Button Area -->
        <div class="mt-8 pt-4 border-t border-stone-100 flex justify-end">
            <button onclick="alert('Platform settings updated successfully!')" class="bg-red-700 hover:bg-red-800 text-white text-xs font-semibold px-5 py-2.5 rounded-xl shadow-sm transition">
                Save changes
            </button>
        </div>
    </div>

    <script>
        function toggleSetting(element) {
            // Optional feedback handler when toggling switches
            console.log("Setting toggled:", element.checked);
        }
    </script>
</x-admin-layout> 