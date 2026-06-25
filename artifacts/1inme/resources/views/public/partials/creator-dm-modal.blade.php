{{-- Paid DMs widget (Task #1210). Shared by the /@handle creator profile and
     the Paid Page link type. Drives the handle-based /viewer/dm endpoints. --}}
@if(!$isOwner && ($creator->dm_access_mode ?? 'open') !== 'closed')
<div id="cp-dm-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9998] hidden items-end sm:items-center justify-center" style="display: none;">
    <style>#cp-dm-modal:not(.hidden){display:flex !important;}</style>
    <div x-data="cpDm({ handle: @js($creator->handle ?: $creator->id), creatorName: @js($creator->name) })"
         x-init="open()"
         class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-md sm:w-[95%] sm:h-[85vh] h-[92vh] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-slate-900">DM {{ $creator->name }}</h3>
                <p class="text-[11px] text-slate-500" x-text="statusLabel()"></p>
            </div>
            <button id="cp-dm-close" class="text-slate-400 hover:text-slate-700 p-1"><i class="fas fa-times"></i></button>
        </div>

        <div x-show="state.reason === 'login_required'" class="flex-1 flex items-center justify-center p-6 text-center">
            <div>
                <p class="text-sm text-slate-700 mb-3">Sign in to send a direct message.</p>
                <a href="#" @click.prevent="window.dispatchEvent(new CustomEvent('open-viewer-login', { detail: { creatorId: {{ (int) $creator->id }} } }))"
                   class="inline-block px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">Sign in</a>
            </div>
        </div>

        <div x-show="state.reason !== 'login_required'" class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50" id="cp-dm-scroll">
            <template x-for="m in state.messages" :key="m.id">
                <div :class="m.side === 'viewer' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.side === 'viewer'
                        ? 'bg-blue-600 text-white rounded-2xl rounded-br-sm px-3 py-2 max-w-[80%]'
                        : (m.kind === 'system'
                            ? 'bg-amber-50 border border-amber-200 text-amber-700 italic rounded-2xl px-3 py-2 max-w-[80%] text-xs'
                            : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-bl-sm px-3 py-2 max-w-[80%]')">
                        <p class="text-sm whitespace-pre-wrap" x-text="m.body"></p>
                        <template x-for="a in m.attachments" :key="a.id">
                            <div class="mt-2">
                                <template x-if="a.is_locked">
                                    <button type="button" @click="unlock(a)"
                                            class="block relative rounded-lg overflow-hidden border border-blue-300 bg-blue-50 text-left">
                                        <img :src="a.thumb_url" class="w-44 h-44 object-cover blur-md" alt="">
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/40">
                                            <span class="text-white text-xs font-bold">
                                                <i class="fas fa-lock mr-1"></i>
                                                Unlock $<span x-text="(a.lock_price_cents/100).toFixed(2)"></span>
                                            </span>
                                        </div>
                                    </button>
                                </template>
                                <template x-if="!a.is_locked">
                                    <a :href="a.url" target="_blank">
                                        <img :src="a.thumb_url || a.url" class="w-44 max-h-60 object-cover rounded-lg" alt="">
                                    </a>
                                </template>
                            </div>
                        </template>
                        <p class="text-[10px] mt-1 opacity-70" x-text="formatTime(m.sent_at)"></p>
                    </div>
                </div>
            </template>
            <div x-show="state.messages.length === 0 && !state.loading" class="text-center text-xs text-slate-400 py-10">
                No messages yet — say hi 👋
            </div>
        </div>

        <div class="border-t border-slate-100 p-3 bg-white" x-show="state.reason !== 'login_required'">
            <template x-if="state.reason === 'closed'">
                <p class="text-xs text-slate-500">DMs are turned off for this creator.</p>
            </template>
            <template x-if="state.reason === 'subs_required'">
                <a :href="`/@${state.handle}/subscribe`" class="block text-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                    Subscribe to message
                    <span x-show="state.policy.min_tier_name" x-text="`· ${state.policy.min_tier_name}`"></span>
                </a>
            </template>
            <template x-if="state.reason === 'paid_required'">
                <button type="button" @click="payToMessage()"
                        class="w-full px-4 py-2 rounded-lg bg-gradient-to-r from-rose-500 to-pink-600 text-white text-sm font-semibold">
                    <i class="fas fa-lock mr-1"></i>
                    Pay $<span x-text="((state.policy.price_cents||0)/100).toFixed(2)"></span> to start chatting
                </button>
            </template>
            <template x-if="state.reason === 'account_blocked' || state.reason === 'thread_blocked'">
                <p class="text-xs text-rose-500">You can't message this creator.</p>
            </template>
            <template x-if="state.reason === 'throttled'">
                <p class="text-xs text-amber-600">Wait for {{ $creator->name }} to reply before sending more messages.</p>
            </template>
            <template x-if="state.reason === 'ok'">
                <form @submit.prevent="send()" class="flex items-end gap-2">
                    <textarea x-model="draft" rows="1" maxlength="5000" placeholder="Write a message…"
                              class="flex-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-blue-400 focus:outline-none text-sm resize-none"
                              @keydown.enter.prevent.exact="send()"></textarea>
                    <button type="button" @click="openTip()" title="Tip"
                            class="px-3 py-2 rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50">
                        <i class="fas fa-heart"></i>
                    </button>
                    <button type="submit" :disabled="state.sending || !draft.trim()"
                            class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold disabled:opacity-50">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </template>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-cp-open-dm]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const m = document.getElementById('cp-dm-modal');
            if (m) m.classList.remove('hidden');
        });
    });
    const close = () => document.getElementById('cp-dm-modal')?.classList.add('hidden');
    document.getElementById('cp-dm-close')?.addEventListener('click', close);
    document.getElementById('cp-dm-modal')?.addEventListener('click', (e) => {
        if (e.target.id === 'cp-dm-modal') close();
    });
})();

