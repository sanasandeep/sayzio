{{--
    Event Connect QR "RSVP & Connect" prompt (Task #6685).
    Rendered at the top of the event page's CTA column when the visitor
    arrived via the Connect QR (?src=connect_qr) and RSVPs are open.
    One flow: OTP sign-in (login == signup) → auto "yes" RSVP → follow
    the host. Already-signed-in visitors get a single confirm tap.
    Vars: $link. Viewer resolved here from ViewerSession / web auth.
--}}
@php
    $cqrViewer = \App\Modules\Common\Services\ViewerSession::user() ?? auth()->guard('web')->user();
    $cqrHostName = $link->user?->name ?: 'the host';
@endphp
<div class="ev-card p-5 mb-4" style="border:1px solid rgba(61,107,255,0.45); box-shadow:0 0 24px rgba(61,107,255,0.12);"
     x-data="connectQrPrompt(@js((bool) $cqrViewer), @js($cqrViewer?->name ?: $cqrViewer?->email))">
    <div class="flex items-center gap-2.5 mb-2">
        <span class="ev-accent-icon-badge w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-qrcode text-sm"></i></span>
        <h2 class="text-sm font-bold uppercase tracking-wide ev-section-label">RSVP &amp; Connect</h2>
    </div>
    <p class="text-xs ev-muted mb-3">You scanned {{ $cqrHostName }}'s Connect QR. One step saves your RSVP and connects you with the host.</p>

    <template x-if="done">
        <div class="text-center py-3">
            <span class="inline-flex w-10 h-10 rounded-full items-center justify-center mb-2" style="background:rgba(16,185,129,0.15); color:#34d399;"><i class="fas fa-check"></i></span>
            <p class="text-sm ev-strong font-semibold" x-text="message"></p>
        </div>
    </template>

    <template x-if="!done && signedIn">
        <div>
            <p class="text-xs ev-muted mb-2">Signed in as <span class="font-semibold ev-strong" x-text="who"></span></p>
            <button @click="confirmConnect" :disabled="busy"
                    class="ev-accent-bg ev-cta-btn w-full py-3 rounded-xl text-sm font-bold text-white hover:opacity-90 transition"
                    x-text="busy ? 'Connecting…' : 'RSVP & Connect'"></button>
            <p class="text-xs mt-2" style="color:#f87171;" x-show="error" x-text="error"></p>
        </div>
    </template>

    <template x-if="!done && !signedIn">
        <div>
            <div class="flex gap-1 mb-3 text-xs">
                <button type="button" :class="channel==='email' ? 'ev-accent-bg text-white' : ''" class="px-3 py-1 rounded ev-chip" @click="channel='email'">Email</button>
                <button type="button" :class="channel==='mobile' ? 'ev-accent-bg text-white' : ''" class="px-3 py-1 rounded ev-chip" @click="channel='mobile'">Mobile</button>
            </div>
            <template x-if="step === 'identifier'">
                <form @submit.prevent="sendOtp" class="space-y-2">
                    <input :type="channel==='email' ? 'email' : 'tel'" x-model="identifier" required
                           :placeholder="channel==='email' ? 'you@email.com' : '+1 555 123 4567'"
                           class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                    <button :disabled="busy" class="ev-accent-bg ev-cta-btn w-full py-2.5 rounded-xl text-sm font-bold text-white hover:opacity-90 transition"
                            x-text="busy ? 'Sending…' : 'Send code'"></button>
                </form>
            </template>
            <template x-if="step === 'otp'">
                <form @submit.prevent="verifyOtp" class="space-y-2">
                    <input type="text" x-model="otp" required maxlength="6" placeholder="6-digit code"
                           class="ev-input w-full rounded-lg px-3 py-2 text-center tracking-[0.4em] font-bold text-base">
                    <div class="flex gap-2">
                        <button type="button" @click="step='identifier'; otp=''" class="px-3 py-2 rounded-lg text-xs ev-muted">← Back</button>
                        <button :disabled="busy" class="ev-accent-bg ev-cta-btn flex-1 py-2.5 rounded-xl text-sm font-bold text-white hover:opacity-90 transition"
                                x-text="busy ? 'Verifying…' : 'Verify & connect'"></button>
                    </div>
                    <button type="button" @click="sendOtp()" :disabled="busy" class="w-full text-xs font-medium mt-1" style="color:#6b93ff;">Resend code</button>
                </form>
            </template>
            <p class="text-xs mt-2 ev-muted" x-show="info" x-text="info"></p>
            <p class="text-xs mt-2" style="color:#f87171;" x-show="error" x-text="error"></p>
            <p class="text-[11px] mt-3 ev-muted" style="opacity:.6;">New here? An account is created for you automatically — no password needed. Your email/phone may be shared with the host.</p>
        </div>
    </template>
</div>
<script>
function connectQrPrompt(signedIn, who) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
        body: JSON.stringify(body || {}),
    });
    return {
        signedIn, who,
        channel: 'email', identifier: '', otp: '', step: 'identifier',
        busy: false, done: false, message: '', info: '', error: '',
        async sendOtp() {
            this.busy = true; this.error = ''; this.info = '';
            try {
                const r = await post(@js(route('events.connect-qr.send', $link->alias)), {identifier: this.identifier, type: this.channel});
                const d = await r.json();
                if (r.ok) {
                    this.step = 'otp';
                    this.info = d.demo_reveal || ('Code sent, check your ' + (this.channel === 'email' ? 'inbox' : 'messages') + '.');
                } else this.error = d.message || 'Could not send code.';
            } catch (e) { this.error = 'Network error.'; }
            this.busy = false;
        },
        async verifyOtp() {
            this.busy = true; this.error = '';
            try {
                const r = await post(@js(route('events.connect-qr.verify', $link->alias)), {identifier: this.identifier, type: this.channel, code: this.otp});
                const d = await r.json();
                if (r.ok && d.success) { this.done = true; this.message = d.message; }
                else this.error = d.message || 'Invalid code.';
            } catch (e) { this.error = 'Network error.'; }
            this.busy = false;
        },
        async confirmConnect() {
            this.busy = true; this.error = '';
            try {
                const r = await post(@js(route('events.connect-qr.confirm', $link->alias)));
                const d = await r.json();
                if (r.ok && d.success) { this.done = true; this.message = d.message; }
                else this.error = d.message || 'Could not complete — please try again.';
            } catch (e) { this.error = 'Network error.'; }
            this.busy = false;
        },
    };
}
</script>
