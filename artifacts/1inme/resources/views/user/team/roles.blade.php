@extends('user.layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Roles &amp; Permissions</h1>
            <p class="text-sm opacity-70 mt-1">
                Customise what each role can do in <strong>{{ $workspace->name }}</strong>.
                Changes take effect on the next request.
            </p>
        </div>
        <a href="{{ route('user.team.index') }}" class="text-sm opacity-70 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> Back to team
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="mb-4 p-3 rounded border border-blue-200 bg-blue-50 text-blue-800 text-sm">
        <i class="fas fa-info-circle mr-1"></i>
        These permissions apply to <strong>everything in this workspace</strong> — links, biolinks,
        forms, posts, subscribers, QR codes and more. Workspace-level admin actions
        (delete workspace, manage billing, transfer ownership) stay <strong>owner-only</strong>
        and are not part of this matrix.
    </div>

    <form method="POST" action="{{ route('user.team.roles.update') }}"
          class="rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
        @csrf
        @method('PUT')
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left opacity-70 border-b" style="border-color: var(--border-strong);">
                        <th class="px-4 py-3">Role</th>
                        @foreach($actions as $a)
                            <th class="px-4 py-3 capitalize text-center">{{ $a }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{-- Owner row is locked — always full access. --}}
                    <tr class="border-b" style="border-color: var(--border-strong);">
                        <td class="px-4 py-3 font-medium">
                            Owner
                            <span class="ml-2 text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-700">always full access</span>
                        </td>
                        @foreach($actions as $a)
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" checked disabled class="opacity-50 cursor-not-allowed">
                            </td>
                        @endforeach
                    </tr>
                    @foreach($roles as $role)
                        <tr class="border-b" style="border-color: var(--border-strong);">
                            <td class="px-4 py-3 capitalize font-medium">{{ $role }}</td>
                            @foreach($actions as $a)
                                @php $locked = \App\Modules\User\Services\WorkspaceRoleMatrix::isLocked($role, $a); @endphp
                                <td class="px-4 py-3 text-center">
                                    @if($locked)
                                        <input type="checkbox" checked disabled class="opacity-50 cursor-not-allowed"
                                               title="Locked — Admins always keep this access.">
                                        {{-- Locked cells still need to post a value so the matrix is intact. --}}
                                        <input type="hidden" name="matrix[{{ $role }}][{{ $a }}]" value="1">
                                    @else
                                        <input type="checkbox" name="matrix[{{ $role }}][{{ $a }}]" value="1"
                                               @checked($matrix[$role][$a] ?? false)>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between p-4 border-t" style="border-color: var(--border-strong);">
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded text-sm font-semibold hover:bg-primary-700">
                Save changes
            </button>
            <button type="button" onclick="document.getElementById('reset-roles-form').submit();"
                    class="text-sm opacity-70 hover:underline">
                <i class="fas fa-undo mr-1"></i> Reset to defaults
            </button>
        </div>
    </form>

    <form id="reset-roles-form" method="POST" action="{{ route('user.team.roles.reset') }}" class="hidden"
          onsubmit="return confirm('Reset every role back to the original defaults?')">
        @csrf
    </form>

    <div class="mt-8 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="px-4 py-3 border-b font-semibold" style="border-color: var(--border-strong);">
            Recent changes
        </div>
        @if(empty($audits))
            <div class="px-4 py-6 text-sm opacity-60">No changes recorded yet.</div>
        @else
            <ul class="divide-y" style="--tw-divide-opacity: 1;">
                @foreach($audits as $a)
                    <li class="px-4 py-3 text-sm border-t" style="border-color: var(--border-strong);">
                        <div class="flex items-center justify-between">
                            <span><strong>{{ $a['actor'] }}</strong>
                                {{ ($a['noop'] ?? false) ? 'reviewed permissions (no changes)' : 'updated permissions' }}
                            </span>
                            <span class="opacity-60 text-xs">{{ optional($a['when'])->diffForHumans() }}</span>
                        </div>
                        @if(!empty($a['lines']))
                            <ul class="mt-1 ml-4 list-disc text-xs opacity-80">
                                @foreach($a['lines'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
