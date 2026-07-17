@extends('user.layouts.app')

@section('title', 'People & connections — ' . $link->title)

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'People & connections',
        'subtitle' => $link->title,
        'icon' => 'fa-people-arrows',
        'back' => route('user.links.ics.tickets', $link),
    ])

    <div class="card-premium p-5 mb-6">
        <div class="flex items-baseline justify-between mb-4">
            <div class="text-sm font-semibold" style="color: var(--text-primary);">Networking at this event</div>
            <div class="text-[11px]" style="color: var(--text-muted);" id="people-updated"></div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <div id="stat-opted-in" class="text-3xl font-bold" style="color: var(--text-primary);">–</div>
                <div class="text-xs mt-1" style="color: var(--text-muted);">Opted in to be discoverable</div>
                <div class="text-[11px] mt-0.5" style="color: var(--text-muted);"><span id="stat-opted-active">–</span> currently active</div>
            </div>
            <div>
                <div id="stat-requests" class="text-3xl font-bold" style="color: var(--text-primary);">–</div>
                <div class="text-xs mt-1" style="color: var(--text-muted);">Exchange requests sent</div>
            </div>
            <div>
                <div id="stat-accepted" class="text-3xl font-bold" style="color: #10b981;">–</div>
                <div class="text-xs mt-1" style="color: var(--text-muted);">Connections made</div>
            </div>
            <div>
                <div id="stat-rate" class="text-3xl font-bold" style="color: var(--text-primary);">–</div>
                <div class="text-xs mt-1" style="color: var(--text-muted);">Acceptance rate</div>
                <div class="text-[11px] mt-0.5" style="color: var(--text-muted);"><span id="stat-pending">–</span> pending</div>
            </div>
        </div>
    </div>

    <div class="card-premium p-5">
        <div class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Recent connections</div>
        <div id="recent-list" class="space-y-2">
            <div class="text-sm" style="color: var(--text-muted);">Loading…</div>
        </div>
    </div>
</div>

<script>
(function () {
    const statsUrl = '{{ route('user.links.ics.people.stats', $link) }}';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    }

    function render(d) {
        document.getElementById('stat-opted-in').textContent = d.opted_in_total;
        document.getElementById('stat-opted-active').textContent = d.opted_in_active;
        document.getElementById('stat-requests').textContent = d.requests_total;
        document.getElementById('stat-accepted').textContent = d.accepted_total;
        document.getElementById('stat-rate').textContent = d.acceptance_rate + '%';
        document.getElementById('stat-pending').textContent = d.pending_total;
        document.getElementById('people-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();

        const list = document.getElementById('recent-list');
        const items = d.recent_accepted || [];
        if (!items.length) {
            list.innerHTML = '<div class="text-sm" style="color: var(--text-muted);">No accepted exchanges yet. Connections will appear here as attendees exchange contacts.</div>';
            return;
        }
        list.innerHTML = items.map(function (x) {
            const when = x.accepted_at ? new Date(x.accepted_at).toLocaleTimeString() : '';
            return `<div class="flex items-center justify-between text-sm p-2 rounded-lg" style="background: rgba(255,255,255,0.03);">
                <span style="color: var(--text-secondary);"><i class="fas fa-handshake mr-2" style="color:#10b981;"></i>${esc(x.requester_name)} <i class="fas fa-arrows-left-right mx-1 text-xs" style="color: var(--text-muted);"></i> ${esc(x.recipient_name)}</span>
                <span class="text-[11px]" style="color: var(--text-muted);">${esc(when)}</span>
            </div>`;
        }).join('');
    }

    function load() {
        fetch(statsUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(d => { if (d) render(d); })
            .catch(() => {});
    }

    load();
    setInterval(load, 5000);
})();
</script>
@endsection
