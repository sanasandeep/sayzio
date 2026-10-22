{{--
    Audience: two classic groups under one rail icon -- Audience &
    Community, then Growth & Marketing. Both keep their own headers, so
    the panel reads as two labelled lists rather than one merged pile of
    ten links.

    Growth & Marketing had three items and Monetization had one. Groups
    that thin are what made the rail worth building: a rail icon opening
    a panel with one link in it looks broken.
--}}
                {{-- ========== AUDIENCE & COMMUNITY (collapsible) ========== --}}
                @if($__can['inbox_view'] || $__can['followers_view'] || $__can['posts_view'] || $__can['settings_view'])
                @php $grpAudienceActive = nav_route_is('user.subscribers.*') || nav_route_is('user.followers.*') || nav_route_is('user.following.*') || nav_route_is('user.feed.*') || nav_route_is('user.posts.*') || nav_route_is('user.contacts.*') || nav_route_is('user.dialer.*'); @endphp
                <div x-data="{ open: {{ $grpAudienceActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Audience &amp; Community</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['inbox_view'])
                        <a href="{{ route('user.subscribers.index') }}"
                           class="sidebar-link {{ nav_route_is('user.subscribers.*') ? 'active' : '' }}"
                           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-users"></i></div>
                            <span class="nav-label">Subscribers</span>
                            <span class="sidebar-tooltip">Subscribers</span>
                        </a>
                        @endif
                        @if($__can['followers_view'])
                        <a href="{{ route('user.followers.index') }}"
                           class="sidebar-link {{ nav_route_is('user.followers.*') || nav_route_is('user.following.*') ? 'active' : '' }}"
                           style="--nav-tint:#f472b6; --nav-tint-soft:rgba(244,114,182,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-user-group"></i></div>
                            <span class="nav-label">Followers</span>
                            <span class="sidebar-tooltip">Followers</span>
                        </a>
                        @endif
                        @if($__can['posts_view'])
                        <a href="{{ route('feed.index') }}"
                           class="sidebar-link {{ nav_route_is('user.feed.*') ? 'active' : '' }}"
                           style="--nav-tint:#34d399; --nav-tint-soft:rgba(52,211,153,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-stream"></i></div>
                            <span class="nav-label">Feed</span>
                            <span class="sidebar-tooltip">Feed</span>
                        </a>
                        <a href="{{ route('user.posts.index') }}"
                           class="sidebar-link {{ nav_route_is('user.posts.*') ? 'active' : '' }}"
                           style="--nav-tint:#60a5fa; --nav-tint-soft:rgba(96,165,250,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-pen-to-square"></i></div>
                            <span class="nav-label">My Posts</span>
                            <span class="sidebar-tooltip">My Posts</span>
                        </a>
                        {{-- Creator Profile existed ONLY in the mobile drawer. The
                             desktop sidebar never had it, so on a laptop the page
                             was unreachable from the nav. One shared partial makes
                             that impossible to repeat, which is the real reason
                             the two navs became one file. Same posts_view gate the
                             mobile copy used. --}}
                        <a href="{{ route('user.creator-profile.edit') }}"
                           class="sidebar-link {{ nav_route_is('user.creator-profile.*') ? 'active' : '' }}"
                           style="--nav-tint:#f472b6; --nav-tint-soft:rgba(244,114,182,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-id-badge"></i></div>
                            <span class="nav-label">Creator Profile</span>
                            <span class="sidebar-tooltip">Creator Profile</span>
                        </a>
                        @endif
                        @if($__can['settings_view'])
                        <a href="{{ route('user.leads.index') }}"
                           class="sidebar-link {{ nav_route_is('user.leads.*') ? 'active' : '' }}"
                           style="--nav-tint:#38bdf8; --nav-tint-soft:rgba(56,189,248,0.12);">
                            <div class="nav-icon-wrap" style="position:relative;"><i class="fas fa-user-plus"></i>
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
                            <div class="nav-icon-wrap" style="position:relative;"><i class="fas fa-address-book"></i>
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
                            <div class="nav-icon-wrap"><i class="fas fa-phone"></i></div>
                            <span class="nav-label">Dialer</span>
                            @include('common.partials.soon-badge', ['feature' => 'dialer'])
                            <span class="sidebar-tooltip">Dialer</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ========== GROWTH & MARKETING (collapsible) ========== --}}
                @if($__can['links_view'] || $__can['stats_view'] || $__can['referrals_view'])
                @php $grpMarketingActive = nav_route_is('user.social-proofs.*') || nav_route_is('user.pixels.*') || nav_route_is('user.referrals.*'); @endphp
                <div x-data="{ open: {{ $grpMarketingActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Growth &amp; Marketing</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['links_view'])
                        <a href="{{ route('user.social-proofs.index') }}"
                           class="sidebar-link {{ nav_route_is('user.social-proofs.*') ? 'active' : '' }}"
                           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-bell"></i></div>
                            <span class="nav-label">Buzz</span>
                            @include('common.partials.soon-badge', ['feature' => 'social_proofs'])
                            <span class="sidebar-tooltip">Buzz</span>
                        </a>
                        @endif
                        @if($__can['stats_view'])
                        <a href="{{ route('user.pixels.index') }}"
                           class="sidebar-link {{ nav_route_is('user.pixels.*') ? 'active' : '' }}"
                           style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div>
                            <span class="nav-label">Pixel</span>
                            @include('common.partials.soon-badge', ['feature' => 'pixels'])
                            <span class="sidebar-tooltip">Pixel</span>
                        </a>
                        @endif
                        @if($__can['referrals_view'])
                        <a href="{{ route('user.referrals.index') }}"
                           class="sidebar-link {{ nav_route_is('user.referrals.*') ? 'active' : '' }}"
                           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-gift"></i></div>
                            <span class="nav-label">Referrals</span>
                            <span class="sidebar-tooltip">Referrals</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
