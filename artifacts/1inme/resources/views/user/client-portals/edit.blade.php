@extends('user.layouts.app')
@section('title', $portal->name)
@section('content')
@php
    use App\Modules\User\Models\ClientPortalShare;
@endphp

<div class="page-hero mb-6 flex items-center gap-4">
    <div class="h-12 w-12 rounded text-white flex items-center justify-center text-lg font-bold" style="background: {{ $portal->brandingColor() }}">
        {{ strtoupper(mb_substr($portal->brandingName(), 0, 1)) }}
    </div>
    <div class="flex-1 min-w-0">
        <h1 class="hero-title">{{ $portal->name }}</h1>
        <p class="hero-subtitle">{{ optional($portal->vaultClient)->name ?: 'No vault client linked' }} · {{ $portal->is_enabled ? 'Enabled' : 'Disabled' }}</p>
    </div>
    <form action="{{ route('user.client-portals.destroy', $portal) }}" method="POST" onsubmit="return confirm('Delete this portal? All shares, magic links and audit history will be lost.')">
        @csrf @method('DELETE')
        <button class="px-3 py-2 text-sm rounded border border-rose-300 text-rose-300 hover:bg-rose-500/10">
            <i class="fas fa-trash mr-1"></i>Delete
        </button>
    </form>
</div>

@if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
        {{ session('success') }}
        @if(session('portal_link_url'))
            <div class="mt-2 font-mono text-xs break-all">{{ session('portal_link_url') }}</div>
        @endif
    </div>
