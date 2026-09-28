{{--
    How a guest page talks to the server.

    Sana, 2026-09-28: "Network error.. when submits".

    It was not a network error. The server was answering correctly the whole
    time; the page was lying about what came back, because every caller was
    written like this:

        try {
            const r = await fetch(url, ...);
            const j = await r.json();          // <- inside the try
            if (!r.ok) { alert(j.error.message); return; }
            ...
        } catch (e) {
            alert('Network error, please try again.');
        }

    `r.json()` sits inside the try. So ANY response that is not JSON -- a 419
    session page, a 500, a proxy error page -- throws while being parsed and
    surfaces as "Network error", and the real status never reaches anyone. The
    guest is told the wifi is broken and the restaurant never learns an order
    was attempted.

    ---- And 419 is the status it was most likely hiding -----------------

    These endpoints live in routes/web.php, so Laravel's CSRF check applies.
    A menu is opened by scanning a QR code at a table and then READ for
    several minutes before anyone orders. When the session lapses in between,
    Place order answers 419 with an HTML page.

    So the page recovers from that one on its own: mint a fresh token, retry
    once, and only then report a failure. A guest sitting at a table should
    not have to know what a CSRF token is.

    Everything else is reported by what it actually was. Three copies of this
    (restaurant quote, restaurant order, store order) is how one of them ends
    up with a message the others don't.

    Exposes window.menuPost(url, body) -> { ok, status, data, message }.
--}}
<script>
(function () {
    var TOKEN_URL = @json(route('public.csrf-token'));

    function currentToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.content : '';
    }

    function setToken(t) {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m && t) m.content = t;
    }

    // A fresh token for this visitor. Starts a new session if the old one
    // lapsed, which is the point.
    function refreshToken() {
        return fetch(TOKEN_URL, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (j) {
              if (j && j.token) { setToken(j.token); return true; }
              return false;
          }).catch(function () { return false; });
    }

    function send(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': currentToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        });
    }

    // What to tell someone standing at a table, by what actually happened.
    function messageFor(status) {
        if (status === 419) return 'This page has been open a while. Please refresh and try again.';
        if (status === 429) return 'Too many attempts. Please wait a moment and try again.';
        if (status === 404) return 'This menu is no longer available.';
        if (status === 0)   return 'No connection. Check your signal and try again.';
        if (status >= 500)  return 'Something went wrong on our side. Please try again.';
        return 'Something went wrong. Please try again.';
    }

    /**
     * POST JSON and always come back with a usable answer.
     *
     * Resolves { ok, status, data, message } and never rejects, so a caller
     * cannot accidentally collapse a real server response into a catch-all.
     */
    window.menuPost = async function (url, body) {
        var r;
        try {
            r = await send(url, body);
        } catch (e) {
            // The genuine article: the request never reached the server.
            return { ok: false, status: 0, data: null, message: messageFor(0) };
        }

        // The session lapsed while the menu was being read. Mint a token and
        // try once more before bothering the guest about it.
        if (r.status === 419) {
            var refreshed = await refreshToken();
            if (refreshed) {
                try {
                    r = await send(url, body);
                } catch (e) {
                    return { ok: false, status: 0, data: null, message: messageFor(0) };
                }
            }
        }

        // Parsed OUTSIDE the try that guards the request, so a body that is
        // not JSON is reported as the status it came with rather than as a
        // network failure.
        var data = null;
        try { data = await r.json(); } catch (e) { data = null; }

        if (r.ok) {
            return { ok: true, status: r.status, data: data, message: null };
        }

        var serverSaid = data && data.error && data.error.message ? data.error.message : null;
        // Laravel's validation shape, which these endpoints also return.
        if (!serverSaid && data && data.message) serverSaid = data.message;

        return {
            ok: false,
            status: r.status,
            data: data,
            message: serverSaid || messageFor(r.status)
        };
    };
})();
</script>
