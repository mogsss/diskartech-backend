<!-- AI Review Modal with Smart File Viewers and Rejection/Approval Form -->
<div x-show="openModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div @click.away="openModal = false" class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl relative text-left max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <div>
                <h3 class="font-bold text-slate-900 text-lg">Verification Review</h3>
                <p class="text-xs text-slate-400">Review submitted documents and credentials</p>
            </div>
            <button @click="openModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4 text-sm text-slate-700">
            <div>
                <span class="font-semibold text-slate-400 block text-xs uppercase">Applicant Name</span>
                <p class="text-base font-bold text-slate-900" x-text="applicant.name"></p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="font-semibold text-slate-400 block text-xs uppercase">Role Type</span>
                    <p class="font-medium text-slate-800" x-text="applicant.role"></p>
                </div>
                <div>
                    <span class="font-semibold text-slate-400 block text-xs uppercase">Reference / School / Business</span>
                    <p class="font-medium text-slate-800" x-text="applicant.reference"></p>
                </div>
            </div>

            <!-- ================= STUDENT DOCUMENTS SECTION ================= -->
            <template x-if="applicant.roleLower === 'student'">
                <div class="space-y-4">
                    <!-- School ID -->
                    <div class="bg-stone-50 border border-stone-200 p-4 rounded-xl">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-600 mb-2">1. School ID</h4>
                        <template x-if="applicant.schoolIdUrl">
                            <div>
                                <template x-if="applicant.schoolIdUrl.toLowerCase().endsWith('.pdf')">
                                    <a :href="applicant.schoolIdUrl" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-stone-200 rounded-lg shadow-sm text-blue-600 hover:bg-stone-50 transition">
                                        <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        <span class="text-xs font-semibold underline truncate">View School ID (PDF)</span>
                                    </a>
                                </template>
                                <template x-if="!applicant.schoolIdUrl.toLowerCase().endsWith('.pdf')">
                                    <a :href="applicant.schoolIdUrl" target="_blank" class="block group">
                                        <img :src="applicant.schoolIdUrl" class="w-full h-44 object-contain bg-white border border-stone-200 rounded-lg shadow-sm group-hover:opacity-90 transition" alt="School ID">
                                        <span class="text-[11px] text-blue-600 underline mt-1.5 inline-block font-medium">Click to view full size</span>
                                    </a>
                                </template>
                            </div>
                        </template>
                        <template x-if="!applicant.schoolIdUrl">
                            <p class="text-xs text-slate-400 italic">No School ID uploaded.</p>
                        </template>

                        <template x-if="applicant.schoolIdUrl">
                            <div class="mt-3 pt-3 border-t border-stone-200/70">
                                <div class="mb-2">
                                    <span class="text-xs font-semibold">AI Recommendation: </span>
                                    <template x-if="applicant.schoolIdAiIsValid === '1' || applicant.schoolIdAiIsValid === 1 || applicant.schoolIdAiIsValid === true">
                                        <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">Valid School ID</span>
                                    </template>
                                    <template x-if="applicant.schoolIdAiIsValid === '0' || applicant.schoolIdAiIsValid === 0 || applicant.schoolIdAiIsValid === false">
                                        <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold">Warning / Invalid ID</span>
                                    </template>
                                    <template x-if="applicant.schoolIdAiIsValid === null || applicant.schoolIdAiIsValid === undefined || applicant.schoolIdAiIsValid === ''">
                                        <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold">Pending Analysis</span>
                                    </template>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 block mb-1">AI Remarks</span>
                                    <p class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-stone-100 italic" x-text="applicant.schoolIdAiRemarks"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Certificate of Enrollment (COE) -->
                    <div class="bg-stone-50 border border-stone-200 p-4 rounded-xl">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-600 mb-2">2. Certificate of Enrollment (COE)</h4>
                        <template x-if="applicant.coeUrl">
                            <div>
                                <template x-if="applicant.coeUrl.toLowerCase().endsWith('.pdf')">
                                    <a :href="applicant.coeUrl" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-stone-200 rounded-lg shadow-sm text-blue-600 hover:bg-stone-50 transition">
                                        <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        <span class="text-xs font-semibold underline truncate">View Certificate of Enrollment (PDF)</span>
                                    </a>
                                </template>
                                <template x-if="!applicant.coeUrl.toLowerCase().endsWith('.pdf')">
                                    <a :href="applicant.coeUrl" target="_blank" class="block group">
                                        <img :src="applicant.coeUrl" class="w-full h-44 object-contain bg-white border border-stone-200 rounded-lg shadow-sm group-hover:opacity-90 transition" alt="Certificate of Enrollment">
                                        <span class="text-[11px] text-blue-600 underline mt-1.5 inline-block font-medium">Click to view full size</span>
                                    </a>
                                </template>
                            </div>
                        </template>
                        <template x-if="!applicant.coeUrl">
                            <p class="text-xs text-slate-400 italic">No COE uploaded.</p>
                        </template>

                        <template x-if="applicant.coeUrl">
                            <div class="mt-3 pt-3 border-t border-stone-200/70">
                                <div class="mb-2">
                                    <span class="text-xs font-semibold">AI Recommendation: </span>
                                    <template x-if="applicant.coeAiIsValid === '1' || applicant.coeAiIsValid === 1 || applicant.coeAiIsValid === true">
                                        <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">Valid COE / COR</span>
                                    </template>
                                    <template x-if="applicant.coeAiIsValid === '0' || applicant.coeAiIsValid === 0 || applicant.coeAiIsValid === false">
                                        <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold">Warning / Invalid COE</span>
                                    </template>
                                    <template x-if="applicant.coeAiIsValid === null || applicant.coeAiIsValid === undefined || applicant.coeAiIsValid === ''">
                                        <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold">Pending Analysis</span>
                                    </template>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 block mb-1">AI Remarks</span>
                                    <p class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-stone-100 italic" x-text="applicant.coeAiRemarks"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Student Resume -->
                    <div class="bg-stone-50 border border-stone-200 p-4 rounded-xl">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-600 mb-2">3. Student Resume</h4>
                        <template x-if="applicant.resumeUrl">
                            <div>
                                <a :href="applicant.resumeUrl" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-stone-200 rounded-lg shadow-sm text-blue-600 hover:bg-stone-50 transition">
                                    <svg class="w-6 h-6 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span class="text-xs font-semibold underline truncate">View / Download Resume</span>
                                </a>
                            </div>
                        </template>
                        <template x-if="!applicant.resumeUrl">
                            <p class="text-xs text-slate-400 italic">No Resume uploaded.</p>
                        </template>
                    </div>
                </div>
            </template>

            <!-- ================= EMPLOYER & HOUSEHOLD VALID ID SECTION ================= -->
            <template x-if="applicant.roleLower !== 'student'">
                <div>
                    <!-- Valid ID Section + Smart Viewer -->
                    <div class="bg-stone-50 border border-stone-200 p-4 rounded-xl mt-4">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1">
                            Valid ID
                        </h4>
                        
                        <template x-if="applicant.idUrl">
                            <div class="mb-3">
                                <!-- Kung PDF o Document -->
                                <template x-if="applicant.idUrl.toLowerCase().endsWith('.pdf') || applicant.idUrl.toLowerCase().endsWith('.doc') || applicant.idUrl.toLowerCase().endsWith('.docx')">
                                    <a :href="applicant.idUrl" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-stone-200 rounded-lg shadow-sm text-blue-600 hover:bg-stone-50 transition">
                                        <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        <span class="text-xs font-semibold underline truncate">View Valid ID Document</span>
                                    </a>
                                </template>

                                <!-- Kung Image -->
                                <template x-if="!applicant.idUrl.toLowerCase().endsWith('.pdf') && !applicant.idUrl.toLowerCase().endsWith('.doc') && !applicant.idUrl.toLowerCase().endsWith('.docx')">
                                    <a :href="applicant.idUrl" target="_blank" class="block group">
                                        <img :src="applicant.idUrl" class="w-full h-48 object-contain bg-white border border-stone-200 rounded-lg shadow-sm group-hover:opacity-90 transition" alt="Valid ID">
                                        <span class="text-[11px] text-blue-600 underline mt-1.5 inline-block font-medium">Click image to view full size</span>
                                    </a>
                                </template>
                            </div>
                        </template>
                        <template x-if="!applicant.idUrl">
                            <p class="text-xs text-slate-400 italic mb-3">No Valid ID uploaded.</p>
                        </template>

                        <div class="mb-2">
                            <span class="text-xs font-semibold">AI Recommendation: </span>
                            <template x-if="applicant.isValid === '1' || applicant.isValid === 1 || applicant.isValid === true">
                                <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">Valid ID</span>
                            </template>
                            <template x-if="applicant.isValid === '0' || applicant.isValid === 0 || applicant.isValid === false">
                                <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold">Warning / Invalid ID</span>
                            </template>
                            <template x-if="applicant.isValid === null || applicant.isValid === undefined || applicant.isValid === ''">
                                <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold">Pending Analysis</span>
                            </template>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-slate-500 block mb-1">AI Remarks</span>
                            <p class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-stone-100 italic" x-text="applicant.remarks"></p>
                        </div>
                    </div>

                    <!-- Business Certificate Section (Employer Only) -->
                    <template x-if="applicant.roleLower === 'employer'">
                        <div class="bg-stone-50 border border-stone-200 p-4 rounded-xl mt-4">
                            <h4 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1">
                                Business Permit / Certificate
                            </h4>
                            
                            <template x-if="applicant.certUrl">
                                <div class="mb-3">
                                    <!-- Kung PDF o Document -->
                                    <template x-if="applicant.certUrl.toLowerCase().endsWith('.pdf') || applicant.certUrl.toLowerCase().endsWith('.doc') || applicant.certUrl.toLowerCase().endsWith('.docx')">
                                        <a :href="applicant.certUrl" target="_blank" class="flex items-center gap-3 p-3 bg-white border border-stone-200 rounded-lg shadow-sm text-blue-600 hover:bg-stone-50 transition">
                                            <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                            <span class="text-xs font-semibold underline truncate">View Uploaded Certificate</span>
                                        </a>
                                    </template>

                                    <!-- Kung Image -->
                                    <template x-if="!applicant.certUrl.toLowerCase().endsWith('.pdf') && !applicant.certUrl.toLowerCase().endsWith('.doc') && !applicant.certUrl.toLowerCase().endsWith('.docx')">
                                        <a :href="applicant.certUrl" target="_blank" class="block group">
                                            <img :src="applicant.certUrl" class="w-full h-48 object-contain bg-white border border-stone-200 rounded-lg shadow-sm group-hover:opacity-90 transition" alt="Business Certificate">
                                            <span class="text-[11px] text-blue-600 underline mt-1.5 inline-block font-medium">Click image to view full size</span>
                                        </a>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!applicant.certUrl">
                                <p class="text-xs text-slate-400 italic mb-3">No business certificate or permit uploaded.</p>
                            </template>

                            <div class="mb-2">
                                <span class="text-xs font-semibold">AI Recommendation: </span>
                                <template x-if="applicant.certIsValid === '1' || applicant.certIsValid === 1 || applicant.certIsValid === true">
                                    <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">Valid Permit/Cert</span>
                                </template>
                                <template x-if="applicant.certIsValid === '0' || applicant.certIsValid === 0 || applicant.certIsValid === false">
                                    <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold">Warning / Invalid Permit</span>
                                </template>
                                <template x-if="applicant.certIsValid === null || applicant.certIsValid === undefined || applicant.certIsValid === ''">
                                    <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold">Pending Analysis</span>
                                </template>
                            </div>

                            <div>
                                <span class="text-xs font-semibold text-slate-500 block mb-1">AI Remarks:</span>
                                <p class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-stone-100 italic" x-text="applicant.certRemarks"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Rejection Form & Actions Footer -->
        <div class="mt-6 border-t pt-4 space-y-3">
            <form method="POST" :action="'/admin/verification/reject/' + applicant.userId" class="space-y-3">
                @csrf
                <input type="hidden" name="role" :value="applicant.roleLower">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Rejection Reason:</label>
                    <input type="text" name="rejection_reason" placeholder="e.g.: Unreadable ID, expired document, blurred photo..." required class="text-xs border border-stone-300 rounded-xl px-3 py-2 w-full focus:outline-none focus:border-rose-500">
                </div>

                <div class="flex justify-between items-center pt-2">
                    <button type="button" @click="openModal = false" class="bg-stone-200 hover:bg-stone-300 text-slate-700 font-medium text-xs px-4 py-2 rounded-xl transition">Close</button>
                    
                    <div class="flex space-x-2">
                        <button type="submit" onclick="return confirm('Sigurado ka bang nais mong i-reject ang verification na ito?')" class="bg-rose-600 hover:bg-rose-700 text-white font-medium text-xs px-4 py-2 rounded-xl transition shadow-sm">
                            Reject Verification
                        </button>
                    </div>
                </div>
            </form>

            <!-- Direct Approve Option inside Modal -->
            <template x-if="!applicant.isVerified">
                <div class="border-t pt-3 flex justify-end">
                    <form method="POST" :action="'/admin/verification/approve/' + applicant.userId" class="inline">
                        @csrf
                        <input type="hidden" name="role" :value="applicant.roleLower">
                        <button type="submit" onclick="return confirm('I-approve ang verification para sa aplikanteng ito?')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs px-4 py-2 rounded-xl transition shadow-sm flex items-center space-x-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Approve This Verification</span>
                        </button>
                    </form>
                </div>
            </template>
        </div>
    </div>
</div>