{{--
    How many of one thing an order may ask for, and when that starts
    producing food coupons.

    Sana, 2026-10-04: "need options while order from menu minimum and max
    order quatity or each order item... this will create like bulk order...
    when bulk order.... need to generate food coupons ids".

    ---- Three boxes, and a sentence underneath ---------------------------

    "Minimum 10, maximum 200, coupons from 20" is three numbers an owner
    has to hold in their head to know what they just did. So the panel
    prints the rule back as the diner will meet it, from the values in the
    boxes, and that sentence is the thing being checked -- not the boxes.

    ---- Empty means what it meant before -------------------------------

    Every dish on every menu today has no floor, no ceiling and no
    coupons, and that has to keep being what blank boxes mean. So minimum
    blank is 1, maximum blank is no limit, and coupons blank is no coupons.
    Nothing here is preselected and nothing is required.

    Parameters:
      $qpModal  the Alpine property holding the open item, e.g. 'itemModal'
      $qpNoun   'dish' or 'product', for the copy
--}}
@php
    $qpModal = $qpModal ?? 'itemModal';
    $qpNoun  = $qpNoun ?? 'dish';
    $qpMax   = \App\Modules\User\Support\MenuBulkOrder::CEILING;
@endphp
<div class="rm-row">
    <label class="rm-label">Quantity &amp; coupons</label>
    <p class="text-xs mb-2" style="color:var(--text-muted)">
        Leave these blank and this {{ $qpNoun }} behaves exactly as it does now.
    </p>

    <div class="qp-grid">
        <div>
            <div class="qp-head">Minimum</div>
            <input class="rm-input qp-n" type="number" min="1" max="{{ $qpMax }}" step="1"
                   placeholder="1" x-model="{{ $qpModal }}.min_quantity">
        </div>
        <div>
            <div class="qp-head">Maximum</div>
            <input class="rm-input qp-n" type="number" min="1" max="{{ $qpMax }}" step="1"
                   placeholder="No limit" x-model="{{ $qpModal }}.max_quantity">
        </div>
        <div>
            <div class="qp-head">Coupons from</div>
            <input class="rm-input qp-n" type="number" min="1" max="{{ $qpMax }}" step="1"
                   placeholder="Never" x-model="{{ $qpModal }}.coupon_from">
        </div>
    </div>

    <p class="qp-say" x-text="menuQuantity.explain({{ $qpModal }}, '{{ $qpNoun }}')"></p>

    {{-- A ceiling under the floor is the one combination that makes the
         item unorderable, and it is easy to type. Said here, before the
         save raises it, so the owner sees their own number. --}}
    <p class="qp-warn" x-show="menuQuantity.ceilingTooLow({{ $qpModal }})"
       x-text="menuQuantity.ceilingWarning({{ $qpModal }})"></p>
</div>

<style>
    .qp-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    @media (max-width: 420px) {
        /* Three number boxes at phone width are too narrow to read the
           placeholder in, and "No limit" is the whole point of that box. */
        .qp-grid { grid-template-columns: 1fr 1fr; }
    }
    .qp-head {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-faint);
        margin-bottom: 4px;
    }
    .qp-n { width: 100%; font-variant-numeric: tabular-nums; }
    .qp-say {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 8px;
        line-height: 1.5;
    }
    .qp-warn {
        font-size: 12px;
        color: var(--accent-danger, #f87171);
        margin-top: 4px;
        line-height: 1.5;
    }
</style>

<script>
@once
window.MENU_QUANTITY_MAX = {{ $qpMax }};

/**
 * The three numbers, read back as a sentence.
 *
 * Both editors include this partial, so the wording lives in one place and
 * a restaurant and a store cannot end up describing the same rule
 * differently.
 */
window.menuQuantity = {
    /** A box's value as a number, or null for blank. */
    num(raw) {
        if (raw === null || raw === undefined || raw === '') { return null; }
        const n = parseInt(raw, 10);
        return Number.isFinite(n) && n > 0 ? n : null;
    },

    floor(modal) { return this.num(modal && modal.min_quantity) || 1; },

    ceilingTooLow(modal) {
        const max = this.num(modal && modal.max_quantity);
        return max !== null && max < this.floor(modal);
    },

    ceilingWarning(modal) {
        return 'A maximum of ' + this.num(modal.max_quantity) + ' is below the minimum of '
            + this.floor(modal) + '. Saved as ' + this.floor(modal) + '.';
    },

    explain(modal, noun) {
        const min = this.floor(modal);
        const max = this.num(modal && modal.max_quantity);
        const from = this.num(modal && modal.coupon_from);

        let said;
        if (min === 1 && max === null) {
            said = 'Any quantity.';
        } else if (max === null) {
            said = 'At least ' + min + ' per order.';
        } else if (max === min) {
            said = 'Exactly ' + min + ' per order.';
        } else {
            said = 'Between ' + min + ' and ' + max + ' per order.';
        }

        if (from === null) {
            return said + ' No coupons.';
        }
        if (from <= min) {
            return said + ' Every order gets one coupon per serving.';
        }
        return said + ' Orders of ' + from + ' or more get one coupon per serving.';
    },
};
@endonce
</script>
