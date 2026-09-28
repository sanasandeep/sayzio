{{--
    What happens the moment an order goes through. Shared by both menu pages.

    Sana, 2026-09-28: "if whatsapp no. give it should open whatsapp app or
    web or anything that send [it] also with confirm order", and "i need
    option to link any page url foir order confirmation page... also if no
    page, then option should be there for message like thank you".

    ---- Why the WhatsApp tab is opened before the order is placed --------

    A popup blocker allows `window.open` only from inside the gesture that
    caused it. The URL we want is not known until the server answers, and by
    then the gesture is long over -- so opening it after the response gets
    the tab swallowed silently, which is the version of this that looks like
    it simply does not work.

    So the tab is reserved on the tap, while the click is still on the
    stack, and pointed at the chat once the order comes back. If there is no
    order to send -- the request failed, or no number is configured after
    all -- the reserved tab is closed again rather than left sitting there
    blank.

    It is only reserved when the menu HAS a WhatsApp number, because that is
    known at render time; a menu without one never opens a tab it would
    immediately have to close.

    ---- The confirmation ------------------------------------------------

    Three modes, resolved server-side by MenuConfirmation so the page never
    sees a half-configured one: the estimated bill (what this always did), a
    message the owner wrote, or the owner's own page.

    The redirect carries nothing with it -- not the order reference, not the
    guest's name. The public token is what polls the order's status, so
    putting it in a URL hands it to whatever sits in front of that page and
    to every referrer header the guest's browser sends onward. If an owner
    wants their page to name the order, that wants a deliberate design, not
    a query string bolted onto a navigation.
--}}
<script>
(function () {
    // Reserve the tab while the tap is still on the stack. Returns null when
    // the menu has no number, or when the browser refused anyway.
    window.menuWhatsappReserve = function (enabled) {
        if (!enabled) { return null; }
        try { return window.open('', '_blank'); } catch (e) { return null; }
    };

    // Point the reserved tab at the chat, or give it back.
    window.menuWhatsappHandoff = function (win, whatsapp) {
        var url = whatsapp && whatsapp.url ? whatsapp.url : null;

        if (!url) {
            if (win && !win.closed) { try { win.close(); } catch (e) {} }
            return false;
        }
        if (win && !win.closed) {
            try { win.location.replace(url); return true; } catch (e) {}
        }
        // Blocked or closed: the button on the confirmation is still there.
        return false;
    };

    /**
     * Shape the confirmation to the owner's setting.
     *
     * cfg  {mode, url, message, headline} as MenuConfirmation resolved it.
     * els  {headline, message, bill: [...]} elements on this page.
     *
     * Returns 'redirected' when the page is leaving -- the caller should
     * stop, because anything it does after this is for a page nobody will
     * see -- or 'shown' when the sheet is the thing to open.
     */
    window.menuConfirmation = function (cfg, els) {
        cfg = cfg || {};
        els = els || {};

        if (cfg.mode === 'url' && cfg.url) {
            window.location.href = cfg.url;
            return 'redirected';
        }

        if (cfg.headline && els.headline) {
            els.headline.textContent = cfg.headline;
        }

        if (cfg.mode === 'message' && cfg.message) {
            (els.bill || []).forEach(function (el) {
                if (el) { el.style.display = 'none'; }
            });
            if (els.message) {
                els.message.textContent = cfg.message;
                els.message.style.display = '';
            }
        }

        return 'shown';
    };
})();
</script>
<style>
    /* The owner's own words, so they get room to be read rather than the
       small print treatment the estimate note gets. */
    .done-msg {
        margin: 14px 0 4px;
        font-size: 15px;
        line-height: 1.5;
        white-space: pre-line;
    }
</style>
