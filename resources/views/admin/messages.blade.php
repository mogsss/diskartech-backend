<x-admin-layout>
    @push('header-title')
        Communications & Messages
    @endpush

    <div x-data="adminMessagesHandler()" x-init="initFirebase()" class="flex flex-col h-[calc(100vh-140px)]">

        <!-- Top Metrics Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5 flex-shrink-0">
            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 font-medium">Monitored Rooms</p>
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight mt-0.5" x-text="chats.length">0</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 font-medium">Messages in Active View</p>
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight mt-0.5" x-text="selectedChat ? messages.length : 0">0</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                    </svg>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 font-medium">Open Reports</p>
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">{{ $openReportsCount }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 font-medium">Firestore Engine</p>
                    <div class="flex items-center space-x-1.5 mt-1">
                        <span class="w-2.5 h-2.5 rounded-full" :class="firebaseConnected ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400'"></span>
                        <span class="text-xs font-bold text-slate-800" x-text="firebaseConnected ? 'Live Synchronized' : 'Connecting...'">Connecting...</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Main Workspace (2-Column Chat Monitor) -->
        <div class="flex-1 bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden flex min-h-0">

            <!-- Left Panel: Conversation Directory -->
            <div class="w-80 md:w-96 border-r border-stone-200/80 flex flex-col bg-[#FAF8F5]">
                
                <!-- Search & Filters -->
                <div class="p-3.5 border-b border-stone-200/80 space-y-2.5 bg-white">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </span>
                        <input
                            type="text"
                            x-model="searchQuery"
                            placeholder="Search names, jobs, or messages..."
                            class="w-full bg-[#F4EFE6] text-xs rounded-xl pl-9 pr-3 py-2 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-red-600 border-0"
                        >
                    </div>

                    <!-- Role Filter Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5">
                        <button
                            @click="filterRole = 'all'"
                            :class="filterRole === 'all' ? 'bg-slate-900 text-white font-semibold' : 'bg-stone-100 text-slate-600 hover:bg-stone-200'"
                            class="px-2.5 py-1 rounded-lg text-[11px] transition whitespace-nowrap"
                        >
                            All (<span x-text="chats.length">0</span>)
                        </button>
                        <button
                            @click="filterRole = 'employer'"
                            :class="filterRole === 'employer' ? 'bg-red-700 text-white font-semibold' : 'bg-stone-100 text-slate-600 hover:bg-stone-200'"
                            class="px-2.5 py-1 rounded-lg text-[11px] transition whitespace-nowrap"
                        >
                            Employers
                        </button>
                        <button
                            @click="filterRole = 'household'"
                            :class="filterRole === 'household' ? 'bg-red-700 text-white font-semibold' : 'bg-stone-100 text-slate-600 hover:bg-stone-200'"
                            class="px-2.5 py-1 rounded-lg text-[11px] transition whitespace-nowrap"
                        >
                            Households
                        </button>
                    </div>
                </div>

                <!-- Chat Room List Container -->
                <div class="flex-1 overflow-y-auto divide-y divide-stone-100">
                    <template x-if="loadingChats">
                        <div class="p-8 text-center text-slate-400 space-y-2">
                            <svg class="w-6 h-6 animate-spin mx-auto text-red-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-xs">Connecting to Firebase chat stream...</p>
                        </div>
                    </template>

                    <template x-if="!loadingChats && filteredChats.length === 0">
                        <div class="p-8 text-center text-slate-400 space-y-2">
                            <svg class="w-8 h-8 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-xs font-medium">No matching conversation threads found.</p>
                        </div>
                    </template>

                    <template x-for="chat in filteredChats" :key="chat.id">
                        <div
                            @click="selectChat(chat)"
                            class="p-3.5 cursor-pointer transition flex items-start space-x-3 text-left relative"
                            :class="selectedChat && selectedChat.id === chat.id ? 'bg-red-50/80 border-l-4 border-red-600' : 'hover:bg-white/80'"
                        >
                            <!-- Avatar Group -->
                            <div class="relative flex-shrink-0">
                                <div class="w-10 h-10 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center uppercase overflow-hidden border border-white shadow-sm">
                                    <template x-if="chat.student_avatar_url">
                                        <img :src="chat.student_avatar_url" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!chat.student_avatar_url">
                                        <span x-text="(chat.student_name || 'S').charAt(0)">S</span>
                                    </template>
                                </div>
                                <div class="w-5 h-5 rounded-full bg-red-600 text-white font-bold text-[9px] flex items-center justify-center absolute -bottom-1 -right-1 border border-white uppercase shadow-xs">
                                    <span x-text="(chat.owner_name || 'E').charAt(0)">E</span>
                                </div>
                            </div>

                            <!-- Room Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-0.5">
                                    <h4 class="text-xs font-bold text-slate-900 truncate" x-text="(chat.student_name || 'Student') + ' & ' + (chat.owner_name || 'Hirer')"></h4>
                                    <span class="text-[10px] text-slate-400 font-medium whitespace-nowrap ml-1" x-text="formatTimeAgo(chat.last_message_at || chat.updated_at)"></span>
                                </div>

                                <!-- Job Title Badge -->
                                <template x-if="chat.job_title">
                                    <div class="mb-1">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-stone-200/70 text-slate-700 truncate max-w-full">
                                            💼 <span class="truncate ml-1" x-text="chat.job_title"></span>
                                        </span>
                                    </div>
                                </template>

                                <!-- Last Message Preview -->
                                <p class="text-xs text-slate-500 truncate" x-text="chat.last_message || 'No messages exchanged yet.'"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Right Panel: Conversation Inspection & Transcript -->
            <div class="flex-1 flex flex-col min-w-0 bg-[#FDFBF7]">

                <template x-if="!selectedChat">
                    <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-[#F5EFE6] border border-stone-200/80 flex items-center justify-center text-slate-400 mb-3 shadow-inner">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-800">Select a Conversation</h3>
                        <p class="text-xs text-slate-500 max-w-sm mt-1">
                            Choose any active chat thread from the left directory to audit messages, inspect participant details, or send official administrative advisories.
                        </p>
                    </div>
                </template>

                <template x-if="selectedChat">
                    <div class="flex-1 flex flex-col min-h-0">
                        
                        <!-- Room Header & Participant Inspection -->
                        <div class="px-6 py-3.5 border-b border-stone-200/80 bg-white flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center space-x-4">
                                <!-- Student Badge -->
                                <div class="flex items-center space-x-2">
                                    <div class="w-9 h-9 rounded-full bg-slate-800 text-white font-bold text-xs flex items-center justify-center uppercase overflow-hidden">
                                        <template x-if="selectedChat.student_avatar_url">
                                            <img :src="selectedChat.student_avatar_url" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!selectedChat.student_avatar_url">
                                            <span x-text="(selectedChat.student_name || 'S').charAt(0)">S</span>
                                        </template>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-1">
                                            <span class="text-xs font-bold text-slate-900" x-text="selectedChat.student_name || 'Student'"></span>
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 bg-blue-50 text-blue-600 rounded">Student</span>
                                        </div>
                                        <p class="text-[10px] text-slate-400" x-text="'ID: ' + (selectedChat.student_user_id || 'N/A')"></p>
                                    </div>
                                </div>

                                <span class="text-slate-300 font-bold">⇄</span>

                                <!-- Hirer Badge -->
                                <div class="flex items-center space-x-2">
                                    <div class="w-9 h-9 rounded-full bg-red-600 text-white font-bold text-xs flex items-center justify-center uppercase overflow-hidden">
                                        <template x-if="selectedChat.owner_avatar_url">
                                            <img :src="selectedChat.owner_avatar_url" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!selectedChat.owner_avatar_url">
                                            <span x-text="(selectedChat.owner_name || 'H').charAt(0)">H</span>
                                        </template>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-1">
                                            <span class="text-xs font-bold text-slate-900" x-text="selectedChat.owner_name || 'Hirer'"></span>
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 bg-amber-50 text-amber-700 rounded capitalize" x-text="selectedChat.owner_role || 'Employer'"></span>
                                        </div>
                                        <p class="text-[10px] text-slate-400" x-text="'ID: ' + (selectedChat.owner_user_id || 'N/A')"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center space-x-2">
                                <template x-if="selectedChat.job_id">
                                    <span class="px-2.5 py-1 bg-stone-100 rounded-lg text-xs font-medium text-slate-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        <span x-text="selectedChat.job_title || ('Job #' + selectedChat.job_id)"></span>
                                    </span>
                                </template>

                                <button
                                    @click="printTranscript()"
                                    class="px-3 py-1.5 bg-[#F4EFE6] hover:bg-stone-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center space-x-1"
                                    title="Export transcript for dispute documentation"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                    <span>Print Log</span>
                                </button>
                            </div>
                        </div>

                        <!-- Messages Stream Container -->
                        <div id="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4">
                            <template x-if="loadingMessages">
                                <div class="py-12 text-center text-slate-400 space-y-2">
                                    <svg class="w-6 h-6 animate-spin mx-auto text-red-600" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="text-xs">Retrieving message transcript...</p>
                                </div>
                            </template>

                            <template x-if="!loadingMessages && messages.length === 0">
                                <div class="py-12 text-center text-slate-400 space-y-2">
                                    <p class="text-xs font-medium">No messages found in this room.</p>
                                </div>
                            </template>

                            <template x-for="msg in messages" :key="msg.id">
                                <div class="flex flex-col" :class="getMessageAlignment(msg)">
                                    
                                    <!-- Sender Info -->
                                    <div class="flex items-center space-x-1.5 mb-1 px-1">
                                        <span class="text-[11px] font-bold text-slate-700" x-text="msg.sender_name || 'User'"></span>
                                        <span class="text-[9px] px-1 py-0.2 rounded font-semibold uppercase" :class="getRoleBadgeClass(msg)" x-text="getSenderRoleLabel(msg)"></span>
                                        <span class="text-[10px] text-slate-400" x-text="formatMessageTime(msg.created_at)"></span>
                                    </div>

                                    <!-- Message Bubble -->
                                    <div
                                        class="max-w-md px-4 py-2.5 rounded-2xl text-xs leading-relaxed shadow-xs"
                                        :class="getMessageBubbleClass(msg)"
                                    >
                                        <p class="whitespace-pre-wrap" x-text="msg.text || msg.message || ''"></p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Admin Moderator Advisory Bar -->
                        <div class="p-3.5 bg-white border-t border-stone-200/80 flex-shrink-0">
                            <form @submit.prevent="sendAdminAdvisory()" class="flex items-center space-x-2">
                                <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center font-black text-xs flex-shrink-0" title="Super Admin Post">
                                    🛡️
                                </div>
                                <input
                                    type="text"
                                    x-model="advisoryText"
                                    placeholder="Post an official Moderator Notice / Safety Warning into this room..."
                                    class="flex-1 bg-[#F5EFE6] text-xs rounded-xl px-3.5 py-2.5 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-600 border-0"
                                >
                                <button
                                    type="submit"
                                    :disabled="!advisoryText.trim() || sendingAdvisory"
                                    class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition flex items-center space-x-1.5"
                                >
                                    <span x-text="sendingAdvisory ? 'Posting...' : 'Post Advisory'"></span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </button>
                            </form>
                            <p class="text-[10px] text-slate-400 mt-1 pl-10">
                                Messages posted here will be marked with official <strong>Moderator / Trust & Safety</strong> badges in the student and employer mobile chat.
                            </p>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </div>

    <!-- Pass Database Users Directory and Firebase Config to JS -->
    <script>
        window.__DISKARTECH_USERS__ = @json($users);
        window.__FIREBASE_CONFIG__ = @json($firebaseConfig);
    </script>

    <!-- Modular Firebase SDK via CDN -->
    <script type="module">
        import { initializeApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
        import { 
            getFirestore, 
            collection, 
            query, 
            orderBy, 
            onSnapshot, 
            addDoc, 
            updateDoc, 
            doc, 
            serverTimestamp 
        } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore.js";

        // Global store for Firebase instance
        const fbApp = getApps().length === 0 ? initializeApp(window.__FIREBASE_CONFIG__) : getApps()[0];
        const db = getFirestore(fbApp);

        window.adminMessagesHandler = function() {
            return {
                firebaseConnected: false,
                loadingChats: true,
                chats: [],
                searchQuery: '',
                filterRole: 'all',

                selectedChat: null,
                loadingMessages: false,
                messages: [],
                messagesUnsubscribe: null,
                advisoryText: '',
                sendingAdvisory: false,

                initFirebase() {
                    try {
                        const chatsRef = collection(db, 'chats');
                        const chatsQuery = query(chatsRef, orderBy('updated_at', 'desc'));

                        onSnapshot(chatsQuery, (snapshot) => {
                            this.firebaseConnected = true;
                            this.loadingChats = false;

                            const loadedChats = [];
                            snapshot.forEach((docSnap) => {
                                const data = docSnap.data();
                                loadedChats.push({
                                    id: docSnap.id,
                                    ...data
                                });
                            });

                            this.chats = loadedChats;

                            // If a chat was selected, keep selected reference updated
                            if (this.selectedChat) {
                                const current = this.chats.find(c => c.id === this.selectedChat.id);
                                if (current) this.selectedChat = current;
                            }
                        }, (error) => {
                            console.error('Firestore chats listener error:', error);
                            this.loadingChats = false;
                            this.firebaseConnected = false;
                        });
                    } catch (err) {
                        console.error('Firebase init error:', err);
                        this.loadingChats = false;
                    }
                },

                get filteredChats() {
                    const q = (this.searchQuery || '').toLowerCase().trim();
                    return this.chats.filter(c => {
                        // Role Filter
                        if (this.filterRole === 'employer' && (c.owner_role || '').toLowerCase() !== 'employer') {
                            return false;
                        }
                        if (this.filterRole === 'household' && (c.owner_role || '').toLowerCase() !== 'household') {
                            return false;
                        }

                        // Search Filter
                        if (!q) return true;
                        const student = (c.student_name || '').toLowerCase();
                        const owner = (c.owner_name || '').toLowerCase();
                        const job = (c.job_title || '').toLowerCase();
                        const lastMsg = (c.last_message || '').toLowerCase();

                        return student.includes(q) || owner.includes(q) || job.includes(q) || lastMsg.includes(q);
                    });
                },

                selectChat(chat) {
                    this.selectedChat = chat;
                    this.messages = [];
                    this.loadingMessages = true;

                    // Unsubscribe previous message listener if exists
                    if (this.messagesUnsubscribe) {
                        this.messagesUnsubscribe();
                        this.messagesUnsubscribe = null;
                    }

                    try {
                        const messagesRef = collection(db, 'chats', chat.id, 'messages');
                        const messagesQuery = query(messagesRef, orderBy('created_at', 'asc'));

                        this.messagesUnsubscribe = onSnapshot(messagesQuery, (snapshot) => {
                            this.loadingMessages = false;
                            const msgs = [];
                            snapshot.forEach((docSnap) => {
                                msgs.push({
                                    id: docSnap.id,
                                    ...docSnap.data()
                                });
                            });
                            this.messages = msgs;

                            // Scroll to bottom after render
                            this.$nextTick(() => {
                                const container = document.getElementById('messagesContainer');
                                if (container) {
                                    container.scrollTop = container.scrollHeight;
                                }
                            });
                        }, (err) => {
                            console.error('Messages query error:', err);
                            this.loadingMessages = false;
                        });
                    } catch (err) {
                        console.error('Error attaching messages listener:', err);
                        this.loadingMessages = false;
                    }
                },

                async sendAdminAdvisory() {
                    if (!this.selectedChat || !this.advisoryText.trim() || this.sendingAdvisory) return;

                    const text = this.advisoryText.trim();
                    this.sendingAdvisory = true;

                    try {
                        const messagesRef = collection(db, 'chats', this.selectedChat.id, 'messages');
                        await addDoc(messagesRef, {
                            text: "🛡️ [Official Admin Advisory]: " + text,
                            sender_id: "admin_moderator",
                            sender_name: "DiskarTech Admin",
                            sender_role: "admin",
                            created_at: serverTimestamp()
                        });

                        const chatRef = doc(db, 'chats', this.selectedChat.id);
                        await updateDoc(chatRef, {
                            last_message: "🛡️ [Admin Advisory]: " + text,
                            last_sender_id: "admin_moderator",
                            last_message_at: serverTimestamp(),
                            updated_at: serverTimestamp()
                        });

                        this.advisoryText = '';
                    } catch (err) {
                        console.error('Error posting admin advisory:', err);
                        alert('Unable to post advisory at this time: ' + err.message);
                    } finally {
                        this.sendingAdvisory = false;
                    }
                },

                getMessageAlignment(msg) {
                    if (msg.sender_role === 'admin') return 'items-center my-2';
                    if (msg.sender_role === 'student') return 'items-start';
                    return 'items-end';
                },

                getMessageBubbleClass(msg) {
                    if (msg.sender_role === 'admin') {
                        return 'bg-amber-100/90 text-amber-900 border border-amber-300 font-medium text-center';
                    }
                    if (msg.sender_role === 'student') {
                        return 'bg-white text-slate-800 border border-stone-200/90 rounded-tl-sm';
                    }
                    return 'bg-red-700 text-white rounded-tr-sm';
                },

                getRoleBadgeClass(msg) {
                    if (msg.sender_role === 'admin') return 'bg-amber-100 text-amber-800';
                    if (msg.sender_role === 'student') return 'bg-blue-100 text-blue-700';
                    return 'bg-rose-100 text-rose-700';
                },

                getSenderRoleLabel(msg) {
                    if (msg.sender_role === 'admin') return 'Admin Moderator';
                    if (msg.sender_role === 'student') return 'Student';
                    return (msg.sender_role || 'Hirer');
                },

                formatTimeAgo(timestamp) {
                    if (!timestamp) return '';
                    let date;
                    if (timestamp.toDate) {
                        date = timestamp.toDate();
                    } else if (timestamp.seconds) {
                        date = new Date(timestamp.seconds * 1000);
                    } else {
                        date = new Date(timestamp);
                    }
                    if (isNaN(date.getTime())) return '';

                    const now = new Date();
                    const diffMs = now - date;
                    const diffMin = Math.floor(diffMs / 60000);
                    const diffHr = Math.floor(diffMin / 60);
                    const diffDays = Math.floor(diffHr / 24);

                    if (diffMin < 1) return 'just now';
                    if (diffMin < 60) return diffMin + 'm ago';
                    if (diffHr < 24) return diffHr + 'h ago';
                    if (diffDays === 1) return 'yesterday';
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                },

                formatMessageTime(timestamp) {
                    if (!timestamp) return '';
                    let date;
                    if (timestamp.toDate) {
                        date = timestamp.toDate();
                    } else if (timestamp.seconds) {
                        date = new Date(timestamp.seconds * 1000);
                    } else {
                        date = new Date(timestamp);
                    }
                    if (isNaN(date.getTime())) return '';
                    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                },

                printTranscript() {
                    window.print();
                }
            };
        };
    </script>
</x-admin-layout>
