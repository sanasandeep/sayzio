{{--
    The order's own QR, on the guest's confirmation.

    Sana, 2026-10-05: "QR code per order".

    ---- What the counter was doing before --------------------------------

    Reading a number off a phone and finding it on a board. That works at
    four orders an hour and stops working at forty, which is exactly when
    it matters -- and it fails in the two ways that cost most: the wrong
    order gets handed over, or the right one gets handed over twice.

    ---- Why it sits under the number and not instead of it ---------------

    The big number is still the thing a guest listens for across a room,
    and it is the fallback when a camera will not focus. The QR is for the
    counter, not for the guest, so it goes second and smaller -- present
    when staff ask for it, not competing with the number for the eye.

    ---- What is in the square -------------------------------------------

    The order's own identifier, which has existed since ordering shipped
    and is what the guest's own screen already polls with. Nothing new is
    minted and nothing private is in it: it names an order on this menu and
    opens nothing on its own.

    The box is provided by the page: <div id="ordQr"></div> inside the
    confirmation sheet. This partial is the behaviour only, like the token.
--}}
@include('common.partials.menu-qr')
<style>
    .oq {
        display: flex; align-items: center; gap: 13px;
        margin: 0 0 14px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid rgba(128,128,128,.28);
    }
    /* Explicit white behind the square, in both themes: a dark module on
       a dark sheet is not a QR. */
    .oq-sq {
        flex: none;
        background: #fff;
        border-radius: 8px;
        padding: 6px;
        width: 86px; height: 86px;
        display: flex; align-items: center; justify-content: center;
    }
    .oq-sq svg { width: 100%; height: 100%; display: block; }
    .oq-t { flex: 1; min-width: 0; }
    .oq-lab { font-size: 13px; font-weight: 700; margin: 0 0 2px; }
    .oq-download { margin-top:8px; padding:6px 10px; border:1px solid rgba(128,128,128,.28); border-radius:8px; background:transparent; color:inherit; font:inherit; font-size:12px; cursor:pointer; }
    .oq-sub { font-size: 12px; opacity: .6; margin: 0; line-height: 1.45; }
</style>

<script>
(function () {
    window.menuOrderQr = {
        /**
         * Paint the order's QR into `box`, or leave the box empty and
         * hidden when there is no code on this order.
         *
         * The words go up immediately and the square arrives into the slot
         * behind them, so a guest whose network blocks the CDN still sees
         * what the panel is for and still has their number.
         */
        show: function (box, order) {
            if (!box) { return false; }

            var code = order && order.order_code;
            if (!code) {
                box.innerHTML = '';
                box.style.display = 'none';
                return false;
            }

            box.style.display = '';
            box.innerHTML = '';
            box.className = 'oq';

            var sq = document.createElement('span');
            sq.className = 'oq-sq';

            var txt = document.createElement('span');
            txt.className = 'oq-t';

            var lab = document.createElement('p');
            lab.className = 'oq-lab';
            lab.textContent = 'Show this at the counter';

            var sub = document.createElement('p');
            sub.className = 'oq-sub';
            sub.textContent = 'Staff scan it to pull your order up. No need to read the number out.';

            txt.appendChild(lab);
            txt.appendChild(sub);
            box.appendChild(sq);
            box.appendChild(txt);

            window.menuQr.one(code, 4).then(function (el) {
                if (!el) {
                    // No square, so the panel is promising something it
                    // cannot deliver. Take it down rather than leave a
                    // white hole next to the words "show this".
                    box.innerHTML = '';
                    box.style.display = 'none';
                    box.className = '';
                    return;
                }
                if (!box.contains(sq)) { return; }
                sq.appendChild(el);
                var download = document.createElement('button');
                download.type = 'button';
                download.className = 'oq-download';
                download.textContent = 'Download QR (SVG)';
                download.addEventListener('click', function () {
                    // A white quiet zone keeps the downloaded QR scannable
                    // even when opened on a dark background or printed.
                    var copy = el.cloneNode(true);
                    copy.setAttribute('x', '24'); copy.setAttribute('y', '24');
                    copy.setAttribute('width', '512'); copy.setAttribute('height', '512');
                    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="560" height="560" viewBox="0 0 560 560"><rect width="560" height="560" fill="white"/>' +
                        new XMLSerializer().serializeToString(copy) + '</svg>';
                    var url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));
                    var anchor = document.createElement('a');
                    anchor.href = url;
                    anchor.download = 'order-qr-' + String(order.token_number || code).replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 80) + '.svg';
                    document.body.appendChild(anchor); anchor.click(); anchor.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                });
                txt.appendChild(download);
            });

            return true;
        },
    };
})();
</script>
