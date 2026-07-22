@extends('admin.layouts.app')
@section('title', 'Site Assistant: Conversations')
@section('page-title', 'Site Assistant, Conversations')

@section('content')
<div class="max-w-7xl space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs ak-green">{{ session('success') }}</div>@endif
    <div class="text-sm text-white/60 ak-muted"><a href="{{ route('admin.site-assistant.edit') }}" class="hover:text-white">← Back to Site Assistant</a></div>

    <form method="GET" class="flex flex-wrap gap-2">
        <input name="search" value="{{ request('search') }}" placeholder="Search by email, name, route…" class="bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white flex-1 min-w-[200px] ak-strong">
        <label class="flex items-center gap-2 text-sm text-white/70 ak-strong"><input type="checkbox" name="handed_off" value="1" {{ request('handed_off')?'checked':'' }}> Handed off</label>
        <label class="flex items-center gap-2 text-sm text-white/70 ak-strong"><input type="checkbox" name="disabled" value="1" {{ request('disabled')?'checked':'' }}> Disabled</label>
        {{-- Preserve analytics deep-link filters across the in-page filter form. --}}
        @if(!empty($activeFilters['cutoffs']))<input type="hidden" name="cutoffs" value="1">@endif
        @if(!is_null($activeFilters['model'] ?? null))<input type="hidden" name="model" value="{{ $activeFilters['model'] }}">@endif
        @if(!empty($activeFilters['route']))<input type="hidden" name="route" value="{{ $activeFilters['route'] }}">@endif
        @if(!empty($activeFilters['days']))<input type="hidden" name="days" value="{{ $activeFilters['days'] }}">@endif
        <button class="px-4 py-2 rounded-xl bg-white/10 text-sm text-white ak-strong">Filter</button>
    </form>

    @php
        $hasDeepFilter = !empty($activeFilters['cutoffs'])
            || !is_null($activeFilters['model'] ?? null)
            || !empty($activeFilters['route'])
            || !empty($activeFilters['days']);
    @endphp
    @if($hasDeepFilter)
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="text-white/50 ak-muted">Filtered from analytics:</span>
            @if(!empty($activeFilters['cutoffs']))
                <span class="px-2 py-1 rounded-md bg-rose-500/15 text-rose-300 border border-rose-500/20 ak-red">cut-offs only</span>
            @endif
            @if(!is_null($activeFilters['model'] ?? null))
                <span class="px-2 py-1 rounded-md bg-white/5 text-white/80 border border-white/10 font-mono ak-strong">model: {{ $activeFilters['model'] === '' ? '(unknown)' : $activeFilters['model'] }}</span>
            @endif
            @if(!empty($activeFilters['route']))
                <span class="px-2 py-1 rounded-md bg-white/5 text-white/80 border border-white/10 font-mono ak-strong">route: {{ $activeFilters['route'] }}</span>
            @endif
            @if(!empty($activeFilters['days']))
                <span class="px-2 py-1 rounded-md bg-white/5 text-white/80 border border-white/10 ak-strong">last {{ $activeFilters['days'] }}d</span>
            @endif
            <a href="{{ route('admin.site-assistant.conversations') }}" class="px-2 py-1 rounded-md bg-white/5 text-white/60 hover:text-white border border-white/10 ak-muted">Clear</a>
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase ak-muted">
                <tr><th class="text-left p-3">When</th><th class="text-left p-3">Visitor</th><th class="text-left p-3">Surface</th><th class="text-left p-3">Last route</th><th class="text-right p-3">Turns</th><th class="text-right p-3">Coins</th><th class="text-left p-3">Status</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
            @forelse($conversations as $c)
                <tr class="border-t border-white/5">
                    <td class="p-3 text-white/70 text-xs ak-strong">{{ optional($c->last_message_at)->diffForHumans() ?? '—' }}</td>
                    <td class="p-3 text-white ak-strong">{{ $c->visitor_email ?: $c->visitor_name ?: ('#'.$c->id) }}</td>
                    <td class="p-3 text-white/70 ak-strong">{{ $c->surface }}</td>
                    <td class="p-3 text-white/70 font-mono text-xs ak-strong">{{ $c->last_route ?: '—' }}</td>
                    <td class="p-3 text-right text-white ak-strong">{{ $c->turns_count }}</td>
                    <td class="p-3 text-right text-white/60 ak-muted">{{ $c->credits_spent }}</td>
                    <td class="p-3">
                        @if($c->is_disabled)<span class="text-red-300 text-xs ak-red">disabled</span>
                        @elseif($c->handed_off)<span class="text-amber-300 text-xs ak-amber">handed off</span>
                        @else<span class="text-emerald-300 text-xs ak-green">active</span>@endif
                    </td>
                    <td class="p-3 text-right"><a href="{{ route('admin.site-assistant.conversations.show', $c) }}" class="text-indigo-300 text-xs ak-blue">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-6 text-center text-white/40 text-sm ak-note">No conversations yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $conversations->links() }}
</div>
@endsection
