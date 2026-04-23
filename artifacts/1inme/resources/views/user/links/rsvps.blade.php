@extends('user.layouts.app')

@section('title', 'RSVPs — ' . $link->title)

@section('content')
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'RSVPs',
        'subtitle' => $link->title,
        'icon' => 'fa-calendar-check',
        'back' => route('user.links.show', $link),
        'chips' => [
            ['icon' => 'fa-users text-emerald-400', 'text' => $counts['total'] . ' responses'],
        ],
        'actions' => [
            ['label' => 'Export CSV', 'url' => route('user.links.rsvps.export', $link), 'icon' => 'fa-download', 'class' => 'btn-primary'],
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

    {{-- Erase a single guest's RSVPs (GDPR-style takedown). Matches by
         email across every Event Invite link this creator owns. --}}
    <div class="card-premium p-5 mb-6">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(239,68,68,0.15); color: #ef4444;">
                <i class="fas fa-user-slash"></i>
            </div>
            <div>
                <div class="text-sm font-semibold" style="color: var(--text-primary);">Erase a guest's RSVP history</div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Removes every RSVP tied to a single guest across all of your event invites. Search by email.
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('user.links.rsvps.erase-voter', $link) }}"
              class="flex flex-col sm:flex-row gap-2"
              onsubmit="return window.themedConfirmSubmit(this, {title: 'Erase every RSVP from this guest?', message: 'Every RSVP matching this guest, across all your event invites, will be permanently deleted. This cannot be undone.', confirmText: 'Erase RSVPs', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
            @csrf
            <input type="email" name="identifier" required maxlength="255"
                   placeholder="email@example.com"
                   class="flex-1 px-3 py-2 rounded-lg text-sm"
                   style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center justify-center gap-1.5"
                    style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                <i class="fas fa-eraser"></i> Erase guest
            </button>
        </form>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $tiles = [
                ['Going (incl. plus-ones)', $counts['yes'],   '#10b981', 'fa-check'],
                ['Maybe',                   $counts['maybe'], '#f59e0b', 'fa-question'],
                ['Can\'t make it',          $counts['no'],    '#94a3b8', 'fa-times'],
                ['Total responses',         $counts['total'], '#a78bfa', 'fa-users'],
            ];
        @endphp
        @foreach($tiles as [$label, $value, $color, $icon])
            <div class="card-premium p-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] uppercase tracking-wider font-semibold" style="color: var(--text-muted);">{{ $label }}</span>
                    <i class="fas {{ $icon }} text-xs" style="color: {{ $color }}"></i>
                </div>
                <div class="text-2xl font-bold" style="color: {{ $color }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="card-premium overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint); border-bottom: 1px solid rgba(255,255,255,0.08);">
                        <th class="text-left font-semibold py-3 pl-5">Guest</th>
                        <th class="text-left font-semibold py-3">Contact</th>
                        <th class="text-left font-semibold py-3">Response</th>
                        <th class="text-left font-semibold py-3">+1s</th>
                        <th class="text-left font-semibold py-3">Source</th>
                        <th class="text-left font-semibold py-3">Submitted</th>
                        <th class="text-right font-semibold py-3 pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rsvps as $r)
                        @php
                            $rmap = ['yes'=>['Going','#10b981'],'maybe'=>['Maybe','#f59e0b'],'no'=>['Not going','#94a3b8']];
                            [$rlabel, $rcolor] = $rmap[$r->response] ?? [$r->response, '#a78bfa'];
                        @endphp
                        <tr style="border-top: 1px solid rgba(255,255,255,0.06);">
                            <td class="py-3 pl-5">
                                <div class="font-semibold" style="color: var(--text-primary);">{{ $r->name ?: '—' }}</div>
                                @if($r->message)
                                    <div class="text-xs italic mt-0.5" style="color: var(--text-muted);">"{{ \Illuminate\Support\Str::limit($r->message, 80) }}"</div>
                                @endif
                            </td>
                            <td class="py-3 text-xs" style="color: var(--text-muted);">
                                @if($r->email)<div><i class="far fa-envelope mr-1 opacity-60"></i>{{ $r->email }}</div>@endif
                                @if($r->phone)<div><i class="fas fa-phone mr-1 opacity-60"></i>{{ $r->phone }}</div>@endif
                            </td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider" style="background: {{ $rcolor }}1f; color: {{ $rcolor }}">
                                    {{ $rlabel }}
                                </span>
                            </td>
                            <td class="py-3" style="color: var(--text-primary);">{{ $r->plus_ones }}</td>
                            <td class="py-3 text-xs capitalize" style="color: var(--text-muted);">{{ str_replace('_',' ', $r->source) }}</td>
                            <td class="py-3 text-xs" style="color: var(--text-muted);">{{ $r->created_at?->diffForHumans() }}</td>
                            <td class="py-3 pr-5 text-right">
                                <form method="POST" action="{{ route('user.links.rsvps.destroy', [$link, $r]) }}" class="inline-block"
                                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this RSVP?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                    @csrf @method('DELETE')
                                    <button class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)" onmouseover="this.style.background='rgba(239,68,68,.20)'" onmouseout="this.style.background='rgba(239,68,68,.10)'" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-12">
                                <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, rgba(236,72,153,0.18), rgba(139,92,246,0.18));">
                                    <i class="fas fa-inbox text-2xl text-violet-400"></i>
                                </div>
                                <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No RSVPs yet</p>
                                <p class="text-xs" style="color: var(--text-muted);">Share your event link to start collecting responses.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($rsvps->hasPages())
        <div class="mt-4">{{ $rsvps->links() }}</div>
    @endif
</div>
@endsection
