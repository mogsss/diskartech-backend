@php
    $reviewDocuments = [
        ['role' => "applicant.roleLower === 'student'", 'title' => 'School ID', 'type' => 'school_id', 'url' => 'schoolIdUrl', 'valid' => 'schoolIdAiIsValid', 'remarks' => 'schoolIdAiRemarks', 'good' => 'Valid School ID', 'bad' => 'Warning / Invalid ID'],
        ['role' => "applicant.roleLower === 'student'", 'title' => 'COR/COE', 'type' => 'coe', 'subtitle' => 'Certificate of Enrolment / Registration', 'url' => 'coeUrl', 'valid' => 'coeAiIsValid', 'remarks' => 'coeAiRemarks', 'good' => 'Valid COE', 'bad' => 'Warning / Invalid COE'],
        ['role' => "applicant.roleLower === 'student'", 'title' => 'Resume', 'url' => 'resumeUrl'],
        ['role' => "applicant.roleLower !== 'student'", 'title' => 'Valid ID', 'type' => 'valid_id', 'url' => 'idUrl', 'valid' => 'isValid', 'remarks' => 'remarks', 'good' => 'Valid ID', 'bad' => 'Warning / Invalid ID'],
        ['role' => "applicant.roleLower === 'employer'", 'title' => 'Business Permit / Certificate', 'type' => 'certificate', 'url' => 'certUrl', 'valid' => 'certIsValid', 'remarks' => 'certRemarks', 'good' => 'Valid Certificate', 'bad' => 'Warning / Invalid Certificate'],
    ];
