<x-admin-layout>
    <!-- Top 4 Stats Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Card 1 -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm relative">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </span>
                <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-0.5 rounded-md">Live</span>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ number_format($studentsCount ?? 0) }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Active students</p>
        </div>

        <!-- Card 2 -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </span>
                <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-0.5 rounded-md">Live</span>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ number_format($employerCount ?? 0) }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Verified employers</p>
        </div>

        <!-- Card 3 -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </span>
                <span class="text-emerald-600 font-semibold text-xs bg-emerald-50 px-2 py-0.5 rounded-md">Live</span>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ number_format($householdCount ?? 0) }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Active households</p>
        </div>

        <!-- Card 4 -->
        <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <span class="text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </span>
                <span class="text-rose-600 font-semibold text-xs bg-rose-50 px-2 py-0.5 rounded-md">System</span>
            </div>
            <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ count($users ?? []) }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Total applicants</p>
        </div>
    </div>

    <!-- Expanded Verification Queue Section (Full Width) -->
    <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-slate-900 text-base">Verification queue</h3>
            <div class="flex items-center space-x-2">
                <button onclick="window.location.reload();" class="text-xs bg-[#F2EDE4] hover:bg-stone-200 font-semibold text-slate-700 px-3 py-1.5 rounded-xl transition flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-400 text-[11px] uppercase tracking-wider border-b border-stone-100">
                        <th class="pb-3 font-semibold">Applicant</th>
                        <th class="pb-3 font-semibold">Type</th>
                        <th class="pb-3 font-semibold">Reference</th>
                        <th class="pb-3 font-semibold">Docs</th>
                        <th class="pb-3 font-semibold">Status</th>
                        <th class="pb-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700 text-xs divide-y divide-stone-50">
                    @forelse($users as $user)
                        @php
                            $profile = null;
                            $docsCountText = '0 of 0';
                            $isComplete = false;
                            $referenceText = 'N/A';
                            $applicantName = $user->email;

                            if ($user->role === 'student') {
                                $profile = $user->studentProfile;
                                $docsCountText = optional($profile)->docs_count ?? '0 of 3';
                                $isComplete = ($docsCountText === '3 of 3');
                                $referenceText = optional($profile)->student_school_name ?? 'Student School';
                                $applicantName = optional($profile)->student_name ?? $user->email;
                            } elseif ($user->role === 'employer') {
                                $profile = $user->employerProfile;
                                $docsCountText = optional($profile)->docs_count ?? '0 of 2';
                                $isComplete = ($docsCountText === '2 of 2');
                                $referenceText = optional($profile)->employer_name ?? 'Business Name';
                                $applicantName = optional($profile)->hirer_name ?? $user->email;
                            } elseif ($user->role === 'household') {
                                $profile = $user->householdProfile;
                                $docsCountText = optional($profile)->docs_count ?? '0 of 1';
                                $isComplete = ($docsCountText === '1 of 1');
                                $referenceText = optional($profile)->location ?? 'Household Location';
                                $applicantName = optional($profile)->household_name ?? $user->email;
                            }
                        @endphp

                        <tr class="hover:bg-stone-50/50 transition">
                            <td class="py-3.5 font-bold text-slate-900">{{ $applicantName }}</td>
                            <td class="py-3.5 text-slate-500">{{ ucfirst($user->role) }}</td>
                            <td class="py-3.5 text-slate-600">{{ $referenceText }}</td>
                            <td class="py-3.5 text-slate-500">{{ $docsCountText }}</td>
                            <td class="py-3.5">
                                @if($isComplete)
                                    <span class="bg-amber-50 text-amber-600 text-[11px] font-semibold px-2.5 py-1 rounded-lg">Pending</span>
                                @else
                                    <span class="bg-stone-100 text-stone-500 text-[11px] font-semibold px-2.5 py-1 rounded-lg">Incomplete</span>
                                @endif
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                @if($isComplete)
                                    <button class="bg-red-700 hover:bg-red-800 text-white font-medium text-[11px] px-3 py-1.5 rounded-lg shadow-sm transition">Approve</button>
                                @else
                                    <button disabled class="bg-stone-200 text-stone-400 font-medium text-[11px] px-3 py-1.5 rounded-lg cursor-not-allowed">Approve</button>
                                @endif
                                <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-medium text-[11px] px-3 py-1.5 rounded-lg transition">Review</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                Wala pang mga aplikante o naghihintay na verifications sa ngayon.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>