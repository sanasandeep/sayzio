{{--
    Shared coin-cost estimate badge for creator-facing AI triggers.

    Usage:
        @include('user.ai._partials.cost-estimate', ['cost' => [
            'endpoint' => route('user.ai.cost-estimate'),
            'feature'  => 'persona',
            'mode'     => 'live',                  // 'live' | 'fixed'
            'input'    => '#persona-prompt',       // CSS selector driving the cost (live only)
            'minChars' => 12,                      // hide the price until this much input
            'prefix'   => 'Up to',                 // 'Up to' | '≈'
            'note'     => 'per voice turn',        // trailing qualifier (optional)
            'class'    => 'mt-2',                   // extra wrapper classes (optional)
        ]])

    Or point it at a feature's own estimate endpoint (biolink/brand-kit/etc.)
    by overriding 'field'/'balanceField' to match that endpoint's JSON shape.

    The badge is a self-contained Alpine island: it wires itself to the host
    input by selector, so it drops into both static forms and pages that
    already own an outer x-data without any nesting conflicts.
--}}
@once
<style>
.ai-cost-estimate{display:inline-flex;flex-wrap:wrap;align-items:center;gap:.45rem;font-size:.75rem;line-height:1.25;color:rgba(255,255,255,.6)}
.ai-cost-estimate[x-cloak]{display:none}
.ai-cost-estimate .acc-coins{color:#fff;font-weight:600}
.ai-cost-estimate .acc-warn{color:#fca5a5}
.ai-cost-estimate .acc-low{color:#fcd34d}
.ai-cost-estimate i{opacity:.85}
html.light-mode .ai-cost-estimate{color:rgba(15,23,42,.6)}
html.light-mode .ai-cost-estimate .acc-coins{color:#0f172a}
</style>
<script>
window.aiCostEstimate = function (cfg) {
    cfg = cfg || {};
    return {
        costEndpoint: cfg.endpoint || '',
        costFeature: cfg.feature || '',
        costMode: cfg.mode || 'fixed',
        costInput: cfg.input || '',
        costPrefix: cfg.prefix || (cfg.mode === 'live' ? 'Up to' : '\u2248'),
        costNote: cfg.note || '',
        costMinChars: cfg.minChars || 0,
        costField: cfg.field || 'coins',
        costBalanceField: cfg.balanceField || 'balance',
        costLowField: cfg.lowField || 'low',
        costExtra: cfg.extra || {},
        costCoins: null,
        costBalance: null,
        costLow: false,
        costEstimating: false,
        _costSeq: 0,
        _csrf() {
            var m = document.querySelector('meta[name="csrf-token"]');
            return m ? m.getAttribute('content') : '';
        },
        _belowMin(text) {
            return this.costMode === 'live' && this.costMinChars > 0 && String(text || '').trim().length < this.costMinChars;
        },
        costInit() {
            var self = this;
            if (this.costInput) {
                var el = document.querySelector(this.costInput);
                if (el) {
                    var t;
                    el.addEventListener('input', function () {
                        clearTimeout(t);
                        t = setTimeout(function () { self.costRefresh(el.value); }, 400);
                    });
                }
            }
            var initText = '';
            if (this.costInput) {
                var e2 = document.querySelector(this.costInput);
                initText = e2 ? (e2.value || '') : '';
            }
            this.costRefresh(initText);
        },
        async costRefresh(text) {
            if (!this.costEndpoint) return;
            text = (text == null) ? '' : String(text);
            var below = this._belowMin(text);
            if (below) this.costCoins = null;
            var seq = ++this._costSeq;
            this.costEstimating = true;
            try {
                var body = Object.assign({ feature: this.costFeature, text: text }, this.costExtra);
                var res = await fetch(this.costEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this._csrf(),
                    },
                    body: JSON.stringify(body),
                });
                if (!res.ok) throw new Error('estimate http ' + res.status);
                var data = await res.json();
                if (seq !== this._costSeq) return;
                if (typeof data[this.costBalanceField] === 'number') this.costBalance = data[this.costBalanceField];
                if (typeof data[this.costLowField] === 'boolean') this.costLow = data[this.costLowField];
                if (!below) {
                    var c = data[this.costField];
                    this.costCoins = (typeof c === 'number' && c > 0) ? c : null;
                }
            } catch (e) {
                /* keep the previous estimate on transient failures */
            } finally {
                if (seq === this._costSeq) this.costEstimating = false;
            }
        },
        costShort() {
            return this.costCoins !== null && this.costBalance !== null && this.costBalance < this.costCoins;
        },
    };
};
</script>
@endonce
<div class="ai-cost-estimate {{ $cost['class'] ?? '' }}"
     x-data="aiCostEstimate(@js([
        'endpoint'     => $cost['endpoint'] ?? route('user.ai.cost-estimate'),
        'feature'      => $cost['feature'] ?? '',
        'mode'         => $cost['mode'] ?? 'fixed',
        'input'        => $cost['input'] ?? '',
        'minChars'     => $cost['minChars'] ?? 0,
        'prefix'       => $cost['prefix'] ?? null,
        'note'         => $cost['note'] ?? '',
        'field'        => $cost['field'] ?? 'coins',
        'balanceField' => $cost['balanceField'] ?? 'balance',
        'lowField'     => $cost['lowField'] ?? 'low',
        'extra'        => $cost['extra'] ?? (object) [],
     ])"
     x-init="costInit()" x-cloak>
    <span x-show="costCoins !== null">
        <i class="fas fa-coins"></i>
        <span x-text="costPrefix"></span>
        <span class="acc-coins" x-text="costCoins"></span>
        <span x-text="costCoins === 1 ? 'coin' : 'coins'"></span>
        <span x-show="costNote" x-text="costNote"></span>
        <template x-if="costBalance !== null">
            <span>&middot; Balance: <span class="acc-coins" x-text="costBalance"></span></span>
        </template>
    </span>
    <span x-show="costCoins === null && costEstimating">
        <i class="fas fa-coins"></i> Estimating&hellip;
    </span>
    <span class="acc-warn" x-show="costShort()">
        <i class="fas fa-triangle-exclamation"></i> Not enough coins &mdash; top up first.
    </span>
    <span class="acc-low" x-show="costLow && !costShort()">
        <i class="fas fa-circle-info"></i> Low balance
    </span>
</div>
