@canInWorkspace('links.edit')
@if(\App\Services\AI\AiEngineSettings::isEnabled())
<div class="mb-6 rounded-2xl border p-4 md:p-5" style="border-color:var(--border-soft);background:var(--bg-card)" x-data="aiBlockEditor()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3"><span class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(61,107,255,.1);color:var(--accent)"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i></span><div><h2 class="font-bold">Edit with AI</h2><p class="text-xs" style="color:var(--text-muted)">One request, multiple changes. Review before applying.</p></div></div>
        <button type="button" class="btn-primary text-sm" @click="open = !open" :aria-expanded="open" x-text="open ? 'Close editor' : 'Describe a change'"></button>
    </div>
    <div x-show="open" x-cloak class="mt-4 space-y-4">
        <div class="flex flex-wrap gap-3 text-sm">
            <label class="flex items-center gap-2"><input type="radio" value="page" x-model="scope" :disabled="!!busy" @change="changeScope()">Whole page</label>
            <label class="flex items-center gap-2"><input type="radio" value="selected" x-model="scope" :disabled="!!busy" @change="changeScope()">Selected blocks</label>
            <span x-show="scope === 'selected'" style="color:var(--text-muted)"><span x-text="selectedIds.length + ' blocks selected'"></span></span>
        </div>
        <div x-show="scope === 'selected'" class="rounded-xl border p-3" style="border-color:var(--border-soft)">
            <p class="text-sm font-semibold mb-2">Choose blocks to change</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-52 overflow-y-auto">
                <template x-for="block in blockChoices" :key="block.id"><label class="flex items-center gap-2 p-2 rounded-lg border text-sm" style="border-color:var(--border-soft)"><input type="checkbox" :checked="selectedIds.includes(block.id)" :disabled="!!busy" @change="toggleBlock(block.id, $event.target.checked)"><span x-text="block.label"></span></label></template>
            </div>
            <p class="text-xs mt-2" style="color:var(--text-muted)">Selections here also select the cards below. Choose at least one block.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="idea in ideas" :key="idea"><button type="button" class="px-3 py-1.5 rounded-lg border text-xs" style="border-color:var(--border-soft)" :disabled="!!busy" @click="prompt = idea; reset()" x-text="idea"></button></template>
        </div>
        <label class="block text-sm font-semibold" for="ai-block-prompt">What would you like to change?</label>
        <textarea id="ai-block-prompt" rows="3" maxlength="4000" x-model="prompt" :disabled="!!busy" @input="reset()" placeholder="Shorten my bio, add a contact button using my existing email, and move it above my links…" class="w-full p-3 rounded-xl border text-sm" style="background:var(--bg-glass-input);border-color:var(--border-soft);color:var(--text-primary)"></textarea>
        <details class="rounded-xl border p-3" style="border-color:var(--border-soft)">
            <summary class="cursor-pointer text-sm font-semibold">What can AI change?</summary>
            <div class="flex flex-wrap gap-4 mt-3 text-sm"><template x-for="area in areaChoices" :key="area.id"><label x-show="scope === 'page' || ['content','appearance'].includes(area.id)" class="flex items-center gap-2"><input type="checkbox" :value="area.id" x-model="areas" :disabled="!!busy" @change="reset()"><span x-text="area.label"></span></label></template></div>
            <p class="text-xs mt-2" style="color:var(--text-muted)">Includes background, fonts, button colours, spacing and search metadata. Integration settings and locked designs stay protected.</p>
        </details>
        <details class="rounded-xl border p-3" style="border-color:var(--border-soft)">
            <summary class="cursor-pointer text-sm font-semibold">Add references or media (optional)</summary>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                <label class="text-sm">Reference links <span class="text-xs">(up to 8, one per line)</span><textarea rows="3" x-model="references" @input="reset()" :disabled="!!busy" placeholder="https://example.com/about" class="w-full border rounded-lg p-2 mt-1" style="border-color:var(--border-soft);background:var(--bg-glass-input)"></textarea></label>
                <label class="text-sm">Image, video or file links <span class="text-xs">(up to 20, one per line)</span><textarea rows="3" x-model="mediaUrls" @input="reset()" :disabled="!!busy" placeholder="https://example.com/photo.jpg" class="w-full border rounded-lg p-2 mt-1" style="border-color:var(--border-soft);background:var(--bg-glass-input)"></textarea></label>
            </div>
            <label class="block text-sm mt-3">Upload images or files<input type="file" multiple :disabled="!!busy || uploading" @change="upload($event.target)" class="block mt-2 text-sm"></label>
            <p x-show="uploading" class="text-xs mt-2">Uploading…</p>
            <div class="flex flex-wrap gap-2 mt-2"><template x-for="file in files" :key="file.id"><button type="button" class="border rounded-lg px-2 py-1 text-xs" :disabled="!!busy || uploading" @click="files = files.filter(item => item.id !== file.id); reset()" x-text="(file.original_name || file.name) + ' ×'"></button></template></div>
            <p class="text-xs mt-2" style="color:var(--text-muted)">AI uses reference titles and descriptions. Uploaded files become media links; their contents are not read. Uploads use your existing file limits.</p>
        </details>
        <p x-show="error" x-text="error" role="alert" class="text-sm text-rose-600"></p>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="px-4 py-2 rounded-lg border text-sm" style="border-color:var(--border-soft)" :disabled="!!busy || uploading || prompt.trim().length < 3 || !effectiveAreas().length || (scope === 'selected' && !selectedIds.length)" @click="estimate()" x-text="busy === 'estimate' ? 'Estimating…' : 'Estimate coins'"></button>
            <span x-show="quote" class="text-sm" x-text="quote ? 'Up to ' + quote.estimated_coins + ' coins · Balance ' + quote.balance : ''"></span>
            <button type="button" x-show="quote && !draft" class="btn-primary text-sm" :disabled="!!busy || (quote && quote.balance < quote.estimated_coins)" @click="generate()" x-text="busy === 'generate' ? 'Preparing draft…' : 'Generate draft'"></button>
        </div>
        <template x-for="warning in quote?.warnings || []" :key="warning"><p class="text-xs" x-text="warning"></p></template>
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
            <div class="flex flex-wrap gap-3"><button type="button" class="btn-primary text-sm" :disabled="!!busy" @click="apply()" x-text="busy === 'apply' ? 'Applying…' : 'Apply changes'"></button><button type="button" class="px-4 py-2 rounded-lg border text-sm" style="border-color:var(--border-soft)" :disabled="!!busy" @click="reset()">Discard draft</button></div>
        </div>
    </div>
