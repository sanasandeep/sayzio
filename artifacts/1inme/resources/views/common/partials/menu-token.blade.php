{{--
    The number a guest is told to listen for.

    Sana, 2026-09-28: "when customer sees token no.. make it upto copied
    text".

    ---- Why it is the biggest thing on the confirmation -------------------

    Because it is the only thing on that screen a guest has to act on. The
    itemised bill is for checking; the number is what they listen for over
    a counter, read out to a driver, or type into a message. It is set at
    a size that survives being held at arm's length in a noisy room.

    ---- Copy, and what happens when copy is not available -----------------

    `navigator.clipboard` needs a secure context and permission, and a menu
    opened from a QR code on a hotel wifi captive portal may have neither.
    So the number is ALSO selectable text: a long-press selects just the
    number, without the words around it. The copy button is the
    convenience, not the mechanism.
--}}
<style>
    .tok {
        display: flex; align-items: center; gap: 14px;
        margin: 0 0 14px;
        padding: 14px 16px;
        border-radius: 14px;
        background: rgba(128,128,128,.12);
    }
    .tok-n {
        font-size: 34px; font-weight: 800; line-height: 1;
        font-variant-numeric: tabular-nums;
        /* Selectable on its own, so a long-press grabs the number and not
           the sentence around it. */
        user-select: all; -webkit-user-select: all;
    }
    .tok-t { flex: 1; min-width: 0; }
    .tok-lab { font-size: 12px; opacity: .65; margin: 0 0 1px; }
    .tok-sub { font-size: 12px; opacity: .55; margin: 0; }
    .tok-copy {
        flex: none;
        border: 1px solid rgba(128,128,128,.4);
        background: transparent; color: inherit;
        border-radius: 9px; padding: 7px 11px;
        font-size: 12.5px; font-weight: 600; cursor: pointer;
    }
    .tok-copy:disabled { opacity: .6; cursor: default; }
</style>

<script>
(function () {
    /**
     * Paint the token into `box` for `order`, or leave the box empty and
     * hidden when this menu hands out no numbers.
     */
    window.menuToken = {
        show: function (box, order, subtitle) {
            if (!box) { return false; }

            var n = order && order.token_number;
            if (!n) {
                box.innerHTML = '';
                box.style.display = 'none';
                return false;
            }

            box.style.display = '';
            box.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'tok';

            var num = document.createElement('span');
            num.className = 'tok-n';
            num.textContent = String(n);

            var text = document.createElement('div');
            text.className = 'tok-t';
            var lab = document.createElement('p');
            lab.className = 'tok-lab';
            lab.textContent = 'Your order number';
            var sub = document.createElement('p');
            sub.className = 'tok-sub';
            sub.textContent = subtitle || 'Listen out for it, or show this screen.';
            text.appendChild(lab);
            text.appendChild(sub);

            var copy = document.createElement('button');
            copy.type = 'button';
            copy.className = 'tok-copy';
            copy.textContent = 'Copy';
            copy.onclick = function () {
                var done = function () {
                    copy.textContent = 'Copied';
                    copy.disabled = true;
                    setTimeout(function () {
                        copy.textContent = 'Copy';
                        copy.disabled = false;
                    }, 1600);
                };

                // Clipboard needs a secure context and can be refused, so
                // a failure selects the number instead of doing nothing.
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(String(n)).then(done, function () {
                        window.menuToken.select(num);
                    });
                } else {
                    window.menuToken.select(num);
                }
            };

            wrap.appendChild(num);
            wrap.appendChild(text);
            wrap.appendChild(copy);
            box.appendChild(wrap);

            return true;
        },

        /** The fallback: put the number under the cursor, ready to copy. */
        select: function (node) {
            try {
                var range = document.createRange();
                range.selectNodeContents(node);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            } catch (e) { /* nothing left to try */ }
        },
    };
})();
</script>
