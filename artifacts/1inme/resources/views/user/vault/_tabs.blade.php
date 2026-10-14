@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $isOwner = WP::userIsOwner();
@endphp
@include('user.planner.styles')
@include('user.partials.page-hero',['title'=>'Workspace Vault','subtitle'=>'Protected credentials, notes and QR codes for your work.','emblemIcon'=>'fa-vault','favicon'=>null,'url'=>null,'back'=>route('user.dashboard'),'chips'=>[['text'=>'Workspace encrypted','icon'=>'fa-lock']],'actions'=>[['label'=>'Clients','url'=>route('user.vault.clients.index'),'icon'=>'fa-users','class'=>'btn-ghost']]])
<style>
.vault-security-panel{padding:24px;border:1px solid var(--border-strong);border-radius:18px;background:var(--bg-card)}.vault-fields input:not([type=checkbox]):not([type=color]),.vault-fields textarea,.vault-fields select{padding:12px;border-radius:12px;background:var(--bg-input,var(--bg-card));border:1px solid var(--border-strong);color:var(--text-primary)}.vault-fields button{border-radius:12px}.vault-security-panel input{width:100%}.vault-security-panel input[type=checkbox]{width:auto}.vault-fields input:not([type=checkbox]):not([type=color]):not([type=hidden]),.vault-fields textarea,.vault-fields select{border-color:var(--border-strong)}.vault-value{transition:opacity .2s,filter .2s}.vault-security-panel .form-chip{display:inline-flex;padding:10px 14px;border:1px solid var(--border-strong);border-radius:12px}@media(prefers-reduced-motion:reduce){.vault-value{transition:none}}
</style>

<div class="flex flex-wrap items-center gap-2 mb-6 border-b" style="border-color: var(--border-glass);">
    <a href="{{ route('user.vault.credentials.index') }}"
       class="px-4 py-2 -mb-px border-b-2 text-sm font-semibold {{ request()->routeIs('user.vault.credentials.*') ? 'border-amber-500 text-amber-500' : 'border-transparent text-gray-400 hover:text-gray-200' }}">
        <i class="fas fa-key mr-1"></i> Credentials
    </a>
    <a href="{{ route('user.vault.clients.index') }}"
       class="px-4 py-2 -mb-px border-b-2 text-sm font-semibold {{ request()->routeIs('user.vault.clients.*') ? 'border-amber-500 text-amber-500' : 'border-transparent text-gray-400 hover:text-gray-200' }}">
        <i class="fas fa-id-card mr-1"></i> Clients
    </a>
    <a href="{{ route('user.vault.audit.index') }}"
       class="px-4 py-2 -mb-px border-b-2 text-sm font-semibold {{ request()->routeIs('user.vault.audit.*') ? 'border-amber-500 text-amber-500' : 'border-transparent text-gray-400 hover:text-gray-200' }}">
        <i class="fas fa-clock-rotate-left mr-1"></i> Activity
    </a>
    @if($isOwner)
        <a href="{{ route('user.vault.export.show') }}"
           class="px-4 py-2 -mb-px border-b-2 text-sm font-semibold {{ request()->routeIs('user.vault.export.*') ? 'border-amber-500 text-amber-500' : 'border-transparent text-gray-400 hover:text-gray-200' }}">
            <i class="fas fa-download mr-1"></i> Export
        </a>
    @endif
</div>

@if(session('status'))
    <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
        {{ session('status') }}
    </div>
@endif
