{{--
    The sheet a customer picks choices in, and the cart maths that goes with
    it. Shared by both menu pages.

    Sana, 2026-09-28: spice levels, sizes, toppings with "pick 2", add-ons
    with a quantity. The owner defines them; this is where a customer
    answers them.

    ---- Why the cart stopped being keyed by item id ----------------------

    It was `ITEMS[id].qty`: one number per dish. That cannot hold "one mild
    and two hot", which is the first thing anybody orders once spice levels
    exist. So the cart is a list of LINES, and a line is an item plus the
    choices made for it. Two lines of the same dish with different choices
    are two lines, and the key that decides "same" is the item plus its
    sorted choices.

    ---- The rules live in one place and are checked in two ---------------

    The sheet stops a customer before they tap Add; MenuCartPricer stops the
    request when it arrives. Neither is optional: without the first the
    customer gets a refusal after they thought they were done, and without
    the second the page is what decides the bill.

    The labels here mirror MenuOptionSelection::ruleLabel so the sentence on
    the item is the sentence in the editor's preview.
--}}
<style>
    .chz { position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.5); display: none; align-items: flex-end; justify-content: center; }
    .chz.show { display: flex; }
    .chz-sheet {
        background: #fff; color: #111;
        width: 100%; max-width: 560px;
        border-radius: 18px 18px 0 0;
        padding: 20px 18px calc(20px + env(safe-area-inset-bottom));
        max-height: 88vh; overflow: auto;
    }
    @media (prefers-color-scheme: dark) { .chz-sheet { background: #15151c; color: #f5f5f7; } }
    @media (min-width: 860px) {
        .chz { align-items: center; }
        .chz-sheet { border-radius: 18px; max-height: 84vh; }
    }
    .chz-sheet h3 { margin: 0 0 2px; font-size: 18px; }
    .chz-base { margin: 0 0 16px; font-size: 13.5px; opacity: .7; }
    .chz-group { margin-bottom: 18px; }
    .chz-group h4 {
        margin: 0 0 2px; font-size: 14.5px;
        display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
    }
    .chz-rule { font-size: 11.5px; font-weight: 500; opacity: .6; white-space: nowrap; }
    .chz-hint { margin: 0 0 8px; font-size: 12.5px; opacity: .65; }
    .chz-opt {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 0; font-size: 14.5px;
        border-top: 1px solid rgba(128,128,128,.18);
        cursor: pointer;
    }
    .chz-opt.gone { opacity: .45; cursor: not-allowed; }
    .chz-opt .nm { flex: 1; min-width: 0; }
    .chz-opt .dl { font-size: 13px; opacity: .75; white-space: nowrap; }
    .chz-step { display: flex; align-items: center; gap: 8px; flex: none; }
    .chz-step button {
        width: 28px; height: 28px; border-radius: 8px; border: 1px solid rgba(128,128,128,.35);
        background: transparent; color: inherit; font-size: 16px; line-height: 1; cursor: pointer;
    }
    .chz-step span { min-width: 16px; text-align: center; font-variant-numeric: tabular-nums; }
    .chz-err { margin: 10px 0 0; font-size: 13.5px; color: #b3261e; }
    @media (prefers-color-scheme: dark) { .chz-err { color: #f2b8b5; } }
    .chz-actions { display: flex; gap: 8px; margin-top: 18px; }
    .chz-actions button { flex: 1; }
    /* What was chosen, under the line it belongs to. */
    .line-opts { display: block; font-size: 12px; opacity: .7; line-height: 1.35; }
</style>

<div class="chz sz-pinned" id="chzModal">
    <div class="chz-sheet">
        <h3 id="chzName"></h3>
        <p class="chz-base" id="chzBase"></p>
        <div id="chzGroups"></div>
        <p class="chz-err" id="chzErr" role="alert" style="display:none"></p>
        <div class="chz-actions">
            <button class="primary" type="button" id="chzAdd"></button>
            <button class="ghost" type="button" onclick="menuChooser.close()">Cancel</button>
        </div>
    </div>
</div>

<script>
(function () {
    // Set by the page: CHOICES[itemId] = [group, ...]; fmt() formats money.
    var CHOICES = {};
    var fmt = function (n) { return String(n); };
    var onAdd = null;
    var current = null;   // { id, name, base }
    var picks = {};       // groupId -> { optionId: quantity }

    function bounds(g) {
        var min = Math.max(0, g.min_select || 0);
        if (g.is_required && min < 1) { min = 1; }
        var max = (g.max_select === null || g.max_select === undefined) ? null : Math.max(1, g.max_select);
        if (max !== null && max < min) { max = min; }
        return { min: min, max: max };
    }

    function cap(g) { return Math.max(1, g.max_per_option || 1); }

    // Mirrors MenuOptionSelection::ruleLabel, so the item, the editor's
    // preview and the server all describe the same rule the same way.
    function ruleLabel(g) {
        var b = bounds(g);
        var label;
        if (b.min > 0 && b.max === b.min) {
            label = 'Choose ' + b.min;
        } else {
            var parts = [];
            if (b.min > 0) { parts.push('choose at least ' + b.min); }
            if (b.max !== null) { parts.push((parts.length ? 'up to ' : 'choose up to ') + b.max); }
            if (!parts.length) { parts.push('optional'); }
            label = parts.join(', ');
            label = label.charAt(0).toUpperCase() + label.slice(1);
        }
        if (cap(g) > 1) { label += ' · each up to ' + cap(g) + 'x'; }
        return label;
    }

    function chosenIn(g) { return picks[g.id] || {}; }
    function distinct(g) { return Object.keys(chosenIn(g)).length; }

    function extra() {
        var sum = 0;
        (CHOICES[current.id] || []).forEach(function (g) {
            var p = chosenIn(g);
            g.options.forEach(function (o) {
                var q = p[o.id] || 0;
                if (q > 0) { sum += o.price_delta * q; }
            });
        });
        return Math.round(sum * 100) / 100;
    }

    /** The first rule that is not satisfied, in the groups' own order. */
    function unmet() {
        var groups = CHOICES[current.id] || [];
        for (var i = 0; i < groups.length; i++) {
            var g = groups[i], b = bounds(g), n = distinct(g);
            if (n < b.min) {
                return b.min === 1
                    ? 'Please choose a ' + g.name.toLowerCase() + '.'
                    : 'Please choose at least ' + b.min + ' from ' + g.name + '.';
            }
            if (b.max !== null && n > b.max) {
                return 'Please choose at most ' + b.max + ' from ' + g.name + '.';
            }
        }
        return null;
    }

    function toggle(g, o) {
        if (o.is_sold_out) { return; }
        var b = bounds(g), p = picks[g.id] || (picks[g.id] = {});

        if (b.max === 1) {
            // One choice: picking replaces rather than adds.
            picks[g.id] = {};
            picks[g.id][o.id] = 1;
        } else if (p[o.id]) {
            delete p[o.id];
        } else {
            if (b.max !== null && distinct(g) >= b.max) {
                // At the ceiling: say so rather than doing nothing, which
                // reads as a broken tap.
                show('You can choose at most ' + b.max + ' from ' + g.name + '.');
                return;
            }
            p[o.id] = 1;
        }
        draw();
    }

    function step(g, o, by) {
        var p = picks[g.id] || (picks[g.id] = {});
        var next = (p[o.id] || 0) + by;
        if (next <= 0) { delete p[o.id]; draw(); return; }
        if (next > cap(g)) { return; }
        var b = bounds(g);
        if (!p[o.id] && b.max !== null && distinct(g) >= b.max) {
            show('You can choose at most ' + b.max + ' from ' + g.name + '.');
            return;
        }
        p[o.id] = next;
        draw();
    }

    function show(message) {
        var el = document.getElementById('chzErr');
        el.textContent = message || '';
        el.style.display = message ? '' : 'none';
    }

    function draw() {
        var box = document.getElementById('chzGroups');
        box.innerHTML = '';

        (CHOICES[current.id] || []).forEach(function (g) {
            var wrap = document.createElement('div');
            wrap.className = 'chz-group';

            var h = document.createElement('h4');
            h.innerHTML = '<span></span><span class="chz-rule"></span>';
            h.firstChild.textContent = g.name;
            h.lastChild.textContent = ruleLabel(g);
            wrap.appendChild(h);

            if (g.hint) {
                var hint = document.createElement('p');
                hint.className = 'chz-hint';
                hint.textContent = g.hint;
                wrap.appendChild(hint);
            }

            var p = chosenIn(g);
            g.options.forEach(function (o) {
                var row = document.createElement('div');
                row.className = 'chz-opt' + (o.is_sold_out ? ' gone' : '');

                var mark = document.createElement('span');
                mark.setAttribute('aria-hidden', 'true');
                mark.textContent = p[o.id] ? '●' : '○';

                var nm = document.createElement('span');
                nm.className = 'nm';
                nm.textContent = o.name + (o.is_sold_out ? ' — sold out' : '');

                row.appendChild(mark);
                row.appendChild(nm);

                if (o.price_delta) {
                    var dl = document.createElement('span');
                    dl.className = 'dl';
                    dl.textContent = (o.price_delta > 0 ? '+' : '−') + fmt(Math.abs(o.price_delta));
                    row.appendChild(dl);
                }

                if (cap(g) > 1 && !o.is_sold_out) {
                    var st = document.createElement('span');
                    st.className = 'chz-step';
                    var minus = document.createElement('button');
                    minus.type = 'button'; minus.textContent = '−';
                    minus.setAttribute('aria-label', 'One fewer ' + o.name);
                    minus.onclick = function (e) { e.stopPropagation(); step(g, o, -1); };
                    var n = document.createElement('span');
                    n.textContent = p[o.id] || 0;
                    var plus = document.createElement('button');
                    plus.type = 'button'; plus.textContent = '+';
                    plus.setAttribute('aria-label', 'One more ' + o.name);
                    plus.onclick = function (e) { e.stopPropagation(); step(g, o, 1); };
                    st.appendChild(minus); st.appendChild(n); st.appendChild(plus);
                    row.appendChild(st);
                } else {
                    row.onclick = function () { toggle(g, o); };
                }

                wrap.appendChild(row);
            });

            box.appendChild(wrap);
        });

        var add = document.getElementById('chzAdd');
        var total = Math.round((current.base + extra()) * 100) / 100;
        add.textContent = 'Add · ' + fmt(total);
    }

    window.menuChooser = {
        /** Called once by the page with its choices and money formatter. */
        install: function (choices, formatter) {
            CHOICES = choices || {};
            fmt = formatter;
        },

        /** Whether an item has anything to ask. */
        asks: function (id) {
            return !!(CHOICES[id] && CHOICES[id].length);
        },

        open: function (item, done) {
            current = item;
            onAdd = done;
            picks = {};
            show(null);
            document.getElementById('chzName').textContent = item.name;
            document.getElementById('chzBase').textContent = fmt(item.base);
            draw();
            document.getElementById('chzModal').classList.add('show');
        },

        close: function () {
            document.getElementById('chzModal').classList.remove('show');
            current = null; onAdd = null; picks = {};
        },

        confirm: function () {
            var problem = unmet();
            if (problem) { show(problem); return; }

            // Flattened in the groups' own order, so two identical orders
            // read identically wherever they are shown.
            var out = [];
            (CHOICES[current.id] || []).forEach(function (g) {
                var p = chosenIn(g);
                g.options.forEach(function (o) {
                    var q = p[o.id] || 0;
                    if (q > 0) {
                        out.push({ option_id: o.id, name: o.name, delta: o.price_delta, quantity: q });
                    }
                });
            });

            var fn = onAdd;
            var it = current;
            this.close();
            if (fn) { fn(it, out); }
        },

        /**
         * What makes two cart lines the same line. The item plus its
         * choices: one mild and two hot are two lines of one dish.
         */
        key: function (id, opts) {
            if (!opts || !opts.length) { return String(id); }
            return id + '|' + opts.map(function (o) { return o.option_id + ':' + o.quantity; }).sort().join(',');
        },

        /** What the choices add to one unit. */
        extraFor: function (opts) {
            var sum = 0;
            (opts || []).forEach(function (o) { sum += o.delta * o.quantity; });
            return Math.round(sum * 100) / 100;
        },

        /** How the choices read on a cart line. */
        label: function (opts) {
            if (!opts || !opts.length) { return ''; }
            return opts.map(function (o) {
                return o.quantity > 1 ? (o.quantity + 'x ' + o.name) : o.name;
            }).join(', ');
        },

        /** The payload shape the server takes. */
        payload: function (opts) {
            return (opts || []).map(function (o) {
                return { option_id: o.option_id, quantity: o.quantity };
            });
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        var add = document.getElementById('chzAdd');
        if (add) { add.onclick = function () { window.menuChooser.confirm(); }; }
        var modal = document.getElementById('chzModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) { window.menuChooser.close(); }
            });
        }
    });
})();
</script>
