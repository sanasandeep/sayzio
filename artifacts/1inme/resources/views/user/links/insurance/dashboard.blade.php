@extends('layouts.user')

@section('title', 'Link Health')

@section('content')
<div class="max-w-6xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-1">Link Health</h1>
    <p class="mb-6" style="color: var(--text-muted);">All short links with Link Insurance enabled. Failed-over links are listed first.</p>

    @if ($links->isEmpty())
        <div class="p-8 text-center border rounded" style="background: var(--bg-card); border-color: var(--border-glass);">
            <p style="color: var(--text-muted);">No links have Link Insurance enabled yet.</p>
            <a href="{{ route('user.links.index') }}" class="inline-block mt-3 text-blue-600">Go to your links →</a>
        </div>
    @else
        <div class="border rounded overflow-hidden" style="background: var(--bg-card); border-color: var(--border-glass);">
            <table class="w-full text-sm">
                <thead class="text-left" style="background: var(--bg-glass); color: var(--text-muted);">
                    <tr>
                        <th class="px-4 py-3">Link</th>
                        <th class="px-4 py-3">State</th>
                        <th class="px-4 py-3">30d uptime</th>
                        <th class="px-4 py-3">Last checked</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($links as $l)
                        @php $u = $uptime->get($l->id); @endphp
                        <tr class="border-t">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $l->title ?: '/'.$l->alias }}</div>
                                <div class="text-xs" style="color: var(--text-muted);">/{{ $l->alias }} → {{ $l->long_url }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs
                                    @if($l->insurance_state === 'primary') bg-green-100 text-green-800
                                    @elseif($l->insurance_state === 'failover') bg-amber-100 text-amber-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($l->insurance_state) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($u && $u->sample_count > 0)
                                    {{ number_format($u->uptime_ratio * 100, 2) }}%
                                    <span class="text-xs" style="color: var(--text-muted);">({{ $u->sample_count }})</span>
                                @else
                                    <span style="color: var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3" style="color: var(--text-muted);">
                                {{ $l->insurance_last_checked_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('user.links.insurance.settings', $l->id) }}" class="text-blue-600">Configure</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $links->links() }}</div>
    @endif
</div>
@endsection
