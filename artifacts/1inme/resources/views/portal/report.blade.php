@extends('portal.layout')
@section('title', 'Performance')
@section('content')
<h1 class="text-xl font-bold mb-1">Performance · {{ $link->title ?: $link->slug }}</h1>
<p class="text-sm text-slate-500 mb-6">Recent snapshots for the link shared with you.</p>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-2">Period</th>
                <th class="text-right px-4 py-2">Views</th>
                <th class="text-right px-4 py-2">Clicks</th>
                <th class="text-right px-4 py-2">Unique</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($snapshots as $snap)
                <tr>
                    <td class="px-4 py-2 text-slate-500">{{ optional($snap->created_at ?? null)->format('M j, Y') ?: ('Snapshot #' . $snap->id) }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format((int) ($snap->views ?? 0)) }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format((int) ($snap->clicks ?? 0)) }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format((int) ($snap->unique_visitors ?? 0)) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No data yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
