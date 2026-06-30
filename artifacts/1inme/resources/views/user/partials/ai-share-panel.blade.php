{{--
    Owner-only "Share" panel for an AI Mind / Persona (Task #2909).

    Expects:
      $shareAction     — POST route to create/update a share
      $shareWorkspaces — Collection<Workspace> the owner can share into
      $shareBadges     — Collection<AccountBadge> the owner holds
      $currentShares   — Collection<AiResourceShare> (with audience_label)
      $destroyRoute    — route name for removing a share
      $destroyParams   — leading route params (e.g. [$mind]); share id appended
      $resourceLabel   — e.g. "knowledge base" / "persona"
--}}
@php
    use App\Modules\User\Models\AiResourceShare;
    $hasTargets = $shareWorkspaces->isNotEmpty() || $shareBadges->isNotEmpty();
@endphp
<div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
    <div>
        <h3 class="text-white font-semibold flex items-center gap-2"><i class="fas fa-user-group text-sky-300"></i> Share this {{ $resourceLabel }}</h3>
        <p class="text-xs text-white/40 mt-1">Give a team or a badge group access. Anyone who can use it is charged AI credits from their own balance, never yours. You stay the owner and can revoke access any time.</p>
    </div>

    @if($hasTargets)
    <form method="POST" action="{{ $shareAction }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <div class="flex-1 min-w-[14rem]">
            <label class="text-xs uppercase tracking-wider text-white/40 block mb-1">Share with</label>
            <select name="audience" required class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                @if($shareWorkspaces->isNotEmpty())
                <optgroup label="Teams">
                    @foreach($shareWorkspaces as $ws)
                        <option value="workspace:{{ $ws->id }}">{{ $ws->name }}</option>
                    @endforeach
                </optgroup>
                @endif
                @if($shareBadges->isNotEmpty())
                <optgroup label="Badge groups">
                    @foreach($shareBadges as $badge)
                        <option value="badge:{{ $badge->id }}">{{ $badge->name }}</option>
                    @endforeach
                </optgroup>
                @endif
            </select>
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40 block mb-1">Access</label>
            <select name="access" class="bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                <option value="use">Use only</option>
                <option value="edit">Use &amp; edit</option>
            </select>
        </div>
        <button class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium">Share</button>
    </form>
    @else
    <p class="text-xs text-white/40">You're not in any team or badge group yet, so there's nothing to share into. Join a team or get a badge first.</p>
    @endif

    @if($currentShares->isNotEmpty())
    <div class="border-t border-white/10 pt-4">
        <p class="text-[11px] uppercase tracking-wider text-white/40 mb-2">Currently shared with</p>
        <ul class="space-y-2">
            @foreach($currentShares as $share)
                <li class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/[0.02] px-3 py-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fas {{ $share->audience_type === AiResourceShare::AUDIENCE_WORKSPACE ? 'fa-users' : 'fa-certificate' }} text-white/40 text-xs"></i>
                        <span class="text-white text-sm truncate">{{ $share->audience_label }}</span>
                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ $share->access === AiResourceShare::ACCESS_EDIT ? 'bg-emerald-500/10 text-emerald-300' : 'bg-white/10 text-white/60' }}">
                            {{ $share->access === AiResourceShare::ACCESS_EDIT ? 'Use & edit' : 'Use only' }}
                        </span>
                    </div>
                    <form method="POST" action="{{ route($destroyRoute, array_merge($destroyParams, [$share->id])) }}"
                          onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this share?', message: 'Members will immediately lose access.', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-300/80 hover:text-red-200 px-2 py-1"><i class="fas fa-trash"></i></button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
