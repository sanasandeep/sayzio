@extends('user.layouts.app')
@section('title', 'Backlinks')

@section('content')
@php
    $propertyLabels = [
        'short_link'        => 'Short link',
        'biolink_username'  => 'Link in Bio',
        'custom_domain'     => 'Custom domain',
    ];
    $propertyIcons = [
        'short_link'        => 'fa-link',
        'biolink_username'  => 'fa-user',
        'custom_domain'     => 'fa-globe',
    ];
@endphp

@include('user.partials.page-hero', [
    'title'    => 'Backlinks',
    'subtitle' => "Pages around the web that link back to your Sayzio properties (collected by the browser extension).",
    'icon'     => 'fa-bullseye',
    'chips'    => [
        ['icon' => 'fa-layer-group', 'text' => number_format($totalAll) . ' total'],
        ['icon' => 'fa-calendar-week', 'text' => number_format($totalThisWeek) . ' this week'],
    ],
    'actions'  => [
        [
            'label' => 'Export CSV',
            'url'   => route('user.backlinks.export', array_filter(['days' => $days, 'property_type' => $propertyType])),
            'icon'  => 'fa-file-csv',
            'class' => 'btn-secondary',
        ],
    ],
])

@if(session('status'))
    <div class="mb-4 px-3 py-2 rounded-lg text-xs flex items-center gap-2"
         style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.25); color: #34d399;">
        <i class="fas fa-check-circle"></i><span>{{ session('status') }}</span>
    </div>
@endif

<div class="card-premium mb-5">
    <form method="GET" class="p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Date range</label>
            <select name="days" class="theme-input appearance-none pr-8">
                <option value="" class="bg-[#0a0612]">All time</option>
                <option value="7"  @selected($days === 7)  class="bg-[#0a0612]">Last 7 days</option>
                <option value="30" @selected($days === 30) class="bg-[#0a0612]">Last 30 days</option>
                <option value="90" @selected($days === 90) class="bg-[#0a0612]">Last 90 days</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Property type</label>
            <select name="property_type" class="theme-input appearance-none pr-8">
                <option value="" class="bg-[#0a0612]">All types</option>
                @foreach($propertyTypes as $t)
                    <option value="{{ $t }}" @selected($propertyType === $t) class="bg-[#0a0612]">{{ $propertyLabels[$t] ?? $t }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-ghost text-xs py-2">
            <i class="fas fa-filter text-[10px]"></i> Apply
        </button>
        @if($days || $propertyType)
            <a href="{{ route('user.backlinks.index') }}" class="text-xs text-blue-400 hover:text-blue-300 font-semibold">
                <i class="fas fa-times text-[9px]"></i> Clear
            </a>
        @endif
    </form>
</div>

<div class="card-premium overflow-hidden">
    @if($backlinks->isEmpty())
        <div class="p-12 text-center">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                 style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.12);">
                <i class="fas fa-bullseye text-xl text-blue-400"></i>
            </div>
            <p class="text-sm mb-1 font-bold" style="color: var(--text-muted);">No backlinks yet</p>
            <p class="text-xs mb-1" style="color: var(--text-dimmed);">
                Install the Sayzio browser extension to start collecting pages that link to your properties.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint); border-bottom: 1px solid var(--border-subtle);">
                        <th class="px-5 py-3">Source page</th>
                        <th class="px-5 py-3">Anchor</th>
                        <th class="px-5 py-3">Linked property</th>
                        <th class="px-5 py-3">First seen</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($backlinks as $row)
                        @php
                            $type  = $row->matched_property_type;
                            $label = $propertyLabels[$type] ?? $type;
                            $icon  = $propertyIcons[$type] ?? 'fa-link';
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td class="px-5 py-3 align-top">
                                <a href="{{ $row->page_url }}" target="_blank" rel="noopener nofollow"
                                   class="font-semibold hover:text-blue-300 transition-colors block truncate max-w-[28ch]"
                                   style="color: var(--text-primary);"
                                   title="{{ $row->page_url }}">
                                    {{ $row->page_title ?: $row->page_host ?: $row->page_url }}
                                </a>
                                @if($row->page_host)
                                    <div class="text-[11px] mt-0.5" style="color: var(--text-faint);">{{ $row->page_host }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 align-top text-xs" style="color: var(--text-muted);">
                                @if($row->anchor_text)
                                    <span class="block truncate max-w-[24ch]" title="{{ $row->anchor_text }}">“{{ $row->anchor_text }}”</span>
                                @else
                                    <span style="color: var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 align-top">
                                <span class="badge inline-flex items-center gap-1.5"
                                      style="background: rgba(110,97,255,0.1); color: #f0abfc; border: 1px solid rgba(110,97,255,0.25);">
                                    <i class="fas {{ $icon }} text-[9px]"></i>{{ $label }}
                                </span>
                                <div class="text-[11px] mt-1 truncate max-w-[28ch]" style="color: var(--text-faint);" title="{{ $row->matched_url }}">
                                    {{ $row->matched_property_value ?: $row->matched_url }}
                                </div>
                            </td>
                            <td class="px-5 py-3 align-top text-xs" style="color: var(--text-muted);">
                                {{ optional($row->first_seen_at)->diffForHumans() ?? '—' }}
                                @if($row->first_seen_at)
                                    <div class="text-[11px]" style="color: var(--text-faint);">{{ $row->first_seen_at->format('M j, Y') }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 align-top text-right">
                                <form method="POST" action="{{ route('user.backlinks.destroy', $row->id) }}"
                                      onsubmit="return confirm('Remove this backlink?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[11px] text-rose-400 hover:text-rose-300 font-semibold"
                                            title="Remove">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3" style="border-top: 1px solid var(--border-subtle);">
            {{ $backlinks->links() }}
        </div>
    @endif
</div>
@endsection
