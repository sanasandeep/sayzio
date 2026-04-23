@extends('user.layouts.app')

@section('title', 'Team')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8" x-data="teamPage()">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">{{ $workspace->name }} — Team</h1>
            <p class="text-sm opacity-70 mt-1">
                Seats used: <strong>{{ $usedSeats }}</strong>
                {{ $maxSeats === -1 ? '(unlimited)' : ' / ' . $maxSeats }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if(!empty($canEditRoles))
                <a href="{{ route('user.team.roles.index') }}"
                   class="px-3 py-2 rounded-lg text-sm font-semibold border hover:bg-gray-50"
                   style="border-color: var(--border-strong); color: var(--text-primary);">
                    <i class="fas fa-sliders-h mr-1"></i> Roles &amp; permissions
                </a>
            @endif
            <button type="button" @click="openInvite()"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700">
                <i class="fas fa-plus mr-1"></i> Invite teammate
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    @if($maxSeats !== -1 && $usedSeats >= $maxSeats)
        <div class="mb-4 p-3 rounded border border-amber-300 bg-amber-50 text-amber-800 text-sm flex items-center justify-between">
            <span><i class="fas fa-info-circle mr-1"></i> You've reached your seat limit. Upgrade your plan or remove a member to invite more.</span>
            <a href="{{ route('user.upgrade') }}" class="ml-3 px-3 py-1 bg-amber-600 text-white rounded text-xs font-semibold">Upgrade</a>
        </div>
    @endif

    <div class="rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="px-4 py-3 border-b font-semibold" style="border-color: var(--border-strong);">Members</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left opacity-70">
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Role</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-t" style="border-color: var(--border-strong);">
                    <td class="px-4 py-3 font-medium">{{ $workspace->owner->name ?? 'Owner' }} <span class="text-xs opacity-60">(you)</span></td>
                    <td class="px-4 py-3">{{ $workspace->owner->email ?? '' }}</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700">Owner</span></td>
                    <td class="px-4 py-3"></td>
                </tr>
                @foreach($members as $m)
                    @php
                        $editPayload = [
                            'id'   => $m->id,
                            'name' => $m->user->name ?? '',
                            'role' => $m->role,
                        ];
                    @endphp
                    <tr class="border-t" style="border-color: var(--border-strong);">
                        <td class="px-4 py-3">{{ $m->user->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $m->user->email ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700">{{ ucfirst($m->role) }}</span></td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" @click="openEdit({{ Js::from($editPayload) }})" class="text-xs text-primary-600 hover:underline mr-3">Edit</button>
                            <form method="POST" action="{{ route('user.team.members.remove', $m) }}" class="inline"
                                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this member?', message: 'They will lose access to this workspace.', confirmText: 'Remove', confirmIcon: 'fa-user-minus', iconClass: 'fa-user-minus'})">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                @if($members->isEmpty())
                    <tr class="border-t" style="border-color: var(--border-strong);">
                        <td colspan="4" class="px-4 py-6 text-center opacity-60">No teammates yet — invite someone above.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if($pendingInvites->isNotEmpty())
        <div class="mt-6 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
            <div class="px-4 py-3 border-b font-semibold" style="border-color: var(--border-strong);">Pending invites</div>
            <table class="w-full text-sm">
                <tbody>
                    @foreach($pendingInvites as $inv)
                        <tr class="border-t" style="border-color: var(--border-strong);">
                            <td class="px-4 py-3">{{ $inv->email }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700">{{ ucfirst($inv->role) }}</span></td>
                            <td class="px-4 py-3 text-xs opacity-60">Expires {{ optional($inv->expires_at)->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
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

    {{-- Invite / edit modal --}}
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
                        Roles apply to <strong>everything in this workspace</strong> — links, biolinks, forms,
                        subscribers, posts, QR codes and more. Workspace-level destructive actions
                        (delete workspace, billing, transfer ownership) stay owner-only.
                    </p>
                </div>

                {{-- Quick reference: what each role can do, generated from the role table. --}}
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
                                    :class="form.role === '{{ $roleSlug }}' ? 'bg-purple-50/40' : ''">
                                    <td class="py-1.5 capitalize font-medium">{{ $roleSlug }}</td>
                                    @foreach(\App\Modules\User\Services\WorkspacePermissions::ACTIONS as $a)
                                        <td class="px-2 text-center">
                                            @if($row[$a] ?? false)
                                                <i class="fas fa-check text-green-500"></i>
                                            @else
                                                <span class="opacity-30">—</span>
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
</div>

<script>
function teamPage() {
    return {
        modal: { open: false, action: '', method: '', title: '' },
        form: { role: 'viewer' },
        validRoles: ['admin','editor','replier','analyst','viewer'],
        openInvite() {
            this.modal = { open: true, action: '{{ route("user.team.invite") }}', method: 'POST', title: 'Invite teammate' };
            this.form = { role: 'editor' };
        },
        openEdit(member) {
            this.modal = { open: true, action: '{{ url("user/team/members") }}/' + member.id, method: 'PUT', title: 'Edit ' + member.name };
            // Legacy "custom" rows fall back to viewer (safest default).
            const role = this.validRoles.includes(member.role) ? member.role : 'viewer';
            this.form = { role };
        },
    };
}
</script>
@endsection
