{{--
    Events: the Events & Calendar group, verbatim.

    The group's own header stays and acts as the panel heading -- which
    is why nothing here had to be unwrapped or restyled.
--}}
                {{-- ========== EVENTS & CALENDAR (collapsible) ========== --}}
                {{--
                    Events shipped with twelve surfaces and no nav entry of its
                    own: the only way in was All Links -> open the event. The
                    three events items are gated on the platform module switch
                    (Task #6726); the two calendar items are deliberately NOT,
                    because those routes keep working when the module is off and
                    gating the whole group would strand them.

                    First in the list and open by default. Both are launch
                    choices, not permanent ones: nobody is on events yet, so
                    the group is a discovery surface rather than clutter. It
                    sat sixth before, which at an ordinary window height put
                    it below the fold with every group above it collapsed --
                    an open group you have to scroll to find is not
                    discoverable. Move it back down and flip `open` to false
                    once events are established.
                --}}
                @php $__eventsOn = \App\Modules\Common\Support\EventsModule::enabled(); @endphp
                @if($__can['links_view'] || $__can['settings_view'])
                <div x-data="{ open: true }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Events &amp; Calendar</span>
                        <svg class="nav-icon grp-chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__eventsOn && $__can['links_view'])
                        <a href="{{ route('user.links.index', ['type' => 'ics']) }}"
                           class="sidebar-link {{ nav_route_is('user.links.index') && request('type') === 'ics' ? 'active' : '' }}"
                           style="--nav-tint:#a855f7; --nav-tint-soft:rgba(168,85,247,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-calendar-check"></use></svg></div>
                            <span class="nav-label">My Events</span>
                            <span class="sidebar-tooltip">My Events</span>
                        </a>
                        @endif
                        @if($__eventsOn && $__can['links_create'])
                        <a href="{{ route('user.links.ics.create') }}"
                           class="sidebar-link {{ nav_route_is('user.links.ics.create') ? 'active' : '' }}"
                           style="--nav-tint:#a855f7; --nav-tint-soft:rgba(168,85,247,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-circle-plus"></use></svg></div>
                            <span class="nav-label">Create Event</span>
                            <span class="sidebar-tooltip">Create Event</span>
                        </a>
                        @endif
                        @if($__eventsOn && $__can['settings_view'])
                        <a href="{{ route('user.events.index') }}"
                           class="sidebar-link {{ nav_route_is('user.events.*') ? 'active' : '' }}"
                           style="--nav-tint:#a855f7; --nav-tint-soft:rgba(168,85,247,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-calendar-range"></use></svg></div>
                            <span class="nav-label">Events Calendar</span>
                            <span class="sidebar-tooltip">Events Calendar</span>
                        </a>
                        @endif
                        @if($__can['links_view'])
                        <a href="{{ route('user.calendars.mine') }}"
                           class="sidebar-link {{ nav_route_is('user.calendars.*') ? 'active' : '' }}"
                           style="--nav-tint:#0ea5e9; --nav-tint-soft:rgba(14,165,233,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-calendar-days"></use></svg></div>
                            <span class="nav-label">My Calendar</span>
                            <span class="sidebar-tooltip">My Calendar</span>
                        </a>
                        @endif
                        @if($__can['settings_view'])
                        <a href="{{ route('user.calendar.index') }}"
                           class="sidebar-link {{ nav_route_is('user.calendar.*') ? 'active' : '' }}"
                           style="--nav-tint:#0ea5e9; --nav-tint-soft:rgba(14,165,233,0.12);">
                            <div class="nav-icon-wrap"><svg class="nav-icon" aria-hidden="true"><use href="#i-rotate-cw"></use></svg></div>
                            <span class="nav-label">Connected Calendars</span>
                            <span class="sidebar-tooltip">Connected Calendars</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
