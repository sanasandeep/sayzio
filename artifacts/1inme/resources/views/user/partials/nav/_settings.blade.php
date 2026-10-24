{{-- ========== ACCOUNT (collapsible) ========== --}}
                @if($__can['settings_view'])
                @php $grpAccountActive = \App\Modules\User\Support\SettingsTabs::activeKey() !== null; @endphp
                <div x-data="{ open: {{ $grpAccountActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Account tools</span>
                        <svg class="nav-icon grp-chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        {{-- Consolidated Settings hub (Task #3220) — one entry
                             replaces the old Profile / Verification / Badge /
                             Devices & sessions / API keys links, all now tabs. --}}
                        <a href="{{ route('user.profile.edit') }}"
                           class="sidebar-link {{ $grpAccountActive ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-sliders-horizontal"></use></svg></div>
                            <span class="nav-label">Settings</span>
                            <span class="sidebar-tooltip">Settings</span>
                        </a>
                        {{-- Linked identifiers: the other mobile-only page. Same
                             story as Creator Profile above. --}}
                        <a href="{{ route('user.identifiers.index') }}"
                           class="sidebar-link {{ nav_route_is('user.identifiers.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-link"></use></svg></div>
                            <span class="nav-label">Linked identifiers</span>
                            <span class="sidebar-tooltip">Linked identifiers</span>
                        </a>
                        <a href="{{ route('user.email-connections.index') }}"
                           class="sidebar-link {{ nav_route_is('user.email-connections.*') ? 'active' : '' }}"
                           style="--nav-tint:#6366f1; --nav-tint-soft:rgba(99,102,241,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-server"></use></svg></div>
                            <span class="nav-label">SMTP Connections</span>
                            <span class="sidebar-tooltip">SMTP Connections: your reusable email senders</span>
                        </a>
                        <a href="{{ route('user.emails.index') }}"
                           class="sidebar-link {{ nav_route_is('user.emails.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-mail-open"></use></svg></div>
                            <span class="nav-label">Email history</span>
                            <span class="sidebar-tooltip">Email history</span>
                        </a>
                    </div>
                </div>
                @endif
