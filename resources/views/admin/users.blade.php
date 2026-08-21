<x-admin-layout>
    @push('header-title')
        Users
    @endpush

    <!-- Accounts Container -->
    <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h3 class="font-bold text-slate-900 text-base">Accounts</h3>
            
            <!-- Search Bar & Dropdown Filter Form -->
            <form method="GET" action="{{ route('admin.users') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
                
                <!-- Search Input -->
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..." 
                        class="w-full sm:w-64 bg-[#F2EDE4] text-xs rounded-xl pl-9 pr-4 py-2 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 placeholder-slate-400 border-0">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                </div>

                <!-- Dropdown Filter para sa Role -->
                <select name="role" onchange="this.form.submit()" 
                    class="bg-[#F2EDE4] text-xs rounded-xl px-4 py-2 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 border-0 font-semibold cursor-pointer">
                    <option value="">All Roles</option>
                    <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Students</option>
                    <option value="employer" {{ request('role') === 'employer' ? 'selected' : '' }}>Employers</option>
                    <option value="household" {{ request('role') === 'household' ? 'selected' : '' }}>Household</option>
                </select>

                <!-- I-retain ang search query kapag nag-filter, o vice versa -->
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
            </form>
        </div>

        <div class="divide-y divide-stone-100 text-xs" id="users-list">
            @forelse($users as $user)
                @php
                    // Kunin ang profile batay sa role ng user
                    $profile = match ($user->role) {
                        'student' => $user->studentProfile,
                        'employer' => $user->employerProfile,
                        'household' => $user->householdProfile,
                        default => null,
                    };

                    $userName = match ($user->role) {
                        'student' => optional($profile)->student_name ?? $user->email,
                        'employer' => optional($profile)->hirer_name ?? $user->email,
                        'household' => optional($profile)->household_name ?? $user->email,
                        default => $user->name ?? $user->email,
                    };

                    $isVerified = optional($profile)->isVerified == 1;
                @endphp

                <div class="py-4 flex items-center justify-between hover:bg-stone-50/50 px-2 rounded-xl transition user-row">
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm">{{ $userName }}</h4>
                        <p class="text-slate-500 mt-0.5">{{ ucfirst($user->role) }} · <span class="text-slate-400">{{ $user->email }}</span></p>
                    </div>
                    <div class="flex items-center space-x-3">
                        @if($isVerified)
                            <span class="bg-emerald-50 text-emerald-600 font-semibold px-3 py-1 rounded-lg">Active</span>
                        @else
                            <span class="bg-amber-50 text-amber-600 font-semibold px-3 py-1 rounded-lg">Unverified</span>
                        @endif

                        <button class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition">
                            Manage
                        </button>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-slate-400">
                    Wala pang rehistradong users o walang tumutugma sa iyong hinahanap.
                </div>
            @endforelse
        </div>

        <!-- Laravel Pagination Links (Para sa limit na 10 users bawat page) -->
        <div class="mt-6 pt-4 border-t border-stone-100">
            {{ $users->links() }}
        </div>
    </div>
</x-admin-layout>