@endphp
<div x-show="openModal" x-cloak style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6"
    x-data="{
        rejecting: false, returnFocus: null,
        close() { openModal = false; },
        verdict(value) { return value === true || value === 1 || value === '1' ? 'valid' : (value === false || value === 0 || value === '0' ? 'invalid' : 'pending'); },
        isImage(url) { return /\.(png|jpe?g|webp|gif)(?:[?#]|$)/i.test(url || ''); },
        trap(event) {
            const items = [...$refs.dialog.querySelectorAll('button, a[href], input, textarea')].filter(el => !el.disabled && el.getClientRects().length);
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    }"
    x-init="$watch('openModal', visible => { if (visible) { rejecting = false; returnFocus = document.activeElement; $nextTick(() => { $refs.reviewBody.scrollTop = 0; $refs.closeReview.focus(); }); } else { returnFocus?.focus(); } })"
    @click.self="close()" @keydown.escape.stop="close()" @keydown.tab="trap($event)">
    <div x-ref="dialog" role="dialog" aria-modal="true" aria-labelledby="verification-review-title" class="flex max-h-[90dvh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl">
        <div class="flex shrink-0 items-center justify-between gap-4 border-b border-stone-100 px-5 py-5 sm:px-6">
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-red-700">Document review</p>
                <h2 id="verification-review-title" class="text-lg font-bold text-slate-900 sm:text-xl">Verification review</h2>
            </div>
            <button x-ref="closeReview" type="button" @click="close()" aria-label="Close verification review" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-stone-200 text-slate-700 hover:bg-stone-50">
                <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M6 6l12 12M6 18L18 6"/></svg>
            </button>
        </div>

        <div x-ref="reviewBody" class="min-h-0 space-y-5 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
            <section class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="break-words text-base font-bold text-slate-900" x-text="applicant.name"></p>
                        <p class="mt-1 break-words text-sm text-slate-500" x-text="applicant.reference"></p>
                    </div>
                    <span class="rounded-lg border border-stone-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600" x-text="applicant.role"></span>
                </div>
                <p x-show="applicant.isVerified" class="mt-3 text-xs font-semibold text-emerald-700">This account is verified.</p>
                <div x-show="applicant.rejectionReason" class="mt-3 border-t border-stone-200 pt-3">
                    <p class="text-xs font-semibold text-rose-700">Previous rejection reason</p>
                    <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-600" x-text="applicant.rejectionReason"></p>
                </div>
            </section>
            <p class="text-xs leading-5 text-slate-500">AI results help with document checks. Review the files yourself before approving or rejecting an account.</p>

            <div class="space-y-4">
                @foreach($reviewDocuments as $document)
                    <template x-if="{{ $document['role'] }}">
                        <section class="overflow-hidden rounded-xl border border-stone-200 bg-white">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-4 py-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">{{ $document['title'] }}</h3>
                                    @isset($document['subtitle'])<p class="mt-1 text-xs text-slate-500">{{ $document['subtitle'] }}</p>@endisset
                                </div>
                                <a x-show="applicant.{{ $document['url'] }}" :href="applicant.{{ $document['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-8 items-center rounded-lg bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100">Open file</a>
                            </div>
                            <div class="p-4">
                                <template x-if="applicant.{{ $document['url'] }}">
                                    <div>
                                        <template x-if="isImage(applicant.{{ $document['url'] }})">
                                            <a :href="applicant.{{ $document['url'] }}" target="_blank" rel="noopener noreferrer" class="block rounded-lg focus:outline-red-700">
                                                <img :src="applicant.{{ $document['url'] }}" alt="{{ $document['title'] }} document preview" loading="lazy" class="max-h-56 w-full rounded-lg border border-stone-100 bg-stone-50 object-contain" x-on:error="$el.style.display = 'none'; $el.nextElementSibling.hidden = false">
                                                <p hidden class="rounded-lg bg-stone-50 p-4 text-xs text-slate-500">Preview unavailable. Use Open file to view the document.</p>
                                            </a>
                                        </template>
                                        <template x-if="!isImage(applicant.{{ $document['url'] }})">
                                            <p class="rounded-lg border border-dashed border-stone-200 bg-stone-50 px-4 py-5 text-sm text-slate-500">Document uploaded. Select Open file to view or download it.</p>
                                        </template>
                                    </div>
                                </template>
                                <p x-show="!applicant.{{ $document['url'] }}" class="rounded-lg bg-stone-50 px-4 py-5 text-sm text-slate-400">No document uploaded.</p>

                                @isset($document['valid'])
                                    <div x-show="applicant.{{ $document['url'] }}" class="mt-4 border-t border-stone-100 pt-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-semibold text-slate-500">AI Recommendation:</span>
                                            <span class="rounded-lg px-2.5 py-1 text-xs font-semibold"
                                                :class="verdict(applicant.{{ $document['valid'] }}) === 'valid' ? 'bg-emerald-50 text-emerald-700' : (verdict(applicant.{{ $document['valid'] }}) === 'invalid' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700')"
                                                x-text="verdict(applicant.{{ $document['valid'] }}) === 'valid' ? '{{ $document['good'] }}' : (verdict(applicant.{{ $document['valid'] }}) === 'invalid' ? '{{ $document['bad'] }}' : 'Pending analysis')"></span>
                                        </div>
                                        <p class="mt-3 whitespace-pre-wrap break-words text-xs leading-6 text-slate-600" x-text="applicant.{{ $document['remarks'] }} || 'No analysis remarks available.'"></p>
                                        <form x-show="verdict(applicant.{{ $document['valid'] }}) === 'pending'" method="POST"
                                            :action="'{{ url('/admin/verification/retry-analysis') }}/' + applicant.userId"
                                            @submit="$el.querySelector('button').disabled = true" class="mt-3">
                                            @csrf
                                            <input type="hidden" name="role" :value="applicant.roleLower">
                                            <input type="hidden" name="document" value="{{ $document['type'] }}">
                                            <p class="mb-2 text-xs leading-5 text-slate-500">The analysis may be queued or could not complete. You can request another check.</p>
                                            <button type="submit" class="rounded-lg border border-stone-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-stone-50 disabled:opacity-50">Retry analysis</button>
                                        </form>
                                    </div>
                                @endisset
                            </div>
                        </section>
                    </template>
                @endforeach
            </div>

            <form x-show="rejecting && (applicant.isComplete || applicant.isVerified)" id="verification-reject-form" method="POST" :action="'{{ url('/admin/verification/reject') }}/' + applicant.userId"
                @submit="if (!confirm(applicant.isVerified ? 'Reject this account and remove its verified status? The reason will be shown to the account owner.' : 'Reject this verification and send the reason to the account owner?')) $event.preventDefault()"
                class="rounded-xl border border-rose-200 bg-rose-50/50 p-4">
                @csrf
                <input type="hidden" name="role" :value="applicant.roleLower">
                <label for="verification-rejection-reason" class="block text-sm font-semibold text-slate-800">Reason for rejection</label>
                <p class="mt-1 text-xs leading-5 text-slate-500">Explain what needs to be corrected. This reason will be shown to the account owner.</p>
                <p x-show="applicant.isVerified" class="mt-2 text-xs font-semibold leading-5 text-rose-700">Rejecting this account will remove its verified status.</p>
                <textarea id="verification-rejection-reason" x-ref="rejectionReason" name="rejection_reason" rows="3" maxlength="255" required
                    @input="$el.setCustomValidity(/<\/?[a-z][^>]*>|<!--|<!doctype/i.test($el.value) ? 'Use plain text only.' : '')"
                    @blur="$el.value = $el.value.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f-\u009f\u202a-\u202e\u2066-\u2069\ufeff]/g, '').trim()"
                    placeholder="For example: Please upload a clearer photo of your ID." class="mt-3 w-full resize-y rounded-xl border border-rose-200 bg-white p-3 text-sm text-slate-900 focus:outline-red-700"></textarea>
            </form>
        </div>

        <div class="shrink-0 border-t border-stone-100 bg-white px-5 py-4 sm:px-6">
            <p x-show="!applicant.isComplete && !applicant.isVerified" class="mb-3 text-xs leading-5 text-amber-700">Review actions are available once all required documents have been uploaded.</p>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" @click="close()" class="min-h-[44px] rounded-xl border border-stone-200 px-4 text-sm font-semibold text-slate-700 hover:bg-stone-50">Close</button>
                <template x-if="applicant.isComplete || applicant.isVerified">
                    <div class="flex flex-wrap gap-2">
                        <button x-show="!rejecting" type="button" @click="rejecting = true; $nextTick(() => requestAnimationFrame(() => { $refs.rejectionReason.value = ''; $refs.rejectionReason.setCustomValidity(''); $refs.rejectionReason.focus(); $refs.rejectionReason.scrollIntoView({ block: 'nearest' }); }))" class="min-h-[44px] rounded-xl border border-rose-200 bg-rose-50 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-100">Reject</button>
                        <button x-show="rejecting" type="button" @click="rejecting = false" class="min-h-[44px] rounded-xl border border-stone-200 px-4 text-sm font-semibold text-slate-600">Back</button>
                        <button x-show="rejecting" type="submit" form="verification-reject-form" class="min-h-[44px] rounded-xl bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800">Confirm rejection</button>
                        <form x-show="!rejecting && !applicant.isVerified" method="POST" :action="'{{ url('/admin/verification/approve') }}/' + applicant.userId" @submit="if (!confirm('Approve this account after reviewing its documents?')) $event.preventDefault()">
                            @csrf
                            <input type="hidden" name="role" :value="applicant.roleLower">
                            <button type="submit" :disabled="!applicant.isComplete" class="min-h-[44px] rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-stone-200 disabled:text-stone-400">Approve account</button>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
