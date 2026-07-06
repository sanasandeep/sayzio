{{-- Public-facing Top Fans leaderboard block. Pulls live data via AJAX so
     ranks stay fresh without forcing a full page render.
     Themed from the biolink's own theme tokens ($fontColor). --}}
@php
    $__cFg = $fontColor ?? '#ffffff';
    $__cBg = $__cFg . '0d';      // ~5% tint of the theme text color
    $__cBorder = $__cFg . '1a';  // ~10% tint of the theme text color
@endphp
<div class="community-leaderboard-block rounded-2xl p-5 my-4" data-link-id="{{ $link->id }}" style="background: {{ $__cBg }}; border:1px solid {{ $__cBorder }}; color: {{ $__cFg }};">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold" style="color: {{ $__cFg }};">
            <i class="fas fa-trophy text-amber-400 mr-1.5"></i>
            {{ $block->settings['title'] ?? 'Top Fans' }}
        </h3>
        <span class="text-xs opacity-60" style="color: {{ $__cFg }};">Updated live</span>
    </div>

    <ol class="leaderboard-list space-y-1.5" data-load-url="{{ route('community.leaderboard', $link->id) }}">
        <li class="text-xs opacity-50" style="color: {{ $__cFg }};">Loading leaderboard…</li>
    </ol>

    @php
        // Prefer per-link FanLeaderboardSetting perks (the canonical
        // source the creator dashboard writes to); fall back to perks
        // configured directly on the block settings for back-compat.
        $perksSetting = \App\Modules\User\Models\FanLeaderboardSetting::query()
            ->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->first();
        $perks = (is_object($perksSetting) && is_array($perksSetting->perks) && !empty($perksSetting->perks))
            ? $perksSetting->perks
            : ($block->settings['perks'] ?? []);
    @endphp
    @if(!empty($perks))
    <div class="mt-4 pt-3" style="border-top: 1px solid {{ $__cBorder }};">
        <div class="text-xs uppercase opacity-60 mb-2" style="color: {{ $__cFg }};">Perks</div>
        <ul class="text-xs space-y-1 opacity-90" style="color: {{ $__cFg }};">
            @foreach($perks as $perk)
            <li><span class="text-amber-400">#{{ $perk['rank'] ?? '?' }}</span> · {{ $perk['label'] ?? '' }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
