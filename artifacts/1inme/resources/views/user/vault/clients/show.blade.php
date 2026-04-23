@extends('user.layouts.app')
@section('title', $item->name)
@section('content')
@include('user.vault._tabs')

@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $canEdit = WP::userCan('vault.edit');
    $canDelete = WP::userCan('vault.delete');
    $fields = $item->getEncrypted('fields', true) ?? [];
    $socials = $item->getEncrypted('social_handles', true) ?? [];
@endphp

<div class="max-w-3xl" x-data="vaultClientView({{ $item->id }})">
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold">{{ $item->name }}</h2>
            <p class="text-sm text-gray-400 mt-1">
                {{ $item->company }} @if($item->website) · <a href="{{ $item->website }}" target="_blank" class="text-blue-400 hover:underline">{{ $item->website }}</a>@endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($canEdit)<a href="{{ route('user.vault.clients.edit', $item) }}" class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-sm">Edit</a>@endif
            @if($canDelete)
                <form method="post" action="{{ route('user.vault.clients.destroy', $item) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this client?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                    @csrf @method('DELETE')
                    <button class="px-3 py-2 rounded-lg bg-red-500/10 text-red-300 hover:bg-red-500/20 text-sm">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="rounded-lg p-3 bg-white/5">
            <h3 class="text-xs uppercase text-gray-400 mb-2">Emails</h3>
            @forelse($item->emails as $e)
                <div class="text-sm">{{ $e->email }} <span class="text-xs text-gray-500">{{ $e->label }}</span>@if($e->is_primary)<span class="ml-1 text-[10px] text-amber-400">primary</span>@endif</div>
            @empty<div class="text-sm text-gray-500">—</div>@endforelse
        </div>
        <div class="rounded-lg p-3 bg-white/5">
            <h3 class="text-xs uppercase text-gray-400 mb-2">Phones</h3>
            @forelse($item->phones as $p)
                <div class="text-sm">{{ $p->phone }} <span class="text-xs text-gray-500">{{ $p->label }}</span>@if($p->is_primary)<span class="ml-1 text-[10px] text-amber-400">primary</span>@endif</div>
            @empty<div class="text-sm text-gray-500">—</div>@endforelse
        </div>
    </div>

    @if($item->addresses->count())
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase text-gray-400 mb-2">Addresses</h3>
            @foreach($item->addresses as $a)
                <div class="text-sm py-1">
                    <span class="text-gray-500 text-xs">{{ $a->label }}</span>
                    {{ collect([$a->line1, $a->line2, $a->city, $a->region, $a->postal_code, $a->country])->filter()->implode(', ') }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="rounded-lg p-3 bg-white/5 mb-6">
        <h3 class="text-xs uppercase text-gray-400 mb-2 flex items-center justify-between">
            <span>Notes (encrypted)</span>
            <button @click="reveal()" class="text-amber-400 hover:underline text-xs"><i class="fas fa-eye mr-1"></i><span x-text="shown ? 'Hide' : 'Reveal'"></span></button>
        </h3>
        <pre x-show="shown" class="whitespace-pre-wrap text-sm" x-text="value"></pre>
        <p x-show="!shown" class="text-sm text-gray-500">Hidden — click reveal to view (logged).</p>
        <p x-show="error" class="text-red-300 text-xs mt-2" x-text="error"></p>
    </div>

    @if(!empty($socials))
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase text-gray-400 mb-2">Social handles</h3>
            <ul class="text-sm space-y-1">
                @foreach($socials as $s)
                    <li>
                        <span class="text-gray-400 text-xs uppercase mr-2">{{ $s['network'] ?? '' }}</span>
                        <span class="font-mono">{{ $s['handle'] ?? '' }}</span>
                        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="ml-2 text-blue-400 hover:underline text-xs">{{ $s['url'] }}</a>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($fields))
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase text-gray-400 mb-2">Custom fields</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($fields as $row)
                    <div class="flex"><dt class="w-1/3 text-xs text-gray-400">{{ $row['key'] ?? '' }}</dt><dd class="text-sm font-mono">{{ $row['value'] ?? '' }}</dd></div>
                @endforeach
            </dl>
        </div>
    @endif

    @if(!empty($item->tags))
        <div class="mb-6">
            @foreach($item->tags as $t)<span class="px-2 py-1 text-xs rounded-full bg-white/5 mr-1">{{ $t }}</span>@endforeach
        </div>
    @endif

    <div class="rounded-lg p-3 bg-white/5">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs uppercase text-gray-400">Attachments</h3>
            @if($canEdit)
                <form method="post" enctype="multipart/form-data" action="{{ route('user.vault.attachments.store', $item) }}" class="flex items-center gap-2">
                    @csrf
                    <input type="file" name="file" required class="text-xs">
                    <button class="text-xs text-amber-400 hover:underline">Upload (5 MB max)</button>
                </form>
            @endif
        </div>
        @forelse($item->attachments as $a)
            <div class="flex items-center justify-between py-1 text-sm">
                <a href="{{ route('user.vault.attachments.download', $a) }}" class="text-blue-400 hover:underline">{{ $a->filename }}</a>
                <span class="text-xs text-gray-500">{{ number_format($a->size / 1024, 1) }} KB</span>
                @if($canDelete)
                    <form method="post" action="{{ route('user.vault.attachments.destroy', $a) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this attachment?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button class="text-red-400 text-xs">Remove</button>
                    </form>
                @endif
            </div>
        @empty<div class="text-sm text-gray-500">No attachments.</div>@endforelse
    </div>
</div>

<script>
function vaultClientView(id) {
    return {
        shown: false, value: '', error: '', clearTimer: null,
        async reveal() {
            if (this.shown) { this.shown = false; this.value = ''; return; }
            this.error = '';
            const r = await fetch(`/user/vault/clients/${id}/reveal-notes`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            if (!r.ok) { this.error = 'Could not decrypt.'; return; }
            const j = await r.json();
            this.value = j.notes || '';
            this.shown = true;
            clearTimeout(this.clearTimer);
            this.clearTimer = setTimeout(() => { this.shown = false; this.value = ''; }, 30000);
        }
    }
}
</script>
@endsection
