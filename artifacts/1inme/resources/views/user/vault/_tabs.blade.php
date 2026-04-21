@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $isOwner = WP::userIsOwner();
@endphp
<div class="page-hero mb-6 flex items-center gap-4">
    <div class="hero-emblem"><i class="fas fa-vault"></i></div>
    <div class="flex-1 min-w-0">
        <h1 class="hero-title">Workspace Vault</h1>
        <p class="hero-subtitle">Encrypted credentials and client records, scoped to this workspace.</p>
    </div>
</div>

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
