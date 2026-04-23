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

    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Erase a single voter's history (GDPR-style takedown). Matches across
         every poll this creator owns, by email, user id, or fingerprint. --}}
    <div class="card-premium p-5 mb-6">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(239,68,68,0.15); color: #ef4444;">
                <i class="fas fa-user-slash"></i>
            </div>
            <div>
                <div class="text-sm font-semibold" style="color: var(--text-primary);">Erase a voter's poll history</div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Removes every vote tied to a single voter across all of your polls. Search by email, user id, or fingerprint.
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('user.links.poll-votes.erase-voter', [$link, $block]) }}"
              class="flex flex-col sm:flex-row gap-2"
              onsubmit="return window.themedConfirmSubmit(this, {title: 'Erase every vote from this voter?', message: 'Every poll vote matching this voter, across all your polls, will be permanently deleted. This cannot be undone.', confirmText: 'Erase votes', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
            @csrf
            <input type="text" name="identifier" required maxlength="255"
                   placeholder="email@example.com, user id, or fingerprint"
                   class="flex-1 px-3 py-2 rounded-lg text-sm"
                   style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center justify-center gap-1.5"
                    style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                <i class="fas fa-eraser"></i> Erase voter
            </button>
        </form>

        {{-- Recent erasures: proves a takedown happened, in-app and not just
             buried in the application log file. Full history lives at the
             dedicated audit screen linked at the bottom. --}}
        <div class="mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,0.06);">
            <div class="flex items-center justify-between mb-2">
                <div class="text-[11px] uppercase tracking-wider font-semibold" style="color: var(--text-muted);">
                    Recent erasures
                </div>
                <a href="{{ route('user.links.poll-votes.erasures', [$link, $block]) }}"
                   class="text-xs font-semibold inline-flex items-center gap-1 hover:underline" style="color: #a78bfa;">
                    Full history <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            </div>
            @if($recentErasures->isEmpty())
                <p class="text-xs" style="color: var(--text-muted);">
                    No voters have been erased yet. Each takedown will be recorded here.
                </p>
            @else
                <ul class="space-y-1.5">
                    @foreach($recentErasures as $e)
                        <li class="flex items-center justify-between gap-3 text-xs px-3 py-2 rounded-lg"
                            style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                            <div class="min-w-0 flex-1">
                                <span class="font-semibold" style="color: var(--text-primary);">{{ $e->identifier }}</span>
                                <span style="color: var(--text-muted);">— {{ $e->removed_count }} {{ \Illuminate\Support\Str::plural('vote', $e->removed_count) }} removed</span>
                            </div>
                            <span class="flex-shrink-0" style="color: var(--text-faint);" title="{{ $e->created_at?->toDateTimeString() }}">
                                {{ $e->created_at?->diffForHumans() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Reset history: each row is a snapshot captured immediately before
         the creator wiped this poll's votes. The most recent unrestored
         snapshot can be undone, recreating anonymized vote rows. --}}
    @if($resetSnapshots->isNotEmpty())
    <div class="card-premium p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <div>
                <div class="text-sm font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-1.5 opacity-70"></i> Reset history
                </div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Snapshots captured the moment "Reset votes" was clicked, so you can compare or restore.
                </div>
            </div>
            <span class="text-[11px] px-2 py-1 rounded-md" style="background: rgba(255,255,255,0.04); color: var(--text-muted);">
                Last {{ $resetSnapshots->count() }}
            </span>
        </div>
        <ul class="space-y-2">
            @foreach($resetSnapshots as $i => $snap)
                @php
                    $snapCounts = (array) $snap->counts;
                    // Only the newest still-unrestored snapshot is undoable
                    // so we can't double-restore older ones.
                    $canUndo = $i === 0 && $snap->restored_at === null;
                @endphp
                <li class="px-3 py-3 rounded-lg" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="text-xs" style="color: var(--text-primary);">
                            <span class="font-semibold">{{ $snap->total }} {{ \Illuminate\Support\Str::plural('vote', $snap->total) }}</span>
                            <span style="color: var(--text-muted);">
                                cleared by {{ $snap->creator?->name ?? 'a teammate' }}
                            </span>
                            <span style="color: var(--text-faint);" title="{{ $snap->reset_at?->toDateTimeString() }}">
                                · {{ $snap->reset_at?->diffForHumans() }}
                            </span>
                            @if($snap->restored_at)
                                <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                      style="background: rgba(16,185,129,0.15); color: #10b981;"
                                      title="Restored {{ $snap->restored_at->toDateTimeString() }}">
                                    Restored
                                </span>
                            @endif
                        </div>
                        @if($canUndo)
                            <form method="POST"
                                  action="{{ route('user.links.poll-votes.undo-reset', [$link, $block, $snap]) }}"
                                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Restore {{ $snap->total }} vote(s)?', message: 'Restored votes will be tagged as &ldquo;restored&rdquo; and will not have voter identities.', confirmText: 'Restore', confirmIcon: 'fa-rotate-left', iconClass: 'fa-rotate-left'})">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5"
                                        style="background: rgba(139,92,246,0.15); border: 1px solid rgba(139,92,246,0.25); color: #a78bfa;">
                                    <i class="fas fa-undo"></i> Undo last reset
                                </button>
                            </form>
                        @endif
                    </div>
                    @if(!empty($snapCounts))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($snapCounts as $optIdx => $cnt)
                                @php
                                    $label = $block->settings['options'][$optIdx] ?? ('Option ' . ((int) $optIdx + 1));
                                @endphp
                                <span class="text-[11px] px-2 py-1 rounded-md"
                                      style="background: rgba(139,92,246,0.10); color: #a78bfa; border: 1px solid rgba(139,92,246,0.18);">
                                    {{ $label }}: <span class="font-semibold">{{ (int) $cnt }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
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
                                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this vote?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
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
