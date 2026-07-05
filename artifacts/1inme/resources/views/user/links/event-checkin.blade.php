@extends('user.layouts.app')

@section('title', 'Check-in — ' . $link->title)

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Door check-in',
        'subtitle' => $link->title,
        'icon' => 'fa-qrcode',
        'back' => route('user.links.ics.tickets', $link),
    ])

    <div class="card-premium p-5 mb-6" id="progress-card">
        <div class="flex items-baseline justify-between mb-3">
            <div class="text-sm font-semibold" style="color: var(--text-primary);">Live door progress</div>
            <div class="text-[11px]" style="color: var(--text-muted);" id="progress-updated"></div>
        </div>
        <div class="flex items-end gap-2 mb-2">
            <span id="progress-in" class="text-3xl font-bold" style="color: var(--text-primary);">–</span>
            <span class="text-sm mb-1" style="color: var(--text-muted);">/ <span id="progress-sold">–</span> checked in</span>
        </div>
        <div class="w-full h-2 rounded-full overflow-hidden" style="background: rgba(255,255,255,0.08);">
            <div id="progress-bar" class="h-full rounded-full" style="width:0%; background: var(--accent, #6366f1); transition: width .3s;"></div>
        </div>
        <div id="progress-tiers" class="mt-4 space-y-1.5"></div>
    </div>

    <div class="card-premium p-5 mb-6">
        <div id="reader" style="max-width: 420px; margin: 0 auto;"></div>
        <div id="scan-result" class="mt-4 text-sm text-center" style="color: var(--text-secondary);"></div>

        <div class="mt-6">
            <label class="text-xs block mb-1" style="color: var(--text-muted);">Or enter code manually</label>
            <form id="manual-form" class="flex gap-2">
                <input type="text" id="manual-code" class="flex-1 px-3 py-2 rounded-lg text-sm" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: var(--text-primary);" placeholder="Ticket code">
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
        document.getElementById('progress-in').textContent = t.checked_in;
        document.getElementById('progress-sold').textContent = t.sold;
        const pct = t.sold > 0 ? Math.round((t.checked_in / t.sold) * 100) : 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
        const tiersEl = document.getElementById('progress-tiers');
        tiersEl.innerHTML = (data.tiers || []).map(function (tier) {
            return `<div class="flex items-center justify-between text-sm">
                <span style="color: var(--text-secondary);">${esc(tier.name)}</span>
                <span style="color: var(--text-primary); font-weight:600;">${tier.checked_in} / ${tier.sold}</span>
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
            resultEl.innerHTML = '<div style="color:#ef4444;">Network error — try again.</div>';
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
            resultEl.innerHTML = '<div style="color:#f59e0b;">Camera unavailable — use manual entry below.</div>';
        });
    }
})();
</script>
@endsection
