@extends('user.layouts.app')

@section('title', 'Poll voter erasures — ' . $link->title)

@section('content')
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Poll voter erasures',
        'subtitle' => 'Every GDPR-style takedown you have run, across all your polls.',
        'icon' => 'fa-user-slash',
        'back' => route('user.links.poll-votes.index', [$link, $block]),
        'chips' => [
            ['icon' => 'fa-shield-alt text-blue-400', 'text' => $erasures->total() . ' total ' . \Illuminate\Support\Str::plural('erasure', $erasures->total())],
        ],
    ])

    <div class="card-premium overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint); border-bottom: 1px solid rgba(255,255,255,0.08);">
                        <th class="text-left font-semibold py-3 pl-5">When</th>
                        <th class="text-left font-semibold py-3">Identifier</th>
                        <th class="text-left font-semibold py-3">From poll</th>
                        <th class="text-right font-semibold py-3">Votes removed</th>
                        <th class="text-left font-semibold py-3 pl-6 pr-5">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($erasures as $e)
                        <tr style="border-top: 1px solid rgba(255,255,255,0.06);">
                            <td class="py-3 pl-5 text-xs" style="color: var(--text-muted);"
                                title="{{ $e->created_at?->toDateTimeString() }}">
                                {{ $e->created_at?->diffForHumans() }}
                            </td>
                            <td class="py-3 text-xs">
                                <span class="font-semibold" style="color: var(--text-primary);">{{ $e->identifier }}</span>
                            </td>
                            <td class="py-3 text-xs" style="color: var(--text-muted);">
                                @if($e->link && $e->block_id)
                                    <a href="{{ route('user.links.poll-votes.index', [$e->link_id, $e->block_id]) }}"
                                       class="hover:underline" style="color: #90acff;">
                                        {{ $e->link->title ?: $e->link->alias }}
                                    </a>
                                @else
                                    <span style="color: var(--text-faint);">— deleted poll —</span>
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider" style="background: rgba(239,68,68,0.15); color: #ef4444;">
                                    {{ $e->removed_count }}
                                </span>
                            </td>
                            <td class="py-3 pl-6 pr-5 text-xs" style="color: var(--text-faint);">{{ $e->ip_address ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-12">
                                <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, rgba(239,68,68,0.18), rgba(92,131,255,0.18));">
                                    <i class="fas fa-user-slash text-2xl text-blue-400"></i>
                                </div>
                                <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No erasures yet</p>
                                <p class="text-xs" style="color: var(--text-muted);">When you erase a voter from any of your polls, a record will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($erasures->hasPages())
        <div class="mt-4">{{ $erasures->links() }}</div>
    @endif
</div>
@endsection
