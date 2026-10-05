{{--
    The serving counter: take a meal coupon, or find an order by phone.

    Sana, 2026-10-04: "when coupoon id give, order of that 1 coupon is
    completed or delivered... also alternatively, search order by phone no
    also possible".

    ---- Why this is at the top of the orders screen ----------------------

    It is the only thing on this page somebody uses while a person is
    standing in front of them. The board below is for the kitchen; this is
    for the hatch. Eight features this month shipped working and reachable
    from nowhere anybody would look, so this one sits where the job happens
    rather than behind a tab.

    ---- Three ways in, because the first two fail in public --------------

    Typing the code is the one that always works. Scanning is faster when
    the browser can do it, and the phone search is for the person who has
    lost the message entirely -- which, at a canteen at 1pm, is somebody
    every day.

    ---- Scanning only where the browser can ------------------------------

    This uses BarcodeDetector, which Chrome on Android has and Safari does
    not. No decoder library is shipped for the others: a megabyte of
    JavaScript downloaded on every orders screen so that one device type
    can skip typing eight characters is not a trade worth making. Where it
    is missing the button is not drawn at all, rather than drawn and
    failing on the tap.

    ---- One box, two kinds of code --------------------------------------

    Sana, 2026-10-05: "QR code per order". Every order now carries its own
    square on the guest's confirmation, and the same camera and the same
    box read it.

    Nothing distinguishes them but length, and nothing needs to: a meal
    coupon is eight characters and an order code is thirty-two, so the
    panel can tell what it is holding from the string alone -- no prefix,
    no marker character, nothing for a guest to get wrong by pasting the
    wrong one. MenuOrderCode owns that rule on the server and a test
    asserts the two lengths stay different.

    Parameters:
      $ocBase       the meal-coupons URL for this link, with no trailing part
      $ocOrderBase  the orders-by-code URL, with no trailing code
      $ocNoun       'order' | 'request'
      $ocBoard      this screen's own URL, for the link to a found card
--}}
@php $ocNoun = $ocNoun ?? 'order'; @endphp
<div class="oc-card" x-data="ordersCounter(@js($ocBase), @js($ocNoun), @js(csrf_token()), @js($ocBoard), @js($ocOrderBase ?? null))">
    <div class="oc-top">
        <div>
            <div class="oc-title">Counter</div>
            <p class="oc-sub">Scan an {{ $ocNoun }}, take a meal coupon, or find an {{ $ocNoun }} by phone number.</p>
        </div>
        <button type="button" class="oc-btn oc-ghost" @click="open = !open"
                :aria-expanded="open ? 'true' : 'false'">
            <span x-text="open ? 'Hide' : 'Open'"></span>
        </button>
    </div>

    <div x-show="open" x-cloak style="margin-top:12px">
        <div class="oc-row">
            <input class="oc-input oc-code" type="text" inputmode="latin"
                   autocomplete="off" spellcheck="false"
                   placeholder="Scan, or type a coupon code"
                   x-model="code" @keydown.enter.prevent="lookup()">
            <button type="button" class="oc-btn" @click="lookup()" :disabled="busy">Look up</button>
            <template x-if="canScan">
                <button type="button" class="oc-btn oc-ghost" @click="toggleScan()">
                    <i class="fas fa-camera"></i> <span x-text="scanning ? 'Stop' : 'Scan'"></span>
                </button>
            </template>
        </div>

        {{-- The camera, only while it is running: a video element sitting
             there with no stream is a black rectangle on the page. --}}
        <div x-show="scanning" x-cloak class="oc-cam">
            <video x-ref="cam" playsinline muted></video>
            <p class="oc-hint">Point at the QR on the {{ $ocNoun }} or the coupon.</p>
        </div>

        <p class="oc-err" x-show="error" x-cloak x-text="error"></p>

        {{-- One order, scanned. Everything a staff member would otherwise
             go to the board for, and the moves that work from here. --}}
        <template x-if="order">
            <div class="oc-found">
                <div class="oc-found-head">
                    <div>
                        <div class="oc-found-code">
                            <span x-show="order.token_number" x-text="'Token ' + order.token_number"></span>
                            <span x-show="!order.token_number">Order</span>
                        </div>
                        <div class="oc-found-item">
                            <span x-text="order.customer_name || 'No name'"></span>
                            <span x-show="order.table_label" x-text="' · ' + order.table_label"></span>
                        </div>
                    </div>
                    <span class="oc-pill" :class="'oc-ord-' + order.status" x-text="order.status_label"></span>
                </div>

                <div class="oc-lines">
                    <template x-for="(li, i) in order.items" :key="i">
                        <div class="oc-line">
                            <span x-text="li.quantity + ' × ' + li.name"></span>
                            <span x-text="li.line_total"></span>
                        </div>
                    </template>
                </div>
                <div class="oc-line oc-line-total">
                    <span>Total</span>
                    <span x-text="order.total"></span>
                </div>

                {{-- The guest's own note. Small, but the reason somebody
                     walks back to the counter when it is missed. --}}
                <p class="oc-said" x-show="order.customer_note" x-cloak x-text="order.customer_note"></p>
                <p class="oc-said" x-show="orderSaid" x-cloak x-text="orderSaid"></p>

                <div class="oc-acts">
                    {{-- Only the moves the server would accept: the list
                         comes from the order's own transition map, so a
                         button that cannot work is never drawn. --}}
                    <template x-for="n in order.next_statuses" :key="n.value">
                        <button type="button" class="oc-btn"
                                :class="n.value === 'cancelled' ? 'oc-ghost' : ''"
                                @click="moveOrder(n.value)" :disabled="busy" x-text="n.label"></button>
                    </template>
                    <a class="oc-btn oc-ghost" :href="cardUrl(order.id)">Open on board</a>
                    <button type="button" class="oc-btn oc-ghost" @click="clear()">Next</button>
                </div>
            </div>
        </template>

        {{-- One coupon, looked up and not yet taken. --}}
        <template x-if="coupon">
            <div class="oc-found">
                <div class="oc-found-head">
                    <div>
                        <div class="oc-found-code" x-text="coupon.code"></div>
                        <div class="oc-found-item" x-text="coupon.item_name"></div>
                    </div>
                    <span class="oc-pill" :class="'oc-' + coupon.status" x-text="statusWord(coupon.status)"></span>
                </div>
                <div class="oc-found-meta">
                    <span x-show="coupon.token_number" x-text="'Token ' + coupon.token_number + ' · '"></span>
                    <span x-show="coupon.customer_name" x-text="coupon.customer_name + ' · '"></span>
                    <span x-text="tallyWords(coupon.tally)"></span>
                </div>
                <p class="oc-said" x-show="said" x-cloak x-text="said"></p>
                <div class="oc-acts">
                    <button type="button" class="oc-btn" x-show="coupon.status === 'issued'"
                            @click="redeem()" :disabled="busy">Collected</button>
                    <button type="button" class="oc-btn oc-ghost" @click="clear()">Next</button>
                </div>
            </div>
        </template>

        <div class="oc-sep"></div>

        <div class="oc-row">
            <input class="oc-input" type="tel" inputmode="tel" autocomplete="off"
                   placeholder="Phone number" x-model="phone"
                   @keydown.enter.prevent="byPhone()">
            <button type="button" class="oc-btn" @click="byPhone()" :disabled="busy">Find</button>
        </div>

        <p class="oc-hint" x-show="phoneSaid" x-cloak x-text="phoneSaid"></p>

        <template x-for="o in found" :key="o.id">
            <div class="oc-hit">
                <div>
                    <span class="oc-hit-tok" x-show="o.token_number" x-text="o.token_number"></span>
                    <span x-text="o.customer_name || 'No name'"></span>
                    <span class="oc-hit-meta" x-text="' · ' + o.status_label + ' · ' + tallyWords(o.coupons)"></span>
                </div>
                {{-- A link, not a scroll. An order found by phone is very
                     often completed AND outside the date window this board
                     is showing, so scrolling to its card would have
                     scrolled to a card that is not being rendered and
                     looked like a dead button. The board already knows how
                     to open on one order from a highlight. --}}
                <a class="oc-btn oc-ghost" :href="cardUrl(o.id)">Open</a>
            </div>
        </template>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    .oc-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        padding: 16px 18px;
        margin-bottom: 14px;
    }
    .oc-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .oc-title { font-weight: 700; font-size: 15px; color: var(--text-primary); }
    .oc-sub { font-size: 12.5px; color: var(--text-muted); margin: 2px 0 0; }
    .oc-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .oc-input {
        flex: 1 1 180px;
        min-width: 0;
        padding: 9px 12px;
        border-radius: 10px;
        border: 1px solid var(--border-glass);
        background: var(--bg-glass-input, rgba(0,0,0,.2));
        color: var(--text-primary);
        font-size: 14px;
        outline: none;
    }
    /* The code is read off a screen and typed: wide spacing, one case. */
    .oc-code {
        font-family: ui-monospace, 'SF Mono', Menlo, monospace;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    /* The placeholder is a sentence, not a code. Without this it inherits
       the uppercasing above and the empty box shouts
       "COUPON CODE, E.G. K3M7-PQRS" at whoever is standing at the hatch. */
    .oc-code::placeholder {
        text-transform: none;
        letter-spacing: normal;
        font-family: inherit;
    }
    .oc-btn {
        padding: 9px 15px;
        border-radius: 999px;
        border: 0;
        background: linear-gradient(135deg,#5c83ff,#6366f1);
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }
    .oc-btn:disabled { opacity: .55; cursor: default; }
    .oc-ghost {
        background: transparent;
        color: var(--text-muted);
        border: 1px solid var(--border-glass);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .oc-cam { margin-top: 10px; }
    .oc-cam video {
        width: 100%;
        max-width: 320px;
        border-radius: 12px;
        background: #000;
        display: block;
    }
    .oc-err { font-size: 13px; color: var(--accent-danger,#f87171); margin: 10px 0 0; }
    .oc-hint { font-size: 12px; color: var(--text-muted); margin: 6px 0 0; }
    .oc-said { font-size: 13px; color: var(--text-primary); margin: 8px 0 0; font-weight: 600; }
    .oc-found {
        margin-top: 12px;
        padding: 12px;
        border: 1px solid var(--border-glass);
        border-radius: 12px;
    }
    .oc-found-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .oc-found-code {
        font-family: ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 17px;
        font-weight: 800;
        letter-spacing: .06em;
        color: var(--text-primary);
    }
    .oc-found-item { font-size: 13px; color: var(--text-primary); margin-top: 2px; }
    .oc-found-meta { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
    .oc-acts { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
    .oc-pill { padding: 4px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #fff; white-space: nowrap; }
    .oc-issued { background: #10b981; }
    .oc-redeemed { background: #6b7280; }
    .oc-void { background: #9ca3af; }
    /* The order's status, in the colour a counter reads at a glance:
       green means hand it over, amber means it is still in the kitchen.
       Its own set rather than reusing the coupon pills, because
       "redeemed" grey and "completed" grey mean different things and
       sharing a class is how one later gets restyled into the other. */
    .oc-ord-new { background: #3d6bff; }
    .oc-ord-accepted { background: #6366f1; }
    .oc-ord-preparing { background: #f59e0b; }
    .oc-ord-ready { background: #10b981; }
    .oc-ord-completed { background: #6b7280; }
    .oc-ord-cancelled { background: #9ca3af; }
    .oc-lines { margin-top: 10px; }
    .oc-line {
        display: flex; justify-content: space-between; gap: 12px;
        font-size: 13px; color: var(--text-primary);
        padding: 3px 0;
    }
    .oc-line-total {
        font-weight: 700;
        border-top: 1px solid var(--border-glass);
        margin-top: 4px; padding-top: 7px;
    }
    .oc-sep { height: 1px; background: var(--border-glass); margin: 14px 0; }
    .oc-hit {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 9px 0;
        border-bottom: 1px dashed var(--border-glass);
        font-size: 13.5px;
        color: var(--text-primary);
        flex-wrap: wrap;
    }
    .oc-hit-tok {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 24px;
        padding: 0 7px;
        margin-right: 8px;
        border-radius: 7px;
        background: var(--bg-subtle, rgba(128,128,128,.14));
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    .oc-hit-meta { color: var(--text-muted); font-size: 12.5px; }
</style>

<script>
@once
function ordersCounter(base, noun, csrf, board, orderBase) {
    return {
        open: false,
        code: '',
        phone: '',
        busy: false,
        error: '',
        said: '',
        phoneSaid: '',
        coupon: null,
        order: null,
        orderSaid: '',
        found: [],
        scanning: false,
        stream: null,
        detector: null,
        scanTimer: null,

        /** Chrome on Android has this; Safari does not. */
        get canScan() {
            return typeof window.BarcodeDetector === 'function'
                && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        },

        clear() {
            this.coupon = null;
            this.order = null;
            this.code = '';
            this.error = '';
            this.said = '';
            this.orderSaid = '';
        },

        /**
         * An order code, or a coupon code?
         *
         * Length alone, which is the whole reason this panel needs no
         * prefix and no second box: a meal coupon is eight characters from
         * an alphabet with no 0, 1, I, L, O or U, and an order code is a
         * thirty-two character hex string. MenuOrderCode::isOne() is the
         * same rule on the server, and a test holds the two lengths apart.
         */
        isOrderCode(typed) {
            return /^[0-9A-F]{32}$/.test(typed);
        },

        statusWord(s) {
            return { issued: 'Not collected', redeemed: 'Collected', void: 'Cancelled' }[s] || s;
        },

        /** "3 of 50 collected", or the plain truth when there are none. */
        tallyWords(t) {
            if (!t || !t.total) { return 'No coupons'; }
            return t.redeemed + ' of ' + t.total + ' collected';
        },

        /**
         * The board, opened on one order.
         *
         * `range=all` as well as the highlight: the board defaults to
         * today, and the order somebody is asking about at the counter is
         * routinely from a date that is not today.
         */
        cardUrl(id) {
            const url = new URL(board, location.origin);
            url.searchParams.set('range', 'all');
            url.searchParams.set('highlight', id);
            return url.toString();
        },

        async lookup() {
            const typed = (this.code || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            if (!typed) { this.error = 'Scan a code, or type one from a coupon.'; return; }

            if (this.isOrderCode(typed)) { return this.lookupOrder(typed); }

            this.busy = true;
            this.error = '';
            this.said = '';
            this.order = null;
            try {
                const r = await fetch(base + '/' + encodeURIComponent(typed), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const j = await r.json();
                if (!r.ok) {
                    this.coupon = null;
                    this.error = (j.error && j.error.message) || 'That code could not be looked up.';
                    return;
                }
                this.coupon = j.data.coupon;
                if (this.coupon.status !== 'issued') {
                    this.said = this.coupon.status === 'void'
                        ? 'This coupon was cancelled.'
                        : 'Already collected.';
                }
            } catch (e) {
                this.error = 'Could not reach the server. Try again.';
            } finally {
                this.busy = false;
            }
        },

        /** The order behind a scanned square. */
        async lookupOrder(typed) {
            if (!orderBase) {
                this.error = 'Scanning orders is not set up on this screen.';
                return;
            }

            this.busy = true;
            this.error = '';
            this.said = '';
            this.orderSaid = '';
            this.coupon = null;
            try {
                const r = await fetch(orderBase + '/' + encodeURIComponent(typed), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const j = await r.json();
                if (!r.ok) {
                    this.order = null;
                    this.error = (j.error && j.error.message) || 'That code could not be looked up.';
                    return;
                }
                this.order = j.data.order;
            } catch (e) {
                this.error = 'Could not reach the server. Try again.';
            } finally {
                this.busy = false;
            }
        },

        /**
         * Move the scanned order along.
         *
         * Posts to the URL the lookup handed over, which is the same
         * endpoint the board uses and the one that validates the
         * transition. The server's answer replaces what is on screen, so a
         * move another phone made in the meantime shows up here rather
         * than being painted over optimistically.
         */
        async moveOrder(status) {
            if (!this.order) { return; }
            this.busy = true;
            this.error = '';
            this.orderSaid = '';
            try {
                const r = await fetch(this.order.status_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ status: status }),
                });
                const j = await r.json();
                if (!r.ok) {
                    this.error = (j.error && j.error.message) || 'That could not be changed.';
                    return;
                }
                // Re-read rather than patch: the response from the status
                // endpoint is the board's shape, not the counter's, and
                // guessing at the new transitions here is how a button
                // that cannot work gets drawn.
                await this.lookupOrder(this.order.code);
                if (this.order) {
                    this.orderSaid = 'Now ' + String(this.order.status_label).toLowerCase() + '.';
                }
            } catch (e) {
                this.error = 'Could not reach the server. Try again.';
            } finally {
                this.busy = false;
            }
        },

        async redeem() {
            if (!this.coupon) { return; }
            this.busy = true;
            this.error = '';
            try {
                const r = await fetch(base + '/' + encodeURIComponent(this.coupon.code.replace(/[^A-Za-z0-9]/g, '')) + '/redeem', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const j = await r.json();
                if (!r.ok) {
                    this.error = (j.error && j.error.message) || 'That could not be recorded.';
                    return;
                }
                this.coupon = j.data.coupon;
                this.said = j.data.message || '';
                // The order may have just finished, which the board below
                // shows -- and it polls, so it will catch up on its own.
            } catch (e) {
                this.error = 'Could not reach the server. Nothing was recorded.';
            } finally {
                this.busy = false;
            }
        },

        async byPhone() {
            const digits = (this.phone || '').replace(/[^0-9]/g, '');
            this.found = [];
            this.phoneSaid = '';
            if (digits.length < 6) {
                this.phoneSaid = 'Type at least six digits of the phone number.';
                return;
            }

            this.busy = true;
            try {
                const r = await fetch(base + '/by-phone?phone=' + encodeURIComponent(digits), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const j = await r.json();
                if (!r.ok) {
                    this.phoneSaid = (j.error && j.error.message) || 'That number could not be searched.';
                    return;
                }
                this.found = j.data.orders || [];
                if (!this.found.length) {
                    // Written out rather than glued: "No order for that
                    // number" and "No request for that number" are both
                    // sentences, and noun + 's' is not how plurals work.
                    this.phoneSaid = 'No ' + noun + ' on this menu for that number.';
                }
            } catch (e) {
                this.phoneSaid = 'Could not reach the server. Try again.';
            } finally {
                this.busy = false;
            }
        },

        async toggleScan() {
            if (this.scanning) { this.stopScan(); return; }
            this.error = '';
            try {
                this.detector = this.detector || new window.BarcodeDetector({ formats: ['qr_code'] });
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                });
                this.scanning = true;
                this.$nextTick(() => {
                    const cam = this.$refs.cam;
                    if (!cam) { return; }
                    cam.srcObject = this.stream;
                    cam.play().catch(() => {});
                    // Four looks a second: enough to feel instant, little
                    // enough that a counter phone does not get hot.
                    this.scanTimer = setInterval(() => this.readFrame(), 250);
                });
            } catch (e) {
                // Camera refused, or no camera. The code box is right there.
                this.error = 'Could not open the camera. Type the code instead.';
                this.stopScan();
            }
        },

        async readFrame() {
            const cam = this.$refs.cam;
            if (!cam || !this.detector || cam.readyState < 2) { return; }
            try {
                const hits = await this.detector.detect(cam);
                if (!hits || !hits.length) { return; }
                const raw = (hits[0].rawValue || '').trim();
                if (!raw) { return; }
                this.stopScan();
                this.code = raw;
                this.lookup();
            } catch (e) {
                // A frame that will not decode is the normal case, not an
                // error: the next one is 250ms away.
            }
        },

        stopScan() {
            if (this.scanTimer) { clearInterval(this.scanTimer); this.scanTimer = null; }
            if (this.stream) {
                this.stream.getTracks().forEach(t => { try { t.stop(); } catch (e) {} });
                this.stream = null;
            }
            const cam = this.$refs.cam;
            if (cam) { cam.srcObject = null; }
            this.scanning = false;
        },
    };
}
@endonce
</script>
