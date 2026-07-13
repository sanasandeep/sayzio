@extends('user.layouts.settings')

@section('title', 'Recent logins')

@section('settings-content')
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold" style="color: var(--text-strong);">Recent logins</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">
            Every successful sign-in to your account from the last few weeks. We email you when a new device, browser, or country signs in.
        </p>
    </div>

    @if(session('status'))
        <div class="mb-4 p-3 rounded-lg text-sm" style="background: var(--surface-soft); color: var(--text);">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-xl overflow-hidden border" style="background: var(--surface); border-color: var(--border);">
        @if($events->isEmpty())
            <div class="p-8 text-center text-sm" style="color: var(--text-muted);">
                No login activity recorded yet.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-soft); color: var(--text-muted); text-align: left;">
                        <th class="px-4 py-3 font-medium">When</th>
                        <th class="px-4 py-3 font-medium">Device</th>
                        <th class="px-4 py-3 font-medium">Location</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $event)
                        <tr class="border-t" style="border-color: var(--border);">
                            <td class="px-4 py-3" style="color: var(--text);">
                                @if($event->created_at)
                                    <time class="js-local-time" datetime="{{ $event->created_at->toIso8601String() }}" title="{{ $event->created_at->format('M j, Y g:i A T') }}">{{ $event->created_at->format('M j, Y g:i A') }}</time>
                                @endif
                            </td>
                            <td class="px-4 py-3" style="color: var(--text);">
                                {{ $event->device_label ?: 'Unknown device' }}
                            </td>
                            <td class="px-4 py-3" style="color: var(--text);">
                                {{ $event->country_code ?: '—' }}
                                @if($event->ip)
                                    <span class="block text-xs" style="color: var(--text-muted); font-family: monospace;">{{ $event->ip }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($event->revoked_at)
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium" style="background:#fee2e2; color:#991b1b;">Revoked</span>
                                @elseif($event->is_new)
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium" style="background:#fef3c7; color:#92400e;">New device</span>
                                @else
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium" style="background:#dcfce7; color:#166534;">Recognized</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(!$event->revoked_at)
                                    <form method="POST" action="{{ route('user.security.logins.revoke-from-list', $event->id) }}" onsubmit="return confirm('Sign every device out and clear your password?');" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium" style="color:#dc2626;">This wasn't me</button>
                                    </form>
                                @else
                                    <span class="text-xs" style="color: var(--text-muted);">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
<script>
(function () {
    function relativeLabel(d) {
        var diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 0) diff = 0;
        if (diff < 45) return 'Just now';
        var mins = Math.floor(diff / 60);
        if (mins < 60) return mins <= 1 ? '1 minute ago' : mins + ' minutes ago';
        var hours = Math.floor(mins / 60);
        if (hours < 24) return hours === 1 ? '1 hour ago' : hours + ' hours ago';
        var days = Math.floor(hours / 24);
        if (days < 7) return days === 1 ? '1 day ago' : days + ' days ago';
        try {
            return d.toLocaleString(undefined, {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: '2-digit'
            });
        } catch (e) {
            return d.toDateString();
        }
    }

    var els = Array.prototype.slice.call(document.querySelectorAll('time.js-local-time'));

    function refresh() {
        els.forEach(function (el) {
            var iso = el.getAttribute('datetime');
            if (!iso) return;
            var d = new Date(iso);
            if (isNaN(d.getTime())) return;
            try {
                el.textContent = relativeLabel(d);
            } catch (e) { /* keep server-rendered fallback */ }
        });
    }

    els.forEach(function (el) {
        var iso = el.getAttribute('datetime');
        if (!iso) return;
        var d = new Date(iso);
        if (isNaN(d.getTime())) return;
        try {
            el.setAttribute('title', d.toLocaleString(undefined, {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: '2-digit', second: '2-digit',
                timeZoneName: 'short'
            }));
        } catch (e) { /* keep server-rendered fallback */ }
    });

    refresh();
    var timer = setInterval(refresh, 60000);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });

    window.addEventListener('pagehide', function () {
        clearInterval(timer);
    });
})();
</script>
@endsection
