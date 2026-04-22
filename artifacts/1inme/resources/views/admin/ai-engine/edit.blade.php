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

        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">Model</th>
                <th class="text-left">Kind</th>
                <th class="text-left">Enabled</th>
                <th class="text-right">In / 1k</th>
                <th class="text-right">Out / 1k</th>
                <th></th>
            </tr></thead>
            <tbody id="models-tbody">
            @foreach($models as $i => $m)
                <tr class="border-t border-white/5">
                    <td class="py-2"><input name="models[{{ $i }}][name]" value="{{ $m['name'] }}" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
                    <td><select name="models[{{ $i }}][kind]" class="bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm">
                        <option value="chat"      {{ $m['kind']==='chat' ? 'selected':'' }}>chat</option>
                        <option value="embedding" {{ $m['kind']==='embedding' ? 'selected':'' }}>embedding</option>
                    </select></td>
                    <td>
                        <input type="hidden" name="models[{{ $i }}][enabled]" value="0">
                        <input type="checkbox" name="models[{{ $i }}][enabled]" value="1" {{ $m['enabled'] ? 'checked':'' }} class="accent-violet-500">
                    </td>
                    <td class="text-right"><input type="number" min="0" name="models[{{ $i }}][in_credits_per_1k]" value="{{ $m['in_credits_per_1k'] }}" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><input type="number" min="0" name="models[{{ $i }}][out_credits_per_1k]" value="{{ $m['out_credits_per_1k'] }}" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
                    <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
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
    row.className = 'border-t border-white/5';
    row.innerHTML = `
        <td class="py-2"><input name="models[${i}][name]" class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm" required></td>
        <td><select name="models[${i}][kind]" class="bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"><option value="chat">chat</option><option value="embedding">embedding</option></select></td>
        <td><input type="hidden" name="models[${i}][enabled]" value="0"><input type="checkbox" name="models[${i}][enabled]" value="1" checked class="accent-violet-500"></td>
        <td class="text-right"><input type="number" min="0" name="models[${i}][in_credits_per_1k]" value="0" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><input type="number" min="0" name="models[${i}][out_credits_per_1k]" value="0" class="w-24 text-right bg-white/5 border border-white/10 rounded px-2 py-1 text-white text-sm"></td>
        <td class="text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button></td>`;
    tb.appendChild(row);
}
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
