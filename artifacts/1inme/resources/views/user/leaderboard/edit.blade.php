@extends('user.layouts.app')

@section('title', 'Top Fans Leaderboard: ' . $link->title)

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Top Fans Leaderboard',
        'subtitle' => $link->title,
        'icon'     => 'fa-trophy',
        'back'     => route('user.links.show', $link),
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2); color:#10b981;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('user.links.leaderboard.update', $link) }}" class="rounded-2xl p-6 space-y-6" style="background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);">
        @csrf @method('PUT')

        <div class="flex items-center gap-6">
            <label class="inline-flex items-center gap-2 text-white">
                <input type="checkbox" name="is_enabled" value="1" {{ $settings->is_enabled ? 'checked' : '' }}>
                Enable public leaderboard
            </label>
            <label class="inline-flex items-center gap-2 text-white">
                <input type="checkbox" name="show_anonymous_option" value="1" {{ $settings->show_anonymous_option ? 'checked' : '' }}>
                Let fans appear anonymously
            </label>
            <label class="inline-flex items-center gap-2 text-white">
                Show top
                <input type="number" name="top_n" min="3" max="100" value="{{ $settings->top_n ?? 10 }}" class="w-20 px-2 py-1 rounded bg-black/20 border border-white/10 text-white">
                fans
            </label>
        </div>

        <div>
            <h3 class="text-white font-semibold mb-3">Point rules</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach(\App\Modules\User\Models\FanLeaderboardSetting::defaultRules() as $action => $default)
                <label class="block">
                    <span class="text-xs text-white/60 uppercase">{{ $action }}</span>
                    <input type="number" name="point_rules[{{ $action }}]" min="0" max="1000" value="{{ $settings->point_rules[$action] ?? $default }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-black/20 border border-white/10 text-white">
                </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="text-white font-semibold mb-3">Perks for top ranks</h3>
            <div id="perks-list" class="space-y-2">
                @foreach(($settings->perks ?? []) as $i => $perk)
                <div class="flex gap-2">
                    <input type="number" name="perks[{{ $i }}][rank]" min="1" value="{{ $perk['rank'] ?? 1 }}" class="w-24 px-3 py-2 rounded-lg bg-black/20 border border-white/10 text-white" placeholder="Rank">
                    <input type="text" name="perks[{{ $i }}][label]" value="{{ $perk['label'] ?? '' }}" class="flex-1 px-3 py-2 rounded-lg bg-black/20 border border-white/10 text-white" placeholder="Perk description">
                </div>
                @endforeach
                <div class="flex gap-2">
                    <input type="number" name="perks[new][rank]" min="1" placeholder="Rank" class="w-24 px-3 py-2 rounded-lg bg-black/20 border border-white/10 text-white">
                    <input type="text" name="perks[new][label]" placeholder="e.g. Free month of premium" class="flex-1 px-3 py-2 rounded-lg bg-black/20 border border-white/10 text-white">
                </div>
            </div>
        </div>

        <button class="btn-primary px-4 py-2 rounded-lg"><i class="fas fa-save mr-1"></i> Save settings</button>
    </form>

    <div class="mt-8 rounded-2xl p-6" style="background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);">
        <h3 class="text-white font-semibold mb-3">Current top fans</h3>
        <ol class="space-y-2">
            @forelse($top as $fan)
            <li class="flex items-center justify-between text-white/80 px-3 py-2 rounded-lg" style="background: rgba(0,0,0,0.2);">
                <span><span class="text-amber-400 font-bold mr-2">#{{ $fan['rank'] }}</span> {{ $fan['name'] }}</span>
                <span class="text-sm text-white/60">{{ $fan['total'] }} pts</span>
            </li>
            @empty
            <li class="text-white/40 text-sm">No fan activity yet.</li>
            @endforelse
        </ol>
    </div>
</div>
@endsection
