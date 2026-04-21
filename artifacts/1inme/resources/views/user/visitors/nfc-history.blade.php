@extends('user.layouts.app')
@section('title', 'NFC Writes')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">NFC Write History</h1>
            <p class="text-sm" style="color: var(--text-muted);">{{ $link->title ?? $link->alias }} · {{ number_format($total) }} writes</p>
        </div>
        <a href="{{ route('user.links.visitors', $link) }}" class="text-sm px-3 py-1.5 rounded-lg border font-semibold" style="border-color: var(--border-soft); color: var(--text-primary);">← Back to visitors</a>
    </div>

    <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft);">
        @if($writes->isEmpty())
            <p class="text-sm" style="color: var(--text-muted);">No NFC writes yet. Use the 1INME mobile app's NFC writer to encode this link onto a physical NFC tag — every write you perform will appear here with the device, platform, and tag details.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase" style="color: var(--text-faint);">
                            <th class="py-2 pr-4">When</th>
                            <th class="py-2 pr-4">Label</th>
                            <th class="py-2 pr-4">URL written</th>
                            <th class="py-2 pr-4">Tag</th>
                            <th class="py-2 pr-4">Device</th>
                            <th class="py-2 pr-4">Source</th>
                            <th class="py-2 pr-4">Location</th>
                            <th class="py-2">Platform</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($writes as $w)
                            <tr class="border-t" style="border-color: var(--border-soft);">
                                <td class="py-2 pr-4 text-xs" style="color: var(--text-faint);" title="{{ ($w->written_at ?? $w->created_at)?->toIso8601String() }}">
                                    {{ ($w->written_at ?? $w->created_at)?->diffForHumans() }}
                                </td>
                                <td class="py-2 pr-4" style="color: var(--text-primary);">{{ $w->label ?: '—' }}</td>
                                <td class="py-2 pr-4 truncate max-w-xs" style="color: var(--text-muted);" title="{{ $w->written_url }}">{{ $w->written_url }}</td>
                                <td class="py-2 pr-4 text-xs" style="color: var(--text-muted);">
                                    @if($w->tag_type) {{ $w->tag_type }}@endif
                                    @if($w->locked) <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-bold">LOCKED</span>@endif
                                </td>
                                <td class="py-2 pr-4 text-xs" style="color: var(--text-muted);">{{ $w->device_label ?: $w->device ?: '—' }}</td>
                                <td class="py-2 pr-4 text-xs" style="color: var(--text-muted);">{{ ucfirst($w->source ?? 'mobile') }}</td>
                                <td class="py-2 pr-4 text-xs" style="color: var(--text-muted);">
                                    @if($w->lat !== null && $w->lng !== null)
                                        <a class="underline" target="_blank" rel="noopener"
                                           href="https://maps.google.com/?q={{ $w->lat }},{{ $w->lng }}"
                                           title="{{ $w->lat }}, {{ $w->lng }}">
                                            {{ number_format((float) $w->lat, 3) }}, {{ number_format((float) $w->lng, 3) }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 text-xs" style="color: var(--text-muted);">{{ ucfirst($w->platform ?? '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $writes->links() }}</div>
        @endif
    </div>
</div>
@endsection
