@extends('user.layouts.app')

@section('title', 'Preview import')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Preview before importing',
        'subtitle' => 'Review the rows we parsed from ' . $originalName . '. Nothing has been saved yet — confirm to create the contacts, or cancel to discard the upload.',
        'icon' => 'fa-table',
        'chips' => [
            ['icon' => 'fa-list text-cyan-400',           'text' => $stats['total'] . ' rows parsed'],
            ['icon' => 'fa-triangle-exclamation text-amber-400', 'text' => $stats['warnings'] . ' with warnings'],
            ['icon' => 'fa-database text-cyan-400',       'text' => $stats['remaining'] . ' slots remaining'],
        ],
    ])

    @if($stats['overCap'] > 0)
    <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
        <i class="fas fa-exclamation-triangle mr-1.5"></i>
        Only {{ $stats['remaining'] }} slot(s) left — {{ $stats['overCap'] }} row(s) at the bottom of the file will be skipped if you confirm.
    </div>
    @endif

    <div class="card-premium p-5 mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase" style="color:var(--text-faint);">
                        <th class="py-2 pr-3">Row</th>
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2 pr-3">Phone</th>
                        <th class="py-2 pr-3">Email</th>
                        <th class="py-2 pr-3">Organization</th>
                        <th class="py-2">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $name = $row['display_name'] ?: trim(($row['given_name'] ?? '') . ' ' . ($row['family_name'] ?? ''));
                            $phones = collect($row['phones'] ?? [])->pluck('value')->filter()->values();
                            $emails = collect($row['emails'] ?? [])->pluck('value')->filter()->values();
                            $hasWarn = !empty($row['warnings']);
                        @endphp
                        <tr style="border-top:1px solid rgba(255,255,255,.06); {{ $hasWarn ? 'background:rgba(245,158,11,0.05);' : '' }}">
                            <td class="py-2 pr-3 font-mono text-xs align-top" style="color:var(--text-muted);">#{{ $row['source_line'] ?? '?' }}</td>
                            <td class="py-2 pr-3 align-top" style="color:var(--text-primary);">
                                {{ $name !== '' ? $name : '—' }}
                            </td>
                            <td class="py-2 pr-3 align-top text-xs" style="color:var(--text-muted);">
                                @forelse($phones as $p)
                                    <div>{{ $p }}</div>
                                @empty
                                    <span style="color:var(--text-faint);">—</span>
                                @endforelse
                            </td>
                            <td class="py-2 pr-3 align-top text-xs" style="color:var(--text-muted);">
                                @forelse($emails as $e)
                                    <div>{{ $e }}</div>
                                @empty
                                    <span style="color:var(--text-faint);">—</span>
                                @endforelse
                            </td>
                            <td class="py-2 pr-3 align-top text-xs" style="color:var(--text-muted);">
                                {{ $row['organization'] ?? '—' }}
                            </td>
                            <td class="py-2 align-top text-xs">
                                @if($hasWarn)
                                    @foreach($row['warnings'] as $w)
                                        <div style="color:#f59e0b;"><i class="fas fa-triangle-exclamation mr-1"></i>{{ $w }}</div>
                                    @endforeach
                                @else
                                    <span style="color:#34d399;"><i class="fas fa-check mr-1"></i>Looks good</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>

    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('user.contacts.import.confirm', ['token' => $token]) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                <i class="fas fa-check mr-1"></i> Confirm import
            </button>
        </form>
        <form method="POST" action="{{ route('user.contacts.import.cancel', ['token' => $token]) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background:rgba(255,255,255,.06);color:var(--text-muted);border:1px solid rgba(255,255,255,.08);">
                Cancel
            </button>
        </form>
    </div>
</div>
@endsection
