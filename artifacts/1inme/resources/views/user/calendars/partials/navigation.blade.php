@include('user.planner.styles')
<nav class="calendar-area-tabs" aria-label="Calendar sections">
@if(\App\Modules\User\Services\WorkspacePermissions::userCan('links.view'))<a href="{{ route('user.calendars.mine') }}" @if(request()->routeIs('user.calendars.*')) aria-current="page" @endif>Schedule</a>@endif
@if(\App\Modules\User\Services\WorkspacePermissions::userCan('settings.view'))
@if(\App\Modules\Common\Support\EventsModule::enabled())<a href="{{ route('user.events.index') }}" @if(request()->routeIs('user.events.*')) aria-current="page" @endif>Event Pages</a>@endif
<a href="{{ route('user.calendar.index') }}" @if(request()->routeIs('user.calendar.*')) aria-current="page" @endif>Connections</a>
@endif
</nav>
<style>.calendar-area-tabs{display:flex;gap:6px;overflow-x:auto;white-space:nowrap;padding:8px;margin-bottom:24px;border:1px solid var(--border-strong);border-radius:16px;background:var(--bg-card)}.calendar-area-tabs a{padding:10px 16px;color:var(--text-secondary);border-radius:10px;font-weight:600;font-size:14px}.calendar-area-tabs a[aria-current=page]{background:var(--accent,#3155ef);color:white}.calendar-context-filter{min-width:160px;flex:1}.calendar-context-filter select{width:100%;padding:10px;border:1px solid var(--border-strong);border-radius:12px;background:var(--bg-card);color:var(--text-primary)}</style>
