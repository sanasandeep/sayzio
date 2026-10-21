@extends('user.layouts.app')

@section('title', 'Check-in: ' . $link->title)

@section('content')
<div class="py-8 px-4">
    @include('user.partials.event-header', [
        'link'  => $link,
        'title' => 'Door check-in',
        'icon'  => 'fa-qrcode',
    ])

    <div class="ev-panel p-5 mb-6" id="progress-card">
        <div class="ev-section-head">
            <span class="ev-section-icon"><i class="fas fa-door-open"></i></span>
            <span class="ev-section-title">Live door progress</span>
            <span class="ev-section-meta" id="progress-updated"></span>
        </div>
        <div class="flex items-end gap-2 mb-3">
            <span id="progress-in" class="ev-stat-value" data-pending="1" style="font-size:2.25rem;">–</span>
            <span class="text-sm mb-1.5" style="color: var(--text-muted);">/ <span id="progress-sold">–</span> checked in</span>
        </div>
        {{-- The track was rgba(255,255,255,0.08) inline: the dark theme's
             value, invisible on a white card. A div is not a form control,
             so the guards in #514 and #517 never looked at it. --}}
        <div class="ev-meter"><div id="progress-bar" class="ev-meter-fill"></div></div>
        <div id="progress-tiers" class="mt-4 space-y-1.5"></div>
    </div>

    <div class="ev-panel p-5 mb-6">
        <div class="ev-section-head">
            <span class="ev-section-icon"><i class="fas fa-qrcode"></i></span>
            <span class="ev-section-title">Scan a ticket</span>
        </div>
        <div id="reader" style="max-width: 420px; margin: 0 auto;"></div>
        <div id="scan-result" class="mt-4 text-sm text-center" style="color: var(--text-secondary);"></div>

        <div class="mt-6">
            <label class="text-xs block mb-1" style="color: var(--text-muted);">Or enter code manually</label>
            <form id="manual-form" class="flex gap-2">
                <input type="text" id="manual-code" class="ev-input flex-1"  placeholder="Ticket code">
                <button type="submit" class="btn-primary px-4 py-2 text-sm rounded-lg">Check in</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const resultEl = document.getElementById('scan-result');
    const progressUrl = '{{ route('user.links.ics.checkin.progress', $link) }}';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    }

    function renderProgress(data) {
        const t = data.totals || { sold: 0, checked_in: 0 };
        // Clear the pending pulse once a real figure lands.
        const inEl = document.getElementById('progress-in');
        inEl.textContent = t.checked_in;
        inEl.removeAttribute('data-pending');
        document.getElementById('progress-sold').textContent = t.sold;
        const pct = t.sold > 0 ? Math.round((t.checked_in / t.sold) * 100) : 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
        const tiersEl = document.getElementById('progress-tiers');
        tiersEl.innerHTML = (data.tiers || []).map(function (tier) {
            return `<div class="flex items-center justify-between text-sm">
                <span style="color: var(--text-secondary);">${esc(tier.name)}</span>
                <span style="color: var(--text-primary); font-weight:600; font-variant-numeric: tabular-nums;">${tier.checked_in} / ${tier.sold}</span>
            </div>`;
        }).join('');
    }

    function loadProgress() {
        fetch(progressUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(d => { if (d) renderProgress(d); })
            .catch(() => {});
    }

    loadProgress();
    setInterval(loadProgress, 5000);

    function renderResult(data) {
        const cls = data.ok ? '#10b981' : '#ef4444';
        let extra = '';
        if (data.ticket) {
            extra = `<div class="mt-1">${esc(data.ticket.attendee_name)} &middot; ${esc(data.ticket.tier_name || '')} &middot; qty ${esc(data.ticket.quantity)}</div>`;
            if (data.status === 'already_checked_in' && data.ticket.checked_in_at) {
                const when = new Date(data.ticket.checked_in_at).toLocaleTimeString();
                const who = data.ticket.checked_in_by ? ' by ' + esc(data.ticket.checked_in_by) : '';
                extra += `<div class="mt-1" style="color: var(--text-muted);">Previously checked in ${when}${who}</div>`;
            }
        }
        resultEl.innerHTML = `<div style="color:${cls}; font-weight:600;">${esc(data.message)}</div>${extra}`;
    }

    function submitCode(code) {
        fetch(window.location.pathname + '/scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ code: code }),
        }).then(r => r.json()).then(function (data) {
            renderResult(data);
            loadProgress();
        }).catch(() => {
            resultEl.innerHTML = '<div style="color:#ef4444;">Network error, try again.</div>';
        });
    }

    document.getElementById('manual-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const val = document.getElementById('manual-code').value.trim();
        if (val) submitCode(val);
    });

    if (window.Html5Qrcode) {
        const scanner = new Html5Qrcode('reader');
        let busy = false;
        Html5Qrcode.getCameras().then(cameras => {
            if (!cameras || !cameras.length) return;
            scanner.start(cameras[0].id, { fps: 10, qrbox: 250 }, (decodedText) => {
                if (busy) return;
                busy = true;
                let code = decodedText;
                try {
                    const url = new URL(decodedText);
                    code = url.pathname.split('/').pop();
                } catch (e) {}
                submitCode(code);
                setTimeout(() => { busy = false; }, 2000);
            }, () => {});
        }).catch(() => {
            resultEl.innerHTML = '<div style="color:#f59e0b;">Camera unavailable, use manual entry below.</div>';
        });
    }
})();
</script>
@endsection
