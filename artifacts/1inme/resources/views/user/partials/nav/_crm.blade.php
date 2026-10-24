@if($__can['settings_view'] || $__can['vault_view'])
                <div x-data="{ open: {{ (nav_route_is('user.vault.clients.*') || nav_route_is('user.delivery-projects.*') || nav_route_is('user.leads.*') || nav_route_is('user.contacts.*') || nav_route_is('user.dialer.*')) ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]"><span>CRM &amp; Customers</span><svg class="nav-icon grp-chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg></button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['vault_view'])
                        <a href="{{ route('user.vault.clients.index') }}"
                           class="sidebar-link {{ nav_route_is('user.vault.clients.*') || nav_route_is('user.delivery-projects.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-workflow"></use></svg></div>
                            <span class="nav-label">Clients</span>
                            <span class="sidebar-tooltip">Clients</span>
                        </a>
                        @endif
                        @if($__can['settings_view'])
                        <a href="{{ route('user.leads.index') }}"
                           class="sidebar-link {{ nav_route_is('user.leads.*') ? 'active' : '' }}"
                           style="--nav-tint:#38bdf8; --nav-tint-soft:rgba(56,189,248,0.12);">
                            <div class="nav-icon-wrap" style="position:relative;"><svg class="nav-icon" aria-hidden="true"><use href="#i-user-plus"></use></svg>
                                @if(($pendingLeadsCount ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full text-[9px] font-bold leading-none text-white ring-2" style="background:#ef4444; --tw-ring-color:var(--sidebar-bg,#0b1020);" title="{{ $pendingLeadsCount }} pending lead(s)">{{ $pendingLeadsCount > 99 ? '99+' : $pendingLeadsCount }}</span>
                                @endif
                            </div>
                            <span class="nav-label">Leads</span>
                            <span class="sidebar-tooltip">Leads</span>
                        </a>
                        <a href="{{ route('user.contacts.index') }}"
                           class="sidebar-link {{ nav_route_is('user.contacts.*') ? 'active' : '' }}"
                           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
                            <div class="nav-icon-wrap" style="position:relative;"><svg class="nav-icon" aria-hidden="true"><use href="#i-book-user"></use></svg>
                                @if(($contactsOverdueFollowUps ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full text-[9px] font-bold leading-none text-white ring-2" style="background:#ef4444; --tw-ring-color:var(--sidebar-bg,#0b1020);" title="{{ $contactsOverdueFollowUps }} overdue follow-up(s)">{{ $contactsOverdueFollowUps > 99 ? '99+' : $contactsOverdueFollowUps }}</span>
                                @endif
                            </div>
                            <span class="nav-label">Contacts</span>
                            <span class="sidebar-tooltip">Contacts</span>
                        </a>
                        <a href="{{ route('user.dialer.index') }}"
                           class="sidebar-link {{ nav_route_is('user.dialer.*') ? 'active' : '' }}"
                           style="--nav-tint:#34d399; --nav-tint-soft:rgba(52,211,153,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-phone"></use></svg></div>
                            <span class="nav-label">Dialer</span>
                            @include('common.partials.soon-badge', ['feature' => 'dialer'])
                            <span class="sidebar-tooltip">Dialer</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
