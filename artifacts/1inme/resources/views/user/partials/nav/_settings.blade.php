{{--
    Settings: Workspace & Tools, then the Account group (Settings,
    SMTP connections, email history). Both verbatim.
--}}
                {{-- ========== WORKSPACE & TOOLS (collapsible) ========== --}}
                @if($__can['links_view'] || $__can['tasks_view'] || $__can['vault_view'] || $__can['settings_view'])
                @php $grpWorkspaceActive = nav_route_is('user.tasks.*') || nav_route_is('user.delivery-projects.*') || nav_route_is('user.vault.*'); @endphp
                <div x-data="{ open: {{ $grpWorkspaceActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Workspace &amp; Tools</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['tasks_view'])
                        <a href="{{ route('user.tasks.index') }}"
                           class="sidebar-link {{ nav_route_is('user.tasks.*') ? 'active' : '' }}"
                           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-list-check"></i></div>
                            <span class="nav-label">Tasks</span>
                            <span class="sidebar-tooltip">Task Boards</span>
                        </a>
                        @endif
                        @if($__can['vault_view'])
                        <a href="{{ route('user.vault.clients.index') }}"
                           class="sidebar-link {{ nav_route_is('user.vault.clients.*') || nav_route_is('user.delivery-projects.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-diagram-project"></i></div>
                            <span class="nav-label">Clients</span>
                            <span class="sidebar-tooltip">Clients</span>
                        </a>
                        @endif
                        @if($__can['vault_view'])
                        <a href="{{ route('user.vault.index') }}"
                           class="sidebar-link {{ nav_route_is('user.vault.*') && !nav_route_is('user.vault.clients.*') ? 'active' : '' }}"
                           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-vault"></i></div>
                            <span class="nav-label">Vault</span>
                            <span class="sidebar-tooltip">Workspace Vault</span>
                        </a>
                        @endif
                        @if($__can['settings_view'])


                        @endif
                    </div>
                </div>
                @endif

                {{-- ========== ACCOUNT (collapsible) ========== --}}
                @if($__can['settings_view'])
                @php $grpAccountActive = \App\Modules\User\Support\SettingsTabs::activeKey() !== null; @endphp
                <div x-data="{ open: {{ $grpAccountActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Account tools</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        {{-- Consolidated Settings hub (Task #3220) — one entry
                             replaces the old Profile / Verification / Badge /
                             Devices & sessions / API keys links, all now tabs. --}}
                        <a href="{{ route('user.profile.edit') }}"
                           class="sidebar-link {{ $grpAccountActive ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-sliders"></i></div>
                            <span class="nav-label">Settings</span>
                            <span class="sidebar-tooltip">Settings</span>
                        </a>
                        {{-- Linked identifiers: the other mobile-only page. Same
                             story as Creator Profile above. --}}
                        <a href="{{ route('user.identifiers.index') }}"
                           class="sidebar-link {{ nav_route_is('user.identifiers.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-link"></i></div>
                            <span class="nav-label">Linked identifiers</span>
                            <span class="sidebar-tooltip">Linked identifiers</span>
                        </a>
                        <a href="{{ route('user.email-connections.index') }}"
                           class="sidebar-link {{ nav_route_is('user.email-connections.*') ? 'active' : '' }}"
                           style="--nav-tint:#6366f1; --nav-tint-soft:rgba(99,102,241,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-server"></i></div>
                            <span class="nav-label">SMTP Connections</span>
                            <span class="sidebar-tooltip">SMTP Connections: your reusable email senders</span>
                        </a>
                        <a href="{{ route('user.emails.index') }}"
                           class="sidebar-link {{ nav_route_is('user.emails.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-envelope-open-text"></i></div>
                            <span class="nav-label">Email history</span>
                            <span class="sidebar-tooltip">Email history</span>
                        </a>
                    </div>
                </div>
                @endif
