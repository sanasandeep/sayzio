@extends('user.layouts.app')
@section('title', $item->name)
@section('content')
@include('user.planner.styles')
@include('user.partials.client-create-styles')
@include('user.partials.page-hero',['title'=>$item->name,'subtitle'=>$item->company ?: 'Client contacts, projects and connected work.','emblemIcon'=>'fa-user','favicon'=>null,'url'=>null,'back'=>route('user.vault.clients.index'),'chips'=>[['text'=>'Client','icon'=>'fa-user']],'actions'=>[]])
<div x-data="{tab:'overview'}">
@include('user.vault.clients.projects')

@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $canEdit = WP::userCan('vault.edit');
    $canDelete = WP::userCan('vault.delete');
    $fields = $item->getEncrypted('fields', true) ?? [];
    $socials = $item->getEncrypted('social_handles', true) ?? [];
@endphp

<div class="client-create-page" x-data="vaultClientView({{ $item->id }})">
    <div x-show="tab==='overview' || tab==='files'" class="client-toolbar">
        <div>
            <h2 class="text-2xl font-bold">{{ $item->name }}</h2>
            <p class="text-sm mt-1" style="color: var(--text-faint);">
                {{ $item->company }} @if($item->website) · <a href="{{ $item->website }}" target="_blank" class="text-blue-400 hover:underline">{{ $item->website }}</a>@endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($canEdit)<a href="{{ route('user.vault.clients.edit', $item) }}" class="client-secondary">Edit client</a>@endif
            @if($canDelete)
                <form method="post" action="{{ route('user.vault.clients.destroy', $item) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this client?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                    @csrf @method('DELETE')
                    <button class="client-danger">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <section x-show="tab==='overview'">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="rounded-lg p-3 bg-white/5">
            <h3 class="text-xs uppercase mb-2" style="color: var(--text-faint);">Emails</h3>
            @forelse($item->emails as $e)
                <div class="text-sm">{{ $e->email }} <span class="text-xs" style="color: var(--text-muted);">{{ $e->label }}</span>@if($e->is_primary)<span class="ml-1 text-[10px] text-amber-400">primary</span>@endif</div>
            @empty<div class="text-sm" style="color: var(--text-muted);">-</div>@endforelse
        </div>
        <div class="rounded-lg p-3 bg-white/5">
            <h3 class="text-xs uppercase mb-2" style="color: var(--text-faint);">Phones</h3>
            @forelse($item->phones as $p)
                <div class="text-sm">{{ $p->phone }} <span class="text-xs" style="color: var(--text-muted);">{{ $p->label }}</span>@if($p->is_primary)<span class="ml-1 text-[10px] text-amber-400">primary</span>@endif</div>
            @empty<div class="text-sm" style="color: var(--text-muted);">-</div>@endforelse
        </div>
    </div>

    @if($item->addresses->count())
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase mb-2" style="color: var(--text-faint);">Addresses</h3>
            @foreach($item->addresses as $a)
                <div class="text-sm py-1">
                    <span class="text-xs" style="color: var(--text-muted);">{{ $a->label }}</span>
                    {{ collect([$a->line1, $a->line2, $a->city, $a->region, $a->postal_code, $a->country])->filter()->implode(', ') }}
                </div>
            @endforeach
        </div>
    @endif

    </section>
    <section x-show="tab==='files'" x-cloak>
    <div class="rounded-lg p-3 bg-white/5 mb-6">
        <h3 class="text-xs uppercase mb-2 flex items-center justify-between" style="color: var(--text-faint);">
            <span>Notes (encrypted)</span>
            <button @click="reveal()" class="text-amber-400 hover:underline text-xs"><i class="fas fa-eye mr-1"></i><span x-text="shown ? 'Hide' : 'Reveal'"></span></button>
        </h3>
        <pre x-show="shown" class="whitespace-pre-wrap text-sm" x-text="value"></pre>
        <p x-show="!shown" class="text-sm" style="color: var(--text-muted);">Hidden, click reveal to view (logged).</p>
        <p x-show="error" class="text-red-300 text-xs mt-2" x-text="error"></p>
    </div>

    @if(!empty($socials))
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase mb-2" style="color: var(--text-faint);">Social handles</h3>
            <ul class="text-sm space-y-1">
                @foreach($socials as $s)
                    <li>
                        <span class="text-xs uppercase mr-2" style="color: var(--text-faint);">{{ $s['network'] ?? '' }}</span>
                        <span class="font-mono">{{ $s['handle'] ?? '' }}</span>
                        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="ml-2 text-blue-400 hover:underline text-xs">{{ $s['url'] }}</a>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($fields))
        <div class="rounded-lg p-3 bg-white/5 mb-6">
            <h3 class="text-xs uppercase mb-2" style="color: var(--text-faint);">Custom fields</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($fields as $row)
                    <div class="flex"><dt class="w-1/3 text-xs" style="color: var(--text-faint);">{{ $row['key'] ?? '' }}</dt><dd class="text-sm font-mono">{{ $row['value'] ?? '' }}</dd></div>
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
            <h3 class="text-xs uppercase" style="color: var(--text-faint);">Files &amp; scannable QR codes</h3>
            @if($canEdit)
                <form method="post" enctype="multipart/form-data" action="{{ route('user.vault.attachments.store', $item) }}" class="client-file-form">
                    @csrf
                    <select name="purpose" aria-label="Attachment purpose"><option value="document">Document</option><option value="qr">QR image</option></select><input type="file" name="file" required class="text-xs">
                    <button type="submit" class="client-primary">Upload (5 MB max)</button>
                </form>
            @endif
        </div>
        <p class="text-sm mb-3">Choose QR image to save a PNG, JPEG or WebP for scanning. Images remain protected by client access permissions.</p>
        @forelse($item->attachments as $a)
            @if($a->purpose==='qr')<details class="client-create-intro"><summary>Show QR · {{ $a->filename }}</summary><p>Opening records an audited reveal. Close this panel after scanning.</p><div x-data="{open:false}" x-init="$el.parentElement.addEventListener('toggle',e=>open=e.target.open)"><template x-if="open"><img src="{{ route('user.vault.attachments.preview',$a) }}" alt="Saved QR code" style="max-width:280px;width:100%;background:white;padding:16px"></template></div></details>@endif
            <div class="flex items-center justify-between py-1 text-sm">
                <a href="{{ route('user.vault.attachments.download', $a) }}" class="text-blue-400 hover:underline">{{ $a->filename }}</a>
                <span class="text-xs" style="color: var(--text-muted);">{{ number_format($a->size / 1024, 1) }} KB</span>
                @if($canDelete)
                    <form method="post" action="{{ route('user.vault.attachments.destroy', $a) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this attachment?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button class="text-red-400 text-xs">Remove</button>
                    </form>
                @endif
            </div>
        @empty<div class="text-sm" style="color: var(--text-muted);">No attachments.</div>@endforelse
    </div>
</section>
</div>
</div>

<script>
function vaultClientView(id) {
    return {
        shown: false, value: '', error: '', clearTimer: null,
        init(){this.log=async(action)=>{const r=await fetch(`/user/vault/events/client/${id}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'},body:JSON.stringify({action,field:'profile'})});if(!r.ok)this.error='Could not record this action.';};this.onCopy=()=>this.log('copy');this.onOpen=e=>{if(e.target.closest('a'))this.log('open');};this.$el.addEventListener('copy',this.onCopy);this.$el.addEventListener('click',this.onOpen);},
        destroy(){clearTimeout(this.clearTimer);this.$el.removeEventListener('copy',this.onCopy);this.$el.removeEventListener('click',this.onOpen);},
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
