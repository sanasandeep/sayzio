@php
    $currentWs = app()->bound('current_workspace') ? app('current_workspace') : null;
    $accessibleWs = auth()->check() ? auth()->user()->accessibleWorkspaces() : collect();
    $maxWs = (int) (auth()->user()?->getPlanFeature('max_workspaces', 1) ?? 1);
    $ownedWsCount = auth()->user()?->ownedWorkspaces()->count() ?? 0;
    $canCreateWs = $maxWs === -1 || $ownedWsCount < $maxWs;
@endphp

@if($currentWs)
<div x-data="{ open:false, creating:false, name:'', icon:'users', color:'#10b981' }" class="px-3 py-2 border-b" style="border-color: var(--border-strong);">
    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between gap-2 px-2 py-2 rounded-xl transition-colors hover:bg-black/5"
            style="color: var(--text-primary);"
            :class="sidebarMode === 'icons' ? 'justify-center' : ''">
        <div class="flex items-center gap-2 min-w-0">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-semibold"
                 style="background:{{ $currentWs->iconColor() }}; color:#fff;"
                 title="{{ $currentWs->is_personal ? 'Personal workspace' : 'Team workspace' }}">
                <i class="fas {{ $currentWs->iconSymbol() }} text-[10px]"></i>
            </div>
            <div class="user-info min-w-0 text-left">
                <div class="truncate text-sm font-semibold flex items-center gap-1.5">
                    <span class="truncate">{{ $currentWs->name }}</span>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider flex-shrink-0"
                          style="{{ $currentWs->is_personal ? 'background:rgba(61,107,255,.12);color:#90acff;' : 'background:rgba(16,185,129,.12);color:#34d399;' }}">
                        {{ $currentWs->is_personal ? 'Personal' : 'Team' }}
                    </span>
                </div>
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
         class="mt-2 rounded-xl border overflow-hidden"
         style="background: var(--bg-card); border-color: var(--border-strong); box-shadow: var(--card-shadow-hover);">
        @foreach($accessibleWs as $ws)
            <form method="POST" action="{{ route('user.workspaces.switch', $ws) }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-black/5"
                        style="color: var(--text-primary);">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="w-5 h-5 rounded flex items-center justify-center text-[10px] font-semibold flex-shrink-0"
                              style="background:{{ $ws->iconColor() }}; color:#fff;">
                            <i class="fas {{ $ws->iconSymbol() }} text-[8px]"></i>
                        </span>
                        <span class="truncate">{{ $ws->name }}</span>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider flex-shrink-0"
                              style="{{ $ws->is_personal ? 'background:rgba(61,107,255,.12);color:#90acff;' : 'background:rgba(16,185,129,.12);color:#34d399;' }}">
                            {{ $ws->is_personal ? 'Personal' : 'Team' }}
                        </span>
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

        @php
            $isOwnerOrAdmin = (int) $currentWs->owner_user_id === auth()->id()
                || (auth()->user()->hasPermission('user.workspaces.access_any'))
                || (optional(auth()->user()->membershipFor($currentWs))->role === 'admin');
        @endphp
        @if($isOwnerOrAdmin)
            <a href="{{ route('user.workspaces.audit.index') }}"
               class="block px-3 py-2 text-sm hover:bg-black/5" style="color: var(--text-primary);">
                <i class="fas fa-shield-halved mr-2 opacity-70"></i> Audit log
            </a>
        @endif

        @if($canCreateWs)
            <button type="button" @click.stop="creating = !creating"
                    class="w-full text-left px-3 py-2 text-sm hover:bg-black/5" style="color: var(--text-primary);">
                <i class="fas fa-plus mr-2 opacity-70"></i> New workspace
            </button>
            <form x-show="creating" method="POST" action="{{ route('user.workspaces.store') }}"
                  class="px-3 py-3 flex flex-col gap-3 border-t" style="border-color: var(--border-strong);" x-cloak>
                @csrf

                <div class="flex flex-col gap-1">
                    <label for="ws-name" class="text-[11px] font-semibold uppercase tracking-wider opacity-70"
                           style="color: var(--text-primary);">Workspace name</label>
                    <input id="ws-name" type="text" name="name" x-model="name" required maxlength="120"
                           placeholder="e.g. Marketing team"
                           class="w-full px-2 py-1.5 text-sm border rounded"
                           style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                </div>

                <div class="flex flex-col gap-1.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider opacity-70"
                          style="color: var(--text-primary);">Symbol</span>
                    <input type="hidden" name="icon" :value="icon">
                    <div class="grid grid-cols-6 gap-1.5">
                        @foreach(\App\Modules\User\Models\Workspace::ICON_CHOICES as $key => $fa)
                            <button type="button" @click="icon = '{{ $key }}'"
                                    :class="icon === '{{ $key }}' ? 'ring-2' : 'opacity-70 hover:opacity-100'"
                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-xs"
                                    :style="icon === '{{ $key }}' ? 'background:' + color + ';color:#fff;' : 'background: var(--bg-card); color: var(--text-primary); border:1px solid var(--border-strong);'"
                                    style="--tw-ring-color: {{ '#fff' }};"
                                    title="{{ ucwords(str_replace('-', ' ', $key)) }}">
                                <i class="fas {{ $fa }} text-[11px]"></i>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider opacity-70"
                          style="color: var(--text-primary);">Color</span>
                    <input type="hidden" name="color" :value="color">
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Modules\User\Models\Workspace::COLOR_CHOICES as $swatch)
                            <button type="button" @click="color = '{{ $swatch }}'"
                                    class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                                    style="background: {{ $swatch }};"
                                    title="{{ $swatch }}">
                                <i class="fas fa-check text-[9px] text-white" x-show="color === '{{ $swatch }}'"></i>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit"
                            class="flex-1 px-3 py-1.5 text-sm font-semibold bg-primary-600 text-white rounded hover:bg-primary-700">
                        Create
                    </button>
                    <button type="button" @click.stop="creating = false"
                            class="px-3 py-1.5 text-sm rounded border"
                            style="border-color: var(--border-strong); color: var(--text-primary);">
                        Cancel
                    </button>
                </div>
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