function cpDm(opts) {
    return {
        state: {
            handle: opts.handle,
            creatorName: opts.creatorName,
            messages: [],
            policy: { mode: 'open', price_cents: 0, currency: 'USD' },
            reason: 'ok',
            conversationId: null,
            loading: true,
            sending: false,
        },
        draft: '',
        async open() {
            await this.refresh();
        },
        async refresh() {
            this.state.loading = true;
            try {
                const r = await fetch(`/viewer/dm/profile/${this.state.handle}/thread`, { credentials: 'same-origin' });
                if (r.status === 401) { this.state.reason = 'login_required'; return; }
                const j = await r.json();
                if (!j.ok) { this.state.reason = j.reason || 'closed'; return; }
                this.state.messages = j.messages;
                this.state.policy = j.policy;
                this.state.reason = j.policy.reason;
                this.state.conversationId = j.conversation_id;
                this.$nextTick(() => {
                    const el = document.getElementById('cp-dm-scroll');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            } catch (e) {
                this.state.reason = 'error';
            } finally {
                this.state.loading = false;
            }
        },
        async send() {
            if (!this.draft.trim() || this.state.sending) return;
            this.state.sending = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const r = await fetch(`/viewer/dm/profile/${this.state.handle}/send`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ body: this.draft }),
                });
                if (r.status === 402) {
                    const j = await r.json();
                    if (j.checkout_url) window.location.href = j.checkout_url;
                    return;
                }
                const j = await r.json();
                if (!j.ok) {
                    alert(j.reason === 'throttled' ? 'Wait for a reply before sending more.' : (j.reason || 'Could not send.'));
                    return;
                }
                this.state.messages.push(j.message);
                this.draft = '';
                this.$nextTick(() => {
                    const el = document.getElementById('cp-dm-scroll');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            } finally {
                this.state.sending = false;
            }
        },
        async payToMessage() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const r = await fetch(`/viewer/dm/profile/${this.state.handle}/send`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ body: '👋' }),
            });
            if (r.status === 402) {
                const j = await r.json();
                if (j.checkout_url) window.location.href = j.checkout_url;
            }
        },
        async unlock(att) {
            if (!confirm(`Unlock for $${(att.lock_price_cents/100).toFixed(2)}?`)) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const r = await fetch(`/viewer/dm/attachments/${att.id}/unlock`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ return_url: window.location.href }),
            });
            const j = await r.json();
            if (j.checkout_url) window.location.href = j.checkout_url;
        },
        openTip() {
            const btn = document.querySelector('[data-cp-open-tip]');
            if (btn) { document.getElementById('cp-dm-modal')?.classList.add('hidden'); btn.click(); }
        },
        statusLabel() {
            const m = this.state.policy?.mode || 'open';
            if (m === 'paid' && !this.state.policy.paid && !this.state.policy.subscribed) {
                return `Pay-to-message — $${((this.state.policy.price_cents||0)/100).toFixed(2)} to start`;
            }
            if (m === 'subs') return 'Subscribers only';
            return 'Direct message';
        },
        formatTime(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleString(undefined, { hour: '2-digit', minute: '2-digit' }); }
            catch { return ''; }
        },
    };
}
</script>
@endif
