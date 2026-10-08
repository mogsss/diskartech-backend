<x-admin-layout>
    @push('header-title')
        Reports & Analytics
    @endpush

    <!-- Top Key Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $metricCards = [
                [
                    'label' => 'Open incident reports',
                    'count' => ($incidentStats['pending'] ?? 0) + ($incidentStats['investigating'] ?? 0),
                    'badge' => ($incidentStats['pending'] ?? 0) > 0 ? 'Action required' : 'Clear',
                    'badgeBg' => ($incidentStats['pending'] ?? 0) > 0 ? 'bg-rose-50' : 'bg-emerald-50',
                    'badgeColor' => ($incidentStats['pending'] ?? 0) > 0 ? 'text-rose-600' : 'text-emerald-600',
                    'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
                ],
                [
                    'label' => 'Overall verification rate',
                    'count' => ($analytics['users']['verification_rate'] ?? 0) . '%',
                    'badge' => 'Trust & safety',
                    'badgeBg' => 'bg-emerald-50',
                    'badgeColor' => 'text-emerald-600',
                    'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
                ],
                [
                    'label' => 'Hiring success rate',
                    'count' => ($analytics['funnel']['hiring_rate'] ?? 0) . '%',
                    'badge' => number_format($analytics['funnel']['hired'] ?? 0) . ' hired',
                    'badgeBg' => 'bg-indigo-50',
                    'badgeColor' => 'text-indigo-600',
                    'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'
                ],
                [
                    'label' => 'Average platform rating',
                    'count' => '★ ' . number_format($analytics['reviews']['average_rating'] ?? 5.0, 1),
                    'badge' => number_format($analytics['reviews']['total'] ?? 0) . ' reviews',
                    'badgeBg' => 'bg-amber-50',
                    'badgeColor' => 'text-amber-700',
                    'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'
                ],
            ];
        @endphp

        @foreach($metricCards as $card)
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
                <h3 class="text-2xl font-black text-slate-900 tracking-tight">{{ $card['count'] }}</h3>
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

    <!-- Main Container with Alpine.js Tabs & Modal -->
    <div x-data="{
        activeTab: '{{ $tab ?? 'incidents' }}',
        openModal: false,
        selectedReport: null,
        showReport(data) {
            this.selectedReport = data;
            this.openModal = true;
        }
    }" class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 relative">

        <!-- Tab Navigation Buttons -->
        <div class="flex items-center justify-between border-b border-stone-200 pb-4 mb-6 flex-wrap gap-4">
            <div class="flex items-center space-x-2">
                <button type="button" @click="activeTab = 'incidents'" 
                    :class="activeTab === 'incidents' ? 'bg-red-700 text-white font-bold shadow-sm' : 'bg-[#F2EDE4] text-slate-700 hover:bg-stone-200 font-medium'"
                    class="px-4 py-2 rounded-xl text-xs transition flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span>Incident & Safety Reports</span>
                    @if(($incidentStats['pending'] ?? 0) > 0)
                        <span class="bg-white text-red-700 text-[10px] font-black px-1.5 py-0.2 rounded-full">
                            {{ $incidentStats['pending'] }}
                        </span>
                    @endif
                </button>

                <button type="button" @click="activeTab = 'analytics'" 
                    :class="activeTab === 'analytics' ? 'bg-red-700 text-white font-bold shadow-sm' : 'bg-[#F2EDE4] text-slate-700 hover:bg-stone-200 font-medium'"
                    class="px-4 py-2 rounded-xl text-xs transition flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span>Platform Analytics & System Overview</span>
                </button>
            </div>

            <div class="text-xs text-slate-400">
                <span class="font-medium text-slate-600">{{ number_format($incidentStats['total']) }}</span> Total incidents logged
            </div>
        </div>

        <!-- ================= TAB 1: INCIDENT & SAFETY REPORTS ================= -->
        <div x-show="activeTab === 'incidents'">
            <!-- Filters Toolbar -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Safety and incident reports</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Review user complaints, flag violations, and record resolution notes</p>
                </div>

                <form method="GET" action="{{ route('admin.reports') }}" class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
                    <input type="hidden" name="tab" value="incidents">

                    <!-- Search Input -->
                    <div class="relative flex-1 sm:flex-initial">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search reason, user, email..." 
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
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="investigating" {{ request('status') === 'investigating' ? 'selected' : '' }}>Investigating</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="dismissed" {{ request('status') === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
                    </select>

                    <!-- Type / Reason Filter -->
                    <select name="type" onchange="this.form.submit()" 
                        class="bg-[#F2EDE4] text-xs rounded-xl px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-600 text-slate-700 border-0 font-semibold cursor-pointer">
                        <option value="">All Reasons</option>
                        @foreach($types as $t)
                            <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>

                    <!-- Clear Filter -->
                    @if(request('search') || request('status') || request('type'))
                        <a href="{{ route('admin.reports', ['tab' => 'incidents']) }}" 
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

            <!-- Table of Reports -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-slate-400 text-[11px] uppercase tracking-wider border-b border-stone-100">
                            <th class="pb-3.5 font-semibold">Incident / Subject</th>
                            <th class="pb-3.5 font-semibold">Reported Entity</th>
                            <th class="pb-3.5 font-semibold">Reported By</th>
                            <th class="pb-3.5 font-semibold">Status</th>
                            <th class="pb-3.5 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-700 text-xs divide-y divide-stone-100">
                        @forelse($reports as $report)
                            @php
                                $reporterName = $report->reporter?->studentProfile?->student_name 
                                    ?? $report->reporter?->employerProfile?->hirer_name 
                                    ?? $report->reporter?->householdProfile?->household_name 
                                    ?? $report->reporter?->name 
                                    ?? $report->reporter?->email 
                                    ?? 'Anonymous User';

                                $reportedTargetName = $report->job ? $report->job->title : (
                                    $report->reportedUser?->studentProfile?->student_name 
                                    ?? $report->reportedUser?->employerProfile?->hirer_name 
                                    ?? $report->reportedUser?->householdProfile?->household_name 
                                    ?? $report->reportedUser?->name 
                                    ?? $report->reportedUser?->email 
                                    ?? 'Unknown Entity'
                                );

                                $payload = [
                                    'id' => $report->id,
                                    'subject' => $report->subject ?: ucfirst(str_replace('_', ' ', $report->report_type)),
                                    'report_type' => ucfirst(str_replace('_', ' ', $report->report_type)),
                                    'description' => $report->description,
                                    'status' => $report->status,
                                    'admin_notes' => $report->admin_notes ?: '',
                                    'date' => $report->created_at ? $report->created_at->format('M d, Y h:i A') : 'N/A',
                                    'reporter' => [
                                        'name' => $reporterName,
                                        'email' => $report->reporter?->email ?? 'N/A',
                                        'role' => ucfirst($report->reporter?->role ?? 'User'),
                                    ],
                                    'target' => [
                                        'name' => $reportedTargetName,
                                        'type' => $report->job ? 'Job Listing' : ucfirst($report->reportedUser?->role ?? 'User'),
                                        'email' => $report->reportedUser?->email ?? ($report->job?->user?->email ?? 'N/A'),
                                        'job_title' => $report->job?->title,
                                    ],
                                    'update_url' => route('admin.reports.status', $report->id),
                                    'delete_url' => route('admin.reports.destroy', $report->id),
                                ];
                            @endphp

                            <tr class="hover:bg-stone-50/50 transition">
                                <!-- Incident Info -->
                                <td class="py-4 pr-3 align-middle">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <button type="button" @click="showReport(@js($payload))"
                                                class="font-bold text-slate-900 text-sm hover:text-red-700 transition text-left">
                                                {{ $report->subject ?: ucfirst(str_replace('_', ' ', $report->report_type)) }}
                                            </button>
                                            <span class="bg-rose-50 text-rose-700 border border-rose-100 font-semibold px-2 py-0.5 rounded text-[10px]">
                                                {{ ucfirst(str_replace('_', ' ', $report->report_type)) }}
                                            </span>
                                        </div>
                                        <p class="text-slate-400 text-[11px] truncate max-w-md">
                                            {{ Str::limit($report->description, 75) }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Reported Target -->
                                <td class="py-4 pr-3 align-middle">
                                    <div>
                                        <p class="font-bold text-slate-800">{{ $reportedTargetName }}</p>
                                        <p class="text-slate-400 text-[11px] mt-0.5">
                                            {{ $report->job ? 'Job Post' : ucfirst($report->reportedUser?->role ?? 'User') }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Reporter Info -->
                                <td class="py-4 pr-3 align-middle">
                                    <div>
                                        <p class="font-semibold text-slate-800">{{ $reporterName }}</p>
                                        <p class="text-slate-400 text-[11px] mt-0.5">
                                            {{ $report->created_at ? $report->created_at->diffForHumans() : '' }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-4 pr-3 align-middle">
                                    @if($report->status === 'pending')
                                        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-600 font-semibold text-[11px] px-2.5 py-1 rounded-lg">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            Pending
                                        </span>
                                    @elseif($report->status === 'investigating')
                                        <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-600 font-semibold text-[11px] px-2.5 py-1 rounded-lg">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                            Investigating
                                        </span>
                                    @elseif($report->status === 'resolved')
                                        <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-600 font-semibold text-[11px] px-2.5 py-1 rounded-lg">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Resolved
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 bg-stone-100 text-stone-500 font-semibold text-[11px] px-2.5 py-1 rounded-lg">
                                            <span class="w-1.5 h-1.5 rounded-full bg-stone-400"></span>
                                            Dismissed
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="py-4 text-right align-middle whitespace-nowrap">
                                    <div class="inline-flex items-center space-x-1.5">
                                        <button type="button" @click="showReport(@js($payload))"
                                            class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-3 py-1.5 rounded-xl transition text-[11px]">
                                            Review
                                        </button>

                                        <form action="{{ route('admin.reports.destroy', $report->id) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Are you sure you want to delete this report record?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                                title="Delete record">
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
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    No safety or incident reports found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="mt-6 pt-4 border-t border-stone-100">
                {{ $reports->links() }}
            </div>
        </div>

        <!-- ================= TAB 2: PLATFORM ANALYTICS ================= -->
        <div x-show="activeTab === 'analytics'" style="display: none;" class="space-y-8">
            <!-- Header for Analytics -->
            <div>
                <h3 class="font-bold text-slate-900 text-base">DiskarTech platform overview & analytics</h3>
                <p class="text-xs text-slate-400 mt-0.5">Key performance indicators, employment matching funnel, and category distribution</p>
            </div>

            <!-- Employment Funnel Cards -->
            <div class="bg-stone-50 border border-stone-200/80 rounded-2xl p-6">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">Employment & Matching Funnel</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white p-4 rounded-xl border border-stone-200/60 shadow-sm">
                        <span class="text-[11px] font-semibold text-slate-400 block uppercase">1. Total Applications</span>
                        <span class="text-2xl font-black text-slate-900 mt-1 block">{{ number_format($analytics['funnel']['total_applications']) }}</span>
                        <span class="text-[10px] text-slate-400 mt-1 block">Students applied</span>
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-stone-200/60 shadow-sm">
                        <span class="text-[11px] font-semibold text-slate-400 block uppercase">2. Pending Review</span>
                        <span class="text-2xl font-black text-amber-600 mt-1 block">{{ number_format($analytics['funnel']['pending']) }}</span>
                        <span class="text-[10px] text-slate-400 mt-1 block">Awaiting employer review</span>
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-stone-200/60 shadow-sm">
                        <span class="text-[11px] font-semibold text-slate-400 block uppercase">3. Scheduled Interviews</span>
                        <span class="text-2xl font-black text-blue-600 mt-1 block">{{ number_format($analytics['funnel']['interview']) }}</span>
                        <span class="text-[10px] text-slate-400 mt-1 block">Active interviews</span>
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-stone-200/60 shadow-sm">
                        <span class="text-[11px] font-semibold text-slate-400 block uppercase">4. Successfully Hired</span>
                        <span class="text-2xl font-black text-emerald-600 mt-1 block">{{ number_format($analytics['funnel']['hired']) }}</span>
                        <span class="text-[10px] text-emerald-600 font-semibold mt-1 block">{{ $analytics['funnel']['hiring_rate'] }}% conversion rate</span>
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-stone-200/60 shadow-sm">
                        <span class="text-[11px] font-semibold text-slate-400 block uppercase">5. Certificates Issued</span>
                        <span class="text-2xl font-black text-indigo-700 mt-1 block">{{ number_format($analytics['funnel']['certificates_issued']) }}</span>
                        <span class="text-[10px] text-slate-400 mt-1 block">Verified working certificates</span>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout: User Community Health vs Job Categories -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- User Community Breakdown -->
                <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
                    <h4 class="font-bold text-slate-900 text-sm mb-1">User Community & Verification Health</h4>
                    <p class="text-xs text-slate-400 mb-5">Ratio of verified active accounts across student, employer, and household roles</p>

                    <div class="space-y-4">
                        <!-- Working Students -->
                        <div>
                            <div class="flex justify-between text-xs font-semibold mb-1">
                                <span class="text-slate-700">Working Students</span>
                                <span class="text-slate-500">
                                    {{ $analytics['users']['verified_students'] }} of {{ $analytics['users']['students'] }} verified
                                    ({{ $analytics['users']['students'] > 0 ? round(($analytics['users']['verified_students'] / $analytics['users']['students']) * 100) : 0 }}%)
                                </span>
                            </div>
                            <div class="w-full bg-stone-100 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-emerald-500 h-2.5 rounded-full" 
                                    style="width: {{ $analytics['users']['students'] > 0 ? ($analytics['users']['verified_students'] / $analytics['users']['students']) * 100 : 0 }}%">
                                </div>
                            </div>
                        </div>

                        <!-- Business Employers -->
                        <div>
                            <div class="flex justify-between text-xs font-semibold mb-1">
                                <span class="text-slate-700">Business Employers</span>
                                <span class="text-slate-500">
                                    {{ $analytics['users']['verified_employers'] }} of {{ $analytics['users']['employers'] }} verified
                                    ({{ $analytics['users']['employers'] > 0 ? round(($analytics['users']['verified_employers'] / $analytics['users']['employers']) * 100) : 0 }}%)
                                </span>
                            </div>
                            <div class="w-full bg-stone-100 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-blue-600 h-2.5 rounded-full" 
                                    style="width: {{ $analytics['users']['employers'] > 0 ? ($analytics['users']['verified_employers'] / $analytics['users']['employers']) * 100 : 0 }}%">
                                </div>
                            </div>
                        </div>

                        <!-- Households -->
                        <div>
                            <div class="flex justify-between text-xs font-semibold mb-1">
                                <span class="text-slate-700">Household Hirers</span>
                                <span class="text-slate-500">
                                    {{ $analytics['users']['verified_households'] }} of {{ $analytics['users']['households'] }} verified
                                    ({{ $analytics['users']['households'] > 0 ? round(($analytics['users']['verified_households'] / $analytics['users']['households']) * 100) : 0 }}%)
                                </span>
                            </div>
                            <div class="w-full bg-stone-100 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-purple-600 h-2.5 rounded-full" 
                                    style="width: {{ $analytics['users']['households'] > 0 ? ($analytics['users']['verified_households'] / $analytics['users']['households']) * 100 : 0 }}%">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-stone-100 grid grid-cols-3 gap-3 text-center">
                        <div class="bg-stone-50 p-2.5 rounded-xl">
                            <span class="text-[10px] text-slate-400 block font-semibold uppercase">Total Users</span>
                            <span class="font-black text-slate-900 text-sm">{{ number_format($analytics['users']['total']) }}</span>
                        </div>
                        <div class="bg-stone-50 p-2.5 rounded-xl">
                            <span class="text-[10px] text-slate-400 block font-semibold uppercase">Active Students</span>
                            <span class="font-black text-slate-900 text-sm">{{ number_format($analytics['users']['students']) }}</span>
                        </div>
                        <div class="bg-stone-50 p-2.5 rounded-xl">
                            <span class="text-[10px] text-slate-400 block font-semibold uppercase">Total Hirers</span>
                            <span class="font-black text-slate-900 text-sm">{{ number_format($analytics['users']['employers'] + $analytics['users']['households']) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Job Categories Breakdown -->
                <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
                    <h4 class="font-bold text-slate-900 text-sm mb-1">Job Opportunities by Category</h4>
                    <p class="text-xs text-slate-400 mb-5">Distribution of available part-time student opportunities</p>

                    <div class="space-y-3 max-h-56 overflow-y-auto pr-1">
                        @forelse($analytics['jobs']['categories'] as $cat)
                            <div>
                                <div class="flex justify-between text-xs font-semibold mb-1">
                                    <span class="text-slate-800">{{ $cat['name'] }}</span>
                                    <span class="text-slate-500">{{ $cat['count'] }} posts ({{ $cat['percentage'] }}%)</span>
                                </div>
                                <div class="w-full bg-stone-100 rounded-full h-2 overflow-hidden">
                                    <div class="bg-amber-500 h-2 rounded-full" style="width: {{ $cat['percentage'] }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 italic py-4 text-center">No categorized jobs found.</p>
                        @endforelse
                    </div>

                    <div class="mt-6 pt-4 border-t border-stone-100 grid grid-cols-3 gap-3 text-center">
                        <div class="bg-stone-50 p-2.5 rounded-xl">
                            <span class="text-[10px] text-slate-400 block font-semibold uppercase">Live Jobs</span>
                            <span class="font-black text-emerald-600 text-sm">{{ number_format($analytics['jobs']['active']) }}</span>
                        </div>
                        <div class="bg-stone-50 p-2.5 rounded-xl">
                            <span class="text-[10px] text-slate-400 block font-semibold uppercase">Hidden</span>
                            <span class="font-black text-amber-600 text-sm">{{ number_format($analytics['jobs']['hidden']) }}</span>
                        </div>
                        <div class="bg-stone-50 p-2.5 rounded-xl">
                            <span class="text-[10px] text-slate-400 block font-semibold uppercase">Closed</span>
                            <span class="font-black text-slate-500 text-sm">{{ number_format($analytics['jobs']['closed']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= INCIDENT REVIEW MODAL ================= -->
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
                
                <template x-if="selectedReport">
                    <div>
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-4 border-b border-stone-100 pb-3">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-100" x-text="selectedReport.report_type"></span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-stone-100 text-stone-600" x-text="'Report #' + selectedReport.id"></span>
                                </div>
                                <h3 class="font-bold text-slate-900 text-lg" x-text="selectedReport.subject"></h3>
                                <p class="text-xs text-slate-400 mt-0.5">Submitted on <span x-text="selectedReport.date"></span></p>
                            </div>
                            <button @click="openModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
                        </div>

                        <!-- Parties Involved -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <!-- Reporter Card -->
                            <div class="bg-stone-50 border border-stone-200/80 rounded-xl p-3.5 text-xs">
                                <span class="font-semibold text-slate-400 block text-[11px] uppercase tracking-wider mb-1">Reported By</span>
                                <p class="font-bold text-slate-800 text-sm" x-text="selectedReport.reporter.name"></p>
                                <p class="text-slate-500 text-[11px]" x-text="selectedReport.reporter.role + ' · ' + selectedReport.reporter.email"></p>
                            </div>

                            <!-- Target Card -->
                            <div class="bg-stone-50 border border-stone-200/80 rounded-xl p-3.5 text-xs">
                                <span class="font-semibold text-slate-400 block text-[11px] uppercase tracking-wider mb-1">Reported Entity / Target</span>
                                <p class="font-bold text-slate-800 text-sm" x-text="selectedReport.target.name"></p>
                                <p class="text-slate-500 text-[11px]" x-text="selectedReport.target.type + ' · ' + selectedReport.target.email"></p>
                            </div>
                        </div>

                        <!-- Incident Description -->
                        <div class="mb-4">
                            <span class="font-semibold text-slate-400 block text-xs uppercase mb-1.5">Incident Description</span>
                            <div class="bg-stone-50 border border-stone-200/70 rounded-xl p-4 text-xs text-slate-700 leading-relaxed whitespace-pre-line" x-text="selectedReport.description"></div>
                        </div>

                        <!-- Moderation & Resolution Form -->
                        <form :action="selectedReport.update_url" method="POST" class="border-t border-stone-100 pt-4 space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label class="font-semibold text-slate-700 block text-xs uppercase mb-1">Moderation Status</label>
                                <select name="status" x-model="selectedReport.status" 
                                    class="w-full bg-[#F2EDE4] text-xs rounded-xl px-3 py-2 font-semibold text-slate-700 border-0 focus:outline-none focus:ring-1 focus:ring-red-600 cursor-pointer">
                                    <option value="pending">Pending</option>
                                    <option value="investigating">Investigating</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="dismissed">Dismissed</option>
                                </select>
                            </div>

                            <div>
                                <label class="font-semibold text-slate-700 block text-xs uppercase mb-1">Internal Admin Resolution Notes</label>
                                <textarea name="admin_notes" x-model="selectedReport.admin_notes" rows="3" placeholder="Enter findings, actions taken, or warning issued to user..."
                                    class="w-full bg-[#F2EDE4] text-xs rounded-xl p-3 text-slate-700 placeholder-slate-400 border-0 focus:outline-none focus:ring-1 focus:ring-red-600"></textarea>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <button type="submit" class="bg-red-700 hover:bg-red-800 text-white font-semibold px-4 py-2 rounded-xl text-xs transition shadow-sm">
                                    Save Resolution
                                </button>

                                <button type="button" @click="openModal = false" class="bg-[#F2EDE4] hover:bg-stone-200 text-slate-700 font-semibold px-4 py-2 rounded-xl text-xs transition">
                                    Close
                                </button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>

    </div>
</x-admin-layout>