</div>
<script>
function aiBlockEditor() {
    return {
        open: false, scope: 'page', prompt: '', quote: null, draft: null, busy: '', error: '', references: '', mediaUrls: '', files: [], uploading: false, selectedIds: [], areas: ['content','appearance','layout','seo'],
        blockChoices: @js($link->biolinkBlocks()->orderBy('sort_order')->get()->map(fn($block) => ['id'=>$block->id,'label'=>(\App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] ?? $block->type).' #'.$block->id.' · '.\Illuminate\Support\Str::limit(strip_tags((string)($block->settings['title'] ?? $block->settings['text'] ?? $block->settings['name'] ?? '')),60)])->values()),
        areaChoices: [{id:'content',label:'Text & blocks'},{id:'appearance',label:'Colours & style'},{id:'layout',label:'Layout & spacing'},{id:'seo',label:'Search details (SEO)'}],
        init() { this.selectedIds = this.ids(); this.selectionListener = event => { if (event.target.matches('.block-select-box')) { this.selectedIds = this.ids(); this.reset(); } }; document.addEventListener('change', this.selectionListener); },
        destroy() { document.removeEventListener('change', this.selectionListener); },
        toggleBlock(id, checked) { const box = document.querySelector('.block-select-box[data-select-id="' + id + '"]'); if (box) { box.checked = checked; box.dispatchEvent(new Event('change', {bubbles:true})); } this.selectedIds = this.ids(); this.reset(); },
        effectiveAreas() { return this.areas.filter(area => this.scope === 'page' || ['content','appearance'].includes(area)); },
        changeScope() { if (!this.effectiveAreas().length) this.areas = ['content','appearance']; this.reset(); },
        lines(value) { return [...new Set(value.split(/\r?\n/).map(line => line.trim()).filter(Boolean))]; },
        signature() { return JSON.stringify([this.prompt,this.scope,this.scope === 'selected' ? this.ids() : [],this.references,this.mediaUrls,this.files.map(file=>file.id),this.effectiveAreas()]); },
        async upload(input) {
            this.uploading = true; this.reset();
            try { for (const file of input.files) { if (this.files.length >= 20) throw new Error('You can attach up to 20 files.'); const body = new FormData(); body.append('file',file); const response = await fetch(@js(route('user.files.upload')), {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':@js(csrf_token())},body}); const result = await response.json(); if (!response.ok || !result.file) throw new Error(result.message || 'Upload failed.'); this.files.push(result.file); } } catch(e) { this.error = e.message; } finally { this.uploading = false; input.value = ''; }
        },
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
            try { this.quotedIds = this.ids(); this.quotedSignature = this.signature(); this.quote = await this.request('estimate', { prompt: this.prompt, scope: this.scope, ids: this.quotedIds, references: this.lines(this.references), media_urls: this.lines(this.mediaUrls), file_ids: this.files.map(file=>file.id), areas: this.effectiveAreas() }); }
            catch (e) { this.error = e.message; } finally { this.busy = ''; }
        },
        async generate() {
            if (this.quotedSignature !== this.signature()) { this.reset(); this.error = 'Your selection changed. Estimate again first.'; return; }
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
