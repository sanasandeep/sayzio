{{--
    Shared sub-nav rendered at the top of every Monetization dashboard
    page. Highlights the active tab and keeps the deep-linked URLs
    consistent across all five sub-pages.
--}}
@php
    $tabs = [
        ['key' => 'earnings',    'label' => 'Earnings',    'icon' => 'fa-chart-line',     'route' => 'user.monetization.earnings'],
        ['key' => 'subscribers', 'label' => 'Subscribers', 'icon' => 'fa-users',          'route' => 'user.monetization.subscribers'],
        ['key' => 'payments',    'label' => 'Payments',    'icon' => 'fa-money-bill-wave','route' => 'user.monetization.payments'],
        ['key' => 'orders',      'label' => 'Orders',      'icon' => 'fa-bag-shopping',   'route' => 'user.monetization.orders'],
        ['key' => 'tiers',       'label' => 'Tiers',       'icon' => 'fa-layer-group',    'route' => 'user.monetization.tiers'],
        ['key' => 'promos',      'label' => 'Promo codes', 'icon' => 'fa-ticket',         'route' => 'user.monetization.promos'],
    ];
@endphp
<div class="mb-6">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Monetization</h1>
            <p class="text-sm" style="color: var(--text-faint);">
                Subscriptions, paywalled posts, and tips, paid out through your connected provider.
                <span class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                      style="background: rgba(16,185,129,0.12); color: #10b981;">
                    <i class="fas fa-check-circle"></i> 0% platform fee
                </span>
            </p>
        </div>
        @auth
            @if(auth()->user()->handle)
                <a href="{{ route('creator-profile.show', ['handle' => auth()->user()->handle]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm rounded-lg border"
                   style="border-color: var(--border-color); color: var(--text-secondary);">
                    <i class="fas fa-up-right-from-square"></i> View public profile
                </a>
            @endif
        @endauth
    </div>
    <div class="flex gap-1 overflow-x-auto border-b" style="border-color: var(--border-color);">
        @foreach($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <a href="{{ route($tab['route']) }}"
               class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $active ? '' : 'hover:opacity-80' }}"
               style="border-color: {{ $active ? '#5c83ff' : 'transparent' }}; color: {{ $active ? '#5c83ff' : 'var(--text-secondary)' }};">
                <i class="fas {{ $tab['icon'] }} mr-1.5"></i>{{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</div>
