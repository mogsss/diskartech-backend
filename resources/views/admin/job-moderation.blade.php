<x-admin-layout>
    @push('header-title')
        Job Moderation
    @endpush

    <!-- Top Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $statsCards = [
                [
                    'label' => 'Total job posts',
                    'count' => $stats['total'] ?? 0,
                    'badge' => 'All',
                    'badgeBg' => 'bg-slate-100',
                    'badgeColor' => 'text-slate-700',
                    'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'
                ],
                [
                    'label' => 'Live on student app',
                    'count' => $stats['live'] ?? 0,
                    'badge' => 'Live',
                    'badgeBg' => 'bg-emerald-50',
                    'badgeColor' => 'text-emerald-600',
                    'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
                ],
                [
                    'label' => 'Hidden / Under review',
                    'count' => $stats['hidden'] ?? 0,
                    'badge' => 'Hidden',
                    'badgeBg' => 'bg-amber-50',
                    'badgeColor' => 'text-amber-600',
                    'icon' => 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18'
                ],
                [
                    'label' => 'Student applications',
                    'count' => $stats['applications'] ?? 0,
                    'badge' => 'System',
                    'badgeBg' => 'bg-indigo-50',
                    'badgeColor' => 'text-indigo-600',
                    'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
                ],
            ];
        @endphp

        @foreach($statsCards as $card)
            <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm relative">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"></path>
                        </svg>
                    </span>
                    <span class="{{ $card['badgeColor'] }} font-semibold text-xs {{ $card['badgeBg'] }} px-2 py-0.5 rounded-md">
                        {{ $card['badge'] }}
                    </span>
                </div>
                <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ number_format($card['count']) }}</h3>
                <p class="text-xs text-slate-500 font-medium mt-0.5">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="mb-4 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Main Container with Alpine.js -->
    <div x-data="{
        openModal: false,
        selectedJob: null,
        showDetails(data) {
            this.selectedJob = data;
            this.openModal = true;
        }
    }" class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 relative">

        <!-- Top Header & Search/Filter Toolbar -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Job posts</h3>
                <p class="text-xs text-slate-400 mt-0.5">Manage live job listings, hide inappropriate content, and view applicant details</p>
            </div>

            <!-- Aligned Toolbar Form -->
            <form method="GET" action="{{ route('admin.job-moderation') }}" class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
                <!-- Search Input -->
                <div class="relative flex-1 sm:flex-initial">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title, hirer, email..." 
                        class="w-full sm:w-56 bg-[#F2EDE4] text-xs rounded-xl pl-9 pr-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 placeholder-slate-400 border-0">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </span>
                </div>

                <!-- Status Filter -->
                <select name="status" onchange="this.form.submit()" 
                    class="bg-[#F2EDE4] text-xs rounded-xl px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 border-0 font-semibold cursor-pointer">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Live</option>
                    <option value="hidden" {{ request('status') === 'hidden' ? 'selected' : '' }}>Hidden</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>

                <!-- Category Filter -->
                @if(isset($categories) && count($categories) > 0)
                    <select name="category" onchange="this.form.submit()" 
                        class="bg-[#F2EDE4] text-xs rounded-xl px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 border-0 font-semibold cursor-pointer">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                @endif

                <!-- Clear Filters Button -->
                @if(request('search') || request('status') || request('category'))
                    <a href="{{ route('admin.job-moderation') }}" 
                        class="text-xs bg-stone-100 hover:bg-stone-200 text-stone-600 font-semibold px-3 py-2 rounded-xl transition flex items-center space-x-1"
                        title="Clear filters">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span>Clear</span>
                    </a>
                @endif

                <!-- Refresh Button -->
                <button type="button" onclick="window.location.reload();"
                    class="text-xs bg-[#F2EDE4] hover:bg-stone-200 font-semibold text-slate-700 px-3 py-2 rounded-xl transition flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                    <span>Refresh</span>
                </button>
            </form>
        </div>

        <!-- Professional Table View matching Verification Dashboard -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-400 text-[11px] uppercase tracking-wider border-b border-stone-100">
                        <th class="pb-3.5 font-semibold">Job Post</th>
                        <th class="pb-3.5 font-semibold">Hirer / Poster</th>
                        <th class="pb-3.5 font-semibold">Schedule</th>
                        <th class="pb-3.5 font-semibold">Rate</th>
                        <th class="pb-3.5 font-semibold">Status</th>
                        <th class="pb-3.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700 text-xs divide-y divide-stone-100">
                    @forelse($jobs as $job)
                        @php
                            $isEmployer = !empty($job->employer);
                            $isHousehold = !empty($job->household);

                            $posterName = match(true) {
                                $isEmployer => $job->employer->employer_name ?? $job->employer->hirer_name ?? $job->user?->name ?? 'Employer',
                                $isHousehold => $job->household->household_name ?? $job->user?->name ?? 'Household',
                                default => $job->user?->name ?? $job->user?->email ?? 'Hirer'
                            };

                            $posterType = match(true) {
                                $isEmployer => 'Business',
                                $isHousehold => 'Household',
                                default => 'Individual'
                            };

                            $posterEmail = $job->user?->email ?? 'N/A';
                            $posterPhone = $job->employer?->contact_number ?? $job->household?->contact_number ?? 'N/A';
                            $posterLocation = $job->employer?->location ?? $job->household?->location ?? 'N/A';

                            $applicationsCount = $job->applications ? $job->applications->count() : 0;

                            $reqsList = is_array($job->requirements) ? $job->requirements : (json_decode($job->requirements, true) ?? []);
                            $skillsList = is_array($job->skills) ? $job->skills : (json_decode($job->skills, true) ?? []);
                            $daysList = is_array($job->available_days) ? $job->available_days : (json_decode($job->available_days, true) ?? []);

                            $jobPayload = [
                                'id' => $job->id,
                                'title' => $job->title,
                                'description' => $job->description,
                                'salary' => number_format((float)$job->salary, 2),
                                'category' => $job->category ?? 'General',
                                'time_slot' => $job->time_slot ?? 'Flexible',
                                'available_days' => $daysList,
                                'status' => $job->status,
                                'created_at' => $job->created_at ? $job->created_at->format('M d, Y h:i A') : 'N/A',
                                'requirements' => $reqsList,
                                'skills' => $skillsList,
                                'poster' => [
                                    'name' => $posterName,
                                    'type' => $posterType,
                                    'email' => $posterEmail,
                                    'phone' => $posterPhone,
                                    'location' => $posterLocation,
                                ],
                                'applications_count' => $applicationsCount,
                                'applications' => $job->applications->map(function($app) {
                                    return [
                                        'id' => $app->id,
                                        'student_name' => $app->student?->student_name ?? 'Student',
                                        'school' => $app->student?->student_school_name ?? 'N/A',
                                        'course' => $app->student?->course ?? 'N/A',
                                        'status' => $app->status ?? 'pending',
                                        'date' => $app->created_at ? $app->created_at->format('M d, Y') : 'N/A',
                                    ];
                                })->values(),
                                'toggle_url' => route('admin.job-moderation.toggle', $job->id),
                                'delete_url' => route('admin.job-moderation.destroy', $job->id),
                            ];
                        @endphp

                        <tr class="hover:bg-stone-50/50 transition">
                            <!-- Job Title & Meta -->
                            <td class="py-4 pr-3 align-middle">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <button type="button" @click="showDetails(@js($jobPayload))" 
                                            class="font-bold text-slate-900 text-sm hover:text-red-700 transition text-left">
                                            {{ $job->title }}
                                        </button>
                                        <span class="bg-stone-100 text-stone-600 font-medium px-2 py-0.5 rounded text-[10px]">
                                            {{ $job->category ?? 'General' }}
                                        </span>
                                    </div>
                                    <p class="text-slate-400 text-[11px] flex items-center gap-1.5">
                                        <span>Posted {{ $job->created_at ? $job->created_at->diffForHumans() : 'recently' }}</span>
                                        <span>·</span>
                                        <span class="text-indigo-600 font-medium">{{ $applicationsCount }} {{ Str::plural('applicant', $applicationsCount) }}</span>
                                    </p>
                                </div>
                            </td>

                            <!-- Hirer / Poster -->
                            <td class="py-4 pr-3 align-middle">
                                <div>
                                    <p class="font-bold text-slate-900">{{ $posterName }}</p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        @if($isEmployer)
                                            <span class="bg-blue-50 text-blue-700 font-semibold px-1.5 py-0.2 rounded text-[10px] border border-blue-100">
                                                Business
                                            </span>
                                        @elseif($isHousehold)
                                            <span class="bg-purple-50 text-purple-700 font-semibold px-1.5 py-0.2 rounded text-[10px] border border-purple-100">
                                                Household
                                            </span>
                                        @endif
                                        <span class="text-slate-400 text-[11px] truncate max-w-[150px]">{{ $posterEmail }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Schedule & Days -->
                            <td class="py-4 pr-3 align-middle">
                                <div class="text-slate-600">
                                    <p class="font-medium text-slate-800">{{ $job->time_slot ?? 'Whole Day' }}</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">
                                        @if(!empty($daysList))
                                            {{ implode(', ', $daysList) }}
                                        @else
                                            Flexible days
                                        @endif
                                    </p>
                                </div>
                            </td>

                            <!-- Rate -->
                            <td class="py-4 pr-3 align-middle">
                                <span class="font-bold text-emerald-700 text-sm">
                                    ₱{{ number_format((float)$job->salary, 2) }}
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-4 pr-3 align-middle">
                                @if($job->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-600 font-semibold text-[11px] px-2.5 py-1 rounded-lg">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Live
                                    </span>
                                @elseif($job->status === 'hidden')
                                    <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-600 font-semibold text-[11px] px-2.5 py-1 rounded-lg">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Hidden
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 bg-stone-100 text-stone-500 font-semibold text-[11px] px-2.5 py-1 rounded-lg">
                                        <span class="w-1.5 h-1.5 rounded-full bg-stone-400"></span>
                                        {{ ucfirst($job->status) }}
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-4 text-right align-middle whitespace-nowrap">
                                <div class="inline-flex items-center space-x-1.5">
                                    <!-- Toggle Hide / Publish -->
                                    <form action="{{ route('admin.job-moderation.toggle', $job->id) }}" method="POST" class="inline">
                                        @csrf
                                        @if($job->status === 'active')
                                            <button type="submit" 
                                                class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition text-[11px]"
                                                title="Hide this job from students">
                                                Hide post
                                            </button>
                                        @else
                                            <button type="submit" 
                                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-3 py-1.5 rounded-xl transition shadow-sm text-[11px]"
                                                title="Make this job live">
                                                Publish
                                            </button>
                                        @endif
                                    </form>

                                    <!-- Details Button -->
                                    <button type="button" @click="showDetails(@js($jobPayload))"
                                        class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition text-[11px]"
                                        title="View job details">
                                        Details
                                    </button>

                                    <!-- Delete Button -->
                                    <form action="{{ route('admin.job-moderation.destroy', $job->id) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to permanently delete this job post? All associated student applications will also be removed.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                            class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Delete permanently">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                No job posts found at this time.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Links -->
        <div class="mt-6 pt-4 border-t border-stone-100">
            {{ $jobs->links() }}
        </div>

        <!-- ================= JOB DETAILS MODAL ================= -->
        <div x-show="openModal" style="display: none;" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">

            <div @click.away="openModal = false" 
                class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl relative text-left max-h-[90vh] overflow-y-auto">
                
                <template x-if="selectedJob">
                    <div>
                        <!-- Modal Top Header -->
                        <div class="flex justify-between items-start mb-4 border-b border-stone-100 pb-3">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-stone-100 text-stone-600" x-text="selectedJob.category"></span>
                                    <template x-if="selectedJob.status === 'active'">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-600">Live</span>
                                    </template>
                                    <template x-if="selectedJob.status === 'hidden'">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-amber-50 text-amber-600">Hidden</span>
                                    </template>
                                </div>
                                <h3 class="font-bold text-slate-900 text-lg" x-text="selectedJob.title"></h3>
                                <p class="text-xs text-slate-400 mt-0.5">Posted on <span x-text="selectedJob.created_at"></span></p>
                            </div>
                            <button @click="openModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
                        </div>

                        <!-- Poster Card -->
                        <div class="bg-stone-50 border border-stone-200/80 rounded-xl p-4 mb-4">
                            <span class="font-semibold text-slate-400 block text-xs uppercase tracking-wider mb-2">Hirer / Poster Information</span>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Name:</span>
                                    <p class="font-bold text-slate-800 text-sm" x-text="selectedJob.poster.name"></p>
                                    <span class="text-slate-400 text-[11px]" x-text="selectedJob.poster.type"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Email:</span>
                                    <p class="font-medium text-slate-800 select-all" x-text="selectedJob.poster.email"></p>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Contact:</span>
                                    <p class="font-medium text-slate-800" x-text="selectedJob.poster.phone"></p>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[11px]">Location:</span>
                                    <p class="font-medium text-slate-800" x-text="selectedJob.poster.location"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Key Specs -->
                        <div class="grid grid-cols-3 gap-3 mb-4 text-center">
                            <div class="bg-stone-50 border border-stone-200/60 rounded-xl p-3">
                                <span class="text-[11px] text-slate-400 block font-semibold uppercase">Offered Rate</span>
                                <span class="text-base font-black text-emerald-700">₱<span x-text="selectedJob.salary"></span></span>
                            </div>
                            <div class="bg-stone-50 border border-stone-200/60 rounded-xl p-3">
                                <span class="text-[11px] text-slate-400 block font-semibold uppercase">Time Slot</span>
                                <span class="text-xs font-bold text-slate-800" x-text="selectedJob.time_slot"></span>
                            </div>
                            <div class="bg-stone-50 border border-stone-200/60 rounded-xl p-3">
                                <span class="text-[11px] text-slate-400 block font-semibold uppercase">Applicants</span>
                                <span class="text-base font-black text-indigo-700" x-text="selectedJob.applications_count"></span>
                            </div>
                        </div>

                        <!-- Work Days -->
                        <div class="mb-4">
                            <span class="font-semibold text-slate-400 block text-xs uppercase mb-2">Available Work Days</span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="day in selectedJob.available_days" :key="day">
                                    <span class="bg-[#F2EDE4] text-slate-700 font-semibold px-2.5 py-1 rounded-lg text-xs" x-text="day"></span>
                                </template>
                                <template x-if="!selectedJob.available_days || selectedJob.available_days.length === 0">
                                    <span class="text-xs text-slate-400 italic">Flexible schedule</span>
                                </template>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <span class="font-semibold text-slate-400 block text-xs uppercase mb-2">Job Description</span>
                            <div class="bg-stone-50 border border-stone-200/60 rounded-xl p-4 text-xs text-slate-700 leading-relaxed whitespace-pre-line" x-text="selectedJob.description"></div>
                        </div>

                        <!-- Requirements & Skills -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <span class="font-semibold text-slate-400 block text-xs uppercase mb-2">Required Documents</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="req in selectedJob.requirements" :key="req">
                                        <span class="bg-blue-50 text-blue-700 border border-blue-100 font-semibold px-2 py-0.5 rounded text-xs" x-text="req"></span>
                                    </template>
                                    <template x-if="!selectedJob.requirements || selectedJob.requirements.length === 0">
                                        <span class="text-xs text-slate-400 italic">None specified</span>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <span class="font-semibold text-slate-400 block text-xs uppercase mb-2">Preferred Skills</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="skill in selectedJob.skills" :key="skill">
                                        <span class="bg-amber-50 text-amber-800 border border-amber-100 font-semibold px-2 py-0.5 rounded text-xs" x-text="skill"></span>
                                    </template>
                                    <template x-if="!selectedJob.skills || selectedJob.skills.length === 0">
                                        <span class="text-xs text-slate-400 italic">None specified</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Applicants Section -->
                        <div class="border-t border-stone-100 pt-4 mb-4">
                            <span class="font-semibold text-slate-400 block text-xs uppercase mb-2">
                                Student Applicants (<span x-text="selectedJob.applications_count"></span>)
                            </span>

                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                <template x-for="app in selectedJob.applications" :key="app.id">
                                    <div class="flex items-center justify-between bg-stone-50 border border-stone-200/60 p-2.5 rounded-xl text-xs">
                                        <div>
                                            <p class="font-bold text-slate-900" x-text="app.student_name"></p>
                                            <p class="text-slate-400 text-[11px]" x-text="app.school + ' · ' + app.course"></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded"
                                                :class="{
                                                    'bg-emerald-50 text-emerald-600': app.status === 'accepted' || app.status === 'hired',
                                                    'bg-amber-50 text-amber-600': app.status === 'pending',
                                                    'bg-blue-50 text-blue-600': app.status === 'interview',
                                                    'bg-rose-50 text-rose-600': app.status === 'rejected'
                                                }"
                                                x-text="app.status.toUpperCase()">
                                            </span>
                                            <span class="text-[10px] text-slate-400 block mt-0.5" x-text="app.date"></span>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!selectedJob.applications || selectedJob.applications.length === 0">
                                    <p class="text-xs text-slate-400 italic py-1">No students have applied for this job post yet.</p>
                                </template>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-stone-100 pt-4 flex items-center justify-between">
                            <form :action="selectedJob.toggle_url" method="POST">
                                @csrf
                                <template x-if="selectedJob.status === 'active'">
                                    <button type="submit" class="bg-amber-50 text-amber-700 hover:bg-amber-100 font-semibold px-4 py-2 rounded-xl text-xs transition">
                                        Hide this post
                                    </button>
                                </template>
                                <template x-if="selectedJob.status !== 'active'">
                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-xl text-xs transition shadow-sm">
                                        Publish to mobile app
                                    </button>
                                </template>
                            </form>

                            <button @click="openModal = false" class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-4 py-2 rounded-xl text-xs transition">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>
</x-admin-layout>