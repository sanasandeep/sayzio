{{--
    Per-event tab bar.

    Running an event touches six pages -- edit, tickets, RSVPs, people,
    check-in, messages -- and until now none of them knew about the
    others. They navigated by `back` links that formed a chain rather
    than a hub: People went "back" to Ticketing, Messages went "back" to
    RSVPs, and there was no path at all from the door scanner to the
    guest list. On the day, that means returning to All Links between
    every task.

    So this is a hub. Every page gets the same bar, `back` on all of them
    points at the event itself, and the cross-page buttons that used to
    sit in the hero as actions are gone -- a tab and a button to the same
    place is one too many.

    Usage, after the page-hero include:

        @include('user.partials.event-tabs', ['link' => $link])

    The active tab is derived from the current route rather than passed
    in: a string argument is one more thing each of seven callers can get
    wrong, and nothing would fail loudly when they did.

    Gates mirror the routes exactly, so a tab is absent rather than
    present-and-403. They are NOT all the same: editing needs
    links.edit, RSVPs needs followers.view, and the rest links.view.
--}}
@php
    use App\Modules\Common\Support\EventsModule;
    use App\Modules\User\Services\WorkspacePermissions as WP;

    $__evTabsLink = $link ?? null;
    $__evTabsOn   = $__evTabsLink
        && ($__evTabsLink->type ?? null) === 'ics'
        && EventsModule::enabled();

    $__evTabs = [];

    if ($__evTabsOn) {
        $__evTabs = array_values(array_filter([
            WP::userCan('links.view') ? [
                'label'  => 'Overview',
                'icon'   => 'fa-chart-simple',
                'url'    => route('user.links.show', $__evTabsLink),
                'active' => nav_route_is('user.links.show'),
            ] : null,
            WP::userCan('links.edit') ? [
                'label'  => 'Edit',
                'icon'   => 'fa-pen',
                'url'    => route('user.links.ics.edit', $__evTabsLink),
                'active' => nav_route_is('user.links.ics.edit'),
            ] : null,
            WP::userCan('links.view') ? [
                'label'  => 'Tickets',
                'icon'   => 'fa-ticket-alt',
                'url'    => route('user.links.ics.tickets', $__evTabsLink),
                'active' => nav_route_is('user.links.ics.tickets'),
            ] : null,
            WP::userCan('followers.view') ? [
                'label'  => 'RSVPs',
                'icon'   => 'fa-calendar-check',
                'url'    => route('user.links.rsvps.index', $__evTabsLink),
                'active' => nav_route_is('user.links.rsvps.*'),
            ] : null,
            WP::userCan('links.view') ? [
                'label'  => 'People',
                'icon'   => 'fa-people-arrows',
                'url'    => route('user.links.ics.people', $__evTabsLink),
                'active' => nav_route_is('user.links.ics.people'),
            ] : null,
            WP::userCan('links.view') ? [
                'label'  => 'Check-in',
                'icon'   => 'fa-qrcode',
                'url'    => route('user.links.ics.checkin', $__evTabsLink),
                'active' => nav_route_is('user.links.ics.checkin'),
            ] : null,
            WP::userCan('links.view') ? [
                'label'  => 'Messages',
                'icon'   => 'fa-paper-plane',
                'url'    => route('user.links.ics.broadcast', $__evTabsLink),
                'active' => nav_route_is('user.links.ics.broadcast'),
            ] : null,
        ]));
    }
@endphp

@if(count($__evTabs) > 1)
@once
@push('styles')
<style>
    /* Scoped to this bar on purpose: two other pages already define their
       own .tab-active / .tab-inactive with different values, and there is
       no shared tab component to extend. */
    .evtabs {
        display: flex; gap: 6px; margin: -8px 0 24px;
        overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none;
        padding-bottom: 2px;
    }
    .evtabs::-webkit-scrollbar { display: none; }
    .evtab {
        display: inline-flex; align-items: center; gap: 7px; flex: 0 0 auto;
        padding: 9px 14px; border-radius: 11px; white-space: nowrap;
        font-size: 13px; font-weight: 600; text-decoration: none;
        color: var(--text-muted); background: transparent;
        border: 1px solid transparent; transition: background .15s, color .15s;
    }
    .evtab:hover { background: var(--bg-glass-input); color: var(--text-primary); }
    .evtab i { font-size: 12px; opacity: .85; }
    .evtab-on {
        color: var(--text-primary);
        background: var(--bg-glass-input);
        border-color: var(--border-strong);
    }
    .evtab-on i { opacity: 1; color: var(--accent, #6366f1); }
    .evtab:focus-visible { outline: 2px solid var(--accent, #6366f1); outline-offset: 2px; }
</style>
@endpush
@endonce
<nav class="evtabs" aria-label="Event sections">
    @foreach($__evTabs as $__evTab)
        <a href="{{ $__evTab['url'] }}"
           class="evtab {{ $__evTab['active'] ? 'evtab-on' : '' }}"
           @if($__evTab['active']) aria-current="page" @endif>
            <i class="fas {{ $__evTab['icon'] }}" aria-hidden="true"></i>
            <span>{{ $__evTab['label'] }}</span>
        </a>
    @endforeach
</nav>
@endif
