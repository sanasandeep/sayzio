@extends('user.layouts.app')
@section('title', $staff->name)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8" x-data="aiStaffShow({
        staffId: {{ $staff->id }},
        domain: @js($staff->domain),
        chatUrl: '{{ route('user.ai.staff.chat', $staff) }}',
        draftInvoiceUrl: '{{ route('user.ai.staff.draft-invoice', $staff) }}',
        chaseUrl: '{{ route('user.ai.staff.chase-suggestions', $staff) }}',
        applyUrlBase: '{{ url('/user/ai/staff/suggestions') }}',
    })">
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('user.ai.staff.index') }}" class="text-xs text-white/40 hover:text-white/70"><i class="fas fa-arrow-left mr-1"></i>All staff</a>
            <h1 class="text-2xl font-bold text-white mt-2">{{ $staff->name }}</h1>
            <p class="text-sm text-white/50">{{ $staff->domainLabel() }} &middot; {{ number_format($balance) }} credits available</p>
        </div>
        <form method="POST" action="{{ route('user.ai.staff.update', $staff) }}">
            @csrf @method('PUT')
            <input type="hidden" name="name" value="{{ $staff->name }}">
            <input type="hidden" name="instructions" value="{{ $staff->instructions }}">
            <input type="hidden" name="is_disabled" value="{{ $staff->is_disabled ? 0 : 1 }}">
            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg {{ $staff->is_disabled ? 'bg-green-600/80 text-white' : 'bg-white/10 text-white/70' }}">
                {{ $staff->is_disabled ? 'Enable' : 'Disable' }}
            </button>
        </form>
    </div>

    @if(!$planAllowed)
        <div class="rounded-2xl border border-amber-400/20 bg-amber-400/5 p-4 text-amber-200/90 text-sm mb-6">
            AI Staff for this domain isn't available on your current plan.
        </div>
    @endif

    <form method="POST" action="{{ route('user.ai.staff.update', $staff) }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 mb-6">
        @csrf @method('PUT')
        <input type="hidden" name="is_disabled" value="{{ $staff->is_disabled ? 1 : 0 }}">
        <label class="block text-xs text-white/50 mb-1">Name</label>
        <input type="text" name="name" value="{{ $staff->name }}" maxlength="120" required
               class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm mb-3">
        <label class="block text-xs text-white/50 mb-1">Instructions / personality</label>
        <textarea name="instructions" maxlength="4000" rows="3"
                  class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm">{{ $staff->instructions }}</textarea>
        <div class="flex justify-between items-center mt-3">
            <button type="submit" class="text-sm px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700">Save</button>
            <button type="button" onclick="if(confirm('Remove this AI staff member?')) document.getElementById('destroy-staff').submit();"
                    class="text-xs text-red-300/80 hover:text-red-200">Remove</button>
        </div>
    </form>
    <form id="destroy-staff" method="POST" action="{{ route('user.ai.staff.destroy', $staff) }}" class="hidden">
        @csrf @method('DELETE')
    </form>

    @if($staff->domain === 'inbox')
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 mb-6">
            <h3 class="text-white font-semibold mb-2"><i class="fas fa-inbox text-blue-300/80 mr-1.5"></i>AI Inbox Agent</h3>
            <p class="text-sm text-white/50">This staff member is a face on top of your existing AI Inbox Agent. Configure autopilot rules, tone and reply drafting from
                <a href="{{ url('/user/settings/inbox') }}" class="text-blue-300 hover:text-blue-200">Settings → Inbox</a>, nothing here duplicates that setup.</p>
        </div>
    @endif

    @if(in_array($staff->domain, ['billing', 'contacts', 'general']))
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 mb-6">
            <h3 class="text-white font-semibold mb-3"><i class="fas fa-comments text-blue-300/80 mr-1.5"></i>Chat with {{ $staff->name }}</h3>
            <div class="space-y-2 mb-3 max-h-80 overflow-y-auto" x-ref="log">
                <template x-for="(m, i) in messages" :key="i">
                    <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
                        <span class="inline-block px-3 py-2 rounded-xl text-sm max-w-[85%]"
                              :class="m.role === 'user' ? 'bg-blue-600 text-white' : 'bg-white/10 text-white/85'"
                              x-text="m.content"></span>
                    </div>
                </template>
                <p x-show="busy" class="text-xs text-white/40">Thinking…</p>
            </div>
            <form @submit.prevent="sendChat">
                <div class="flex gap-2">
                    <input type="text" x-model="draft" placeholder="Ask a question…" maxlength="2000"
                           class="flex-1 rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm">
                    <button type="submit" :disabled="busy || !draft.trim()" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">Send</button>
                </div>
            </form>
        </div>
    @endif

    @if($staff->domain === 'billing')
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 mb-6">
            <h3 class="text-white font-semibold mb-3"><i class="fas fa-file-invoice text-blue-300/80 mr-1.5"></i>Draft an invoice from a prompt</h3>
            <textarea x-model="invoicePrompt" rows="2" maxlength="2000" placeholder="e.g. Invoice Acme Co for 5 hours of consulting at $120/hr, due in 14 days"
                      class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm mb-3"></textarea>
            <button @click="draftInvoice" :disabled="busy || !invoicePrompt.trim()" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                <i class="fas fa-wand-magic-sparkles"></i> Draft invoice
            </button>
            <p x-show="invoiceError" x-text="invoiceError" class="text-xs text-red-300 mt-2"></p>

            <div class="mt-5 pt-4 border-t border-white/10 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-white/80">Chase unpaid invoices</h4>
                <button @click="generateChases" :disabled="busy" class="text-xs px-3 py-1.5 rounded-lg bg-white/10 text-white/80 hover:bg-white/20 disabled:opacity-50">
                    Scan for overdue invoices
                </button>
            </div>
        </div>
    @endif

    @if($staff->domain === 'contacts' && $contacts->isNotEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 mb-6">
            <h3 class="text-white font-semibold mb-3"><i class="fas fa-address-book text-blue-300/80 mr-1.5"></i>Contacts</h3>
            <select x-model="contactId" class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-white text-sm mb-3">
                <option value="">Pick a contact…</option>
                @foreach($contacts as $c)
                    <option value="{{ $c->id }}">{{ $c->display_name ?: trim(($c->given_name ?? '').' '.($c->family_name ?? '')) }}{{ $c->organization ? ', '.$c->organization : '' }}</option>
                @endforeach
            </select>
            <div class="flex gap-2 mb-3">
                <button @click="summarizeContact" :disabled="busy || !contactId" class="text-xs px-3 py-1.5 rounded-lg bg-white/10 text-white/80 hover:bg-white/20 disabled:opacity-50">Summarize + next steps</button>
                <button @click="draftFollowup" :disabled="busy || !contactId" class="text-xs px-3 py-1.5 rounded-lg bg-white/10 text-white/80 hover:bg-white/20 disabled:opacity-50">Draft follow-up</button>
            </div>
            <div x-show="contactSummary" class="text-sm text-white/80 bg-white/5 rounded-xl p-3 mb-2" x-text="contactSummary"></div>
            <ul x-show="contactSteps.length" class="text-sm text-white/70 list-disc list-inside space-y-1 mb-2">
                <template x-for="(s, i) in contactSteps" :key="i"><li x-text="s"></li></template>
            </ul>
            <div x-show="contactFollowup" class="text-sm text-white/80 bg-white/5 rounded-xl p-3" x-text="contactFollowup"></div>
        </div>
    @endif

    @if($suggestions->isNotEmpty())
        <h3 class="text-white font-semibold mb-3"><i class="fas fa-lightbulb text-amber-300/80 mr-1.5"></i>Suggestions</h3>
        <ul class="space-y-2">
            @foreach($suggestions as $sug)
                <li class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-white/85">{{ $sug->title }}</p>
                            @if($sug->kind === 'chase_invoice' && !empty($sug->payload['draft_message']))
                                <p class="text-xs text-white/50 mt-1 whitespace-pre-line">{{ $sug->payload['draft_message'] }}</p>
                            @endif
                            <p class="text-[11px] text-white/40 mt-1">{{ ucfirst($sug->status) }} &middot; {{ $sug->created_at->diffForHumans() }}</p>
                            @if($sug->message)
                                <p class="text-[11px] text-white/40">{{ $sug->message }}</p>
                            @endif
                        </div>
                        @if($sug->status === 'pending')
                            <div class="flex gap-2 shrink-0">
                                <button @click="applySuggestion({{ $sug->id }})" class="text-xs px-3 py-1.5 rounded-lg bg-green-600/80 text-white hover:bg-green-600">Confirm</button>
                                <button @click="dismissSuggestion({{ $sug->id }})" class="text-xs px-3 py-1.5 rounded-lg bg-white/10 text-white/70 hover:bg-white/20">Dismiss</button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<script>
function aiStaffShow(cfg) {
    return {
        messages: [],
        draft: '',
        busy: false,
        invoicePrompt: '',
        invoiceError: '',
        contactId: '',
        contactSummary: '',
        contactSteps: [],
        contactFollowup: '',
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        async postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify(body || {}),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error((data.error && data.error.message) || 'Something went wrong.');
            return data;
        },
        async sendChat() {
            const msg = this.draft.trim();
            if (!msg) return;
            this.messages.push({ role: 'user', content: msg });
            this.draft = '';
            this.busy = true;
            try {
                const data = await this.postJson(cfg.chatUrl, { message: msg, history: this.messages.slice(-10) });
                this.messages.push({ role: 'assistant', content: data.reply });
            } catch (e) {
                this.messages.push({ role: 'assistant', content: 'Error: ' + e.message });
            } finally {
                this.busy = false;
                this.$nextTick(() => { if (this.$refs.log) this.$refs.log.scrollTop = this.$refs.log.scrollHeight; });
            }
        },
        async draftInvoice() {
            this.busy = true; this.invoiceError = '';
            try {
                await this.postJson(cfg.draftInvoiceUrl, { prompt: this.invoicePrompt });
                this.invoicePrompt = '';
                window.location.reload();
            } catch (e) {
                this.invoiceError = e.message;
            } finally { this.busy = false; }
        },
        async generateChases() {
            this.busy = true;
            try {
                await this.postJson(cfg.chaseUrl, {});
                window.location.reload();
            } catch (e) {
                alert(e.message);
            } finally { this.busy = false; }
        },
        async summarizeContact() {
            this.busy = true;
            try {
                const data = await this.postJson('/user/ai/staff/' + cfg.staffId + '/contacts/' + this.contactId + '/summarize', {});
                this.contactSummary = data.summary; this.contactSteps = data.next_steps || [];
            } catch (e) { alert(e.message); } finally { this.busy = false; }
        },
        async draftFollowup() {
            this.busy = true;
            try {
                const data = await this.postJson('/user/ai/staff/' + cfg.staffId + '/contacts/' + this.contactId + '/draft-followup', {});
                this.contactFollowup = data.message;
            } catch (e) { alert(e.message); } finally { this.busy = false; }
        },
        async applySuggestion(id) {
            try {
                const data = await this.postJson(cfg.applyUrlBase + '/' + id + '/apply', {});
                alert(data.message || 'Applied.');
                window.location.reload();
            } catch (e) { alert(e.message); }
        },
        async dismissSuggestion(id) {
            try {
                await this.postJson(cfg.applyUrlBase + '/' + id + '/dismiss', {});
                window.location.reload();
            } catch (e) { alert(e.message); }
        },
    };
}
</script>
@endsection
