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

    Parameters:
      $ocBase   the meal-coupons URL for this link, with no trailing part
      $ocNoun   'order' | 'request'
      $ocBoard  this screen's own URL, for the link to a found card
--}}
@php $ocNoun = $ocNoun ?? 'order'; @endphp
<div class="oc-card" x-data="ordersCounter(@js($ocBase), @js($ocNoun), @js(csrf_token()), @js($ocBoard))">
    <div class="oc-top">
        <div>
            <div class="oc-title">Counter</div>
            <p class="oc-sub">Take a meal coupon, or find an {{ $ocNoun }} by phone number.</p>
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
                   placeholder="Coupon code, e.g. K3M7-PQRS"
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
            <p class="oc-hint">Point at the QR on the coupon.</p>
        </div>

        <p class="oc-err" x-show="error" x-cloak x-text="error"></p>

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
function ordersCounter(base, noun, csrf, board) {
    return {
        open: false,
        code: '',
        phone: '',
        busy: false,
        error: '',
        said: '',
        phoneSaid: '',
        coupon: null,
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
            this.code = '';
            this.error = '';
            this.said = '';
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
            if (!typed) { this.error = 'Type the code from the coupon.'; return; }

            this.busy = true;
            this.error = '';
            this.said = '';
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
