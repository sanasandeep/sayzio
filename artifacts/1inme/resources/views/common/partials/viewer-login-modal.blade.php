{{--
    Reusable viewer sign-in / follow modal.
    Required vars (set defaults if not provided):
      $modalCreatorId  – creator id this modal will follow on success (nullable)
      $modalAccent     – text color for primary text (defaults to #fff)
      $modalBgPanel    – modal panel bg color (defaults to slate-900-ish)
      $viewerInitial   – pre-existing viewer (from ViewerSession) or null
--}}
@php
    $modalCreatorId = $modalCreatorId ?? null;
    $modalAccent    = $modalAccent ?? '#ffffff';
    $modalBgPanel   = $modalBgPanel ?? '#0f172a';
    $viewerInitial  = $viewerInitial ?? null;
@endphp
<div x-data="viewerLoginModal({{ $modalCreatorId ? (int)$modalCreatorId : 'null' }}, @js($viewerInitial))"
     x-cloak
     @open-viewer-login.window="open($event.detail || {})"
     @viewer-followed.window="onFollowed($event.detail)"
     style="position: fixed; inset: 0; z-index: 9999;"
     x-show="visible">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="visible = false"></div>
    <div class="absolute left-1/2 top-1/2 w-[95%] max-w-sm -translate-x-1/2 -translate-y-1/2 rounded-2xl shadow-2xl p-6"
         style="background: {{ $modalBgPanel }}; color: {{ $modalAccent }};">
        <div class="flex items-start justify-between mb-3">
            <div>
                <h3 class="text-lg font-extrabold"
                    x-text="pendingAction === 'message' ? 'Sign in to message' : 'Sign in to follow'"></h3>
                <p class="text-xs opacity-70 mt-0.5">One-time code · No password.</p>
            </div>
            <button @click="visible = false" class="opacity-50 hover:opacity-100 text-xl leading-none">&times;</button>
        </div>
        <template x-if="!loggedIn">
            <div>
                <div class="flex gap-1 mb-3 text-xs">
                    <button type="button" :class="channel==='email' ? 'bg-blue-600 text-white' : 'bg-white/10'" class="px-3 py-1 rounded" @click="channel='email'">Email</button>
                    <button type="button" :class="channel==='mobile' ? 'bg-blue-600 text-white' : 'bg-white/10'" class="px-3 py-1 rounded" @click="channel='mobile'">Mobile</button>
                </div>
                <template x-if="step === 'identifier'">
                    <form @submit.prevent="sendOtp" class="space-y-2">
                        <input :type="channel==='email' ? 'email' : 'tel'" x-model="identifier" required
                               :placeholder="channel==='email' ? 'you@email.com' : '+1 555 123 4567'"
                               class="w-full px-3 py-2 rounded-lg text-sm outline-none bg-white/10 border border-white/15 focus:border-blue-500"/>
                        <button :disabled="sending" class="w-full px-4 py-2 rounded-lg text-sm font-bold bg-blue-600 hover:bg-blue-500" x-text="sending ? 'Sending…' : 'Send code'"></button>
                    </form>
                </template>
                <template x-if="step === 'otp'">
                    <form @submit.prevent="verifyOtp" class="space-y-2">
                        <input type="text" x-model="otp" required maxlength="6" placeholder="6-digit code"
                               class="w-full px-3 py-2 rounded-lg text-center tracking-[0.4em] font-bold text-lg outline-none bg-white/10 border border-white/15 focus:border-blue-500"/>
                        <div class="flex gap-2">
                            <button type="button" @click="step='identifier'; otp=''" class="px-3 py-2 rounded-lg text-xs opacity-80">← Back</button>
                            <button :disabled="verifying" class="flex-1 px-4 py-2 rounded-lg text-sm font-bold bg-blue-600 hover:bg-blue-500" x-text="verifying ? 'Verifying…' : (creatorId ? 'Verify & follow' : 'Verify')"></button>
                        </div>
                        <button type="button" @click="sendOtp()" :disabled="sending" class="w-full text-xs font-medium text-blue-300 hover:text-blue-200 mt-1" x-text="sending ? 'Resending…' : 'Resend code'"></button>
                    </form>
                </template>
                <p class="text-xs mt-3 opacity-90" x-show="message" x-text="message"></p>
                <p class="text-[11px] mt-3 opacity-50">By continuing you agree your email/phone may be shared with the creator you follow.</p>
            </div>
        </template>
        <template x-if="loggedIn">
            <div class="text-center py-4">
                <p class="text-sm opacity-80">You're signed in as</p>
                <p class="font-bold mt-1" x-text="me?.name || me?.email"></p>
                <button @click="visible = false" class="mt-4 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold">Continue</button>
            </div>
        </template>
    </div>
</div>
<script>
function viewerLoginModal(creatorId, initialMe) {
    return {
        visible: false,
        loggedIn: !!initialMe,
        me: initialMe,
        creatorId: creatorId,
        // Pending action triggered by the caller. Currently:
        //   - default (null): follow `creatorId` after login.
        //   - 'message':       open chat overlay against `pendingBiolinkId`.
        pendingAction: null,
        pendingBiolinkId: null,
        pendingCreatorName: '',
        channel: 'email',
        identifier: '',
        otp: '',
        step: 'identifier',
        sending: false, verifying: false,
        message: '',
        csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
        open(detail) {
            detail = detail || {};
            // Allow overriding the creator id when re-using on the directory.
            if (detail.creatorId) this.creatorId = detail.creatorId;
            this.pendingAction       = detail.action || null;
            this.pendingBiolinkId    = detail.biolinkId || null;
            this.pendingCreatorName  = detail.creatorName || '';
            if (this.loggedIn) {
                if (this.pendingAction === 'message' && this.pendingBiolinkId) {
                    this.dispatchMessageReady();
                    return;
                }
                if (this.creatorId && detail.followAfterLogin !== false) {
                    // Already signed in: just follow + close.
                    this.followAndClose();
                    return;
                }
            }
            this.visible = true;
            this.message = '';
        },
        dispatchMessageReady() {
            window.dispatchEvent(new CustomEvent('viewer-message-ready', {
                detail: {
                    biolinkId:   this.pendingBiolinkId,
                    creatorId:   this.creatorId,
                    creatorName: this.pendingCreatorName,
                    me:          this.me,
                },
            }));
            this.visible = false;
        },
        async sendOtp() {
            this.sending = true; this.message = '';
            try {
                const r = await fetch('/viewer/otp/send', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'Accept':'application/json'}, body: JSON.stringify({identifier:this.identifier, type:this.channel})});
                const d = await r.json();
                if (r.ok) {
                    this.step='otp';
                    // Demo mode (admin toggle): when on, the backend returns
                    // the actual code so it can be shown on screen.
                    this.message = d.demo_reveal || ('Code sent, check your '+(this.channel==='email'?'inbox':'messages')+'.');
                }
                else this.message = d.message || 'Could not send code.';
            } catch(e) { this.message = 'Network error.'; }
            this.sending = false;
        },
        async verifyOtp() {
            this.verifying = true; this.message='';
            try {
                const r = await fetch('/viewer/otp/verify', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'Accept':'application/json'}, body: JSON.stringify({identifier:this.identifier, type:this.channel, code:this.otp})});
                const d = await r.json();
                if (r.ok) {
                    this.loggedIn = true;
                    this.me = d.user;
                    if (this.pendingAction === 'message' && this.pendingBiolinkId) {
                        this.dispatchMessageReady();
                    } else if (this.creatorId) {
                        await this.followAndClose();
                    } else { this.message = 'Signed in!'; setTimeout(()=>this.visible=false, 600); }
                } else this.message = d.message || 'Invalid code.';
            } catch(e) { this.message = 'Network error.'; }
            this.verifying = false;
        },
        async followAndClose() {
            try {
                const r = await fetch('/viewer/follow/' + this.creatorId, {method:'POST', headers:{'X-CSRF-TOKEN':this.csrf,'Accept':'application/json'}});
                const d = await r.json();
                window.dispatchEvent(new CustomEvent('viewer-followed', {detail: {creatorId: this.creatorId, following: d.following, me: this.me}}));
            } catch(e) {}
            this.visible = false;
            // Reload so the page reflects the signed-in viewer state
            // (avatar menu, follow status, identified visitor tracking, etc.).
            setTimeout(() => window.location.reload(), 250);
        },
        onFollowed(detail) { /* no-op: child cards listen */ }
    }
}
</script>
