<section class="rounded-2xl border border-stone-200/80 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="job-status-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="job-status-title" class="text-base font-bold text-slate-900">Job application status</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">Track applications from first interest to completed work.</p>
        </div>
        <span class="rounded-lg bg-stone-50 px-2.5 py-1 text-xs font-medium text-slate-500">All time</span>
    </div>

    <div class="mt-6 flex flex-col items-center gap-6 sm:flex-row sm:gap-8">
        <figure class="relative h-52 w-52 shrink-0" aria-label="Job application status distribution">
            <svg class="h-full w-full" viewBox="0 0 200 200" role="img" aria-labelledby="job-status-chart-title job-status-chart-description">
                <title id="job-status-chart-title">Job applications by current status</title>
                <desc id="job-status-chart-description">{{ number_format($jobApplicationsTotal) }} total applications. @foreach($jobStatusChart as $segment){{ $segment['label'] }}: {{ $segment['count'] }} ({{ number_format($segment['percentage'], 1) }}%). @endforeach</desc>
                <circle cx="100" cy="100" r="78" fill="none" stroke="#F1F5F9" stroke-width="24" />
                @php $chartOffset = 0; @endphp
                @foreach($jobStatusChart as $segment)
                    @if($segment['count'] > 0)
                        <circle cx="100" cy="100" r="78" fill="none" stroke="{{ $segment['color'] }}" stroke-width="24" pathLength="100"
                            stroke-dasharray="{{ number_format($segment['percentage'], 6, '.', '') }} {{ number_format(100 - $segment['percentage'], 6, '.', '') }}"
                            stroke-dashoffset="{{ number_format(-$chartOffset, 6, '.', '') }}" transform="rotate(-90 100 100)"
                            class="transition-opacity hover:opacity-75">
                            <title>{{ $segment['label'] }}: {{ number_format($segment['count']) }} {{ $segment['count'] === 1 ? 'application' : 'applications' }} ({{ number_format($segment['percentage'], 1) }}%)</title>
                        </circle>
                        @php $chartOffset += $segment['percentage']; @endphp
                    @endif
                @endforeach
            </svg>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center" aria-hidden="true">
                <span class="max-w-[125px] break-all text-center text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($jobApplicationsTotal) }}</span>
                <span class="mt-1 text-xs text-slate-500">Applications</span>
            </div>
        </figure>

        <ul class="w-full min-w-0 flex-1 space-y-1" aria-label="Application status totals and percentages">
            @foreach($jobStatusChart as $segment)
                <li class="flex items-center gap-3 rounded-xl px-2 py-2 text-sm transition hover:bg-stone-50">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $segment['color'] }}" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1 font-medium text-slate-600">{{ $segment['label'] }}</span>
                    <span class="font-semibold tabular-nums text-slate-900">{{ number_format($segment['count']) }}</span>
                    <span class="w-14 shrink-0 text-right text-xs tabular-nums text-slate-400">{{ number_format($segment['percentage'], 1) }}%</span>
                </li>
            @endforeach
        </ul>
    </div>

    <p class="mt-5 border-t border-stone-100 pt-4 text-xs leading-5 text-slate-500">{{ $jobApplicationsTotal > 0 ? 'Each application is counted once by its current status. A job can have multiple applicants.' : 'No job applications yet. Status counts will appear when students start applying.' }}</p>
</section>
