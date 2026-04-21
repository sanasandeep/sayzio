@php
    $currentWs = app()->bound('current_workspace') ? app('current_workspace') : null;
    $accessibleWs = auth()->check() ? auth()->user()->accessibleWorkspaces() : collect();
    $maxWs = (int) (auth()->user()?->getPlanFeature('max_workspaces', 1) ?? 1);
    $ownedWsCount = auth()->user()?->ownedWorkspaces()->count() ?? 0;
    $canCreateWs = $maxWs === -1 || $ownedWsCount < $maxWs;
@endphp

@if($currentWs)
<div x-data="{ open:false, creating:false, name:'' }" class="px-3 py-2 border-b" style="border-color: var(--border-strong);">
    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between gap-2 px-2 py-2 rounded-lg hover:bg-black/5"
            style="color: var(--text-primary);"
            :class="sidebarMode === 'icons' ? 'justify-center' : ''">
        <div class="flex items-center gap-2 min-w-0">
            <div class="w-7 h-7 rounded-md flex items-center justify-center text-xs font-semibold"
                 style="background:#7c3aed; color:#fff;">
                {{ mb_strtoupper(mb_substr($currentWs->name, 0, 1)) }}
            </div>
            <div class="user-info min-w-0 text-left">
                <div class="truncate text-sm font-semibold">{{ $currentWs->name }}</div>
                <div class="truncate text-[11px] opacity-70">
                    @if((int)$currentWs->owner_user_id === auth()->id())
                        Owner
                    @else
                        {{ ucfirst(auth()->user()->membershipFor($currentWs)?->role ?? 'member') }}
                    @endif
                </div>
            </div>
        </div>
        <i class="fas fa-chevron-down text-xs opacity-60 user-info"></i>
    </button>

    <div x-show="open" @click.outside="open=false" x-cloak
         class="mt-2 rounded-lg border shadow-sm overflow-hidden"
         style="background: var(--bg-card); border-color: var(--border-strong);">
        @foreach($accessibleWs as $ws)
            <form method="POST" action="{{ route('user.workspaces.switch', $ws) }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-black/5"
                        style="color: var(--text-primary);">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="w-5 h-5 rounded flex items-center justify-center text-[10px] font-semibold"
                              style="background:#a78bfa; color:#fff;">
                            {{ mb_strtoupper(mb_substr($ws->name, 0, 1)) }}
                        </span>
                        <span class="truncate">{{ $ws->name }}</span>
                    </span>
                    <span class="text-[11px] opacity-70 whitespace-nowrap">
                        {{ (int)$ws->owner_user_id === auth()->id() ? 'Owner' : (ucfirst(auth()->user()->membershipFor($ws)?->role ?? 'Member')) }}
                        @if($ws->id === $currentWs->id)
                            <i class="fas fa-check ml-1 text-green-500"></i>
                        @endif
                    </span>
                </button>
            </form>
        @endforeach

        <div class="border-t" style="border-color: var(--border-strong);"></div>

        <a href="{{ route('user.team.index') }}"
           class="block px-3 py-2 text-sm hover:bg-black/5" style="color: var(--text-primary);">
            <i class="fas fa-users mr-2 opacity-70"></i> Team settings
        </a>

        @if($canCreateWs)
            <button type="button" @click.stop="creating = !creating"
                    class="w-full text-left px-3 py-2 text-sm hover:bg-black/5" style="color: var(--text-primary);">
                <i class="fas fa-plus mr-2 opacity-70"></i> New workspace
            </button>
            <form x-show="creating" method="POST" action="{{ route('user.workspaces.store') }}"
                  class="px-3 py-2 flex gap-2 border-t" style="border-color: var(--border-strong);" x-cloak>
                @csrf
                <input type="text" name="name" x-model="name" required maxlength="120"
                       placeholder="Workspace name"
                       class="flex-1 px-2 py-1 text-sm border rounded"
                       style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                <button class="px-3 py-1 text-sm bg-primary-600 text-white rounded">Create</button>
            </form>
        @else
            <a href="{{ route('user.upgrade') }}"
               class="block px-3 py-2 text-sm hover:bg-black/5 text-amber-600">
                <i class="fas fa-arrow-up mr-2"></i> Upgrade for more workspaces
                <span class="block text-[11px] opacity-70 ml-6">Plan limit: {{ $maxWs }}</span>
            </a>
        @endif
    </div>
</div>
@endif
