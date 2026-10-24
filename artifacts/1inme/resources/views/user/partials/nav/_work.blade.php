{{-- ========== WORKSPACE & TOOLS (collapsible) ========== --}}
                @if($__can['links_view'] || $__can['tasks_view'] || $__can['vault_view'] || $__can['settings_view'] || ($__can['attendance_view'] ?? false))
                @php $grpWorkspaceActive = nav_route_is('user.attendance.*') || nav_route_is('user.tasks.*') || (nav_route_is('user.vault.*') && !nav_route_is('user.vault.clients.*')); @endphp
                <div x-data="{ open: {{ $grpWorkspaceActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Work &amp; Team</span>
                        <svg class="nav-icon grp-chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['tasks_view'])
                        <a href="{{ route('user.tasks.index') }}"
                           class="sidebar-link {{ nav_route_is('user.tasks.*') ? 'active' : '' }}"
                           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-list-checks"></use></svg></div>
                            <span class="nav-label">Tasks</span>
                            <span class="sidebar-tooltip">Task Boards</span>
                        </a>
                        @endif
@if(($__can['attendance_view'] ?? false))
                        <a href="{{ route('user.attendance.index') }}" class="sidebar-link {{ (nav_route_is('user.attendance.*') && !nav_route_is('user.attendance.leave')) ? 'active' : '' }}"><div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-calendar-check"></use></svg></div><span class="nav-label">Attendance</span></a>
<a href="{{ route('user.attendance.leave') }}" class="sidebar-link {{ nav_route_is('user.attendance.leave') ? 'active' : '' }}"><div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-calendar-check"></use></svg></div><span class="nav-label">Leave Management</span></a>
                        @endif

                        @if($__can['vault_view'])
                        <a href="{{ route('user.vault.index') }}"
                           class="sidebar-link {{ nav_route_is('user.vault.*') && !nav_route_is('user.vault.clients.*') ? 'active' : '' }}"
                           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-vault"></use></svg></div>
                            <span class="nav-label">Vault</span>
                            <span class="sidebar-tooltip">Workspace Vault</span>
                        </a>
                        @endif
                        @if($__can['settings_view'])


                        @endif
                    </div>
                </div>
                @endif
