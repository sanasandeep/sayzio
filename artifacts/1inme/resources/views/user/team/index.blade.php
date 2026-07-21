@extends('user.layouts.app')

@section('title', 'Seats')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8" x-data="seatsPage({{ Js::from([
    'rows'              => $rows,
    'reassignOptions'   => $reassignOptions,
    'inviteRoute'       => route('user.team.invite'),
    'memberBaseUrl'     => url('user/team/members'),
    'rolesRoute'        => $canEditRoles ? route('user.team.roles.index') : null,
    'usedSeats'         => $usedSeats,
    'maxSeats'          => $maxSeats,
    'pendingCount'      => $pendingCount,
]) }})">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">{{ $workspace->name }}, Seats</h1>
            <p class="text-sm opacity-70 mt-1">
                Manage who can work in this workspace, what they can do, and how many seats you're using.
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if(!empty($isOwner))
                <a href="{{ route('user.workspaces.settings', $workspace) }}"
                   class="px-3 py-2 rounded-lg text-sm font-semibold border glass-hover"
                   style="border-color: var(--border-strong); color: var(--text-primary);">
                    <i class="fas fa-gear mr-1"></i> Workspace settings
                </a>
            @endif
            @if(!empty($canEditRoles))
                <a href="{{ route('user.team.roles.index') }}"
                   class="px-3 py-2 rounded-lg text-sm font-semibold border glass-hover"
                   style="border-color: var(--border-strong); color: var(--text-primary);">
                    <i class="fas fa-sliders-h mr-1"></i> Roles &amp; permissions
                </a>
                <a href="{{ route('user.workspaces.activity.index') }}"
                   class="px-3 py-2 rounded-lg text-sm font-semibold border glass-hover"
                   style="border-color: var(--border-strong); color: var(--text-primary);">
                    <i class="fas fa-clipboard-list mr-1"></i> Activity log
                </a>
            @endif
            <button type="button" @click="openInvite()"
                    :disabled="atLimit"
                    :class="atLimit ? 'opacity-50 cursor-not-allowed' : ''"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700">
                <i class="fas fa-plus mr-1"></i> Invite teammate
            </button>
            @if(empty($isOwner) && !$workspace->is_personal)
                <form method="POST" action="{{ route('user.team.leave') }}"
                      onsubmit="return confirm('Leave {{ $workspace->name }}? You\'ll lose access to this workspace and be moved back to your personal one. Anything you created here transfers to the owner.');">
                    @csrf
                    <button type="submit"
                            class="px-3 py-2 rounded-lg text-sm font-semibold border glass-hover text-red-500"
                            style="border-color: var(--border-strong);">
                        <i class="fas fa-arrow-right-from-bracket mr-1"></i> Leave workspace
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Plan usage banner. --}}
    <div class="mb-6 rounded-lg border p-4"
         :class="atLimit ? 'border-amber-300 bg-amber-50' : (nearLimit ? 'border-blue-200 bg-blue-50' : '')"
         style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span class="text-sm font-semibold" style="color: var(--text-primary);">Seat usage</span>
                    <span class="text-xs opacity-70">
                        <strong>{{ $usedSeats }}</strong>{{ $maxSeats === -1 ? ' used' : ' of ' . $maxSeats . ' seats used' }}
                        @if($pendingCount > 0)
                            · {{ $pendingCount }} pending invite{{ $pendingCount === 1 ? '' : 's' }}
                        @endif
                    </span>
                    <span class="text-xs opacity-60">
                        · Each seat is included in your <strong>{{ $planLabel }}</strong> plan (no per-seat charge).
                    </span>
                </div>
                @if($maxSeats !== -1)
                    <div class="mt-2 h-2 w-full rounded-full overflow-hidden bg-gray-200">
                        <div class="h-full rounded-full transition-all"
                             :class="atLimit ? 'bg-amber-500' : (nearLimit ? 'bg-blue-500' : 'bg-primary-600')"
                             :style="'width: ' + Math.min(100, Math.round({{ $usedSeats }} / {{ max($maxSeats, 1) }} * 100)) + '%'"></div>
                    </div>
                    <p class="mt-2 text-xs opacity-70">
                        <span x-show="atLimit">You've reached your seat limit. Remove or suspend a seat, or upgrade your plan to invite more.</span>
                        <span x-show="!atLimit && nearLimit">You're nearing your seat limit. Plan ahead before inviting more.</span>
                        <span x-show="!atLimit && !nearLimit">{{ max(0, $maxSeats - $usedSeats) }} seat{{ ($maxSeats - $usedSeats) === 1 ? '' : 's' }} left on your plan.</span>
                    </p>
                @else
                    <p class="mt-2 text-xs opacity-70">Your plan includes unlimited seats.</p>
                @endif
            </div>
            @if($maxSeats !== -1)
                <a href="{{ route('user.upgrade') }}"
                   x-show="atLimit || nearLimit"
                   class="px-3 py-2 rounded text-sm font-semibold"
                   :class="atLimit ? 'bg-amber-600 text-white' : 'bg-primary-600 text-white'">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span x-text="atLimit ? 'Upgrade plan' : 'See bigger plans'"></span>
                </a>
            @endif
        </div>
    </div>

    {{-- Filter bar (client-side over the already-loaded rows). --}}
    <div class="mb-3 flex items-center gap-2 flex-wrap">
        <div class="relative flex-1 min-w-[200px]">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs opacity-50"></i>
            <input type="search" x-model="search" placeholder="Search by name, email or role"
                   class="w-full pl-9 pr-3 py-2 text-sm border rounded"
                   style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
        </div>
        <div class="flex items-center gap-1 text-xs">
            <template x-for="opt in statusFilters" :key="opt.value">
                <button type="button" @click="status = opt.value"
                        :class="status === opt.value
                                ? 'bg-primary-600 text-white border-primary-600'
                                : 'opacity-70 hover:opacity-100'"
                        class="px-3 py-1.5 rounded border font-semibold capitalize"
                        style="border-color: var(--border-strong);">
                    <span x-text="opt.label"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Seats table. Owner row is rendered server-side and excluded
         from filtering / removal. --}}
    <div class="rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left opacity-70 border-b" style="border-color: var(--border-strong);">
                        <th class="px-4 py-3">Member</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Last login</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t" style="border-color: var(--border-strong);">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $workspace->owner->name ?? 'Owner' }} <span class="text-xs opacity-60">(you)</span></div>
                            <div class="text-xs opacity-60">{{ $workspace->owner->email ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">Owner</span></td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Active</span></td>
                        <td class="px-4 py-3 text-xs opacity-70">
                            @if($workspace->owner?->last_login_at)
                                {{ $workspace->owner->last_login_at->diffForHumans() }}
                            @else
 -
                            @endif
                        </td>
                        <td class="px-4 py-3"></td>
                    </tr>

                    <template x-for="m in filteredRows" :key="m.id">
                        <tr class="border-t" style="border-color: var(--border-strong);">
                            <td class="px-4 py-3">
                                <div class="font-medium" x-text="m.name"></div>
                                <div class="text-xs opacity-60" x-text="m.email"></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs capitalize" style="background: var(--bg-glass-light); color: var(--text-secondary);" x-text="m.role"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs"
                                      :class="m.is_suspended ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'"
                                      x-text="m.is_suspended ? 'Suspended' : 'Active'"></span>
                                <span x-show="m.owned_count > 0" class="ml-1 text-[11px] opacity-60"
                                      x-text="'· ' + m.owned_count + ' item' + (m.owned_count === 1 ? '' : 's')"></span>
                            </td>
                            <td class="px-4 py-3 text-xs opacity-70" x-text="m.last_seen_human || '—'"></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" @click="openEdit(m)" class="text-xs text-primary-600 hover:underline mr-3">Edit</button>
                                <template x-if="!m.is_suspended">
                                    <form :action="memberBaseUrl + '/' + m.id + '/suspend'" method="POST" class="inline"
                                          onsubmit="return window.themedConfirmSubmit(this, {title: 'Suspend this seat?', message: 'They keep their seat slot but lose access until you reactivate them.', confirmText: 'Suspend', confirmIcon: 'fa-pause', iconClass: 'fa-pause'})">
                                        @csrf
                                        <button class="text-xs text-amber-600 hover:underline mr-3">Suspend</button>
                                    </form>
                                </template>
                                <template x-if="m.is_suspended">
                                    <form :action="memberBaseUrl + '/' + m.id + '/reactivate'" method="POST" class="inline">
                                        @csrf
                                        <button class="text-xs text-green-600 hover:underline mr-3">Reactivate</button>
                                    </form>
                                </template>
                                <button type="button" @click="openRemove(m)" class="text-xs text-red-600 hover:underline">Remove</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredRows.length === 0 && {{ count($rows) }} > 0">
                        <td colspan="5" class="px-4 py-6 text-center opacity-60 border-t" style="border-color: var(--border-strong);">
                            No seats match that search.
                        </td>
                    </tr>
                    @if(empty($rows))
                        <tr class="border-t" style="border-color: var(--border-strong);">
                            <td colspan="5" class="px-4 py-6 text-center opacity-60">No teammates yet, invite someone above.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- AI shared into this workspace. --}}
    @if($sharedAiMinds->isNotEmpty() || $sharedAiPersonas->isNotEmpty())
        <div class="mt-6 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
            <div class="px-4 py-3 border-b font-semibold flex items-center gap-2" style="border-color: var(--border-strong);">
                <i class="fas fa-share-nodes"></i> Shared AI
            </div>
            <div class="p-4 space-y-4">
                <p class="text-xs opacity-70">
                    AI Minds and AI agents that members have shared into this workspace. Anyone with a seat can use them; AI and coin costs are charged to whoever runs them, not the owner.
                </p>

                @if($sharedAiMinds->isNotEmpty())
                    <div>
                        <div class="text-xs uppercase tracking-wider opacity-60 mb-2">AI Minds</div>
                        <div class="space-y-2">
                            @foreach($sharedAiMinds as $s)
                                <div class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2" style="border-color: var(--border-strong);">
                                    <div class="min-w-0">
                                        <a href="{{ route('user.minds.edit', $s->resource_model) }}" class="font-medium hover:underline" style="color: var(--text-primary);">{{ $s->resource_label }}</a>
                                        <div class="text-xs opacity-60">Shared by {{ $s->owner_name }}</div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-xs whitespace-nowrap {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' }}">
                                        {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'Can edit' : 'View only' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($sharedAiPersonas->isNotEmpty())
                    <div>
                        <div class="text-xs uppercase tracking-wider opacity-60 mb-2">AI agents</div>
                        <div class="space-y-2">
                            @foreach($sharedAiPersonas as $s)
                                <div class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2" style="border-color: var(--border-strong);">
                                    <div class="min-w-0">
                                        <a href="{{ route('user.ai-personas.edit', $s->resource_model) }}" class="font-medium hover:underline" style="color: var(--text-primary);">{{ $s->resource_label }}</a>
                                        <div class="text-xs opacity-60">Shared by {{ $s->owner_name }}</div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-xs whitespace-nowrap {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' }}">
                                        {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'Can edit' : 'View only' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- AI shared into badge groups you hold. --}}
    @if($badgeSharedAiMinds->isNotEmpty() || $badgeSharedAiPersonas->isNotEmpty())
        <div class="mt-6 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
            <div class="px-4 py-3 border-b font-semibold flex items-center gap-2" style="border-color: var(--border-strong);">
                <i class="fas fa-id-badge"></i> Shared via your badge groups
            </div>
            <div class="p-4 space-y-4">
                <p class="text-xs opacity-70">
                    AI Minds and AI agents shared with badge groups you currently hold. AI and coin costs are charged to whoever runs them, not the owner. Access ends automatically if you lose the badge.
                </p>

                @if($badgeSharedAiMinds->isNotEmpty())
                    <div>
                        <div class="text-xs uppercase tracking-wider opacity-60 mb-2">AI Minds</div>
                        <div class="space-y-2">
                            @foreach($badgeSharedAiMinds as $s)
                                <div class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2" style="border-color: var(--border-strong);">
                                    <div class="min-w-0">
                                        <a href="{{ route('user.minds.edit', $s->resource_model) }}" class="font-medium hover:underline" style="color: var(--text-primary);">{{ $s->resource_label }}</a>
                                        <div class="text-xs opacity-60">Shared by {{ $s->owner_name }} &middot; {{ $s->audience_label }}</div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-xs whitespace-nowrap {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' }}">
                                        {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'Can edit' : 'View only' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($badgeSharedAiPersonas->isNotEmpty())
                    <div>
                        <div class="text-xs uppercase tracking-wider opacity-60 mb-2">AI agents</div>
                        <div class="space-y-2">
                            @foreach($badgeSharedAiPersonas as $s)
                                <div class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2" style="border-color: var(--border-strong);">
                                    <div class="min-w-0">
                                        <a href="{{ route('user.ai-personas.edit', $s->resource_model) }}" class="font-medium hover:underline" style="color: var(--text-primary);">{{ $s->resource_label }}</a>
                                        <div class="text-xs opacity-60">Shared by {{ $s->owner_name }} &middot; {{ $s->audience_label }}</div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-xs whitespace-nowrap {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' }}">
                                        {{ $s->access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'Can edit' : 'View only' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Pending invites. --}}
    @if($pendingInvites->isNotEmpty())
        <div class="mt-6 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
            <div class="px-4 py-3 border-b font-semibold" style="border-color: var(--border-strong);">Pending invites</div>
            <table class="w-full text-sm">
                <tbody>
                    @foreach($pendingInvites as $inv)
                        <tr class="border-t" style="border-color: var(--border-strong);">
                            <td class="px-4 py-3">{{ $inv->email }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs capitalize" style="background: var(--bg-glass-light); color: var(--text-secondary);">{{ $inv->role }}</span></td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800">Pending invite</span></td>
                            <td class="px-4 py-3 text-xs opacity-60">Expires {{ optional($inv->expires_at)->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('user.team.invites.resend', $inv) }}" class="inline">
                                    @csrf
                                    <button class="text-xs text-primary-600 hover:underline mr-3">Resend</button>
                                </form>
                                <form method="POST" action="{{ route('user.team.invites.revoke', $inv) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Post approval workflow (owner-only) --}}
    @if(!empty($isOwner) && !$workspace->is_personal)
        <div class="mt-6 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
            <div class="px-4 py-3 border-b font-semibold flex items-center gap-2" style="border-color: var(--border-strong);">
                <i class="fas fa-shield-check"></i> Post approval workflow
            </div>
            <form method="POST" action="{{ route('user.workspaces.post-approval.update', $workspace) }}" class="p-4 space-y-4">
                @csrf @method('PUT')
                <p class="text-xs opacity-70">
                    Hold posts in a review queue before they go live. Editors submit posts; reviewers approve, request changes, or reject.
                    Personal workspaces aren't affected.
                </p>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" {{ !empty($approvalCfg['enabled']) ? 'checked' : '' }}>
                    <span class="font-medium">Require approval for posts</span>
                </label>

                <div>
                    <div class="text-xs font-medium mb-2">Roles that can approve</div>
                    <div class="flex flex-wrap gap-3 text-sm">
                        @foreach(['admin' => 'Admin','editor' => 'Editor','replier' => 'Replier','analyst' => 'Analyst','viewer' => 'Viewer'] as $slug => $label)
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" name="approver_roles[]" value="{{ $slug }}"
                                       {{ in_array($slug, $approvalCfg['approver_roles'] ?? ['admin'], true) ? 'checked' : '' }}>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] opacity-60 mt-2">
                        Workspace owners can always approve. Members in any of the selected roles can also approve. Members in other roles must submit posts for review.
                    </p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 text-sm rounded bg-primary-600 text-white font-semibold">
                        <i class="fas fa-save mr-1"></i> Save approval settings
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- 2FA workspace policy + compliance --}}
    <div class="mt-8 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="px-4 py-3 border-b font-semibold flex items-center justify-between" style="border-color: var(--border-strong);">
            <span><i class="fas fa-shield-halved mr-2"></i> Two-factor authentication</span>
            @if($twoFactorRequired)
                <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Required</span>
            @else
                <span class="px-2 py-0.5 rounded text-xs" style="background: var(--bg-glass-light); color: var(--text-secondary);">Optional</span>
            @endif
        </div>

        <div class="p-4 space-y-4">
            @if($isWorkspaceOwner)
                <form method="POST" action="{{ route('user.workspaces.security.update') }}" class="space-y-3 border-b pb-4" style="border-color: var(--border-strong);">
                    @csrf @method('PUT')
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="require_2fa" value="1" {{ $twoFactorRequired ? 'checked' : '' }}
                               class="w-4 h-4">
                        <span class="font-medium">Require all members to have 2FA enabled</span>
                    </label>
                    <p class="text-xs opacity-70 ml-6">When on, every member is walked through TOTP setup on their next sign-in (and blocked from accessing this workspace until enrolled). The owner is exempt.</p>

                    <div class="ml-6 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-xs uppercase tracking-wider opacity-70 mb-1">Grace period (days)</label>
                            <input type="number" name="grace_days" min="0" max="90" value="{{ $twoFactorDeadline ? max(0, $twoFactorDeadline->diffInDays(now(), false) * -1) : 7 }}"
                                   class="w-24 px-2 py-1 border rounded text-sm"
                                   style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                        </div>
                        @if($twoFactorRequired)
                            <label class="flex items-center gap-2 text-xs opacity-80">
                                <input type="checkbox" name="reset_grace" value="1"> Reset grace deadline
                            </label>
                        @endif
                        <button class="ml-auto px-3 py-2 bg-primary-600 text-white rounded text-sm font-semibold">Save policy</button>
                    </div>

                    @if($twoFactorRequired && $twoFactorDeadline)
                        <p class="ml-6 text-xs opacity-80">
                            <i class="fas fa-clock mr-1"></i>
                            Enforcement begins {{ $twoFactorDeadline->isPast() ? 'now (deadline passed)' : $twoFactorDeadline->diffForHumans() }}
                            ({{ $twoFactorDeadline->toDayDateTimeString() }}).
                        </p>
                    @endif
                </form>

                @if($twoFactorRequired)
                    <form method="POST" action="{{ route('user.workspaces.security.remind') }}">
                        @csrf
                        <button class="px-3 py-2 rounded border text-sm font-semibold" style="border-color: var(--border-strong); color: var(--text-primary);">
                            <i class="fas fa-envelope mr-1"></i> Email un-enrolled members a reminder
                        </button>
                    </form>
                @endif

                @if($twoFactorRequired && !$ownerHas2FA)
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                        <i class="fas fa-triangle-exclamation mr-1"></i> You require 2FA from your team but haven't enabled it yourself.
                        <a href="{{ route('user.account.two-factor.show') }}" class="underline font-semibold">Enable it now</a>
                    </p>
                @endif
            @else
                <p class="text-xs opacity-70">Only the workspace owner can change the 2FA policy.</p>
            @endif

            <div>
                <h3 class="text-sm font-semibold mb-2">Compliance</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left opacity-70">
                            <th class="px-2 py-1.5">Member</th>
                            <th class="px-2 py-1.5">Role</th>
                            <th class="px-2 py-1.5 text-right">2FA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($twoFactorCompliance as $row)
                            <tr class="border-t" style="border-color: var(--border-strong);">
                                <td class="px-2 py-2">
                                    <div class="font-medium">{{ $row['name'] ?: '—' }}</div>
                                    <div class="text-xs opacity-60">{{ $row['email'] ?: '—' }}</div>
                                </td>
                                <td class="px-2 py-2 text-xs">{{ $row['role'] }}</td>
                                <td class="px-2 py-2 text-right">
                                    @if($row['enrolled'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">
                                            <i class="fas fa-check-circle"></i> Enrolled
                                        </span>
                                    @elseif($row['is_owner'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs" style="background: var(--bg-glass-light); color: var(--text-muted);">
                                            Not enrolled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div x-show="modal.open" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-2xl" style="background: var(--bg-card);">
            <form :action="modal.action" method="POST" class="p-6">
                @csrf
                <template x-if="modal.method === 'PUT'"><input type="hidden" name="_method" value="PUT"></template>
                <h2 class="text-lg font-bold mb-4" x-text="modal.title"></h2>

                <template x-if="modal.method !== 'PUT'">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Email address</label>
                        <input type="email" name="email" required
                               class="w-full px-3 py-2 border rounded"
                               style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                    </div>
                </template>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Role</label>
                    <select name="role" x-model="form.role"
                            class="w-full px-3 py-2 border rounded"
                            style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                        @foreach($roleDescriptions as $slug => $desc)
                            <option value="{{ $slug }}">{{ $desc }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs opacity-70">
                        <i class="fas fa-info-circle mr-1"></i>
                        Roles apply to <strong>everything in this workspace</strong>, links, Link in Bio pages, forms,
                        subscribers, posts, QR codes and more. Workspace-level destructive actions
                        (delete workspace, billing, transfer ownership) stay owner-only.
                    </p>
                </div>

                <div class="mb-4 border rounded p-3 overflow-x-auto" style="border-color: var(--border-strong);">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left opacity-70">
                                <th class="py-1">Role</th>
                                @foreach(\App\Modules\User\Services\WorkspacePermissions::ACTIONS as $a)
                                    <th class="px-2 capitalize text-center">{{ $a }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($effectiveMatrix as $roleSlug => $row)
                                <tr class="border-t" style="border-color: var(--border-strong);"
                                    :class="form.role === '{{ $roleSlug }}' ? 'bg-indigo-50/40' : ''">
                                    <td class="py-1.5 capitalize font-medium">{{ $roleSlug }}</td>
                                    @foreach(\App\Modules\User\Services\WorkspacePermissions::ACTIONS as $a)
                                        <td class="px-2 text-center">
                                            @if($row[$a] ?? false)
                                                <i class="fas fa-check text-green-500"></i>
                                            @else
                                                <span class="opacity-30">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="modal.open = false"
                            class="px-3 py-2 text-sm rounded border" style="border-color: var(--border-strong);">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm rounded bg-primary-600 text-white font-semibold">
                        <span x-text="modal.method === 'PUT' ? 'Save changes' : 'Send invite'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Remove-seat confirmation modal (reassignment picker shown
         only when the seat owns content). --}}
    <div x-show="removeModal.open" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-lg" style="background: var(--bg-card);">
            <form :action="memberBaseUrl + '/' + removeModal.member.id" method="POST" class="p-6">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <h2 class="text-lg font-bold mb-2">
                    Remove <span x-text="removeModal.member.name"></span>?
                </h2>
                <p class="text-sm opacity-80 mb-4">
                    They'll lose access to <strong>{{ $workspace->name }}</strong> immediately.
                    This frees up their seat.
                </p>

                <template x-if="removeModal.member.owned_count > 0">
                    <div class="mb-4 p-3 rounded border border-amber-300 bg-amber-50 text-amber-900 text-sm">
                        <p class="font-semibold mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span x-text="removeModal.member.name"></span> created
                            <span x-text="removeModal.member.owned_count"></span>
                            item<span x-show="removeModal.member.owned_count !== 1">s</span>
                            in this workspace (links, posts, drafts, forms…).
                        </p>
                        <p class="text-xs opacity-90">
                            Pick a teammate to take over those items so nothing's left without an author.
                        </p>
                        <label class="block text-xs font-medium mt-3 mb-1">Reassign their content to</label>
                        <select name="reassign_to" required
                                class="w-full px-2 py-2 text-sm border rounded bg-white text-amber-900"
                                style="border-color: rgb(252 211 77);">
                            <option value="">Choose someone</option>
                            <template x-for="opt in reassignOptionsFor(removeModal.member)" :key="opt.user_id">
                                <option :value="opt.user_id" x-text="opt.label"></option>
                            </template>
                        </select>
                    </div>
                </template>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="removeModal.open = false"
                            class="px-3 py-2 text-sm rounded border" style="border-color: var(--border-strong);">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm rounded bg-red-600 text-white font-semibold hover:bg-red-700">
                        <i class="fas fa-user-minus mr-1"></i> Remove seat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function seatsPage(initial) {
    return {
        rows: initial.rows || [],
        reassignOptions: initial.reassignOptions || [],
        inviteRoute: initial.inviteRoute,
        memberBaseUrl: initial.memberBaseUrl,
        usedSeats: initial.usedSeats,
        maxSeats: initial.maxSeats,
        pendingCount: initial.pendingCount,
        search: '',
        status: 'all',
        statusFilters: [
            { value: 'all',       label: 'All' },
            { value: 'active',    label: 'Active' },
            { value: 'suspended', label: 'Suspended' },
        ],
        modal: { open: false, action: '', method: '', title: '' },
        form: { role: 'viewer' },
        removeModal: { open: false, member: { id: 0, name: '', owned_count: 0, user_id: 0 } },
        validRoles: ['admin','editor','replier','analyst','viewer'],

        get atLimit() {
            return this.maxSeats !== -1 && (this.usedSeats + this.pendingCount) >= this.maxSeats;
        },
        get nearLimit() {
            if (this.maxSeats === -1) return false;
            const used = this.usedSeats + this.pendingCount;
            return used >= Math.max(1, this.maxSeats - 1) && used < this.maxSeats;
        },
        get filteredRows() {
            const q = this.search.trim().toLowerCase();
            return this.rows.filter(r => {
                if (this.status !== 'all' && r.status !== this.status) return false;
                if (!q) return true;
                return (r.name || '').toLowerCase().includes(q)
                    || (r.email || '').toLowerCase().includes(q)
                    || (r.role  || '').toLowerCase().includes(q);
            });
        },
        reassignOptionsFor(member) {
            return this.reassignOptions.filter(o => o.user_id !== member.user_id);
        },
        openInvite() {
            if (this.atLimit) return;
            this.modal = { open: true, action: this.inviteRoute, method: 'POST', title: 'Invite teammate' };
            this.form = { role: 'editor' };
        },
        openEdit(member) {
            this.modal = { open: true, action: this.memberBaseUrl + '/' + member.id, method: 'PUT', title: 'Edit ' + member.name };
            const role = this.validRoles.includes(member.role) ? member.role : 'viewer';
            this.form = { role };
        },
        openRemove(member) {
            this.removeModal = { open: true, member };
        },
    };
}
</script>
@endsection
