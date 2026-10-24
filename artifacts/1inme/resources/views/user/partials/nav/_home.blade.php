{{-- Home navigation; Dashboard includes the existing-folder dropdown. --}}
<div class="navrail-section-head">Home</div>
@include('user.partials.dashboard-folder-dropdown')
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
