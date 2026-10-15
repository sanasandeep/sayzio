{{--
    Owner-only "Share" panel for an AI Mind / Persona (Task #2909).

    Expects:
      $shareAction     — POST route to create/update a share
      $shareWorkspaces — Collection<Workspace> the owner can share into
      $shareBadges     — Collection<AccountBadge> the owner holds
      $currentShares   — Collection<AiResourceShare> (with audience_label)
      $destroyRoute    — route name for removing a share
      $destroyParams   — leading route params (e.g. [$mind]); share id appended
      $resourceLabel   — e.g. "AI Mind" / "persona"
--}}
@php
    use App\Modules\User\Models\AiResourceShare;
    $hasTargets = $shareWorkspaces->isNotEmpty() || $shareBadges->isNotEmpty();
@endphp
<div class="ai-share-panel rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
    <div>
        <h3 class="text-white font-semibold flex items-center gap-2"><i class="fas fa-user-group text-sky-300"></i> Share this {{ $resourceLabel }}</h3>
        <p class="text-xs text-white/40 mt-1">Select one or more teams or badge groups and set their access. You stay the owner and can remove access at any time.</p>
    </div>

    @if($hasTargets)
    <form method="POST" action="{{ $shareAction }}" class="ai-share-form">
        @csrf
        <div class="ai-share-audience">
            <label class="text-xs uppercase tracking-wider text-white/40 block mb-1">Share with</label>
            <fieldset class="ai-share-options"><legend class="sr-only">Teams and badge groups</legend>
                @foreach($shareWorkspaces as $ws)
                    <label class="ai-share-choice"><input type="checkbox" name="audiences[]" value="workspace:{{ $ws->id }}" @checked(in_array('workspace:'.$ws->id, old('audiences', [])))><span>{{ $ws->name }}<small>Team</small></span></label>
                @endforeach
                @foreach($shareBadges as $badge)
                    <label class="ai-share-choice"><input type="checkbox" name="audiences[]" value="badge:{{ $badge->id }}" @checked(in_array('badge:'.$badge->id, old('audiences', [])))><span>{{ $badge->name }}<small>Badge group</small></span></label>
                @endforeach
            </fieldset>
            @error('audiences')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror

        </div>
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40 block mb-1">Access</label>
            <select aria-label="Sharing access" name="access" class="bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                <option value="use">Use only</option>
                <option value="edit">Use &amp; edit</option>
            </select>
        </div>
        <button class="ai-share-submit btn-primary">Share access</button>
    </form>
    <p class="ai-share-note">Members use coins from their own balance. “Use &amp; edit” also lets them change sources.</p>
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
                        <button aria-label="Remove access for {{ $share->audience_label }}" class="text-xs text-red-300/80 hover:text-red-200 px-2 py-1"><i class="fas fa-trash"></i></button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>

@once
<style>
.ai-share-form{display:grid;grid-template-columns:minmax(0,1fr) minmax(150px,220px) auto;gap:14px;align-items:end;padding:16px;background:var(--surface-subtle,rgba(128,128,128,.04));border:1px solid var(--border-color,rgba(128,128,128,.14));border-radius:14px}.ai-share-form>div{min-width:0}.ai-share-form select{width:100%;min-height:44px}.ai-share-submit{min-height:44px;padding:10px 20px;border-radius:12px;white-space:nowrap;background:#3454ef!important;color:#fff!important;font-size:14px;font-weight:600}.ai-share-note{font-size:12px;color:var(--text-secondary,#9299aa);margin-top:12px}.ai-share-panel li{flex-wrap:wrap}.ai-share-panel h3 i{color:#5671ed!important}@media(max-width:640px){.ai-share-form{grid-template-columns:1fr}.ai-share-submit{width:100%}}
.ai-share-options{display:flex;flex-wrap:wrap;gap:8px;max-height:240px;overflow:auto}.ai-share-choice{display:flex;gap:10px;align-items:center;padding:10px 14px;border:1px solid var(--border-color,#dbe2ef);border-radius:12px;cursor:pointer;min-width:150px}.ai-share-choice:has(input:checked){border-color:#5269ed;background:rgba(82,105,237,.08)}.ai-share-choice input{accent-color:#3454ef;width:16px;height:16px}.ai-share-choice span{font-size:13px}.ai-share-choice small{display:block;font-size:10px;color:var(--text-secondary,#9299aa);margin-top:2px}
</style>
@endonce
