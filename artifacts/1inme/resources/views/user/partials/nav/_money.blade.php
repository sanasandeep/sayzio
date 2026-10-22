{{--
    Money: Monetization (one item) and Billing & Accounting (seven),
    both verbatim and both keeping their headers.
--}}
                {{-- ========== MONETIZATION (collapsible) ========== --}}
                @if($__can['posts_view'])
                @php $grpMonetActive = nav_route_is('user.monetization.*'); @endphp
                <div x-data="{ open: {{ $grpMonetActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Monetization</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        <a href="{{ route('user.monetization.earnings') }}"
                           class="sidebar-link {{ nav_route_is('user.monetization.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-gem"></i></div>
                            <span class="nav-label">Monetization</span>
                            @include('common.partials.soon-badge', ['feature' => 'monetization'])
                            <span class="sidebar-tooltip">Monetization</span>
                        </a>
                    </div>
                </div>
                @endif

                {{-- ========== BILLING & ACCOUNTING (collapsible) ========== --}}
                @if($__can['tasks_view'])
                @php $grpBillingActive = nav_route_is('user.client-invoices.*') || nav_route_is('user.billing.*'); @endphp
                <div x-data="{ open: {{ $grpBillingActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Billing &amp; Accounting</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        <a href="{{ route('user.billing.companies.index') }}" class="sidebar-link {{ nav_route_is('user.billing.companies.*') ? 'active' : '' }}" style="--nav-tint:#6366f1; --nav-tint-soft:rgba(99,102,241,0.12);"><div class="nav-icon-wrap"><i class="fas fa-building"></i></div><span class="nav-label">Companies</span><span class="sidebar-tooltip">Billing Companies</span></a>
                        <a href="{{ route('user.client-invoices.dashboard') }}"
                           class="sidebar-link {{ nav_route_is('user.client-invoices.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-file-invoice-dollar"></i></div>
                            <span class="nav-label">Invoices</span>
                            <span class="sidebar-tooltip">Invoices</span>
                        </a>
                        <a href="{{ route('user.billing.recurring.index') }}"
                           class="sidebar-link {{ nav_route_is('user.billing.recurring.*') ? 'active' : '' }}"
                           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-repeat"></i></div>
                            <span class="nav-label">Recurring</span>
                            <span class="sidebar-tooltip">Recurring Invoices</span>
                        </a>
                        <a href="{{ route('user.billing.expenses.index') }}"
                           class="sidebar-link {{ nav_route_is('user.billing.expenses.*') ? 'active' : '' }}"
                           style="--nav-tint:#ef4444; --nav-tint-soft:rgba(239,68,68,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-receipt"></i></div>
                            <span class="nav-label">Expenses</span>
                            <span class="sidebar-tooltip">Expenses</span>
                        </a>
                        <a href="{{ route('user.billing.catalog.index') }}"
                           class="sidebar-link {{ nav_route_is('user.billing.catalog.*') ? 'active' : '' }}"
                           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-boxes-stacked"></i></div>
                            <span class="nav-label">Catalog</span>
                            <span class="sidebar-tooltip">Item Catalog</span>
                        </a>
                        <a href="{{ route('user.billing.tax-rules.index') }}"
                           class="sidebar-link {{ nav_route_is('user.billing.tax-rules.*') ? 'active' : '' }}"
                           style="--nav-tint:#64748b; --nav-tint-soft:rgba(100,116,139,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-percent"></i></div>
                            <span class="nav-label">Tax Rules</span>
                            <span class="sidebar-tooltip">Tax Rules</span>
                        </a>
                        <a href="{{ route('user.billing.ledger.index') }}"
                           class="sidebar-link {{ nav_route_is('user.billing.ledger.*') ? 'active' : '' }}"
                           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
                            <span class="nav-label">Ledger</span>
                            <span class="sidebar-tooltip">Ledger / P&amp;L</span>
                        </a>
                    </div>
                </div>
                @endif
