{{--
    The meal coupons a bulk order produced, on the guest's confirmation.

    Sana, 2026-10-04: "when bulk order.... need to generate food coupons
    ids".

    ---- One per serving, each with its own QR ----------------------------

    An office orders 200 lunches and 200 different people collect them.
    The person who placed the order forwards one code to each of them, so
    each code has to stand on its own: readable text to paste into a
    message, and a QR for the counter to scan.

    ---- The QR comes from the library this app already ships -------------

    `qrcode-generator`, the same one the table-QR and store-QR pages use.
    A hand-rolled encoder was written for this first and produced squares
    that no decoder could read -- three codes, none scannable -- so it was
    thrown away rather than shipped.

    It is fetched only when an order actually has coupons, which on most
    menus is never: every diner buying one dosa would otherwise pay for a
    CDN request for a feature their order cannot produce. The codes are
    printed as text FIRST and the squares fill in behind them, so a slow
    or blocked CDN costs the guest nothing they need -- a camera that will
    not focus is not a reason to turn somebody away from lunch.

    ---- Not called "coupon code" on its own ------------------------------

    The order sheet two screens back has a "Discount code" box. Calling
    both of these a coupon code is how somebody types their lunch pass into
    the discount field and is told it is invalid.

    The page provides the box: <div id="mealCoupons"></div> inside the
    confirmation sheet. This partial is the behaviour only, like the token.
--}}
<style>
    .mc-wrap { margin: 14px 0; text-align: left; }
    .mc-head { font-size: 14px; font-weight: 700; margin: 0 0 2px; }
    .mc-sub { font-size: 12.5px; opacity: .65; margin: 0 0 10px; }
    .mc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(124px, 1fr));
        gap: 8px;
        max-height: 330px;
        overflow: auto;
    }
    .mc-one {
        border: 1px solid rgba(128,128,128,.3);
        border-radius: 10px;
        padding: 9px;
        text-align: center;
        min-width: 0;
    }
    /* Explicit white behind every QR, in both themes: a dark module on a
       dark sheet is not a QR. */
    .mc-qr {
        background: #fff;
        border-radius: 6px;
        padding: 5px;
        display: inline-block;
        margin-bottom: 6px;
        line-height: 0;
    }
    .mc-qr img, .mc-qr svg { display: block; width: 86px; height: 86px; }
    .mc-code {
        font-family: ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .04em;
        user-select: all;
        -webkit-user-select: all;
        word-break: break-all;
    }
    .mc-item { font-size: 11px; opacity: .6; margin-top: 2px; }
    .mc-copy {
        margin-top: 10px;
        border: 1px solid rgba(128,128,128,.4);
        background: transparent;
        color: inherit;
        border-radius: 9px;
        padding: 8px 12px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
    }
</style>

@include('common.partials.menu-qr')
<script>
(function () {
    /**
     * The loader and the draw helper moved to common/partials/menu-qr so
     * the order QR on this same screen could use them too, rather than
     * carrying a second copy inches away that would drift.
     */
    function generator() { return window.menuQr.ready(); }

    /** A QR for one code, or null when the library cannot draw it. */
    function qrFor(make, text) {
        var box = window.menuQr.draw(make, text, 3);
        if (box) { box.className = 'mc-qr'; }
        return box;
    }

    window.menuMealCoupons = {
        show: function (box, coupons) {
            if (!box) { return false; }
            if (!coupons || !coupons.length) {
                box.innerHTML = '';
                box.style.display = 'none';
                return false;
            }

            box.style.display = '';
            box.innerHTML = '';
            box.className = 'mc-wrap';

            var head = document.createElement('p');
            head.className = 'mc-head';
            head.textContent = coupons.length === 1
                ? '1 meal coupon'
                : coupons.length + ' meal coupons';

            // When every coupon is for the same dish -- which is what a
            // bulk order usually is -- the name goes in the subheading
            // once instead of onto all two hundred tiles, where it wrapped
            // to two lines and pushed the codes apart for no information.
            var names = [];
            coupons.forEach(function (c) {
                if (names.indexOf(c.item_name) === -1) { names.push(c.item_name); }
            });
            var oneDish = names.length === 1 ? names[0] : null;

            var sub = document.createElement('p');
            sub.className = 'mc-sub';
            sub.textContent = (oneDish ? 'One per serving of ' + oneDish + '. ' : 'One per serving. ')
                + 'Send one to each person, or show them at the counter.';

            box.appendChild(head);
            box.appendChild(sub);

            var grid = document.createElement('div');
            grid.className = 'mc-grid';

            // The codes go up now. The squares arrive after, into the slot
            // kept for them, so nothing the guest needs waits on a CDN.
            var slots = [];
            coupons.forEach(function (c) {
                var one = document.createElement('div');
                one.className = 'mc-one';

                var slot = document.createElement('div');
                one.appendChild(slot);
                slots.push([slot, c.code]);

                var code = document.createElement('div');
                code.className = 'mc-code';
                code.textContent = c.code;

                one.appendChild(code);
                if (!oneDish) {
                    var item = document.createElement('div');
                    item.className = 'mc-item';
                    item.textContent = c.item_name;
                    one.appendChild(item);
                }
                grid.appendChild(one);
            });

            box.appendChild(grid);

            generator().then(function (make) {
                if (!make) { return; }
                slots.forEach(function (pair) {
                    var qr = qrFor(make, pair[1]);
                    if (qr) { pair[0].appendChild(qr); }
                });
            });

            var copy = document.createElement('button');
            copy.type = 'button';
            copy.className = 'mc-copy';
            copy.textContent = 'Copy all codes';
            copy.onclick = function () {
                var text = coupons.map(function (c) {
                    return c.code + '  ' + c.item_name;
                }).join('\n');
                var done = function () {
                    copy.textContent = 'Copied';
                    setTimeout(function () { copy.textContent = 'Copy all codes'; }, 1600);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done, function () {});
                }
            };
            box.appendChild(copy);

            return true;
        },
    };
})();
</script>
