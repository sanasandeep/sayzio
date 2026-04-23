@extends('user.layouts.app')

@section('title', 'Poll votes — ' . $link->title)

@section('content')
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Poll votes',
        'subtitle' => $link->title . ' — ' . $question,
        'icon' => 'fa-poll',
        'back' => route('user.links.show', $link),
        'chips' => [
            ['icon' => 'fa-users text-emerald-400', 'text' => $total . ' votes'],
            ['icon' => 'fa-shield-alt text-violet-400', 'text' => 'Dedupe: 1 vote per voter'],
        ],
        'actions' => [
            ['label' => 'Export CSV', 'url' => route('user.links.poll-votes.export', [$link, $block]), 'icon' => 'fa-download', 'class' => 'btn-primary'],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Per-option breakdown --}}
    <div class="card-premium p-5 mb-6">
        <div class="text-[11px] uppercase tracking-wider font-semibold mb-4" style="color: var(--text-muted);">
            Results breakdown
        </div>
        @if(empty($breakdown))
            <p class="text-sm" style="color: var(--text-muted);">This poll has no options configured.</p>
        @else
            <div class="space-y-3">
                @foreach($breakdown as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $row['label'] ?: ('Option ' . ($row['index'] + 1)) }}</span>
                            <span style="color: var(--text-muted);">{{ $row['count'] }} ({{ $row['pct'] }}%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full overflow-hidden" style="background: rgba(255,255,255,0.06);">
                            <div class="h-full rounded-full" style="width: {{ $row['pct'] }}%; background: linear-gradient(90deg, #8b5cf6, #ec4899);"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card-premium overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint); border-bottom: 1px solid rgba(255,255,255,0.08);">
                        <th class="text-left font-semibold py-3 pl-5">Option</th>
                        <th class="text-left font-semibold py-3">Voter</th>
                        <th class="text-left font-semibold py-3">Source</th>
                        <th class="text-left font-semibold py-3">Submitted</th>
                        <th class="text-right font-semibold py-3 pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($votes as $v)
                        <tr style="border-top: 1px solid rgba(255,255,255,0.06);">
                            <td class="py-3 pl-5">
                                <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider" style="background: rgba(139,92,246,0.18); color: #a78bfa;">
                                    {{ $v->option_label ?: ('Option ' . ($v->option_index + 1)) }}
                                </span>
                            </td>
                            <td class="py-3 text-xs" style="color: var(--text-muted);">
                                @if($v->user)
                                    <div class="font-semibold" style="color: var(--text-primary);">{{ $v->user->name }}</div>
                                    <div><i class="far fa-envelope mr-1 opacity-60"></i>{{ $v->user->email }}</div>
                                @else
                                    <div class="font-semibold" style="color: var(--text-primary);">Anonymous</div>
                                    <div title="Fingerprint used to dedupe repeat votes">
                                        <i class="fas fa-fingerprint mr-1 opacity-60"></i>{{ \Illuminate\Support\Str::limit($v->voter_fingerprint, 16) }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 text-xs capitalize" style="color: var(--text-muted);">{{ str_replace('_',' ', $v->source) }}</td>
                            <td class="py-3 text-xs" style="color: var(--text-muted);">{{ $v->created_at?->diffForHumans() }}</td>
                            <td class="py-3 pr-5 text-right">
                                <form method="POST" action="{{ route('user.links.poll-votes.destroy', [$link, $block, $v]) }}" class="inline-block"
                                      onsubmit="return confirm('Remove this vote?')">
                                    @csrf @method('DELETE')
                                    <button class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)" onmouseover="this.style.background='rgba(239,68,68,.20)'" onmouseout="this.style.background='rgba(239,68,68,.10)'" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-12">
                                <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, rgba(236,72,153,0.18), rgba(139,92,246,0.18));">
                                    <i class="fas fa-poll text-2xl text-violet-400"></i>
                                </div>
                                <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No votes yet</p>
                                <p class="text-xs" style="color: var(--text-muted);">Share your biolink to start collecting poll responses.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($votes->hasPages())
        <div class="mt-4">{{ $votes->links() }}</div>
    @endif
</div>
@endsection
