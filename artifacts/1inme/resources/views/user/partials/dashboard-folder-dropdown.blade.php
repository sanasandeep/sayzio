@php
    $dashboardFolders = $__can['links_view'] ? workspace_owner()->projects()->orderBy('name')->get(['id', 'name', 'color']) : collect();
@endphp
<div class="dashboard-folder-dropdown" x-data="{ foldersOpen: false }" @open-nav-folders.window="foldersOpen=true; $nextTick(() => $el.scrollIntoView({block:'nearest'}))" @keydown.escape.stop="foldersOpen=false">
    <div class="flex items-center">
        <a href="{{ route('user.dashboard') }}" class="sidebar-link flex-1 {{ nav_route_is('user.dashboard') ? 'active' : '' }}" style="--nav-tint:#3d6bff;--nav-tint-soft:rgba(61,107,255,0.12)">
            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-house"></use></svg></div>
            <span class="nav-label">Dashboard</span><span class="sidebar-tooltip">Dashboard</span>
        </a>
        @if($__can['links_view'])
            <button type="button" @click="foldersOpen=!foldersOpen" class="nav-label navrail-subtle-btn p-2 mr-1 rounded-lg" :aria-expanded="foldersOpen ? 'true' : 'false'" aria-label="Choose a folder" title="Open a folder">
                <svg class="nav-icon text-xs transition-transform" :class="foldersOpen ? 'rotate-180' : ''" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
            </button>
        @endif
    </div>
    @if($__can['links_view'])
        <div x-show="foldersOpen" x-cloak x-collapse class="nav-label pl-3 pr-2 pb-2" data-dashboard-folder-options>
            <div style="max-height:260px;overflow-y:auto;overscroll-behavior:contain">
                @forelse($dashboardFolders as $folder)
                    <a href="{{ route('user.projects.show', $folder) }}" class="folder-sidebar-item flex items-center gap-2 px-2 py-2 rounded-lg text-[13px] {{ request('project_id') == $folder->id || (nav_route_is('user.projects.*') && request()->route('project') instanceof \App\Modules\User\Models\Project && request()->route('project')->id === $folder->id) ? 'is-current' : '' }}">
                        <svg class="nav-icon text-xs" style="color:{{ $folder->color ?: '#3b82f6' }}" aria-hidden="true"><use href="#i-folder"></use></svg><span class="truncate">{{ $folder->name }}</span>
                    </a>
                @empty
                    <p class="px-2 py-2 text-xs" style="color:var(--text-muted)">No folders yet.</p>
                @endforelse
            </div>
            @if(\App\Modules\User\Services\WorkspacePermissions::userCan('projects.create'))
                <a href="{{ route('user.projects.create') }}" class="navrail-subtle-btn flex items-center gap-2 px-2 py-2 rounded-lg text-[13px]"><svg class="nav-icon text-xs" aria-hidden="true"><use href="#i-plus"></use></svg><span>New folder</span></a>
            @endif
        </div>
    @endif
</div>
