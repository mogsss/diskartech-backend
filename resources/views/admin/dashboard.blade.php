<x-admin-layout>
    <!-- Top 4 Stats Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Card 1: Active Students -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm relative">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                </span>
                <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-0.5 rounded-md">Live</span>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ number_format($studentsCount) }}</h3>

            <p class="text-xs text-slate-500 font-medium mt-0.5">Active students</p>
        </div>

        <!-- Card 2: Households (Bago) -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                        </path>
                    </svg>
                </span>
                <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-0.5 rounded-md">Live</span>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ number_format($householdCount ?? 0) }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Active households</p>
        </div>

        <!-- Card 3: Verified Employers -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                        </path>
                    </svg>
                </span>
                <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-0.5 rounded-md">Live</span>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ number_format($employerCount ?? 0) }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Verified employers</p>
        </div>

        <!-- Card 4: Open Job Posts (Dito na sa dulo) -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                        </path>
                    </svg>
                </span>
                <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-0.5 rounded-md">Live</span>
            </div>
            {{-- <h3class="text-2xlfont-blacktext-slate-900tracking-tight">number_format($openJobsCount) </h3> --}}
                <p class="text-xs text-slate-500 font-medium mt-0.5">Open job posts</p>
        </div>
    </div>

    <!-- Main Grid (Verification Queue + Side Info) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Verification Queue Table -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-bold text-slate-900 text-base">Verification queue</h3>
                <button
                    class="text-xs bg-[#F2EDE4] hover:bg-stone-200 font-semibold text-slate-700 px-3 py-1.5 rounded-xl transition">View
                    all</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-slate-400 text-[11px] uppercase tracking-wider border-b border-stone-100">
                            <th class="pb-3 font-semibold">User</th>
                            <th class="pb-3 font-semibold">Type</th>
                            <th class="pb-3 font-semibold">Docs</th>
                            <th class="pb-3 font-semibold">Status</th>
                            <th class="pb-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-700 text-xs divide-y divide-stone-50">
                        @forelse($users as $user)
                            @php
                                // Kunin ang tamang profile batay sa role ng user
                                $profile = null;
                                $docsCountText = '0 of 0';
                                $isComplete = false;

                                if ($user->role === 'student') {
                                    $profile = $user->studentProfile;
                                    $docsCountText = optional($profile)->docs_count ?? '0 of 3';
                                    // I-check kung umabot na sa 3 of 3
                                    $isComplete = $docsCountText === '3 of 3';
                                } elseif ($user->role === 'employer') {
                                    $profile = $user->employerProfile;
                                    $docsCountText = optional($profile)->docs_count ?? '0 of 2';
                                    // I-check kung umabot na sa 2 of 2
                                    $isComplete = $docsCountText === '2 of 2';
                                } elseif ($user->role === 'household') {
                                    $profile = $user->householdProfile;
                                    $docsCountText = optional($profile)->docs_count ?? '0 of 1';
                                    // I-check kung umabot na sa 1 of 1
                                    $isComplete = $docsCountText === '1 of 1';
                                }
                            @endphp

                            <tr class="hover:bg-stone-50/50 transition">
                                <!-- User Name -->
                                <td class="py-3.5 font-bold text-slate-900">
                                    @if($user->role === 'student')
                                        {{ optional($user->studentProfile)->student_name ?? $user->email }}
                                    @elseif($user->role === 'household')
                                        {{ optional($user->householdProfile)->household_name ?? $user->email }}
                                    @elseif($user->role === 'employer')
                                        {{ optional($user->employerProfile)->employer_name ?? $user->email }}
                                    @else
                                        {{ $user->name ?? $user->email }}
                                    @endif
                                </td>

                                <!-- Type -->
                                <td class="py-3.5 text-slate-500">{{ ucfirst($user->role) }}</td>

                                <!-- Docs Count -->
                                <td class="py-3.5 text-slate-500">
                                    {{ $docsCountText }}
                                </td>

                                <!-- Status (Dito nagbabago depende kungkumpleto o Incomplete) -->
                                <td class="py-3.5">
                                    @if($isComplete)
                                        <span
                                            class="bg-amber-50 text-amber-600 text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                                            Pending
                                        </span>
                                    @else
                                        <span
                                            class="bg-stone-100 text-stone-500 text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                                            Incomplete
                                        </span>
                                    @endif
                                </td>

                                <!-- Action -->
                                <td class="py-3.5 text-right space-x-1">
                                    @if($isComplete)
                                        <button
                                            class="bg-red-700 hover:bg-red-800 text-white font-medium text-[11px] px-3 py-1.5 rounded-lg shadow-sm transition">Approve</button>
                                    @else
                                        <button disabled
                                            class="bg-stone-200 text-stone-400 font-medium text-[11px] px-3 py-1.5 rounded-lg cursor-not-allowed">Approve</button>
                                    @endif
                                    <button
                                        class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-medium text-[11px] px-3 py-1.5 rounded-lg transition">Review</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400">Wala pang nakabinbing verifications
                                    sa ngayon.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Side Widgets (Flagged Content Loop) -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5">
                <h3 class="font-bold text-slate-900 text-sm mb-4">Flagged content</h3>
                {{-- -
                <div class="space-y-3 text-xs">
                    @forelse($flaggedContent as $report)
                    <div class="p-3 bg-[#FAFAFA] rounded-xl border border-stone-100 flex justify-between items-center">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $report->title }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">REF-{{ $report->id }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-rose-600 font-bold block text-[10px]">{{ ucfirst($report->severity ??
                                'High') }}</span>
                            <a href="#" class="text-red-700 font-semibold text-[10px] hover:underline">Resolve</a>
                        </div>
                    </div>
                    @empty
                    <p class="text-slate-400 text-center py-4">Walang flagged reports sa kasalukuyan.</p>
                    @endforelse
                </div>
                --}}
            </div>

            <!-- Weekly Activity Dark Box -->
            <div class="bg-stone-900 text-white rounded-2xl p-5 shadow-sm">
                <h3 class="font-bold text-sm mb-4">Weekly activity</h3>
                <div class="flex items-end justify-between h-24 pt-4 px-2">
                    <div class="w-6 bg-amber-500/80 rounded-t-lg h-10"></div>
                    <div class="w-6 bg-amber-500 rounded-t-lg h-16"></div>
                    <div class="w-6 bg-amber-500/60 rounded-t-lg h-8"></div>
                    <div class="w-6 bg-amber-500 rounded-t-lg h-14"></div>
                    <div class="w-6 bg-amber-500 rounded-t-lg h-20"></div>
                    <div class="w-6 bg-amber-500 rounded-t-lg h-24"></div>
                </div>
                <div
                    class="flex justify-between text-[10px] text-stone-400 px-1 mt-2 border-t border-stone-800 pt-2 font-mono">
                    <span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                </div>
                <p class="text-[11px] text-stone-300 mt-3 font-medium">Real-time database sync active.</p>
            </div>
        </div>

    </div>
</x-admin-layout>