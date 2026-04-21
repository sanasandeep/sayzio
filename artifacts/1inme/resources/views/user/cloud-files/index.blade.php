@extends('user.layouts.app')
@section('title', 'Workspace Cloud Files')
@section('content')
@include('user.cloud-files._tabs')

@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    use App\Modules\User\Models\CloudProviderApp;
    $canCreate = WP::userCan('files.create');
    $canDelete = WP::userCan('files.delete');
    $usableConnections = $myConnections->filter(fn($c) => !$c->isBroken() && ($apps[$c->provider] ?? null)?->isConfigured());
@endphp

<div x-data="cloudPicker()" class="space-y-4">

    {{-- Filter / search bar --}}
    <form method="get" class="flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[220px]">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by file name…"
                   class="w-full pl-9 pr-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
        </div>
        <select name="provider" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm"
                onchange="this.form.submit()">
            <option value="">All providers</option>
            @foreach(CloudProviderApp::PROVIDERS as $p)
                <option value="{{ $p }}" @selected(request('provider') === $p)>{{ CloudProviderApp::PROVIDER_LABELS[$p] }}</option>
            @endforeach
        </select>
        <select name="owner" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm"
                onchange="this.form.submit()">
            <option value="">All teammates</option>
            @foreach($contributors as $u)
                <option value="{{ $u->id }}" @selected((int) request('owner') === (int) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-sm">Filter</button>

        @if($canCreate)
            @if($usableConnections->isEmpty())
                <a href="{{ route('user.cloud-files.connections') }}"
                   class="ml-auto px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-semibold">
                    <i class="fas fa-plug mr-1"></i> Connect a cloud account
                </a>
            @else
                <div class="ml-auto flex items-center gap-2">
                    <select x-model="connectionId" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                        @foreach($usableConnections as $c)
                            <option value="{{ $c->id }}">{{ $c->providerLabel() }} — {{ $c->account_label ?: $c->account_email }}</option>
                        @endforeach
                    </select>
                    <button type="button" @click="open()" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-semibold">
                        <i class="fas fa-plus mr-1"></i> Add files
                    </button>
                </div>
            @endif
        @endif
    </form>

    {{-- Library list --}}
    @if($files->isEmpty())
        <div class="rounded-xl border border-dashed border-white/15 p-10 text-center" style="background: var(--bg-card);">
            <i class="fas fa-cloud-arrow-up text-4xl text-cyan-400/50 mb-3"></i>
            <h3 class="text-lg font-semibold mb-1">No files in the workspace library yet</h3>
            <p class="text-sm text-gray-400 mb-4">Connect your Google Drive, Dropbox, or OneDrive and pick files to share with your team. Bytes stay in the cloud — only the link is shared.</p>
            <a href="{{ route('user.cloud-files.connections') }}" class="inline-block px-5 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-semibold">
                <i class="fas fa-plug mr-1"></i> Connect a cloud account
            </a>
        </div>
    @else
        <div class="rounded-xl border border-white/10 overflow-hidden" style="background: var(--bg-card);">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Provider</th>
                        <th class="px-4 py-3 text-left">Size</th>
                        <th class="px-4 py-3 text-left">Added by</th>
                        <th class="px-4 py-3 text-left">Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach($files as $f)
                        <tr class="hover:bg-white/5">
                            <td class="px-4 py-3">
                                <a href="{{ $f->link }}" target="_blank" rel="noopener noreferrer" class="font-medium hover:text-cyan-300">
                                    <i class="far fa-file mr-1 text-gray-400"></i> {{ $f->name }}
                                    <i class="fas fa-arrow-up-right-from-square ml-1 text-[10px] text-gray-500"></i>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-300"><i class="{{ $f->providerIcon() }} mr-1"></i> {{ $f->providerLabel() }}</td>
                            <td class="px-4 py-3 text-gray-400">{{ $f->humanSize() }}</td>
                            <td class="px-4 py-3 text-gray-400">{{ $f->addedBy?->name ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-400">{{ $f->added_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($canDelete)
                                    <form method="POST" action="{{ route('user.cloud-files.destroy', $f) }}"
                                          onsubmit="return confirm('Remove this file from the workspace library? The original file in the cloud is not touched.')">
                                        @csrf @method('DELETE')
                                        <button class="text-rose-300 hover:text-rose-200 text-xs"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $files->links() }}</div>
    @endif

    {{-- Picker modal --}}
    @if($canCreate && $usableConnections->isNotEmpty())
    <div x-show="visible" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="close()">
        <div class="w-full max-w-2xl rounded-xl border border-white/10 max-h-[80vh] flex flex-col" style="background: var(--bg-card);">
            <div class="px-5 py-3 border-b border-white/10">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-semibold">Pick files</div>
                    <button @click="close()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" x-model="searchTerm" @keydown.enter.prevent="runSearch()"
                               placeholder="Search files in this account…"
                               class="w-full pl-9 pr-3 py-1.5 rounded bg-white/5 border border-white/10 text-sm">
                    </div>
                    <button type="button" @click="runSearch()" class="px-3 py-1.5 rounded bg-white/5 hover:bg-white/10 text-sm">Search</button>
                    <button type="button" x-show="searching" @click="clearSearch()" class="px-3 py-1.5 rounded bg-white/5 hover:bg-white/10 text-sm">Clear</button>
                </div>
                <div class="text-xs text-gray-400 mt-2" x-show="!searching">
                    <template x-for="(crumb, i) in breadcrumbs" :key="i">
                        <span>
                            <a href="#" @click.prevent="goBackTo(i)" class="hover:text-cyan-300" x-text="crumb.name"></a>
                            <span class="mx-1 text-gray-600">/</span>
                        </span>
                    </template>
                </div>
                <div class="text-xs text-gray-400 mt-2" x-show="searching">
                    <i class="fas fa-magnifying-glass mr-1"></i> Search results for "<span x-text="searchTerm"></span>"
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-3" x-ref="list">
                <div x-show="loading" class="text-center text-gray-400 py-8"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
                <div x-show="error" x-text="error" class="px-3 py-2 rounded bg-rose-500/15 text-rose-200 text-sm"></div>

                <template x-for="folder in folders" :key="'f' + folder.id">
                    <button type="button" @click="enter(folder)"
                            class="w-full text-left px-3 py-2 rounded hover:bg-white/5 flex items-center gap-2">
                        <i class="fas fa-folder text-amber-400"></i>
                        <span class="flex-1" x-text="folder.name"></span>
                        <i class="fas fa-chevron-right text-xs text-gray-500"></i>
                    </button>
                </template>

                <template x-for="file in files" :key="'x' + file.id">
                    <label class="px-3 py-2 rounded hover:bg-white/5 flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" :value="file.id" @change="toggle(file, $event.target.checked)" class="w-4 h-4">
                        <i class="far fa-file text-gray-400"></i>
                        <span class="flex-1 text-sm" x-text="file.name"></span>
                        <span class="text-xs text-gray-500" x-text="humanSize(file.size)"></span>
                    </label>
                </template>

                <div x-show="!loading && folders.length === 0 && files.length === 0" class="text-center text-gray-500 py-8 text-sm">This folder is empty.</div>
            </div>

            <div class="px-5 py-3 border-t border-white/10 flex items-center justify-between">
                <div class="text-xs text-gray-400"><span x-text="selected.length"></span> selected</div>
                <div class="flex gap-2">
                    <button type="button" @click="close()" class="px-3 py-2 rounded bg-white/5 text-sm">Cancel</button>
                    <button type="button" @click="submit()" :disabled="selected.length === 0 || saving"
                            class="px-4 py-2 rounded bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-semibold disabled:opacity-40">
                        <span x-show="!saving">Add to library</span>
                        <span x-show="saving"><i class="fas fa-spinner fa-spin"></i> Adding…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function cloudPicker() {
    return {
        visible: false,
        connectionId: @json($usableConnections->first()?->id),
        loading: false,
        saving: false,
        searching: false,
        searchTerm: '',
        error: '',
        folders: [],
        files: [],
        selected: [],
        breadcrumbs: [{ id: null, name: 'Home' }],

        open() {
            this.visible = true; this.selected = []; this.searching = false; this.searchTerm = '';
            this.breadcrumbs = [{ id: null, name: 'Home' }]; this.load(null);
        },
        close() { this.visible = false; },
        async fetchPicker(params) {
            this.loading = true; this.error = ''; this.folders = []; this.files = [];
            try {
                const r = await fetch(`/user/cloud-files/picker/${this.connectionId}?` + new URLSearchParams(params), {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const j = await r.json();
                if (!r.ok) { this.error = j.message || 'Could not load files. ' + (j.error === 'reconnect_required' ? 'Reconnect needed.' : ''); return; }
                this.folders = j.folders || [];
                this.files = j.files || [];
            } catch (e) { this.error = 'Network error.'; }
            finally { this.loading = false; }
        },
        load(folderId) { this.searching = false; this.fetchPicker(folderId ? { folder: folderId } : {}); },
        runSearch() {
            const q = this.searchTerm.trim();
            if (!q) return;
            this.searching = true;
            this.fetchPicker({ search: q });
        },
        clearSearch() { this.searchTerm = ''; this.searching = false; this.load(this.breadcrumbs.at(-1).id); },
        enter(folder) { this.breadcrumbs.push({ id: folder.id, name: folder.name }); this.load(folder.id); },
        goBackTo(i) { this.breadcrumbs = this.breadcrumbs.slice(0, i + 1); this.load(this.breadcrumbs[i].id); },
        toggle(file, on) {
            if (on) this.selected.push(file);
            else this.selected = this.selected.filter(s => s.id !== file.id);
        },
        humanSize(b) {
            if (!b) return '';
            const u = ['B','KB','MB','GB']; let i = 0;
            while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
            return b.toFixed(i ? 1 : 0) + ' ' + u[i];
        },
        async submit() {
            if (this.selected.length === 0) return;
            this.saving = true;
            try {
                const r = await fetch('{{ route('user.cloud-files.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        connection_id: this.connectionId,
                        items: this.selected.map(f => ({
                            remote_id: f.id, name: f.name, mime: f.mime,
                            size: f.size, link: f.link, thumbnail_url: f.thumbnail_url,
                        })),
                        parent_folder_path: this.breadcrumbs.map(b => b.name).join(' / '),
                    }),
                });
                if (!r.ok) { this.error = 'Could not add files.'; return; }
                window.location.reload();
            } finally { this.saving = false; }
        },
    }
}
</script>
@endpush
@endsection
