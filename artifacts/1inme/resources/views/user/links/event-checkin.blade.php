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

    function renderResult(data) {
        const cls = data.ok ? '#10b981' : '#ef4444';
        let extra = '';
        if (data.ticket) {
            extra = `<div class="mt-1">${data.ticket.attendee_name} &middot; ${data.ticket.tier_name || ''} &middot; qty ${data.ticket.quantity}</div>`;
        }
        resultEl.innerHTML = `<div style="color:${cls}; font-weight:600;">${data.message}</div>${extra}`;
    }

    function submitCode(code) {
        fetch(window.location.pathname + '/scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ code: code }),
        }).then(r => r.json()).then(renderResult).catch(() => {
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
