@extends('user.layouts.app')
@section('title', ($follower->name ?: 'Follower') . ' · ' . ($link->title ?: $link->alias))

@section('content')
@push('styles')
<style>
    .period-bar { background: var(--bg-glass); border: 1px solid var(--border-glass); border-radius: 18px; padding: 10px 14px; backdrop-filter: blur(20px); }
    .pill { padding: 7px 13px; border-radius: 11px; font-size: 11px; font-weight: 600; transition: all .2s ease; color: var(--text-muted); }
    .pill:hover { background: var(--bg-glass-hover); color: var(--text-primary); }
    .pill-active { background: linear-gradient(135deg, #3d6bff, #5c83ff); color: #fff !important; box-shadow: 0 6px 18px rgba(61,107,255,0.4); }
    .section-card { position: relative; background: var(--bg-glass); border: 1px solid var(--border-glass); border-radius: 14px; padding: 28px 32px; overflow: hidden; }
    .section-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, #3d6bff, #ec4899); opacity: 0.7; }
    .fancy-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .fancy-table thead th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-faint); padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border-glass-light); }
    .fancy-table tbody td { padding: 11px 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-glass); }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; background: rgba(61,107,255,0.15); color: #dbe4ff; border: 1px solid rgba(61,107,255,0.3); }
</style>
@endpush

@php
    $heroActions = [
        ['label' => 'Back to Followers', 'url' => route('user.links.followers', $link), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
    ];
@endphp
@include('user.partials.page-hero', [
    'title'    => ($follower->name ?: 'Follower') . ' on ' . ($link->title ?: $link->alias),
    'icon'     => 'fa-user',
    'favicon'  => $follower->avatar ?: null,
    'chips'    => [
        ['icon' => 'fa-envelope', 'text' => $follower->email ?: '—'],
        ['icon' => 'fa-mouse-pointer', 'text' => number_format($totalVisits) . ' clicks in period'],
        ['icon' => 'fa-calendar', 'text' => $startDate->format('M d') . ' – ' . $endDate->format('M d, Y')],
    ],
    'back'     => route('user.links.followers', $link),
    'actions'  => $heroActions,
])

<div class="section-card mb-6">
    <h2 class="font-bold mb-4" style="color: var(--text-primary);">
        <i class="fas fa-history text-blue-400 mr-2"></i>
        Visit history on this link
    </h2>

    @if($visits->isEmpty())
        <p class="text-sm py-6 text-center" style="color: var(--text-faint);">No visits in this period.</p>
    @else
        <div class="overflow-x-auto">
            <table class="fancy-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>What was clicked</th>
                        <th>Destination</th>
                        <th>Device</th>
                        <th>Location</th>
                        <th>Referrer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visits as $v)
                        @php
                            $blockLabel = $v->block_id
                                ? (($blockTypes[$v->block_type]['label'] ?? ucfirst((string)$v->block_type)))
                                : 'Page view';
                        @endphp
                        <tr>
                            <td class="text-xs" style="color: var(--text-faint);">
                                {{ $v->clicked_at?->format('M d, Y · H:i') }}
                                <div class="text-[10px]">{{ $v->clicked_at?->diffForHumans() }}</div>
                            </td>
                            <td>
                                <span class="badge">{{ $blockLabel }}</span>
                            </td>
                            <td class="truncate" style="max-width: 240px; color: var(--text-muted);">
                                @if($v->destination_url)
                                    <a href="{{ $v->destination_url }}" target="_blank" rel="noopener" class="hover:underline">{{ \Illuminate\Support\Str::limit($v->destination_url, 60) }}</a>
                                @else <span style="color: var(--text-faint);">-</span> @endif
                            </td>
                            <td class="text-xs">{{ trim(($v->device_type ?? '') . ' · ' . ($v->browser ?? '') . ' · ' . ($v->os ?? ''), ' · ') ?: '—' }}</td>
                            <td class="text-xs">{{ trim(($v->city ?? '') . ', ' . ($v->country_code ?? ''), ', ') ?: '—' }}</td>
                            <td class="text-xs truncate" style="max-width: 180px; color: var(--text-faint);">{{ $v->referrer ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $visits->links() }}</div>
    @endif
</div>
@endsection
