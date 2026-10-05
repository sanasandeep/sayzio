{{--
    Drawing a QR on a menu page, once, for everything that needs one.

    Sana, 2026-10-05: "QR code per order".

    ---- Why this is its own partial --------------------------------------

    The meal-coupon tiles already had a loader for `qrcode-generator` and
    a draw helper, both private to that partial's closure. The order QR
    needs exactly the same two things on exactly the same screen, and the
    choice was to copy twenty lines or to lift them out. Copied, the next
    person to fix a QR bug fixes it in one of two places and the other one
    keeps the bug -- and on this page the two would be inches apart.

    ---- Fetched only when something actually draws -----------------------

    The library is requested on the first call and never before. Most menus
    hand out no coupons and most guests never place an order, so a page
    that draws nothing pays nothing. One request serves every QR on the
    screen afterwards.

    ---- A blocked CDN is not an error -----------------------------------

    Everything here resolves to null rather than throwing: a missing
    script, a refused request, a string the encoder will not take. Callers
    put their readable text up FIRST and let the square arrive into a slot
    behind it, so a guest on hotel wifi still has the thing they need. A
    camera that will not focus is not a reason to turn somebody away from
    lunch, and neither is a CDN.
--}}
@once
<script>
(function () {
    var SRC = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
    var pending = null;

    /** The generator, once. Resolves to null when it cannot be had. */
    function generator() {
        if (typeof window.qrcode === 'function') { return Promise.resolve(window.qrcode); }
        if (pending) { return pending; }

        pending = new Promise(function (resolve) {
            var s = document.createElement('script');
            s.src = SRC;
            s.async = true;
            s.onload = function () {
                resolve(typeof window.qrcode === 'function' ? window.qrcode : null);
            };
            s.onerror = function () { resolve(null); };
            document.head.appendChild(s);
        });

        return pending;
    }

    window.menuQr = {
        /** Resolves to the generator function, or null. */
        ready: generator,

        /**
         * A <span> holding one QR, or null when it cannot be drawn.
         *
         * Synchronous, and takes the generator the caller already awaited,
         * so a list of codes draws in one pass rather than one promise per
         * tile.
         */
        draw: function (make, text, cellSize) {
            if (typeof make !== 'function' || !text) { return null; }
            try {
                // Type 0 lets the library pick the smallest version that
                // fits; M correction survives a thumbprint on a phone
                // screen, which is the condition every one of these is
                // read under.
                var qr = make(0, 'M');
                qr.addData(String(text));
                qr.make();
                var box = document.createElement('span');
                box.setAttribute('aria-hidden', 'true');
                box.innerHTML = qr.createSvgTag({ cellSize: cellSize || 3, margin: 0, scalable: true });
                return box;
            } catch (e) {
                return null;
            }
        },

        /** The one-off case: load if needed, then draw. Resolves to null on failure. */
        one: function (text, cellSize) {
            var self = this;
            return generator().then(function (make) {
                return make ? self.draw(make, text, cellSize) : null;
            });
        },
    };
})();
</script>
@endonce
