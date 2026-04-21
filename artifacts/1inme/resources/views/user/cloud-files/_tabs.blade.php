@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $isOwner = WP::userIsOwner();
@endphp
<div class="flex flex-wrap items-center gap-2 mb-5 border-b border-white/10 pb-3">
    <h1 class="text-2xl font-bold mr-4"><i class="fas fa-cloud mr-2 text-cyan-400"></i> Cloud Files</h1>
    <a href="{{ route('user.cloud-files.index') }}"
       class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ request()->routeIs('user.cloud-files.index') ? 'bg-cyan-500/20 text-cyan-300' : 'text-gray-300 hover:bg-white/5' }}">
        <i class="fas fa-folder-open mr-1"></i> Library
    </a>
    <a href="{{ route('user.cloud-files.connections') }}"
       class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ request()->routeIs('user.cloud-files.connections') ? 'bg-cyan-500/20 text-cyan-300' : 'text-gray-300 hover:bg-white/5' }}">
        <i class="fas fa-plug mr-1"></i> My Connections
    </a>
    @if($isOwner)
        <a href="{{ route('user.cloud-files.settings.index') }}"
           class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ request()->routeIs('user.cloud-files.settings.*') ? 'bg-cyan-500/20 text-cyan-300' : 'text-gray-300 hover:bg-white/5' }}">
            <i class="fas fa-key mr-1"></i> OAuth Apps
        </a>
    @endif
</div>

@if(session('success'))
    <div class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-sm">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 px-4 py-2.5 rounded-lg bg-rose-500/15 border border-rose-500/30 text-rose-200 text-sm">
        <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
    </div>
@endif
