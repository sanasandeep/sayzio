{{-- ========== MONETIZATION (collapsible) ========== --}}
                @if($__can['posts_view'])
                @php $grpMonetActive = nav_route_is('user.monetization.*') || nav_route_is('user.payouts.*') || nav_route_is('user.adult-content.*'); @endphp
                <div x-data="{ open: {{ $grpMonetActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Personal monetization</span>
                        <svg class="nav-icon grp-chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        <a href="{{ route('user.monetization.earnings') }}"
                           class="sidebar-link {{ nav_route_is('user.monetization.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-gem"></use></svg></div>
                            <span class="nav-label">Creator earnings</span>
                            @include('common.partials.soon-badge', ['feature' => 'monetization'])
                            <span class="sidebar-tooltip">Monetization</span>
                        </a>
<a href="{{ route('user.payouts.show') }}"
                           class="sidebar-link {{ nav_route_is('user.payouts.*') || nav_route_is('user.adult-content.*') ? 'active' : '' }}"
                           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-wallet"></use></svg></div>
                            <span class="nav-label">Earnings & Payouts</span>
                            @include('common.partials.soon-badge', ['feature' => 'payouts'])
                            <span class="sidebar-tooltip">Earnings & Payouts</span>
                        </a>
                    </div>
                </div>
                @endif