@endif
@if($errors->any())
    <div class="mb-4 px-4 py-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">{{ $errors->first() }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ─── Settings ─── --}}
    <div class="lg:col-span-1 space-y-6">
        <div class="rounded-xl border p-5" style="border-color: var(--border-strong); background: var(--bg-card);">
            <h2 class="font-semibold mb-3"><i class="fas fa-cog mr-1"></i> Settings</h2>
            <form action="{{ route('user.client-portals.update', $portal) }}" method="POST" class="space-y-3 text-sm">
                @csrf @method('PUT')
                <input type="hidden" name="vault_client_id" value="{{ $portal->vault_client_id }}">
                <div>
                    <label class="block text-xs opacity-70 mb-1">Name</label>
                    <input name="name" value="{{ $portal->name }}" required class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                </div>
                <div>
                    <label class="block text-xs opacity-70 mb-1">Vault client</label>
                    <select name="vault_client_id" class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                        <option value="">— None —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" @selected($portal->vault_client_id == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs opacity-70 mb-1">Brand name</label>
                    <input name="brand_name" value="{{ $portal->brand_name }}" placeholder="Defaults to workspace name" class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs opacity-70 mb-1">Color</label>
                        <input type="color" name="brand_color" value="{{ $portal->brand_color ?: '#7c3aed' }}" class="h-10 w-full rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                    </div>
                    <div>
                        <label class="block text-xs opacity-70 mb-1">Enabled</label>
                        <label class="flex items-center gap-2 px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                            <input type="checkbox" name="is_enabled" value="1" @checked($portal->is_enabled)> <span class="text-sm">Active</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs opacity-70 mb-1">Logo URL</label>
                    <input type="url" name="brand_logo_url" value="{{ $portal->brand_logo_url }}" placeholder="https://…" class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                </div>
                <div>
                    <label class="block text-xs opacity-70 mb-1">Welcome message</label>
                    <textarea name="welcome_message" rows="3" class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">{{ $portal->welcome_message }}</textarea>
                </div>
                <button class="w-full px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold text-sm">Save</button>
            </form>
        </div>

        {{-- ─── Magic links ─── --}}
        <div class="rounded-xl border p-5" style="border-color: var(--border-strong); background: var(--bg-card);">
            <h2 class="font-semibold mb-3"><i class="fas fa-link mr-1"></i> Magic links</h2>
            <form action="{{ route('user.client-portals.links.send', $portal) }}" method="POST" class="space-y-2 text-sm mb-4">
                @csrf
                <input name="email" type="email" required placeholder="client@example.com" class="w-full px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                <div class="flex items-center gap-2">
                    <input name="expires_in" type="number" min="1" max="365" value="30" class="w-24 px-3 py-2 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                    <span class="text-xs opacity-60">days</span>
                    <button class="ml-auto px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold text-xs"><i class="fas fa-paper-plane mr-1"></i>Send link</button>
                </div>
            </form>
            <ul class="space-y-2">
                @forelse($portal->links as $link)
                    <li class="flex items-center gap-2 text-xs">
                        <span class="flex-1 min-w-0 truncate">{{ $link->email }}</span>
                        <span class="px-2 py-0.5 rounded-full {{ $link->isUsable() ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-300' }}">{{ $link->statusLabel() }}</span>
                        @if($link->isUsable())
                            <form action="{{ route('user.client-portals.links.revoke', [$portal, $link]) }}" method="POST" class="inline">
                                @csrf <button class="text-rose-300 hover:text-rose-200" title="Revoke"><i class="fas fa-ban"></i></button>
                            </form>
                            <a href="{{ route('portal.start', $link->token) }}" target="_blank" class="text-primary-400 hover:text-primary-300" title="Open"><i class="fas fa-external-link-alt"></i></a>
                        @endif
                        <form action="{{ route('user.client-portals.links.rotate', [$portal, $link]) }}" method="POST" class="inline">
                            @csrf <button class="text-amber-300 hover:text-amber-200" title="Rotate"><i class="fas fa-sync"></i></button>
                        </form>
                    </li>
                @empty
                    <li class="text-xs opacity-60">No magic links yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- ─── Shares + Activity ─── --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-xl border p-5" style="border-color: var(--border-strong); background: var(--bg-card);">
            <h2 class="font-semibold mb-3"><i class="fas fa-share-alt mr-1"></i> Shared content</h2>

            <details class="mb-4">
                <summary class="cursor-pointer text-sm font-semibold opacity-80">+ Add a share</summary>
                <form action="{{ route('user.client-portals.shares.store', $portal) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 p-3 rounded border text-sm" style="border-color: var(--border-glass);">
                    @csrf
                    <label>
                        <span class="block text-xs opacity-70 mb-1">Type</span>
                        <select name="shareable_type" id="share-type" class="w-full px-2 py-1.5 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                            @foreach(ClientPortalShare::TYPES as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="block text-xs opacity-70 mb-1">Item</span>
                        <select name="shareable_id" id="share-id" class="w-full px-2 py-1.5 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                            @foreach($boards as $b)
                                <option data-type="task_board" value="{{ $b->id }}">[Board] {{ $b->name }}</option>
                            @endforeach
                            @foreach($drafts as $d)
                                <option data-type="creator_post" value="{{ $d->id }}">[Draft] {{ $d->title ?: \Illuminate\Support\Str::limit($d->body, 40) }}</option>
                            @endforeach
                            @foreach($invoices as $inv)
                                <option data-type="invoice" value="{{ $inv->id }}">[Invoice] {{ $inv->number }} — {{ strtoupper($inv->currency) }} {{ number_format($inv->grand_total_minor / 100, 2) }}</option>
                            @endforeach
                            @foreach($links as $l)
                                <option data-type="link_performance" value="{{ $l->id }}">[Report] {{ $l->title ?: $l->slug }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="md:col-span-2">
                        <span class="block text-xs opacity-70 mb-1">Label (optional)</span>
                        <input name="label" maxlength="160" class="w-full px-2 py-1.5 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                    </label>
                    <div class="md:col-span-2 hidden" id="folder-fields">
                        <div class="grid grid-cols-2 gap-2">
                            <label>
                                <span class="block text-xs opacity-70 mb-1">Provider</span>
                                <input name="settings[provider]" placeholder="google_drive / dropbox / …" class="w-full px-2 py-1.5 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                            </label>
                            <label>
                                <span class="block text-xs opacity-70 mb-1">Folder path</span>
                                <input name="settings[folder_path]" placeholder="/Clients/Acme" class="w-full px-2 py-1.5 rounded border" style="border-color: var(--border-strong); background: var(--bg-input);">
                            </label>
                        </div>
                    </div>
                    <label class="md:col-span-2 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="requires_approval" value="1"> Requires client approval (drafts only)
                    </label>
                    <div class="md:col-span-2 text-right">
                        <button class="px-4 py-1.5 rounded bg-primary-600 text-white text-sm font-semibold">Add share</button>
                    </div>
                </form>
                <script>
                    (function(){
                        const sel = document.getElementById('share-type');
                        const idSel = document.getElementById('share-id');
                        const folderBox = document.getElementById('folder-fields');
                        function refresh() {
                            const t = sel.value;
                            folderBox.classList.toggle('hidden', t !== 'cloud_folder');
                            for (const opt of idSel.options) {
                                opt.hidden = (t === 'cloud_folder') ? true : (opt.dataset.type !== t);
                            }
                            const first = [...idSel.options].find(o => !o.hidden);
                            if (first) idSel.value = first.value;
                        }
                        sel.addEventListener('change', refresh); refresh();
                    })();
                </script>
            </details>

            @if($portal->shares->isEmpty())
                <p class="text-sm opacity-60">No content shared yet. Add boards, drafts, files or invoices above.</p>
            @else
                <ul class="divide-y" style="border-color: var(--border-glass);">
                    @foreach($portal->shares as $share)
                        <li class="py-3 flex items-center gap-3 text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs bg-primary-500/20 text-primary-300">{{ $share->typeLabel() }}</span>
                            <span class="flex-1 min-w-0 truncate">
                                {{ $share->label ?: ('#' . $share->shareable_id) }}
                                @if($share->shareable_type === ClientPortalShare::TYPE_CLOUD_FOLDER)
                                    <span class="opacity-60 text-xs">{{ ($share->settings['provider'] ?? '') . ': ' . ($share->settings['folder_path'] ?? '') }}</span>
                                @endif
                            </span>
                            @if($share->requires_approval)
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    {{ $share->approval_status === 'approved' ? 'bg-emerald-500/20 text-emerald-300' :
                                       ($share->approval_status === 'rejected' ? 'bg-rose-500/20 text-rose-300' : 'bg-amber-500/20 text-amber-300') }}">
                                    {{ ucfirst($share->approval_status ?: 'pending') }}
                                </span>
                            @endif
                            <form action="{{ route('user.client-portals.shares.destroy', [$portal, $share]) }}" method="POST">
                                @csrf @method('DELETE')
                                <button class="text-rose-300 hover:text-rose-200" title="Remove"><i class="fas fa-times"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ─── Activity ─── --}}
        <div class="rounded-xl border p-5" style="border-color: var(--border-strong); background: var(--bg-card);">
            <h2 class="font-semibold mb-3"><i class="fas fa-bolt mr-1"></i> Recent client activity</h2>
            @if($recentActions->isEmpty())
                <p class="text-sm opacity-60">No activity yet — the portal will record every view, download, comment and approval.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach($recentActions as $a)
                        <li class="flex items-center gap-3">
                            <span class="text-xs opacity-60 w-32 flex-shrink-0">{{ $a->occurred_at?->diffForHumans() }}</span>
                            <span class="px-2 py-0.5 rounded-full text-xs bg-slate-500/20">{{ str_replace('_',' ', $a->action) }}</span>
                            <span class="opacity-80">{{ $a->email ?: '—' }}</span>
                            <span class="opacity-60 text-xs">{{ $a->target_type ? ($a->target_type . ($a->target_id ? ' #' . $a->target_id : '')) : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
