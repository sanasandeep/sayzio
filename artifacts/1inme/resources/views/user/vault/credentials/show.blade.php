@extends('user.layouts.app')
@section('title', $item->label)
@section('content')
@include('user.vault._tabs')

@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $canEdit = WP::userCan('vault.edit');
    $canDelete = WP::userCan('vault.delete');
    $cfs = $item->getEncrypted('custom_fields', true) ?? [];
    $notes = $item->getEncrypted('notes');
@endphp

<div class="max-w-3xl" x-data="vaultCredentialView({{ $item->id }})">
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold">{{ $item->label }}</h2>
            <p class="text-sm text-gray-400 mt-1">
                @if($item->visibility === 'private')<span class="text-red-300"><i class="fas fa-lock mr-1"></i>Private</span> · @endif
                Updated {{ $item->updated_at?->diffForHumans() }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($canEdit)<a href="{{ route('user.vault.credentials.edit', $item) }}" class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-sm">Edit</a>@endif
            @if($canDelete)
                <form method="post" action="{{ route('user.vault.credentials.destroy', $item) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this credential?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                    @csrf @method('DELETE')
                    <button class="px-3 py-2 rounded-lg bg-red-500/10 text-red-300 hover:bg-red-500/20 text-sm">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        @if($item->url)
            <div class="rounded-lg p-3 bg-white/5"><dt class="text-xs uppercase text-gray-400">URL</dt><dd class="mt-1"><a href="{{ $item->url }}" target="_blank" class="text-blue-400 hover:underline">{{ $item->url }}</a></dd></div>
        @endif
        @if($item->username)
            <div class="rounded-lg p-3 bg-white/5">
                <dt class="text-xs uppercase text-gray-400 flex items-center justify-between">
                    <span>Username</span>
                    <button type="button" onclick="vaultCopyClear(this.dataset.v)" data-v="{{ $item->username }}" class="text-xs text-gray-400 hover:text-white" title="Copy (auto-clears in 30s)"><i class="fas fa-copy"></i></button>
                </dt>
                <dd class="mt-1 font-mono text-sm">{{ $item->username }}</dd>
            </div>
        @endif
        <div class="rounded-lg p-3 bg-white/5 md:col-span-2">
            <dt class="text-xs uppercase text-gray-400 flex items-center justify-between">
                <span>Password</span>
                <button @click="reveal()" class="text-amber-400 hover:underline text-xs"><i class="fas fa-eye mr-1"></i><span x-text="shown ? 'Hide' : 'Reveal'"></span></button>
            </dt>
            <dd class="mt-2 font-mono text-sm">
                <span x-show="!shown">••••••••••••</span>
                <span x-show="shown" x-text="value"></span>
                <button x-show="shown" @click="copy()" class="ml-2 text-xs text-gray-400 hover:text-white"><i class="fas fa-copy"></i></button>
                <span x-show="error" class="text-red-300 text-xs ml-2" x-text="error"></span>
            </dd>
            <p class="text-[11px] text-gray-500 mt-2">Each reveal is recorded in the workspace audit log.</p>
        </div>
    </dl>

    @if(!empty($notes))
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase text-gray-400 mb-2 flex items-center justify-between">
                <span>Notes</span>
                <span x-show="!shown" class="text-[11px] text-gray-500 normal-case">(hidden — reveal to view)</span>
            </h3>
            <pre x-show="shown" class="whitespace-pre-wrap text-sm" x-text="revealedNotes"></pre>
            <p x-show="!shown" class="text-xs text-gray-500">Click <em>Reveal</em> above to display notes.</p>
        </div>
    @endif

    @if(!empty($cfs))
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase text-gray-400 mb-2 flex items-center justify-between">
                <span>Custom fields</span>
                <span x-show="!shown" class="text-[11px] text-gray-500 normal-case">(hidden — reveal to view)</span>
            </h3>
            <dl x-show="shown" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <template x-for="row in revealedFields" :key="row.key">
                    <div class="flex"><dt class="w-1/3 text-xs text-gray-400" x-text="row.key"></dt><dd class="text-sm font-mono" x-text="row.value"></dd></div>
                </template>
            </dl>
            <p x-show="!shown" class="text-xs text-gray-500">Click <em>Reveal</em> above to display custom fields.</p>
        </div>
    @endif

    @if(!empty($item->tags))
        <div class="mb-6">
            @foreach($item->tags as $t)<span class="px-2 py-1 text-xs rounded-full bg-white/5 mr-1">{{ $t }}</span>@endforeach
        </div>
    @endif
</div>

<script>
function vaultCredentialView(id) {
    return {
        shown: false, value: '', revealedNotes: '', revealedFields: [], error: '', clearTimer: null,
        async reveal() {
            if (this.shown) { this.shown = false; this.value = ''; this.revealedNotes = ''; this.revealedFields = []; return; }
            // Explicit confirm so a misclick doesn't reveal a secret + write an audit row.
            if (!await window.themedConfirmAsync({
                title: 'Reveal credential?',
                message: 'Reveal the password, notes and custom fields? This action is logged in the workspace audit trail.',
                confirmText: 'Reveal',
                confirmIcon: 'fa-eye',
                iconClass: 'fa-eye',
            })) return;
            this.error = '';
            const r = await fetch(`/user/vault/credentials/${id}/reveal`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            if (!r.ok) { this.error = 'Could not decrypt.'; return; }
            const j = await r.json();
            this.value = j.password ?? '';
            this.revealedNotes = j.notes ?? '';
            this.revealedFields = (j.custom_fields ?? []).map(f => ({ key: f.key ?? '', value: f.value ?? '' }));
            this.shown = true;
            // Auto-clear after 30s so revealed secrets don't linger on screen.
            clearTimeout(this.clearTimer);
            this.clearTimer = setTimeout(() => { this.shown = false; this.value = ''; this.revealedNotes = ''; this.revealedFields = []; }, 30000);
        },
        async copy() {
            await vaultCopyClear(this.value);
        }
    }
}
function vaultCopyClear(text) {
    if (!text) return Promise.resolve();
    return navigator.clipboard.writeText(text).then(() => {
        setTimeout(() => { navigator.clipboard.writeText('').catch(() => {}); }, 30000);
    }).catch(() => {});
}
</script>
@endsection
