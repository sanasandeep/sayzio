@extends('admin.layouts.app')
@section('title', 'Banned Names')
@section('page-title', 'Banned Names')

@section('content')
<div class="max-w-5xl space-y-6">

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">Reserved &amp; banned names</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                    Names on this list cannot be claimed as a profile handle or as any link alias
                    (regular, file, calendar or contact). Matching is case-insensitive. Existing
                    handles/aliases are not retroactively renamed, the conflict count below shows
                    where current values already match a banned entry.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.banned-names.bulk') }}"
                   class="px-3 py-2 rounded-xl text-sm font-medium bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 inline-flex items-center gap-2 ak-strong">
                    <i class="fas fa-file-import text-xs"></i> Bulk import
                </a>
                <form method="POST" action="{{ route('admin.banned-names.restore-defaults') }}"
                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Re-apply the curated default list?', message: 'Existing entries are kept untouched, only missing defaults will be added.', confirmText: 'Re-apply defaults', confirmIcon: 'fa-rotate-left', iconClass: 'fa-rotate-left'})"
                      class="inline">
                    @csrf
                    <button type="submit"
                            title="Re-insert any default reserved names that aren't on the list yet. Existing entries are not modified."
                            class="px-3 py-2 rounded-xl text-sm font-medium bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 inline-flex items-center gap-2 ak-strong">
                        <i class="fas fa-rotate-left text-xs"></i> Restore defaults
                    </button>
                </form>
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                            class="px-3 py-2 rounded-xl text-sm font-medium bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 inline-flex items-center gap-2 ak-strong">
                        <i class="fas fa-file-export text-xs"></i> Export
                        <i class="fas fa-chevron-down text-[10px] opacity-60"></i>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 mt-1 w-40 rounded-xl bg-zinc-900 border border-white/10 shadow-lg z-10 overflow-hidden">
                        <a href="{{ route('admin.banned-names.export', ['format' => 'csv']) }}"
                           class="block px-3 py-2 text-sm text-white/80 hover:bg-white/5 ak-strong">Download CSV</a>
                        <a href="{{ route('admin.banned-names.export', ['format' => 'json']) }}"
                           class="block px-3 py-2 text-sm text-white/80 hover:bg-white/5 ak-strong">Download JSON</a>
                    </div>
                </div>
                <a href="{{ route('admin.banned-names.create') }}"
                   class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-2">
                    <i class="fas fa-plus text-xs"></i> Add name
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif

    @php
        $bulkImported   = session('bulk_imported', []);
        $bulkDuplicates = session('bulk_duplicates', []);
        $bulkRejected   = session('bulk_rejected', []);
    @endphp
    @if(!empty($bulkImported) || !empty($bulkDuplicates) || !empty($bulkRejected))
        <div class="glass rounded-2xl border border-white/10 p-5 space-y-3">
            <div class="text-sm font-semibold text-white/80 ak-strong">Import results</div>
            @if(!empty($bulkImported))
                <details class="text-xs text-white/70 ak-strong">
                    <summary class="cursor-pointer text-emerald-300 ak-green">
                        Imported {{ count($bulkImported) }} name{{ count($bulkImported) === 1 ? '' : 's' }}
                    </summary>
                    <div class="mt-2 font-mono text-white/60 break-words ak-muted">{{ implode(', ', $bulkImported) }}</div>
                </details>
            @endif
            @if(!empty($bulkDuplicates))
                <details class="text-xs text-white/70 ak-strong">
                    <summary class="cursor-pointer text-amber-300 ak-amber">
                        Skipped {{ count($bulkDuplicates) }} duplicate{{ count($bulkDuplicates) === 1 ? '' : 's' }}
                    </summary>
                    <div class="mt-2 font-mono text-white/60 break-words ak-muted">{{ implode(', ', $bulkDuplicates) }}</div>
                </details>
            @endif
            @if(!empty($bulkRejected))
                <details class="text-xs text-white/70 ak-strong" open>
                    <summary class="cursor-pointer text-rose-300 ak-red">
                        Rejected {{ count($bulkRejected) }} entr{{ count($bulkRejected) === 1 ? 'y' : 'ies' }}
                    </summary>
                    <ul class="mt-2 space-y-1">
                        @foreach($bulkRejected as $r)
                            <li class="text-white/70 ak-strong">
                                <span class="font-mono text-white/80 ak-strong">{{ $r['name'] }}</span>
                                <span class="text-white/40 ak-note"> - {{ $r['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    @if(!empty($defaultConflicts))
        <div class="glass rounded-2xl border border-amber-500/20 p-5 space-y-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <div class="text-sm font-semibold text-amber-200 inline-flex items-center gap-2 ak-amber">
                        <i class="fas fa-triangle-exclamation"></i>
                        Default-list conflicts
                    </div>
                    <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                        These names ship in the default reserved-handle list but are already
                        claimed on this install. They are <em>not</em> retroactively renamed,
                        review each one and decide whether to rename, contact, or grandfather
                        the existing owner.
                    </p>
                </div>
                <span class="text-[11px] px-2 py-1 rounded-full bg-amber-500/15 text-amber-200 border border-amber-500/30 ak-amber">
                    {{ count($defaultConflicts) }} name{{ count($defaultConflicts) === 1 ? '' : 's' }} conflict
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-white/5">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wider text-white/40 bg-white/[0.02] ak-note">
                        <tr>
                            <th class="px-4 py-2 text-left">Default name</th>
                            <th class="px-4 py-2 text-left">Conflicting record(s)</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($defaultConflicts as $row)
                        <tr class="border-t border-white/5 align-top">
                            <td class="px-4 py-3 font-mono text-white/90 whitespace-nowrap ak-strong">
                                {{ $row['name'] }}
                            </td>
                            <td class="px-4 py-3">
                                <ul class="space-y-1.5">
                                    @foreach($row['users'] as $u)
                                        <li class="text-xs text-white/70 flex items-center gap-2 flex-wrap ak-strong">
                                            <span class="px-1.5 py-0.5 rounded bg-blue-500/15 border border-blue-500/30 text-blue-200 text-[10px] uppercase tracking-wide ak-blue">user</span>
                                            <a href="{{ route('admin.users.show', $u) }}"
                                               class="font-mono text-white/90 hover:text-blue-200 underline-offset-2 hover:underline ak-strong">
                                                {{ '@' . $u->handle }}
                                            </a>
                                            <span class="text-white/40 ak-note">·</span>
                                            <span class="text-white/60 ak-muted">{{ $u->name ?: 'Unnamed user' }}</span>
                                            @if($u->email)
                                                <span class="text-white/30 ak-note">{{ $u->email }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                    @foreach($row['links'] as $l)
                                        <li class="text-xs text-white/70 flex items-center gap-2 flex-wrap ak-strong">
                                            <span class="px-1.5 py-0.5 rounded bg-sky-500/15 border border-sky-500/30 text-sky-200 text-[10px] uppercase tracking-wide ak-blue">link</span>
                                            <a href="{{ route('admin.links.show', $l) }}"
                                               class="font-mono text-white/90 hover:text-sky-200 underline-offset-2 hover:underline ak-strong">
                                                /{{ $l->alias }}
                                            </a>
                                            <span class="text-white/40 ak-note">·</span>
                                            <span class="text-white/60 ak-muted">{{ $l->title ?: ucfirst((string) $l->type) . ' link' }}</span>
                                            @if($l->user)
                                                <span class="text-white/30 ak-note">owned by {{ '@' . $l->user->handle }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                    @foreach($row['extras'] as $a)
                                        <li class="text-xs text-white/70 flex items-center gap-2 flex-wrap ak-strong">
                                            <span class="px-1.5 py-0.5 rounded bg-teal-500/15 border border-teal-500/30 text-teal-200 text-[10px] uppercase tracking-wide ak-green">extra alias</span>
                                            @if($a->link)
                                                <a href="{{ route('admin.links.show', $a->link) }}"
                                                   class="font-mono text-white/90 hover:text-teal-200 underline-offset-2 hover:underline ak-strong">
                                                    /{{ $a->alias }}
                                                </a>
                                                <span class="text-white/40 ak-note">·</span>
                                                <span class="text-white/60 ak-muted">on {{ $a->link->title ?: ('/' . $a->link->alias) }}</span>
                                                @if($a->link->user)
                                                    <span class="text-white/30 ak-note">owned by {{ '@' . $a->link->user->handle }}</span>
                                                @endif
                                            @else
                                                <span class="font-mono text-white/90 ak-strong">/{{ $a->alias }}</span>
                                                <span class="text-white/40 ak-note">(orphaned)</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        @if($items->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-white/40 ak-note">
                <i class="fas fa-ban text-2xl text-white/20 mb-3 ak-note"></i>
                <div>No banned names yet. Add one to start reserving handles &amp; aliases.</div>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wider text-white/40 bg-white/[0.02] ak-note">
                    <tr>
                        <th class="px-5 py-3 text-left">Name</th>
                        <th class="px-5 py-3 text-left">Note</th>
                        <th class="px-5 py-3 text-center">Force rename<br><span class="text-[9px] normal-case text-white/30 ak-note">on next login</span></th>
                        <th class="px-5 py-3 text-right">Existing conflicts</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    @php $c = $conflicts[$item->id] ?? ['users'=>0,'links'=>0,'extras'=>0]; @endphp
                    @php $totalC = $c['users'] + $c['links'] + $c['extras']; @endphp
                    <tr class="border-t border-white/5">
                        <td class="px-5 py-3 font-mono text-white/90 ak-strong">{{ $item->name }}</td>
                        <td class="px-5 py-3 text-white/60 ak-muted">{{ $item->note ?: '—' }}</td>
                        <td class="px-5 py-3 text-center">
                            <form method="POST" action="{{ route('admin.banned-names.toggle-force-rename', $item) }}" class="inline">
                                @csrf
                                @if($item->force_rename_on_login)
                                    <button type="submit"
                                            title="Currently ON: affected users are prompted on next login. Click to disable."
                                            class="px-2 py-1 rounded-lg text-[11px] bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-200 border border-emerald-500/30 inline-flex items-center gap-1 ak-green">
                                        <i class="fas fa-toggle-on"></i> On
                                    </button>
                                @else
                                    <button type="submit"
                                            title="Currently OFF: affected users keep their handle. Click to require a rename on next login."
                                            class="px-2 py-1 rounded-lg text-[11px] bg-white/5 hover:bg-white/10 text-white/50 border border-white/10 inline-flex items-center gap-1 ak-muted">
                                        <i class="fas fa-toggle-off"></i> Off
                                    </button>
                                @endif
                            </form>
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if($totalC === 0)
                                <span class="text-white/30 text-xs ak-note">none</span>
                            @else
                                <a href="{{ route('admin.banned-names.conflicts', $item) }}"
                                   class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-200 ak-amber"
                                   title="View and act on the {{ $totalC }} conflicting record{{ $totalC === 1 ? '' : 's' }}">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    {{ $totalC }} match{{ $totalC === 1 ? '' : 'es' }}
                                    <i class="fas fa-arrow-right text-[10px]"></i>
                                </a>
                                <div class="text-[10px] text-white/40 mt-1 ak-note">
                                    {{ $c['users'] }} handle{{ $c['users'] === 1 ? '' : 's' }} ·
                                    {{ $c['links'] }} primary ·
                                    {{ $c['extras'] }} extra
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.banned-names.edit', $item) }}"
                                   class="px-2.5 py-1.5 rounded-lg text-xs bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 ak-strong">
                                    <i class="fas fa-pen text-[10px]"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.banned-names.destroy', $item) }}"
                                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove “{{ $item->name }}” from the banned list?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="px-2.5 py-1.5 rounded-lg text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 ak-red">
                                        <i class="fas fa-trash text-[10px]"></i> Remove
                                    </button>
                                </form>
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
