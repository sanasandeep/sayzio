@extends('admin.layouts.app')
@section('title', 'Site Assistant')
@section('page-title', 'Site Assistant')

@section('content')
<div class="max-w-5xl space-y-6">
    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 p-6 grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
        <div><div class="text-2xl font-semibold text-white">{{ number_format($totals['conversations']) }}</div><div class="text-xs text-white/50">Conversations</div></div>
        <div><div class="text-2xl font-semibold text-white">{{ number_format($totals['handoffs']) }}</div><div class="text-xs text-white/50">Handoffs</div></div>
        <div><div class="text-2xl font-semibold text-white">{{ number_format($totals['turns_month']) }}</div><div class="text-xs text-white/50">Turns this month</div></div>
        <div><div class="text-2xl font-semibold text-white">{{ number_format($monthly_spend) }}</div><div class="text-xs text-white/50">Credits this month</div></div>
        <div><div class="text-2xl font-semibold text-white">{{ number_format($totals['page_hints']) }} / {{ $totals['templates'] }}</div><div class="text-xs text-white/50">Hints / Templates</div></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.site-assistant.hints') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white">Page Hints</a>
        <a href="{{ route('admin.site-assistant.sources') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white">Knowledge Sources</a>
        <a href="{{ route('admin.site-assistant.templates') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white">Response Templates</a>
        <a href="{{ route('admin.site-assistant.conversations') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white">Conversations</a>
        <a href="{{ route('admin.site-assistant.analytics') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-white">Analytics</a>
    </div>

    <form method="POST" action="{{ route('admin.site-assistant.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <h3 class="font-semibold text-white">Surfaces</h3>
            <p class="text-xs text-white/40">Choose where the chat widget appears.</p>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="enabled_marketing" value="0">
                <input type="checkbox" name="enabled_marketing" value="1" class="rounded" {{ $cfg['enabled_marketing'] ? 'checked' : '' }}>
                <span class="text-sm text-white">Show on marketing pages (logged-out)</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="enabled_app" value="0">
                <input type="checkbox" name="enabled_app" value="1" class="rounded" {{ $cfg['enabled_app'] ? 'checked' : '' }}>
                <span class="text-sm text-white">Show on logged-in app pages</span>
            </label>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <h3 class="font-semibold text-white">Appearance</h3>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1">Position</label>
                    <select name="launcher_position" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                        <option value="bottom-right" @selected($cfg['launcher_position']==='bottom-right')>Bottom right</option>
                        <option value="bottom-left"  @selected($cfg['launcher_position']==='bottom-left')>Bottom left</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Accent color</label>
                    <input type="text" name="accent_color" value="{{ $cfg['accent_color'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white" placeholder="#7c3aed">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Avatar URL (optional)</label>
                    <input type="text" name="avatar_url" value="{{ $cfg['avatar_url'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white" placeholder="https://…">
                </div>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1">Greeting</label>
                <input type="text" name="greeting" value="{{ $cfg['greeting'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1">Starter prompts (one per line)</label>
                <textarea name="starter_prompts_text" rows="4" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white" placeholder="How does pricing work?">{{ implode("\n", (array)$cfg['starter_prompts']) }}</textarea>
                <script>
                  document.currentScript.previousElementSibling.addEventListener('change', function(){
                    var lines=this.value.split('\n').map(function(s){return s.trim();}).filter(Boolean);
                    var form=this.form;
                    [].slice.call(form.querySelectorAll('input[name^="starter_prompts["]')).forEach(function(n){n.remove();});
                    lines.forEach(function(l,i){ var i2=document.createElement('input'); i2.type='hidden'; i2.name='starter_prompts['+i+']'; i2.value=l; form.appendChild(i2); });
                  });
                  // Initial sync
                  var ta=document.currentScript.previousElementSibling;
                  ta.dispatchEvent(new Event('change'));
                </script>
            </div>

            <div class="pt-2 border-t border-white/10 space-y-3">
                <div>
                    <h4 class="text-sm font-semibold text-white">Per-language greeting & starter prompts</h4>
                    <p class="text-xs text-white/40">Visitors are matched to the closest language from their browser's <span class="font-mono text-white/60">Accept-Language</span> header (e.g. <span class="font-mono text-white/60">fr-CA</span> falls back to <span class="font-mono text-white/60">fr</span>). Any field left blank uses the default copy above. Use BCP-47 codes like <span class="font-mono text-white/60">fr</span>, <span class="font-mono text-white/60">es</span>, <span class="font-mono text-white/60">pt-BR</span>, <span class="font-mono text-white/60">zh-CN</span>.</p>
                </div>

                <div id="intro_locales" class="space-y-3"></div>

                <div class="flex items-center gap-3">
                    <button type="button" id="intro_locale_add" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-purple-500/15 border border-purple-500/35 text-purple-200">
                        + Add language
                    </button>
                    <span class="text-[11px] text-white/40">Up to 50 languages.</span>
                </div>

                <template id="intro_locale_row_tpl">
                    <div class="intro-locale-row rounded-xl p-4 bg-white/5 border border-white/10">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-xs text-white/60 flex-1 max-w-[240px]">Language code (BCP-47)
                                <input type="text" data-intro-locale-code value="" placeholder="fr or pt-BR"
                                    class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono"
                                    pattern="[A-Za-z]{2,3}([-_][A-Za-z]{2,4})?">
                            </label>
                            <button type="button" data-intro-locale-remove class="text-xs text-red-300 hover:text-red-200 px-2 py-1">Remove</button>
                        </div>
                        <div class="space-y-3">
                            <label class="block text-xs text-white/60">Greeting
                                <input type="text" maxlength="500" data-intro-greeting class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="block text-xs text-white/60">Starter prompts (one per line, up to 10)
                                <textarea rows="3" data-intro-prompts class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white"></textarea>
                            </label>
                        </div>
                    </div>
                </template>

                <script>
                (function () {
                    var host = document.getElementById('intro_locales');
                    var tpl  = document.getElementById('intro_locale_row_tpl');
                    var addBtn = document.getElementById('intro_locale_add');
                    var seededGreetings = @json((object)($cfg['greeting_locales'] ?? new \stdClass()));
                    var seededPrompts   = @json((object)($cfg['starter_prompts_locales'] ?? new \stdClass()));
                    var seq = 0;

                    function bucketName(row) {
                        var code = (row.querySelector('[data-intro-locale-code]').value || '').trim();
                        return code === '' ? '__pending_' + (row.dataset.rowId || '0') : code;
                    }

                    function syncPromptInputs(row) {
                        var bucket = bucketName(row);
                        // Drop any prior hidden inputs we generated for this row
                        row.querySelectorAll('input[data-intro-prompt-hidden]').forEach(function (n) { n.remove(); });
                        var ta = row.querySelector('[data-intro-prompts]');
                        var lines = (ta.value || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
                        lines.forEach(function (line, i) {
                            var h = document.createElement('input');
                            h.type = 'hidden';
                            h.setAttribute('data-intro-prompt-hidden', '');
                            h.name = 'starter_prompts_locales[' + bucket + '][' + i + ']';
                            h.value = line;
                            row.appendChild(h);
                        });
                    }

                    function rewire(row) {
                        var bucket = bucketName(row);
                        var g = row.querySelector('[data-intro-greeting]');
                        g.name = 'greeting_locales[' + bucket + ']';
                        syncPromptInputs(row);
                    }

                    function addRow(code, greeting, prompts) {
                        if (host.querySelectorAll('.intro-locale-row').length >= 50) return;
                        var node = tpl.content.firstElementChild.cloneNode(true);
                        node.dataset.rowId = String(++seq);
                        var codeInput = node.querySelector('[data-intro-locale-code]');
                        var greetInput = node.querySelector('[data-intro-greeting]');
                        var promptsTa = node.querySelector('[data-intro-prompts]');
                        codeInput.value = code || '';
                        greetInput.value = greeting || '';
                        promptsTa.value = (prompts && prompts.length) ? prompts.join('\n') : '';
                        node.querySelector('[data-intro-locale-remove]').addEventListener('click', function () { node.remove(); });
                        codeInput.addEventListener('input', function () { rewire(node); });
                        promptsTa.addEventListener('input', function () { syncPromptInputs(node); });
                        host.appendChild(node);
                        rewire(node);
                    }

                    if (addBtn) addBtn.addEventListener('click', function () { addRow('', '', null); });

                    var codes = {};
                    if (seededGreetings && typeof seededGreetings === 'object' && !Array.isArray(seededGreetings)) {
                        Object.keys(seededGreetings).forEach(function (c) { codes[c] = true; });
                    }
                    if (seededPrompts && typeof seededPrompts === 'object' && !Array.isArray(seededPrompts)) {
                        Object.keys(seededPrompts).forEach(function (c) { codes[c] = true; });
                    }
                    Object.keys(codes).sort().forEach(function (code) {
                        addRow(
                            code,
                            (seededGreetings && seededGreetings[code]) || '',
                            (seededPrompts && seededPrompts[code]) || null
                        );
                    });
                })();
                </script>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <h3 class="font-semibold text-white">Behavior</h3>
            <div>
                <label class="block text-xs text-white/60 mb-1">System prompt</label>
                <textarea name="system_prompt" rows="8" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono">{{ $cfg['system_prompt'] }}</textarea>
                <p class="text-xs text-white/40 mt-1">The model is told it may reply either as plain prose or with a JSON envelope <code>{"text":"…","blocks":[…]}</code> for rich blocks.</p>
            </div>

            <div class="pt-2 border-t border-white/10 space-y-3">
                <div>
                    <h4 class="text-sm font-semibold text-white">Per-language system prompt</h4>
                    <p class="text-xs text-white/40">Visitors are matched to the closest language from their browser's <span class="font-mono text-white/60">Accept-Language</span> header (e.g. <span class="font-mono text-white/60">fr-CA</span> falls back to <span class="font-mono text-white/60">fr</span>). Any language left blank uses the default English prompt above. Use BCP-47 codes like <span class="font-mono text-white/60">fr</span>, <span class="font-mono text-white/60">es</span>, <span class="font-mono text-white/60">pt-BR</span>, <span class="font-mono text-white/60">zh-CN</span>.</p>
                </div>

                <div id="sp_locales" class="space-y-3"></div>

                <div class="flex items-center gap-3">
                    <button type="button" id="sp_locale_add" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-purple-500/15 border border-purple-500/35 text-purple-200">
                        + Add language
                    </button>
                    <span class="text-[11px] text-white/40">Up to 50 languages.</span>
                </div>

                <template id="sp_locale_row_tpl">
                    <div class="sp-locale-row rounded-xl p-4 bg-white/5 border border-white/10">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-xs text-white/60 flex-1 max-w-[240px]">Language code (BCP-47)
                                <input type="text" data-sp-locale-code value="" placeholder="fr or pt-BR"
                                    class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono"
                                    pattern="[A-Za-z]{2,3}([-_][A-Za-z]{2,4})?">
                            </label>
                            <button type="button" data-sp-locale-remove class="text-xs text-red-300 hover:text-red-200 px-2 py-1">Remove</button>
                        </div>
                        <label class="block text-xs text-white/60">System prompt
                            <textarea rows="6" maxlength="8000" data-sp-prompt class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono"></textarea>
                        </label>
                    </div>
                </template>

                <script>
                (function () {
                    var host = document.getElementById('sp_locales');
                    var tpl  = document.getElementById('sp_locale_row_tpl');
                    var addBtn = document.getElementById('sp_locale_add');
                    var seeded = @json((object)($cfg['system_prompt_locales'] ?? new \stdClass()));
                    var seq = 0;

                    function bucketName(row) {
                        var code = (row.querySelector('[data-sp-locale-code]').value || '').trim();
                        return code === '' ? '__pending_' + (row.dataset.rowId || '0') : code;
                    }

                    function rewire(row) {
                        var bucket = bucketName(row);
                        row.querySelector('[data-sp-prompt]').name = 'system_prompt_locales[' + bucket + ']';
                    }

                    function addRow(code, prompt) {
                        if (host.querySelectorAll('.sp-locale-row').length >= 50) return;
                        var node = tpl.content.firstElementChild.cloneNode(true);
                        node.dataset.rowId = String(++seq);
                        var codeInput = node.querySelector('[data-sp-locale-code]');
                        var promptTa  = node.querySelector('[data-sp-prompt]');
                        codeInput.value = code || '';
                        promptTa.value = prompt || '';
                        node.querySelector('[data-sp-locale-remove]').addEventListener('click', function () { node.remove(); });
                        codeInput.addEventListener('input', function () { rewire(node); });
                        host.appendChild(node);
                        rewire(node);
                    }

                    if (addBtn) addBtn.addEventListener('click', function () { addRow('', ''); });

                    if (seeded && typeof seeded === 'object' && !Array.isArray(seeded)) {
                        Object.keys(seeded).sort().forEach(function (code) {
                            addRow(code, seeded[code] || '');
                        });
                    }
                })();
                </script>
            </div>
            <div class="grid md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1">Temperature</label>
                    <input type="number" step="0.05" min="0" max="2" name="temperature" value="{{ $cfg['temperature'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Max tokens</label>
                    <input type="number" min="64" max="4000" name="max_tokens" value="{{ $cfg['max_tokens'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Per-session msgs/min</label>
                    <input type="number" min="1" max="120" name="session_rate_per_minute" value="{{ $cfg['session_rate_per_minute'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Monthly budget (credits, 0 = unlimited)</label>
                    <input type="number" min="0" name="monthly_budget_credits" value="{{ $cfg['monthly_budget_credits'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Chat model</label>
                    <select name="model" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                        <option value="">Default (companion mapping)</option>
                        @foreach($chatModels as $m)
                            <option value="{{ $m }}" {{ ($cfg['model'] ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs text-white/60 mb-1">Knowledge Bases (platform Minds)</label>
                    @if($platformMinds->isEmpty())
                        <p class="text-xs text-white/40">No platform Minds yet. <a class="text-purple-300 underline" href="{{ route('admin.site-assistant.knowledge') }}">Manage knowledge bases →</a></p>
                    @else
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @php $picked = array_map('intval', (array)($cfg['mind_ids'] ?? [])); @endphp
                            @foreach($platformMinds as $m)
                                <label class="flex items-center gap-2 text-sm text-white/80 bg-black/20 rounded-lg px-3 py-2 border border-white/10">
                                    <input type="checkbox" name="mind_ids[]" value="{{ $m->id }}" {{ in_array((int)$m->id, $picked, true) ? 'checked' : '' }}>
                                    <span>{{ $m->name }}@if($m->is_default) <em class="text-white/40">(default)</em>@endif</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-white/40 mt-1">Leave all unchecked to use the platform-default Mind only. <a class="text-purple-300 underline" href="{{ route('admin.site-assistant.knowledge') }}">Manage knowledge bases →</a></p>
                    @endif
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs text-white/60 mb-1">Billing account for anonymous visitors</label>
                    <select name="billing_user_id" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                        <option value="">Auto (first platform admin)</option>
                        @foreach($billingCandidates as $u)
                            <option value="{{ $u->id }}" {{ (int)($cfg['billing_user_id'] ?? 0) === (int)$u->id ? 'selected' : '' }}>
                                {{ $u->name }} &lt;{{ $u->email }}&gt;
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-white/40 mt-1">Signed-in visitors are always billed to their own account. Anonymous marketing visitors are billed to this user.</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <h3 class="font-semibold text-white">Low-balance warning</h3>
            <p class="text-xs text-white/40">Shown to visitors before they send a message when their AI credit balance is close to running out. The runtime estimates an average reply cost from recent history; the fallback below is used when there's no history yet.</p>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1">Trigger threshold (× average reply)</label>
                    <input type="number" min="1" max="50" name="low_balance_multiplier" value="{{ $cfg['low_balance_multiplier'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    <p class="text-xs text-white/40 mt-1">Warn when balance is below this many average replies. Default 3.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Fallback average reply (credits)</label>
                    <input type="number" min="1" max="100000" name="low_balance_default_credits" value="{{ $cfg['low_balance_default_credits'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    <p class="text-xs text-white/40 mt-1">Used until the visitor has assistant replies on record.</p>
                </div>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1">Signed-in message</label>
                <input type="text" maxlength="500" name="low_balance_message_signed_in" value="{{ $cfg['low_balance_message_signed_in'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                <p class="text-xs text-white/40 mt-1">Use <code>{remaining}</code> for replies left, <code>{avg}</code> for average reply cost, <code>{balance}</code> for raw credits.</p>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1">Anonymous visitor message</label>
                <input type="text" maxlength="500" name="low_balance_message_anonymous" value="{{ $cfg['low_balance_message_anonymous'] }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                <p class="text-xs text-white/40 mt-1">No numbers are leaked to anonymous visitors — keep this generic.</p>
            </div>

            <div class="pt-2 border-t border-white/10 space-y-3">
                <div>
                    <h4 class="text-sm font-semibold text-white">Per-language translations</h4>
                    <p class="text-xs text-white/40">Visitors are matched to the closest language from their browser's <span class="font-mono text-white/60">Accept-Language</span> header (e.g. <span class="font-mono text-white/60">fr-CA</span> falls back to <span class="font-mono text-white/60">fr</span>). Any field left blank uses the default copy above. Use BCP-47 codes like <span class="font-mono text-white/60">fr</span>, <span class="font-mono text-white/60">es</span>, <span class="font-mono text-white/60">pt-BR</span>, <span class="font-mono text-white/60">zh-CN</span>.</p>
                </div>

                <div id="lb_locales" class="space-y-3"></div>

                <div class="flex items-center gap-3">
                    <button type="button" id="lb_locale_add" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-purple-500/15 border border-purple-500/35 text-purple-200">
                        + Add language
                    </button>
                    <span class="text-[11px] text-white/40">Up to 50 languages.</span>
                </div>

                <template id="lb_locale_row_tpl">
                    <div class="lb-locale-row rounded-xl p-4 bg-white/5 border border-white/10">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-xs text-white/60 flex-1 max-w-[240px]">Language code (BCP-47)
                                <input type="text" data-lb-locale-code value="" placeholder="fr or pt-BR"
                                    class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono"
                                    pattern="[A-Za-z]{2,3}([-_][A-Za-z]{2,4})?">
                            </label>
                            <button type="button" data-lb-locale-remove class="text-xs text-red-300 hover:text-red-200 px-2 py-1">Remove</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="block text-xs text-white/60">Signed-in message
                                <input type="text" maxlength="500" data-lb-loc="signed_in" class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="block text-xs text-white/60">Anonymous visitor message
                                <input type="text" maxlength="500" data-lb-loc="anonymous" class="mt-1 w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                        </div>
                    </div>
                </template>

                <script>
                (function () {
                    var host = document.getElementById('lb_locales');
                    var tpl  = document.getElementById('lb_locale_row_tpl');
                    var addBtn = document.getElementById('lb_locale_add');
                    var seeded = @json((object)($cfg['low_balance_message_locales'] ?? new \stdClass()));
                    var KEYS = ['signed_in','anonymous'];
                    var seq = 0;

                    function rewire(row) {
                        var codeInput = row.querySelector('[data-lb-locale-code]');
                        var raw = (codeInput.value || '').trim();
                        var bucket = raw === '' ? '__pending_' + (row.dataset.rowId || '0') : raw;
                        row.querySelectorAll('[data-lb-loc]').forEach(function (el) {
                            var k = el.getAttribute('data-lb-loc');
                            el.name = 'low_balance_message_locales[' + bucket + '][' + k + ']';
                        });
                    }

                    function addRow(code, values) {
                        if (host.querySelectorAll('.lb-locale-row').length >= 50) return;
                        var node = tpl.content.firstElementChild.cloneNode(true);
                        node.dataset.rowId = String(++seq);
                        var codeInput = node.querySelector('[data-lb-locale-code]');
                        codeInput.value = code || '';
                        if (values && typeof values === 'object') {
                            KEYS.forEach(function (k) {
                                var f = node.querySelector('[data-lb-loc="' + k + '"]');
                                if (f && values[k] != null) f.value = values[k];
                            });
                        }
                        node.querySelector('[data-lb-locale-remove]').addEventListener('click', function () { node.remove(); });
                        codeInput.addEventListener('input', function () { rewire(node); });
                        host.appendChild(node);
                        rewire(node);
                    }

                    if (addBtn) addBtn.addEventListener('click', function () { addRow('', null); });

                    if (seeded && typeof seeded === 'object' && !Array.isArray(seeded)) {
                        Object.keys(seeded).forEach(function (code) { addRow(code, seeded[code]); });
                    }
                })();
                </script>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
            <h3 class="font-semibold text-white">Customer Care Handoff</h3>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="handoff_enabled" value="0">
                <input type="checkbox" name="handoff_enabled" value="1" class="rounded" {{ $cfg['handoff_enabled'] ? 'checked' : '' }}>
                <span class="text-sm text-white">Allow visitors to escalate the chat into the Contact Inbox</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="handoff_freeze_after" value="0">
                <input type="checkbox" name="handoff_freeze_after" value="1" class="rounded" {{ $cfg['handoff_freeze_after'] ? 'checked' : '' }}>
                <span class="text-sm text-white">Freeze the bot after handoff (recommended)</span>
            </label>
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
            <div>
                <h3 class="font-semibold text-white">Cut-off retry alerts</h3>
                <p class="text-xs text-white/50 mt-1">A scheduled check looks at the last 24h of cut-off / failed assistant streams and notifies admins (in-app + email) when the abandon rate — the share of cut-offs visitors never clicked Retry on — exceeds the threshold below. Useful for catching upstream regressions before users complain.</p>
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="cutoff_alert_enabled" value="0">
                <input type="checkbox" name="cutoff_alert_enabled" value="1" class="rounded" {{ !empty($cfg['cutoff_alert_enabled']) ? 'checked' : '' }}>
                <span class="text-sm text-white">Enable cut-off abandon-rate alerts</span>
            </label>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1">Abandon-rate threshold (%)</label>
                    <input type="number" min="1" max="100" name="cutoff_alert_abandon_threshold" value="{{ (int)($cfg['cutoff_alert_abandon_threshold'] ?? 60) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    <p class="text-[10px] text-white/40 mt-1">Alert fires when the 24h abandon rate is at or above this value.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Minimum sample size</label>
                    <input type="number" min="1" max="100000" name="cutoff_alert_min_sample" value="{{ (int)($cfg['cutoff_alert_min_sample'] ?? 20) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    <p class="text-[10px] text-white/40 mt-1">Skip the check until at least this many cut-offs occurred in 24h.</p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Cooldown between alerts (hours)</label>
                    <input type="number" min="1" max="168" name="cutoff_alert_cooldown_hours" value="{{ (int)($cfg['cutoff_alert_cooldown_hours'] ?? 6) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    <p class="text-[10px] text-white/40 mt-1">Suppress repeat alerts inside this window.</p>
                </div>
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1">Email recipients (optional)</label>
                <input type="text" name="cutoff_alert_emails" value="{{ $cfg['cutoff_alert_emails'] ?? '' }}" placeholder="ops@example.com, oncall@example.com" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                <p class="text-[10px] text-white/40 mt-1">Comma- or space-separated. Leave blank to email every platform admin (settings.manage) with a verified email instead.</p>
            </div>
            @if(!empty($cfg['cutoff_alert_last_sent_at']))
                <p class="text-[11px] text-white/40">Last alert dispatched: <span class="text-white/70">{{ $cfg['cutoff_alert_last_sent_at'] }}</span></p>
            @endif
        </div>

        <div class="flex justify-end">
            <button class="px-5 py-2.5 rounded-xl bg-purple-500 hover:bg-purple-400 text-white text-sm font-semibold">Save settings</button>
        </div>
    </form>
</div>
@endsection
