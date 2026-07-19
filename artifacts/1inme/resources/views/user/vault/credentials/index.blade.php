@extends('user.layouts.app')
@section('title', 'Vault: Credentials')
@section('content')
@include('user.vault._tabs')

@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $canCreate = WP::userCan('vault.create');
@endphp

<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="get" class="flex-1 min-w-[240px]">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by label, username, URL or tag…"
                   class="w-full pl-9 pr-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
        </div>
    </form>
    @if($canCreate)
        <a href="{{ route('user.vault.credentials.create') }}" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">
            <i class="fas fa-plus mr-1"></i> New credential
        </a>
    @endif
</div>

<div class="rounded-xl border border-white/10 overflow-hidden" style="background: var(--bg-card);">
    <table class="min-w-full text-sm">
        <thead class="bg-white/5 text-xs uppercase tracking-wide" style="color: var(--text-faint);">
            <tr>
                <th class="px-4 py-3 text-left">Label</th>
                <th class="px-4 py-3 text-left">Username</th>
                <th class="px-4 py-3 text-left">URL</th>
                <th class="px-4 py-3 text-left">Visibility</th>
                <th class="px-4 py-3 text-left">Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($items as $c)
                <tr class="hover:bg-white/5">
                    <td class="px-4 py-3 font-semibold">
                        <a href="{{ route('user.vault.credentials.show', $c) }}" class="text-amber-300 hover:underline">{{ $c->label }}</a>
                        @foreach(($c->tags ?? []) as $t)
                            <span class="ml-1 px-2 py-0.5 text-[10px] rounded-full bg-white/5">{{ $t }}</span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3">
                        <span x-data="{ copied: false }" class="inline-flex items-center gap-2">
                            <span class="font-mono text-xs">{{ $c->username }}</span>
                            @if($c->username)
                                <button type="button" @click="vaultCopy('{{ addslashes($c->username) }}'); copied = true; setTimeout(()=>copied=false, 1500)" class="hover:text-amber-300 text-xs" style="color: var(--text-muted);" title="Copy username (auto-clears in 30s)">
                                    <i class="fas fa-copy" x-show="!copied"></i>
                                    <i class="fas fa-check text-emerald-400" x-show="copied"></i>
                                </button>
                            @endif
                        </span>
                    </td>
                    <td class="px-4 py-3 truncate max-w-[260px]">
                        @if($c->url)<a href="{{ $c->url }}" target="_blank" rel="noopener" class="text-blue-400 hover:underline">{{ $c->url }}</a>@endif
                    </td>
                    <td class="px-4 py-3">
                        @if($c->visibility === 'private')
                            <span class="text-xs px-2 py-1 rounded bg-red-500/20 text-red-300"><i class="fas fa-lock mr-1"></i>Private</span>
                        @else
                            <span class="text-xs px-2 py-1 rounded bg-emerald-500/20 text-emerald-300">Shared</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs" style="color: var(--text-faint);">{{ $c->updated_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <span x-data="vaultInlineReveal({{ $c->id }})" class="inline-flex items-center gap-3">
                            <span x-show="shown" class="font-mono text-xs text-amber-200" x-text="value"></span>
                            <button type="button" @click="reveal()" class="hover:text-amber-300 text-xs" style="color: var(--text-faint);" :title="shown ? 'Hide' : 'Reveal password (logged)'">
                                <i :class="shown ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                            </button>
                            <button type="button" x-show="shown" @click="vaultCopy(value)" class="hover:text-amber-300 text-xs" style="color: var(--text-faint);" title="Copy password (auto-clears in 30s)">
                                <i class="fas fa-copy"></i>
                            </button>
                            <a href="{{ route('user.vault.credentials.show', $c) }}" class="hover:text-white text-xs" style="color: var(--text-faint);"><i class="fas fa-arrow-right"></i></a>
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center" style="color: var(--text-faint);">No credentials yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($audits->count())
    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-300 mb-2">Recent activity</h2>
        <div class="rounded-xl border border-white/10 divide-y divide-white/5" style="background: var(--bg-card);">
            @foreach($audits as $a)
                <div class="px-4 py-2 text-xs flex items-center gap-3" style="color: var(--text-faint);">
                    <span class="w-20" style="color: var(--text-muted);">{{ $a->occurred_at?->diffForHumans() }}</span>
                    <span class="px-2 py-0.5 rounded bg-white/5 uppercase tracking-wider text-[10px]">{{ $a->action }}</span>
                    <span>{{ $a->target_type }}</span>
                    <span class="text-gray-300">{{ $a->target_label }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

<script>
function vaultCopy(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        // Best-effort clipboard wipe after 30s — overwriting only succeeds
        // while the page has focus, but matches the credential reveal SLA.
        setTimeout(() => { navigator.clipboard.writeText('').catch(() => {}); }, 30000);
    }).catch(() => {});
}
function vaultInlineReveal(id) {
    return {
        shown: false, value: '', clearTimer: null,
        async reveal() {
            if (this.shown) { this.shown = false; this.value = ''; if (this.clearTimer) clearTimeout(this.clearTimer); return; }
            if (!await window.themedConfirmAsync({
                title: 'Reveal password?',
                message: 'Reveal this password? This action is logged in the workspace audit trail.',
                confirmText: 'Reveal',
                confirmIcon: 'fa-eye',
                iconClass: 'fa-eye',
            })) return;
            const r = await fetch(`/user/vault/credentials/${id}/reveal`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            if (!r.ok) return;
            const data = await r.json();
            this.value = data.password ?? '';
            this.shown = true;
            this.clearTimer = setTimeout(() => { this.shown = false; this.value = ''; }, 30000);
        },
    };
}
</script>
@endsection
