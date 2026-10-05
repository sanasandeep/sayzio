{{--
    The cart survives a reload.

    Sana, 2026-09-28: "when reload page, selected items are lost".

    ---- What is stored, and what is deliberately NOT ---------------------

    Only what the customer CHOSE: the item id, the option ids, and how
    many. Never the name, never the price, never the computed per-unit
    total. Those are read back from the live menu on restore.

    That distinction is the whole feature. A cart that restores its own
    stored prices is a cart that quietly sells yesterday's price, or offers
    a dish that was taken off the menu this morning, or carries a topping
    the kitchen has run out of. The quote endpoint would refuse it at
    checkout and the customer would get a rejection after they thought they
    were done -- which is the failure this is supposed to prevent, arriving
    later and more annoyingly.

    So the restore re-derives everything from the page, drops any line it
    cannot rebuild honestly, and SAYS SO. Silently handing someone a
    different order from the one they left is worse than losing it.

    The one exception: the old per-unit price is stored, and used for
    nothing except noticing that it has changed so the customer can be
    told. It never reaches a total.

    ---- Why it expires ---------------------------------------------------

    Reopening a QR code the next lunchtime and finding yesterday's half
    order waiting is confusing at best, and at worst the customer taps
    Place order without reading it. A few hours is "I was just here"; a day
    is not.

    ---- Why every call is wrapped ---------------------------------------

    `localStorage` throws, not returns null, in a Safari private window and
    wherever site data is blocked -- and a menu opened from a QR code on a
    hotel captive portal hits both. A menu that will not render because
    storage is unavailable is a far worse bug than a cart that does not
    persist, so every read and write is guarded and failure is silent.
--}}
<script>
(function () {
    /** Long enough for "I stepped away", short enough not to span a meal. */
    var TTL_MS = 4 * 60 * 60 * 1000;

    function key(alias) { return 'sz.cart.' + alias; }

    function available() {
        try {
            var k = '__sz' + Math.random();
            window.localStorage.setItem(k, '1');
            window.localStorage.removeItem(k);
            return true;
        } catch (e) {
            return false;
        }
    }

    var ON = available();

    window.menuCart = {
        /** Called on every change to the cart. Cheap and silent. */
        save: function (alias, lines) {
            if (!ON) { return; }
            try {
                if (!lines || !lines.length) { this.clear(alias); return; }
                window.localStorage.setItem(key(alias), JSON.stringify({
                    at: Date.now(),
                    lines: lines.map(function (l) {
                        return {
                            id: l.id,
                            qty: l.qty,
                            // Ids and counts only. Names and deltas come
                            // back from the live menu.
                            opts: (l.opts || []).map(function (o) {
                                return { option_id: o.option_id, quantity: o.quantity };
                            }),
                            // Kept ONLY to notice it has changed. Never used
                            // as a price.
                            was: l.perUnit,
                        };
                    }),
                }));
            } catch (e) { /* storage full or blocked: not worth a word */ }
        },

        clear: function (alias) {
            if (!ON) { return; }
            try { window.localStorage.removeItem(key(alias)); } catch (e) {}
        },

        /** The raw stored object, or null when there is nothing usable. */
        read: function (alias) {
            if (!ON) { return null; }
            try {
                var raw = window.localStorage.getItem(key(alias));
                if (!raw) { return null; }
                var parsed = JSON.parse(raw);
                if (!parsed || !Array.isArray(parsed.lines) || !parsed.lines.length) { return null; }
                if (!parsed.at || (Date.now() - parsed.at) > TTL_MS) {
                    this.clear(alias);
                    return null;
                }
                return parsed;
            } catch (e) {
                // Corrupt or from an older shape: throw it away rather than
                // letting it break the page every time they come back.
                this.clear(alias);
                return null;
            }
        },

        /**
         * Rebuild the cart against the menu as it is RIGHT NOW.
         *
         * @param items   the page's catalog, keyed by id
         * @param chooser window.menuChooser
         * @returns {lines, dropped, repriced, lifted} or null when there
         *          is nothing
         */
        restore: function (alias, items, chooser) {
            var stored = this.read(alias);
            if (!stored) { return null; }

            var lines = [], dropped = 0, repriced = 0, lifted = 0;

            stored.lines.forEach(function (sl) {
                var it = items[sl.id];
                // Gone from the menu, or sold out: its Add row is not on
                // the page, so it is not in the catalog either.
                if (!it) { dropped++; return; }

                // Null when a choice has been retired or sold out, or when
                // the item has since gained a rule this selection breaks.
                var opts = chooser.rebuild(sl.id, sl.opts);
                if (opts === null) { dropped++; return; }


                // The backstop was a literal 99 here, which silently cut a
                // restored bulk cart down to 99 the moment bulk orders
                // existed. It comes from the server now, same as the
                // order endpoint's own ceiling.
                var cap = window.MENU_LINE_MAX || 99;
                var want = Math.max(1, Math.min(cap, parseInt(sl.qty, 10) || 1));
                // The item may have gained a minimum or a maximum since
                // this cart was saved, and a quantity outside it cannot be
                // ordered at all. Moved to the nearest number that can,
                // and counted, because the notice has to say so -- leaving
                // the old number would hand them a cart the server refuses
                // and no way to see why.
                var fit = window.menuLimits
                    ? Math.max(window.menuLimits.of(it).min, Math.min(window.menuLimits.of(it).max, want))
                    : want;
                if (fit !== want) { lifted++; }
                var bulk = it.bulkPrice != null && it.bulkPrice !== '' && +it.couponFrom > 0 && fit >= +it.couponFrom;
                var base = bulk ? +it.bulkPrice : it.price;
                var perUnit = Math.round((base + chooser.extraFor(opts)) * 100) / 100;
                if (sl.was != null && Math.abs(sl.was - perUnit) > 0.001) { repriced++; }

                lines.push({
                    key: chooser.key(it.id, opts),
                    id: it.id,
                    name: it.name,
                    opts: opts,
                    perUnit: perUnit,
                    qty: fit,
                });
            });

            if (!lines.length) {
                this.clear(alias);
                return dropped ? { lines: [], dropped: dropped, repriced: 0, lifted: 0 } : null;
            }

            return { lines: lines, dropped: dropped, repriced: repriced, lifted: lifted };
        },

        /**
         * What to tell them, or ''. One sentence: they are looking at a
         * menu, not reading a changelog.
         */
        notice: function (result, noun, nouns) {
            if (!result) { return ''; }
            var parts = [];
            if (result.dropped) {
                // Both words are passed in. Appending an s to the
                // singular gives "dishs", which is the exact bug that
                // shipped on the choices panel a week ago: English
                // plurals are not a string operation.
                parts.push(result.dropped === 1
                    ? 'One ' + noun + ' you had chosen is no longer available, so it has been removed.'
                    : result.dropped + ' ' + nouns + ' you had chosen are no longer available, so they have been removed.');
            }
            if (result.repriced) {
                parts.push('Some prices have changed since you were last here.');
            }
            if (result.lifted) {
                // Both words again, for the same reason as above.
                parts.push(result.lifted === 1
                    ? 'How many of one ' + noun + ' you can order has changed, so the number has been adjusted.'
                    : 'How many of some ' + nouns + ' you can order has changed, so the numbers have been adjusted.');
            }
            return parts.join(' ');
        },
    };
})();
</script>
