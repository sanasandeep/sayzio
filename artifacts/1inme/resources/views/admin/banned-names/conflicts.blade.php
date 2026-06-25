@extends('admin.layouts.app')
@section('title', 'Conflicts: ' . $item->name)
@section('page-title', 'Conflicts for "' . $item->name . '"')

@section('content')
<div class="max-w-5xl space-y-6">

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <a href="{{ route('admin.banned-names.index') }}"
                   class="text-xs text-white/40 hover:text-white/70 inline-flex items-center gap-1">
                    <i class="fas fa-arrow-left text-[10px]"></i> Back to banned names
                </a>
                <h2 class="text-lg font-semibold text-white/90 mt-2">
                    Existing matches for <span class="font-mono text-amber-200">{{ $item->name }}</span>
                </h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl">
                    These users and link aliases were created before <span class="font-mono">{{ $item->name }}</span>
                    was added to the banned list. New signups can no longer claim it, but existing values
                    aren't renamed automatically — review them below. You can acknowledge each row to clear
                    it from the badge, nudge the owner, or rename / remove it directly.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.banned-names.toggle-force-rename', $item) }}">
                @csrf
                @if($item->force_rename_on_login)
                    <button type="submit"
                            class="px-3 py-2 rounded-xl text-xs font-medium bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-200 border border-emerald-500/30 inline-flex items-center gap-2"
                            title="Affected users are bounced to profile-edit on next login. Click to disable.">
                        <i class="fas fa-toggle-on"></i> Force rename on next login
                    </button>
                @else
                    <button type="submit"
                            class="px-3 py-2 rounded-xl text-xs font-medium bg-white/5 hover:bg-white/10 text-white/70 border border-white/10 inline-flex items-center gap-2"
                            title="Click to require a rename from each affected user on their next sign-in.">
                        <i class="fas fa-toggle-off"></i> Force rename on next login
                    </button>
                @endif
            </form>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        @if(empty($rows))
            <div class="px-6 py-12 text-center text-sm text-white/40">
                <i class="fas fa-circle-check text-2xl text-emerald-400/40 mb-3"></i>
                <div>No existing values currently match this banned name.</div>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wider text-white/40 bg-white/[0.02]">
                    <tr>
                        <th class="px-5 py-3 text-left">Kind</th>
                        <th class="px-5 py-3 text-left">Value</th>
                        <th class="px-5 py-3 text-left">Detail</th>
                        <th class="px-5 py-3 text-left">Owner</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr class="border-t border-white/5 align-top {{ $row['acknowledged'] ? 'opacity-60' : '' }}">
                        <td class="px-5 py-3">
                            @if($row['kind'] === 'user')
                                <span class="text-xs px-2 py-0.5 rounded bg-blue-500/15 text-blue-200 border border-blue-500/30">handle</span>
                            @elseif($row['kind'] === 'link')
                                <span class="text-xs px-2 py-0.5 rounded bg-sky-500/15 text-sky-200 border border-sky-500/30">primary alias</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded bg-teal-500/15 text-teal-200 border border-teal-500/30">extra alias</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 font-mono text-white/90">{{ $row['label'] }}</td>
                        <td class="px-5 py-3 text-white/60 text-xs">{{ $row['detail'] }}</td>
                        <td class="px-5 py-3 text-white/70 text-xs">
                            @if($row['owner'])
                                <div>{{ $row['owner']->name ?: 'Unnamed' }}</div>
                                <div class="text-white/40">{{ $row['owner']->email }}</div>
                            @else
                                <span class="text-white/30">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex flex-col items-end gap-2">
                                <div class="inline-flex items-center gap-2 flex-wrap justify-end">
                                    @if($row['kind'] === 'user' && $row['owner'])
                                        <form method="POST" action="{{ route('admin.banned-names.notify-user', [$item, $row['owner']->id]) }}" class="inline"
                                              onsubmit="return window.themedConfirmSubmit(this, {title: 'Notify {{ $row['owner']->name ?: $row['label'] }}?', message: 'A system notification will be sent asking them to change their handle.', confirmText: 'Send notification', confirmIcon: 'fa-bell', iconClass: 'fa-bell'})">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 rounded-lg text-xs bg-blue-500/15 hover:bg-blue-500/25 text-blue-200 border border-blue-500/30">
                                                <i class="fas fa-bell text-[10px]"></i> Notify user
                                            </button>
                                        </form>
                                    @endif

                                    @if($row['acknowledged'])
                                        <form method="POST" action="{{ route('admin.banned-names.unacknowledge', $item) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="conflict_type" value="{{ $row['kind'] }}">
                                            <input type="hidden" name="conflict_id" value="{{ $row['id'] }}">
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 rounded-lg text-xs bg-white/5 hover:bg-white/10 text-white/60 border border-white/10"
                                                    title="Acknowledged {{ $row['acknowledged']->diffForHumans() }} — click to re-open.">
                                                <i class="fas fa-rotate-left text-[10px]"></i> Re-open
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.banned-names.acknowledge', $item) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="conflict_type" value="{{ $row['kind'] }}">
                                            <input type="hidden" name="conflict_id" value="{{ $row['id'] }}">
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 rounded-lg text-xs bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-200 border border-emerald-500/30">
                                                <i class="fas fa-check text-[10px]"></i> Acknowledge
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                @include('admin.banned-names._resolve_form', [
                                    'type'        => $row['kind'],
                                    'id'          => $row['id'],
                                    'allowRemove' => $row['kind'] !== 'link',
                                    'removeLabel' => $row['kind'] === 'user' ? 'Clear handle' : 'Delete alias',
                                ])
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
