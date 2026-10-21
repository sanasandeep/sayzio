@extends('user.layouts.app')

@section('title', 'People & connections: ' . $link->title)

@section('content')
<div class="pb-8 px-4">
    @include('user.partials.event-header', [
        'link'  => $link,
        'title' => 'People & connections',
        'icon'  => 'fa-people-arrows',
    ])

    <div class="ev-panel p-5 mb-6">
        <div class="ev-section-head">
            <span class="ev-section-icon"><i class="fas fa-people-arrows"></i></span>
            <span class="ev-section-title">Networking at this event</span>
            <span class="ev-section-meta" id="people-updated"></span>
        </div>

        <div class="ev-stats">
            <div class="ev-stat">
                <div class="ev-stat-label">Discoverable</div>
                <div id="stat-opted-in" class="ev-stat-value" data-pending="1">–</div>
                <div class="ev-stat-note"><span id="stat-opted-active">–</span> currently active</div>
            </div>
            <div class="ev-stat">
                <div class="ev-stat-label">Requests sent</div>
                <div id="stat-requests" class="ev-stat-value" data-pending="1">–</div>
            </div>
            <div class="ev-stat">
                <div class="ev-stat-label">Connections</div>
                <div id="stat-accepted" class="ev-stat-value is-good" data-pending="1">–</div>
            </div>
            <div class="ev-stat">
                <div class="ev-stat-label">Acceptance</div>
                <div id="stat-rate" class="ev-stat-value is-accent" data-pending="1">–</div>
                <div class="ev-stat-note"><span id="stat-pending">–</span> pending</div>
            </div>
        </div>
    </div>

    <div class="ev-panel p-5">
        <div class="ev-section-head">
            <span class="ev-section-icon"><i class="fas fa-link"></i></span>
            <span class="ev-section-title">Recent connections</span>
        </div>
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

    // Writes a value and takes it out of the pending state. A figure that
    // never arrives keeps pulsing rather than settling on a confident
    // zero: "we have not heard" and "none" are different answers.
    function put(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = value;
        el.removeAttribute('data-pending');

        // Nothing to be pleased about yet: drop the semantic colour so a
        // zero does not announce itself as good news. Parses "0%" too.
        var n = parseFloat(String(value));
        el.classList.toggle('is-zero', !isFinite(n) || n === 0);
    }

    function render(d) {
        put('stat-opted-in', d.opted_in_total);
        put('stat-opted-active', d.opted_in_active);
        put('stat-requests', d.requests_total);
        put('stat-accepted', d.accepted_total);
        put('stat-rate', d.acceptance_rate + '%');
        put('stat-pending', d.pending_total);
        document.getElementById('people-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();

        const list = document.getElementById('recent-list');
        const items = d.recent_accepted || [];
        if (!items.length) {
            list.innerHTML = '<div class="ev-empty">'
                + '<div class="ev-empty-icon"><i class="fas fa-handshake"></i></div>'
                + '<div class="ev-empty-title">No connections yet</div>'
                + '<div class="ev-empty-note">They will appear here as attendees exchange contacts.</div>'
                + '</div>';
            return;
        }
        list.innerHTML = items.map(function (x) {
            const when = x.accepted_at ? new Date(x.accepted_at).toLocaleTimeString() : '';
            return `<div class="flex items-center justify-between text-sm p-2 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
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
