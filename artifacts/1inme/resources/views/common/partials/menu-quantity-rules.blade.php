{{--
    How many of one thing a guest may put in the cart, in the browser.

    Sana, 2026-10-04: "need options while order from menu minimum and max
    order quatity or each order item".

    The server decides -- MenuBulkOrder::check refuses a request whatever
    the page did. This exists so the guest is not refused AFTER they
    thought they were done, which is the same pair of guards the choice
    rules have and for the same reason.

    ---- The first tap is not one of them --------------------------------

    A dish sold in trays of ten starts at ten. Adding one and then making
    the guest press + nine times, with the order refused the whole way, is
    a worse version of having no rule at all. So Add puts the minimum in
    the cart and the stepper moves from there.

    ---- And minus removes the line -------------------------------------

    From ten, one tap of minus cannot leave nine: nine is not orderable.
    So at the floor, minus clears the line, which is the only other thing
    it could honestly mean.
--}}
<script>
(function () {
    /**
     * The backstop for an item that sets no ceiling of its own.
     *
     * Comes from the server, so this and the order endpoint's validation
     * cannot disagree -- the browser offering a quantity the request will
     * refuse is the failure this whole file exists to prevent.
     */
    var CART_MAX = {{ \App\Modules\User\Support\MenuBulkOrder::LINE_MAX }};
    window.MENU_LINE_MAX = CART_MAX;

    window.menuLimits = {
        /**
         * The rule on a catalog entry.
         *
         * Reads `min`/`max` off the entry the page built from the Add row's
         * data attributes, so an item with no rule answers {min:1} and
         * everything below behaves as it did before any of this existed.
         */
        of: function (it) {
            var min = Math.max(1, parseInt(it && it.min, 10) || 1);
            var max = (it && it.max != null && it.max !== '')
                ? Math.max(min, parseInt(it.max, 10) || min)
                : CART_MAX;

            return { min: min, max: Math.min(CART_MAX, max) };
        },

        /** How many a first Add puts in the cart. */
        first: function (it) { return this.of(it).min; },

        /** One more, or null when the ceiling says no. */
        up: function (it, qty) {
            var lim = this.of(it);
            return qty >= lim.max ? null : qty + 1;
        },

        /** One fewer, or 0 meaning remove the line. */
        down: function (it, qty) {
            var lim = this.of(it);
            return qty <= lim.min ? 0 : qty - 1;
        },

        /**
         * Say it on the item's own row, and take it back down again.
         *
         * Not an alert and not the cart's error line: the guest is looking
         * at the + they just pressed, and the cart is two taps away.
         */
        say: function (id, message) {
            var el = document.querySelector('[data-cap="' + id + '"]');
            if (!el) { return; }

            el.textContent = message || '';
            el.style.display = message ? '' : 'none';

            if (el._szTimer) { clearTimeout(el._szTimer); }
            if (!message) { return; }

            el._szTimer = setTimeout(function () {
                el.textContent = '';
                el.style.display = 'none';
            }, 4000);
        },

        /** What to say when the + will not go, or ''. */
        atCeiling: function (it, qty) {
            var lim = this.of(it);
            if (qty < lim.max) { return ''; }

            return lim.max >= CART_MAX
                ? 'That is as many as one order can take.'
                : 'You can order at most ' + lim.max + ' of ' + (it.name || 'this') + '.';
        },
    };
})();
</script>
