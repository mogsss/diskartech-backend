<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Employment - {{ $data['student_name'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Great+Vibes&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex flex-col items-center bg-slate-50 px-[15px] py-[30px] font-['Plus_Jakarta_Sans',sans-serif] text-slate-800 print:bg-white print:p-0">

    <div class="mb-5 flex w-full max-w-[820px] flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="javascript:window.close()" class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border-0 px-5 py-2.5 text-sm font-bold no-underline transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 bg-slate-200 text-slate-700 hover:bg-slate-300 focus-visible:outline-slate-700">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="m12 19-7-7 7-7M5 12h14" />
            </svg>
            <span class="leading-none">Back</span>
        </a>
        <button type="button" onclick="window.print()" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-0 px-5 py-2.5 text-sm font-bold no-underline transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 bg-red-600 text-white shadow-[0_4px_12px_rgba(220,38,38,0.25)] hover:bg-red-700 focus-visible:outline-red-700">
            Save as PDF
        </button>
    </div>

    <div class="relative w-full max-w-[820px] overflow-hidden rounded-[20px] border-2 border-slate-200 bg-white p-5 shadow-[0_10px_30px_rgba(0,0,0,0.06)] sm:px-[55px] sm:py-[50px] print:max-w-none print:rounded-none print:border-0 print:p-5 print:shadow-none">
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 -rotate-[25deg] select-none whitespace-nowrap text-[80px] font-black tracking-[12px] text-red-600/[0.03]">DISKARTECH VERIFIED</div>
        
        <div class="relative px-5 py-7 sm:px-10 sm:py-[35px] print:px-8 print:py-7">
            <div class="mb-[30px] text-center">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-red-100 bg-red-50 px-3.5 py-1 text-[11px] font-extrabold uppercase tracking-[1.5px] text-red-600">Official DiskarTech Working Student Certification</div>
                <h1 class="mb-1.5 font-['Cinzel',serif] text-[22px] font-black uppercase tracking-[2px] text-slate-900 sm:text-[26px] print:text-[26px]">Certificate of Employment</h1>
                <p class="text-[13px] font-semibold uppercase tracking-[1.5px] text-slate-500">Working Student Engagement Verification</p>
            </div>

            <div class="mb-[30px] break-words text-justify text-[14.5px] leading-[1.85] text-slate-700">
                This is to certify that <span class="inline-block max-w-full break-words text-[22px] font-extrabold text-slate-900 underline decoration-red-600 underline-offset-4">{{ $data['student_name'] }}</span>, a bonafide student of <span class="font-bold text-slate-900">{{ $data['student_school'] }}</span> taking up <span class="font-bold text-slate-900">{{ $data['student_course'] }}</span>, is officially recognized as an active and registered working student under <span class="font-bold text-slate-900">{{ $data['company_name'] }}</span> via the <strong>DiskarTech Job Matching Platform</strong>.
                <br><br>
                The said individual has rendered diligent work services in the capacity of <span class="font-bold text-slate-900">{{ $data['job_title'] }}</span> ({{ $data['job_category'] }}) from <span class="font-bold text-slate-900">{{ $data['start_date'] }}</span> to <span class="font-bold text-slate-900">{{ $data['end_date'] }}</span>.
            </div>

            <div class="mb-[35px] mt-[25px] grid break-inside-avoid grid-cols-1 gap-[15px] rounded-[14px] border border-slate-200 bg-slate-50 px-5 py-[18px] sm:grid-cols-2 sm:px-6 print:grid-cols-2 print:px-6">
                <div class="flex min-w-0 flex-col">
                    <span class="mb-0.5 text-[10.5px] font-bold uppercase tracking-[0.8px] text-slate-500">Working Schedule</span>
                    <span class="break-words text-[13.5px] font-bold text-slate-900">{{ $data['work_schedule'] }}</span>
                </div>
                <div class="flex min-w-0 flex-col">
                    <span class="mb-0.5 text-[10.5px] font-bold uppercase tracking-[0.8px] text-slate-500">Agreed Compensation</span>
                    <span class="break-words text-[13.5px] font-bold text-slate-900">{{ $data['agreed_rate'] }}</span>
                </div>
                <div class="flex min-w-0 flex-col">
                    <span class="mb-0.5 text-[10.5px] font-bold uppercase tracking-[0.8px] text-slate-500">Workplace</span>
                    <span class="break-words text-[13.5px] font-bold text-slate-900">{{ $data['company_address'] }}</span>
                </div>
                <div class="flex min-w-0 flex-col">
                    <span class="mb-0.5 text-[10.5px] font-bold uppercase tracking-[0.8px] text-slate-500">Certificate Reference No.</span>
                    <span class="break-words text-[13.5px] font-bold text-red-600">{{ $data['certificate_number'] }}</span>
                </div>
            </div>

            <div class="mb-[25px] text-[13.5px] leading-[1.7] text-slate-600">
                This certification is issued upon the request of the interested party for academic verification, scholarship requirements, or school endorsement.
            </div>

            <div class="mt-10 flex break-inside-avoid flex-col items-stretch justify-between gap-8 pt-5 sm:flex-row sm:items-end sm:gap-5 print:flex-row print:items-end print:gap-5">
                <div class="min-w-0 flex-1 break-words text-center sm:min-w-[200px] print:min-w-0">
                    @if(!empty($data['employer_signature']))
                        <div class="mb-1 flex h-12 items-end justify-center">
                            <img src="{{ $data['employer_signature'] }}" alt="Digitally Signed" class="max-h-12 max-w-[170px] object-contain" />
                        </div>
                    @else
                        <div class="-mb-2.5 font-['Great_Vibes',cursive] text-[32px] text-slate-900">{{ $data['hirer_name'] }}</div>
                    @endif
                    <div class="border-t-[1.5px] border-slate-900 pt-1.5 text-[13px] font-bold text-slate-900">{{ $data['hirer_name'] }}</div>
                    <div class="text-[11px] font-semibold text-slate-500">Employer</div>
                    <div class="mt-[3px] text-[10px] text-slate-400">Date: {{ $data['issued_date'] }}</div>
                </div>


                <div class="min-w-0 flex-1 break-words text-center sm:min-w-[200px] print:min-w-0">
                    <div class="-mb-2.5 font-['Great_Vibes',cursive] text-[32px] text-red-600">DiskarTech</div>
                    <div class="text-[11px] font-semibold text-slate-500">Platform Verification Seal</div>
                </div>
            </div>

            <div class="mt-[30px] break-words text-center text-[10px] tracking-[0.5px] text-slate-400">
                Reference ID: {{ $data['certificate_number'] }} • Electronically Generated via DiskarTech
            </div>
        </div>
    </div>

</body>
</html>
