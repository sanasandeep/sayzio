{{--
    Tab navigation between the per-link analytics views.
    Drop-in: include with `['link' => $link, 'active' => 'overview'|'followers']`.
    The buttons re-use the same .pill / .pill-active styles from show.blade.php
    so the bar visually matches the period-filter pills below it.
--}}
@php $tabActive = $active ?? 'overview'; @endphp
<div class="period-bar mb-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);">
            <i class="fas fa-chart-pie text-violet-400"></i> View
        </span>
        <a href="{{ route('user.links.show', $link) }}"
           class="pill {{ $tabActive === 'overview' ? 'pill-active' : '' }}">
            <i class="fas fa-chart-line text-[9px] mr-1"></i> Overview
        </a>
        <a href="{{ route('user.links.followers', $link) }}"
           class="pill {{ $tabActive === 'followers' ? 'pill-active' : '' }}">
            <i class="fas fa-user-heart text-[9px] mr-1"></i><i class="fas fa-users text-[9px] mr-1"></i> Followers
        </a>
        @if($link->type === 'biolink')
            <a href="{{ route('user.links.visitors', $link) }}"
               class="pill {{ $tabActive === 'visitors' ? 'pill-active' : '' }}">
                <i class="fas fa-fingerprint text-[9px] mr-1"></i> Visitor Insights
            </a>
        @endif
    </div>
</div>
