<!-- AI Review Modal with Smart File Viewers and Rejection Form -->
<div x-show="openModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div @click.away="openModal = false" class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl relative text-left max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 class="font-bold text-slate-900 text-lg">Verification</h3>
            <button @click="openModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
        </div>

        <div class="space-y-4 text-sm text-slate-700">
            <div>
                <span class="font-semibold text-slate-400 block text-xs uppercase">Applicant Name</span>
                <p class="text-base font-bold text-slate-900" x-text="selectedName"></p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="font-semibold text-slate-400 block text-xs uppercase">Role Type</span>
                    <p class="font-medium text-slate-800" x-text="selectedRole"></p>
                </div>
                <div>
                    <span class="font-semibold text-slate-400 block text-xs uppercase">Reference / Business</span>
                    <p class="font-medium text-slate-800" x-text="selectedRef"></p>
                </div>
            </div>

            <!-- Valid ID Section + Smart Viewer -->
            <div class="bg-stone-50 border border-stone-200 p-4 rounded-xl mt-4">
                <h4 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1">
                    Valid ID
                </h4>
                
                <template x-if="idUrl">
                    <div class="mb-3">
                        <template x-if="idUrl.endsWith('.pdf') || idUrl.endsWith('.doc') || idUrl.endsWith('.docx')">
                            <a :href="idUrl" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-stone-200 rounded-lg shadow-sm text-blue-600 hover:bg-stone-50 transition">
                                <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                <span class="text-xs font-semibold underline truncate">Valid Id</span>
                            </a>
                        </template>
                        <template x-if="!idUrl.endsWith('.pdf') && !idUrl.endsWith('.doc') && !idUrl.endsWith('.docx')">
                            <a :href="idUrl" target="_blank" class="block">
                                <img :src="idUrl" class="w-full h-48 object-contain bg-white border border-stone-200 rounded-lg shadow-sm hover:opacity-90 transition" alt="Valid ID">
                            </a>
                        </template>
                        <span class="text-[11px] text-blue-600 underline mt-1 inline-block">View</span>
                    </div>
                </template>
                <template x-if="!idUrl">
                    <p class="text-xs text-slate-400 italic mb-3">No Valid ID uploaded.</p>
                </template>

                <div class="mb-2">
                    <span class="text-xs font-semibold">Recommendation: </span>
                    <template x-if="selectedValid === '1' || selectedValid === true">
                        <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">Valid ID</span>
                    </template>
                    <template x-if="selectedValid === '0' || selectedValid === false || selectedValid === '' || selectedValid === 'null'">
                        <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold">Warning / Invalid ID</span>
                    </template>
                </div>

                <div>
                    <span class="text-xs font-semibold text-slate-500 block mb-1">AI Remarks</span>
                    <p class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-stone-100 italic" x-text="selectedRemarks"></p>
                </div>
            </div>

            <!-- Business Certificate Section + Smart Viewer (Employer Only) -->
            <template x-if="selectedRole === 'Employer'">
                <div class="bg-stone-50 border border-stone-200 p-4 rounded-xl mt-4">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1">
                        Business Permit
                    </h4>
                    
                    <template x-if="certUrl">
                        <div class="mb-3">
                            <template x-if="certUrl.endsWith('.pdf') || certUrl.endsWith('.doc') || certUrl.endsWith('.docx')">
                                <a :href="certUrl" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-stone-200 rounded-lg shadow-sm text-blue-600 hover:bg-stone-50 transition">
                                    <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                    <span class="text-xs font-semibold underline truncate">View Uploaded Documents</span>
                                </a>
                            </template>
                            <template x-if="!certUrl.endsWith('.pdf') && !certUrl.endsWith('.doc') && !certUrl.endsWith('.docx')">
                                <a :href="certUrl" target="_blank" class="block">
                                    <img :src="certUrl" class="w-full h-48 object-contain bg-white border border-stone-200 rounded-lg shadow-sm hover:opacity-90 transition" alt="Business Certificate">
                                </a>
                            </template>
                            <span class="text-[11px] text-blue-600 underline mt-1 inline-block">View Document</span>
                        </div>
                    </template>
                    <template x-if="!certUrl">
                        <p class="text-xs text-slate-400 italic mb-3">No business certificate or permit uploaded.</p>
                    </template>

                    <div class="mb-2">
                        <span class="text-xs font-semibold">Recommendation: </span>
                        <template x-if="selectedCertValid === '1' || selectedCertValid === true">
                            <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">Valid Permit/Cert</span>
                        </template>
                        <template x-if="selectedCertValid === '0' || selectedCertValid === false || selectedCertValid === '' || selectedCertValid === 'null'">
                            <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold">Warning / Invalid Permit</span>
                        </template>
                    </div>

                    <div>
                        <span class="text-xs font-semibold text-slate-500 block mb-1">AI Remarks:</span>
                        <p class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-stone-100 italic" x-text="selectedCertRemarks"></p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Rejection Form & Actions Footer -->
        <div class="mt-6 border-t pt-4">
            <form method="POST" :action="'/admin/verification/reject/' + selectedUserId" class="space-y-3">
                @csrf
                <input type="hidden" name="role" x-model="selectedRoleLower">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Reason:</label>
                    <input type="text" name="rejection_reason" placeholder="e.g: Unreadable Name..." required class="text-xs border border-stone-300 rounded-xl px-3 py-2 w-full focus:outline-none focus:border-rose-500">
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" @click="openModal = false" class="bg-stone-200 hover:bg-stone-300 text-slate-700 font-medium text-xs px-4 py-2 rounded-xl transition">Close</button>
                    <button type="submit" onclick="return confirm('Are you sure to reject this Verification?')" class="bg-rose-600 hover:bg-rose-700 text-white font-medium text-xs px-4 py-2 rounded-xl transition">
                        Reject Verification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>