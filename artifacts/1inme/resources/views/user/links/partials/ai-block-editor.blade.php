@canInWorkspace('links.edit')
@if(\App\Services\AI\AiEngineSettings::isEnabled())
<div class="mb-6 rounded-2xl border p-4 md:p-5" style="border-color:var(--border-soft);background:var(--bg-card)" x-data="aiBlockEditor()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3"><span class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(61,107,255,.1);color:var(--accent)"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i></span><div><h2 class="font-bold">Edit with AI</h2><p class="text-xs" style="color:var(--text-muted)">One request, multiple changes. Review before applying.</p></div></div>
        <button type="button" class="btn-primary text-sm" @click="open = !open" :aria-expanded="open" x-text="open ? 'Close editor' : 'Describe a change'"></button>
    </div>
    <div x-show="open" x-cloak class="mt-4 space-y-4">
        <div class="flex flex-wrap gap-3 text-sm">
            <label class="flex items-center gap-2"><input type="radio" value="page" x-model="scope" :disabled="busy" @change="reset()">Whole page</label>
            <label class="flex items-center gap-2"><input type="radio" value="selected" x-model="scope" :disabled="busy" @change="reset()">Selected blocks</label>
            <span x-show="scope === 'selected'" style="color:var(--text-muted)">Tick the blocks below before estimating.</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="idea in ideas" :key="idea"><button type="button" class="px-3 py-1.5 rounded-lg border text-xs" style="border-color:var(--border-soft)" :disabled="busy" @click="prompt = idea; reset()" x-text="idea"></button></template>
        </div>
        <label class="block text-sm font-semibold" for="ai-block-prompt">What would you like to change?</label>
        <textarea id="ai-block-prompt" rows="3" maxlength="4000" x-model="prompt" :disabled="busy" @input="reset()" placeholder="Shorten my bio, add a contact button using my existing email, and move it above my links…" class="w-full p-3 rounded-xl border text-sm" style="background:var(--bg-glass-input);border-color:var(--border-soft);color:var(--text-primary)"></textarea>
        <p class="text-xs" style="color:var(--text-muted)">Edit text, links, profiles, block colours and styling; add, remove, reorder or rebuild content. Page title and theme colour can also change. Include exact facts and URLs. Integration blocks and locked designs keep their existing settings.</p>
        <p x-show="error" x-text="error" role="alert" class="text-sm text-rose-600"></p>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="px-4 py-2 rounded-lg border text-sm" style="border-color:var(--border-soft)" :disabled="busy || prompt.trim().length < 3" @click="estimate()" x-text="busy === 'estimate' ? 'Estimating…' : 'Estimate coins'"></button>
            <span x-show="quote" class="text-sm" x-text="quote ? 'Up to ' + quote.estimated_coins + ' coins · Balance ' + quote.balance : ''"></span>
            <button type="button" x-show="quote && !draft" class="btn-primary text-sm" :disabled="busy || (quote && quote.balance < quote.estimated_coins)" @click="generate()" x-text="busy === 'generate' ? 'Preparing draft…' : 'Generate draft'"></button>
        </div>
        <p x-show="quote && !draft" class="text-xs" style="color:var(--text-muted)">Generation uses coins. Applying the draft is free. Discarding a usable draft does not refund generation.</p>
        <div x-show="draft" x-cloak class="rounded-xl border p-4 space-y-4" style="border-color:var(--border-soft)">
            <div><h3 class="font-semibold">Your proposed changes</h3><p class="text-sm mt-1" x-text="draft?.summary"></p><p class="text-xs mt-2" style="color:var(--text-muted)" x-text="draft ? draft.coins_spent + ' coins used · Balance ' + draft.balance : ''"></p></div>
            <template x-for="(change, idx) in draft?.changes.updates || []" :key="change.id">
                <details class="p-3 rounded-lg border" style="border-color:var(--border-soft)"><summary class="cursor-pointer text-sm font-semibold" x-text="'Update ' + change.type + ' #' + change.id + (change.is_active ? '' : ' · Hidden')"></summary><div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3"><div><span class="text-xs font-semibold">Before</span><pre class="text-xs whitespace-pre-wrap break-words mt-2" x-text="readable(change.before)"></pre></div><div><span class="text-xs font-semibold">After</span><pre class="text-xs whitespace-pre-wrap break-words mt-2" x-text="readable(change.after)"></pre></div></div></details>
            </template>
            <template x-for="(block, idx) in draft?.changes.add || []" :key="idx"><div class="p-3 rounded-lg border" style="border-color:var(--border-soft)"><p class="text-sm font-semibold" x-text="'Add ' + block.type"></p><pre class="text-xs whitespace-pre-wrap break-words mt-2" x-text="readable(block.settings)"></pre></div></template>
            <p class="text-sm text-rose-600" x-show="draft?.changes.delete_ids.length" x-text="draft ? 'Remove blocks: ' + draft.changes.delete_ids.join(', ') : ''"></p>
            <p class="text-sm" x-show="draft?.changes.order.length" x-text="draft ? 'New block order: ' + draft.changes.order.join(' → ') : ''"></p>
            <pre class="text-xs whitespace-pre-wrap" x-show="draft && Object.keys(draft.changes.page).length" x-text="draft ? 'Page details: ' + readable(draft.changes.page) : ''"></pre>
            <div class="flex flex-wrap gap-3"><button type="button" class="btn-primary text-sm" :disabled="busy" @click="apply()" x-text="busy === 'apply' ? 'Applying…' : 'Apply changes'"></button><button type="button" class="px-4 py-2 rounded-lg border text-sm" style="border-color:var(--border-soft)" :disabled="busy" @click="reset()">Discard draft</button></div>
        </div>
    </div>
