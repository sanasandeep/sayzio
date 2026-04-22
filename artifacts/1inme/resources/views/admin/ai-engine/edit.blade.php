@extends('admin.layouts.app')
@section('title', 'AI Engine')
@section('page-title', 'AI Engine')

@section('content')
<form method="POST" action="{{ route('admin.ai-engine.update') }}" class="max-w-5xl space-y-6">
    @csrf @method('PUT')

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Master toggle + OpenAI key --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
        <div>
            <h3 class="font-semibold text-white">Engine</h3>
            <p class="text-xs text-white/40">Master switch for the entire AI subsystem and API credentials.</p>
        </div>

        <label class="flex items-center gap-3 cursor-pointer">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}
                   class="w-4 h-4 accent-violet-500">
            <span class="text-sm text-white">Enable AI Engine</span>
        </label>

        <div>
            <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">OpenAI API key</label>
            @if($hasKey)
                <p class="text-xs text-white/60 mb-2">Stored: <span class="font-mono text-amber-300">{{ $maskedKey }}</span></p>
            @endif
            <input type="password" name="openai_api_key" autocomplete="off"
                   placeholder="{{ $hasKey ? 'Paste a new key to replace' : 'sk-…' }}"
                   class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
            @if($hasKey)
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-white/60">
                    <input type="hidden" name="clear_openai_api_key" value="0">
                    <input type="checkbox" name="clear_openai_api_key" value="1" class="accent-red-500">
                    Remove the stored key
                </label>
            @endif
            <p class="text-[11px] text-white/30 mt-1">Encrypted at rest with the application key. Never displayed back.</p>
        </div>
    </div>

    {{-- Models with per-1k credit rates --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-white">Models &amp; rates</h3>
                <p class="text-xs text-white/40">Credits charged per 1 000 tokens. Set both input and output rates.</p>
            </div>
            <button type="button" onclick="addModelRow()"
                    class="px-3 py-1.5 bg-violet-600 text-white rounded-lg text-xs font-medium hover:bg-violet-700">
                <i class="fas fa-plus mr-1"></i> Add model
            </button>
        </div>

        @php
            $modelToFeatures = [];
            foreach ($featureModels as $feat => $modelName) {
                $modelToFeatures[$modelName][] = $feat;
            }
        @endphp

        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">Model</th>
                <th class="text-left">Used by</th>
                <th class="text-left">Kind</th>
                <th class="text-left">Enabled</th>
                <th class="text-right">In / 1k</th>
                <th class="text-right">Out / 1k</th>
                <th></th>
            </tr></thead>
            <tbody id="models-tbody">
            @foreach($models as $i => $m)
                @php $usedBy = $modelToFeatures[$m['name']] ?? []; @endphp
                <tr class="border-t border-white/5 align-top" data-model-row data-features="{{ implode(',', $usedBy) }}">
                    <td class="py-2"><input name="models[{{ $i }}][name]" value="{{ $m['name'] }}" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
                    <td class="py-2">
                        @if($usedBy)
                            <div class="flex flex-wrap gap-1">
                                @foreach($usedBy as $feat)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-violet-500/15 border border-violet-500/30 text-violet-200 text-[10px] font-mono uppercase tracking-wider">{{ $feat }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-white/30 text-xs">—</span>
                        @endif
                    </td>
                    <td><select name="models[{{ $i }}][kind]" class="bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm">
                        <option value="chat"      {{ $m['kind']==='chat' ? 'selected':'' }}>chat</option>
                        <option value="embedding" {{ $m['kind']==='embedding' ? 'selected':'' }}>embedding</option>
                    </select></td>
                    <td>
                        <input type="hidden" name="models[{{ $i }}][enabled]" value="0">
                        <input type="checkbox" data-enabled-toggle name="models[{{ $i }}][enabled]" value="1" {{ $m['enabled'] ? 'checked':'' }} class="accent-violet-500">
                        <p data-disable-warning class="hidden mt-1 text-[11px] text-amber-300 flex items-start gap-1">
                            <i class="fas fa-triangle-exclamation mt-0.5"></i>
                            <span data-disable-warning-text></span>
                        </p>
                    </td>
                    <td class="text-right"><input type="number" min="0" name="models[{{ $i }}][in_credits_per_1k]" value="{{ $m['in_credits_per_1k'] }}" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><input type="number" min="0" name="models[{{ $i }}][out_credits_per_1k]" value="{{ $m['out_credits_per_1k'] }}" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Per-feature model picker --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-4">
        <div>
            <h3 class="font-semibold text-white">Feature models</h3>
            <p class="text-xs text-white/40">
                Choose which chat model each AI feature uses. Falls back to
                <span class="font-mono text-amber-300">{{ $defaultFeatureModel }}</span> if unset.
            </p>
        </div>

        @php
            $chatModelNames = array_values(array_map(fn($m) => $m['name'],
                array_filter($models, fn($m) => ($m['kind'] ?? 'chat') === 'chat')));
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($features as $f)
                @php
                    $current = $featureModels[$f] ?? $defaultFeatureModel;
                    $status  = $featureStatus[$f];
                    $inList  = in_array($current, $chatModelNames, true);
                @endphp
                <div class="space-y-1.5">
                    <label class="text-xs uppercase tracking-wider text-white/40 block">{{ ucfirst($f) }}</label>
                    <select name="feature_models[{{ $f }}]"
                            data-feature-select="{{ $f }}"
                            class="w-full bg-white/5 border border-white/10 rounded-lg px-2 py-2 text-white text-sm">
                        @foreach($chatModelNames as $name)
                            <option value="{{ $name }}" {{ $name === $current ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                        @if(!$inList)
                            <option value="{{ $current }}" selected>{{ $current }} (not in models table)</option>
                        @endif
                    </select>
                    @if(!$status['ok'])
                        <p class="text-[11px] text-red-300 flex items-start gap-1.5">
                            <i class="fas fa-triangle-exclamation mt-0.5"></i>
                            <span>{{ $status['message'] }}</span>
                        </p>
                    @else
                        <p class="text-[11px] text-white/30">Spend tagged <span class="font-mono">{{ $f }}</span>.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Wallet -> credits conversion --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <h3 class="font-semibold text-white">Wallet → AI credits</h3>
        <p class="text-xs text-white/40">Conversion rate when users exchange wallet coins for AI credits.</p>
        <div class="flex items-center gap-2 text-sm text-white">
            <span>1 wallet coin =</span>
            <input type="number" min="1" name="wallet_to_credits_rate" value="{{ $walletRate }}"
                   class="w-24 bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm">
            <span>AI credits</span>
        </div>
    </div>

    {{-- Credit packs --}}
    <div class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-white">Credit packs</h3>
                <p class="text-xs text-white/40">Bundles users can buy from their dashboard with wallet coins.</p>
            </div>
            <button type="button" onclick="addPackRow()"
                    class="px-3 py-1.5 bg-violet-600 text-white rounded-lg text-xs font-medium hover:bg-violet-700">
                <i class="fas fa-plus mr-1"></i> Add pack
            </button>
        </div>
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">ID (slug)</th>
                <th class="text-left">Label</th>
                <th class="text-right">Credits</th>
                <th class="text-right">Wallet cost (coins)</th>
                <th></th>
            </tr></thead>
            <tbody id="packs-tbody">
            @foreach($packs as $i => $p)
                <tr class="border-t border-white/5">
                    <td class="py-2"><input name="packs[{{ $i }}][id]"  value="{{ $p['id'] }}" pattern="[a-z0-9_-]+" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm font-mono" required></td>
                    <td><input name="packs[{{ $i }}][label]" value="{{ $p['label'] }}" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
                    <td class="text-right"><input type="number" min="1" name="packs[{{ $i }}][credits]" value="{{ $p['credits'] }}" class="w-28 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><input type="number" min="1" name="packs[{{ $i }}][wallet_cost]" value="{{ $p['wallet_cost'] }}" class="w-28 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('admin.ai-usage.index') }}" class="text-xs text-violet-300 hover:underline">
            View AI usage report →
        </a>
        <button type="submit" class="px-5 py-2.5 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700">
            Save settings
        </button>
    </div>
</form>

<script>
function addModelRow() {
    const tb = document.getElementById('models-tbody');
    const i = tb.children.length;
    const row = document.createElement('tr');
    row.className = 'border-t border-white/5 align-top';
    row.setAttribute('data-model-row', '');
    row.setAttribute('data-features', '');
    row.innerHTML = `
        <td class="py-2"><input name="models[${i}][name]" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
        <td class="py-2"><span class="text-white/30 text-xs">—</span></td>
        <td><select name="models[${i}][kind]" class="bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"><option value="chat">chat</option><option value="embedding">embedding</option></select></td>
        <td><input type="hidden" name="models[${i}][enabled]" value="0"><input type="checkbox" data-enabled-toggle name="models[${i}][enabled]" value="1" checked class="accent-violet-500"><p data-disable-warning class="hidden mt-1 text-[11px] text-amber-300 flex items-start gap-1"><i class="fas fa-triangle-exclamation mt-0.5"></i><span data-disable-warning-text></span></p></td>
        <td class="text-right"><input type="number" min="0" name="models[${i}][in_credits_per_1k]" value="0" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><input type="number" min="0" name="models[${i}][out_credits_per_1k]" value="0" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>`;
    tb.appendChild(row);
}

(function () {
    function rowsByModelName() {
        const map = {};
        document.querySelectorAll('[data-model-row]').forEach(function (row) {
            const nameInput = row.querySelector('input[name^="models["][name$="[name]"]');
            const name = nameInput ? nameInput.value.trim() : '';
            if (!name) return;
            (map[name] = map[name] || []).push(row);
        });
        return map;
    }
    function renderBadges(row, features) {
        const cell = row.children[1];
        if (!cell) return;
        if (!features.length) {
            cell.innerHTML = '<span class="text-white/30 text-xs">—</span>';
            return;
        }
        cell.innerHTML = '<div class="flex flex-wrap gap-1">' + features.map(function (f) {
            return '<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-violet-500/15 border border-violet-500/30 text-violet-200 text-[10px] font-mono uppercase tracking-wider">' + f + '</span>';
        }).join('') + '</div>';
    }
    function refreshDisableWarning(row) {
        const features = (row.getAttribute('data-features') || '').split(',').filter(Boolean);
        const toggle = row.querySelector('[data-enabled-toggle]');
        const warn = row.querySelector('[data-disable-warning]');
        if (!toggle || !warn) return;
        if (features.length && !toggle.checked) {
            warn.querySelector('[data-disable-warning-text]').textContent =
                'In use by ' + features.join(', ') + ' — those features will fail until reassigned.';
            warn.classList.remove('hidden');
        } else {
            warn.classList.add('hidden');
        }
    }
    function syncFeatureUsage() {
        const byModel = rowsByModelName();
        const usage = {};
        document.querySelectorAll('[data-feature-select]').forEach(function (sel) {
            const feat = sel.getAttribute('data-feature-select');
            const target = sel.value.trim();
            if (target) (usage[target] = usage[target] || []).push(feat);
        });
        document.querySelectorAll('[data-model-row]').forEach(function (row) {
            const nameInput = row.querySelector('input[name^="models["][name$="[name]"]');
            const name = nameInput ? nameInput.value.trim() : '';
            const features = name && usage[name] ? usage[name] : [];
            row.setAttribute('data-features', features.join(','));
            renderBadges(row, features);
            refreshDisableWarning(row);
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('[data-enabled-toggle]')) {
            const row = e.target.closest('[data-model-row]');
            if (row) refreshDisableWarning(row);
        }
        if (e.target && e.target.matches('[data-feature-select]')) {
            syncFeatureUsage();
        }
        if (e.target && e.target.matches('input[name^="models["][name$="[name]"]')) {
            syncFeatureUsage();
        }
    });
})();
function addPackRow() {
    const tb = document.getElementById('packs-tbody');
    const i = tb.children.length;
    const row = document.createElement('tr');
    row.className = 'border-t border-white/5';
    row.innerHTML = `
        <td class="py-2"><input name="packs[${i}][id]" pattern="[a-z0-9_-]+" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm font-mono" required></td>
        <td><input name="packs[${i}][label]" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
        <td class="text-right"><input type="number" min="1" name="packs[${i}][credits]" value="1000" class="w-28 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><input type="number" min="1" name="packs[${i}][wallet_cost]" value="100" class="w-28 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>`;
    tb.appendChild(row);
}
</script>
@endsection
