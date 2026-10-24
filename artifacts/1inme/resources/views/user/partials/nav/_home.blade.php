{{--
    Home: the eight links that had no group at all in the classic
    sidebar, plus the folders tree.

    Markup moved here verbatim from user/layouts/app.blade.php so the
    routes, permission gates, icons and --nav-tint values are the ones
    that already shipped. Nothing was retyped; a regroup must not be a
    chance to mistype a route.

    This is the one section that needs a header of its own, because the
    eight links never had a group toggle to supply one.
--}}
<div class="navrail-section-head">Home</div>
                @if($__can['links_view'])
                        @php
                            $__navFolders = workspace_owner()->projects()->orderBy('name')->limit(10)->get(['id', 'name', 'color']);
                        @endphp
                        <div x-data="{ foldersOpen: {{ nav_route_is('user.projects.*') || request()->filled('project_id') ? 'true' : 'false' }} }"
                             {{-- Same reason as the workspace switcher: the rail's
                                  folders tile opens this group rather than rendering
                                  a second folder list beside the rail. --}}
                             @open-nav-folders.window="foldersOpen = true; $nextTick(() => $el.scrollIntoView({ block: 'nearest' }))">
                            <div class="flex items-center">
                                <a href="{{ route('user.dashboard') }}#folders"
                                   class="sidebar-link flex-1 {{ nav_route_is('user.projects.*') || request()->filled('project_id') ? 'active' : '' }}"
                                   style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                                    <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-folder"></use></svg></div>
                                    <span class="nav-label">Folders</span>
                                    <span class="sidebar-tooltip">Folders</span>
                                </a>
                                @if($__navFolders->isNotEmpty())
                                <button type="button" @click="foldersOpen = !foldersOpen"
                                        class="nav-label navrail-subtle-btn p-1.5 mr-1 rounded-lg"
                                        :aria-expanded="foldersOpen ? 'true' : 'false'" aria-label="Toggle folders list">
                                    <svg class="nav-icon text-[10px] transition-transform" :class="foldersOpen ? '' : '-rotate-90'" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                                </button>
                                @endif
                            </div>
                            <div x-show="foldersOpen" x-collapse class="nav-label pl-9 pr-2 pb-1 space-y-0.5">
                                @foreach($__navFolders as $__nf)
                                <a href="{{ route('user.projects.show', $__nf) }}"
                                   {{-- The classic markup carried text-white / bg-white/10
                                        here, which is 30% to 100% white on a white ground
                                        in light mode: the folder list was there and
                                        unreadable. Four of the sidebar's white literals
                                        were in this block, so moving it was the cheap
                                        moment to put them on tokens. --}}
                                   class="folder-sidebar-item flex items-center gap-2 px-2 py-1.5 rounded-lg text-[13px] {{ (request('project_id') == $__nf->id || (nav_route_is('user.projects.*') && request()->route('project') instanceof \App\Modules\User\Models\Project && request()->route('project')->id === $__nf->id)) ? 'is-current' : '' }}">
                                    <svg class="nav-icon text-xs" style="color: {{ $__nf->color ?: '#3b82f6' }}" aria-hidden="true"><use href="#i-folder"></use></svg>
                                    <span class="truncate">{{ $__nf->name }}</span>
                                </a>
                                @endforeach
                                <a href="{{ route('user.projects.create') }}" class="navrail-subtle-btn flex items-center gap-2 px-2 py-1.5 rounded-lg text-[13px]">
                                    <svg class="nav-icon text-xs" aria-hidden="true"><use href="#i-plus"></use></svg>
                                    <span>New folder</span>
                                </a>
                            </div>
                        </div>
                @endif
                {{-- ========== TOP LEVEL — most-used destinations stay visible ========== --}}
                <a href="{{ route('user.dashboard') }}"
                   class="sidebar-link {{ nav_route_is('user.dashboard') ? 'active' : '' }}"
                   style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                    <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-house"></use></svg></div>
                    <span class="nav-label">Dashboard</span>
                    <span class="sidebar-tooltip">Dashboard</span>
                </a>
                @if($__can['links_view'])
                <a href="{{ route('user.links.index') }}"
                   class="sidebar-link {{ nav_route_is('user.links.index') || nav_route_is('user.links.show') ? 'active' : '' }}"
                   style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                    <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-link"></use></svg></div>
                    <span class="nav-label">All Links</span>
                    <span class="sidebar-tooltip">All Links</span>
                </a>
                @endif
                @if($__can['links_create'])
                <a href="{{ route('user.links.create') }}"
                   class="sidebar-link {{ nav_route_is('user.links.create') ? 'active' : '' }}"
                   style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
                    <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-circle-plus"></use></svg></div>
                    <span class="nav-label">Create Link</span>
                    <span class="sidebar-tooltip">Create Link</span>
                </a>
                @endif

                @if($__can['inbox_view'])
                <a href="{{ route('user.inbox.unified.index') }}"
                   class="sidebar-link {{ nav_route_is('user.inbox.unified.*') ? 'active' : '' }}"
                   style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                    <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-inbox"></use></svg></div>
                    <span class="nav-label">Inbox
                        @php $__topInbox = (new \App\Modules\User\Services\InboxAggregator(auth()->id()))->unreadCount(); @endphp
                        @if($__topInbox)<span class="ml-1 inline-block px-1.5 rounded-full text-[10px] bg-blue-500 text-white">{{ $__topInbox > 99 ? '99+' : $__topInbox }}</span>@endif
                    </span>
                    <span class="sidebar-tooltip">Inbox 2.0, triaged across forms, DMs &amp; sponsorships</span>
                </a>
                @endif
                <a href="{{ route('user.notifications.index') }}"
                   class="sidebar-link {{ nav_route_is('user.notifications.*') ? 'active' : '' }}"
                   style="--nav-tint:#fbbf24; --nav-tint-soft:rgba(251,191,36,0.12);">
                    <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-bell"></use></svg></div>
                    <span class="nav-label">Notifications
                        @php $__unread = \App\Modules\User\Models\UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count(); @endphp
                        @if($__unread)<span class="ml-1 inline-block px-1.5 rounded-full text-[10px] bg-rose-500 text-white">{{ $__unread }}</span>@endif
                    </span>
                    <span class="sidebar-tooltip">Notifications</span>
                </a>

                {{-- Task #3848 — the Wallet nav item moved to a compact icon +
                     balance badge in the top header (see the header markup
                     below); it no longer has its own sidebar entry. --}}