</div>
<script>
function aiBlockEditor() {
    return {
        open: false, scope: 'page', prompt: '', quote: null, draft: null, busy: '', error: '',
        ideas: ['Make my text clearer and shorter', 'Improve my page structure', 'Give my blocks a fresh, consistent style', 'Refresh my page title and theme colour', 'Rebuild the supported content using my existing details'],
        reset() { this.quote = null; this.draft = null; this.error = ''; },
        ids() { return Array.from(document.querySelectorAll('.block-select-box:checked')).map(el => Number(el.dataset.selectId)); },
        readable(settings) { return JSON.stringify(Object.fromEntries(Object.entries(settings).filter(([key]) => !key.startsWith('_') || key === '_style')), null, 2); },
        async request(action, body) {
            const urls = @js(['estimate'=>route('user.links.ai-edit.estimate',$link),'generate'=>route('user.links.ai-edit.generate',$link),'apply'=>route('user.links.ai-edit.apply',$link)]);
            const response = await fetch(urls[action], { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify(body) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'The request failed. Please try again.');
            return data;
        },
        async estimate() {
            this.busy = 'estimate'; this.error = ''; this.quote = null; this.draft = null;
            try { this.quotedIds = this.ids(); this.quotedPrompt = this.prompt; this.quotedScope = this.scope; this.quote = await this.request('estimate', { prompt: this.prompt, scope: this.scope, ids: this.quotedIds }); }
            catch (e) { this.error = e.message; } finally { this.busy = ''; }
        },
        async generate() {
            if (this.quotedPrompt !== this.prompt || this.quotedScope !== this.scope || (this.scope === 'selected' && JSON.stringify(this.quotedIds) !== JSON.stringify(this.ids()))) { this.reset(); this.error = 'Your selection changed. Estimate again first.'; return; }
            this.busy = 'generate'; this.error = '';
            try { this.draft = await this.request('generate', { token: this.quote.token }); }
            catch (e) { this.error = e.message; } finally { this.busy = ''; }
        },
        async apply() {
            this.busy = 'apply'; this.error = '';
            try { const result = await this.request('apply', { token: this.draft.token }); window.location.assign(result.redirect); }
            catch (e) { this.error = e.message; } finally { this.busy = ''; }
        },
    };
}
</script>
@endif
@endcanInWorkspace
