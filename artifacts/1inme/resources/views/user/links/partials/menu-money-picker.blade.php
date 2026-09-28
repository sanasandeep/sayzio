{{--
    How a price is WRITTEN. Shared by both menu editors.

    Sana, 2026-09-23: "prices should have options like INR, [Rs.] USD or $
    like that... before or after number..."

    Until now the currency field was a three-character text box and the
    page printed the ISO code, a space, and two decimals. Always. So an
    Indian restaurant could not write "Rs.120", which is how every menu in
    India prints a price.

    This is separate from Prices on the design card, which decides WHERE
    the number sits. This decides what is next to it.

    The live sample is the point of the panel: three radio groups describing
    a format in words is harder to read than one line showing it.

    Parameters: none. Binds to `menu.currency`, `menu.price_display`,
    `menu.price_position`, `menu.price_decimals` and `saveSettings()`.
--}}
<div class="rm-row">
    <label class="rm-label">Price format</label>

    <div class="rm-opts">
        @foreach(\App\Modules\User\Support\MenuMoney::DISPLAYS as $dk => $dv)
        <label class="rm-opt"
               :class="{ 'on': menu.price_display === '{{ $dk }}' }"
               title="{{ $dv['hint'] }}">
            <input type="radio" value="{{ $dk }}" x-model="menu.price_display" @change="saveSettings()">
            <span>{{ $dv['label'] }}</span>
        </label>
        @endforeach
    </div>

    {{-- These two rows are the same shape, so without a heading of its own
         the second reads as three more options in the first -- five ways to
         write a price rather than two questions about one. The labels are
         short enough to fit a chip now, which is what makes the heading
         necessary: "Before" alone does not say before what. --}}
    <div class="rm-sublabel" x-show="menu.price_display !== 'none'" x-cloak>Which side of the number</div>

    <div class="rm-opts two"
         x-show="menu.price_display !== 'none'" x-cloak>
        @foreach(\App\Modules\User\Support\MenuMoney::POSITIONS as $pk => $pv)
        <label class="rm-opt"
               :class="{ 'on': menu.price_position === '{{ $pk }}' }"
               title="{{ $pv['hint'] }}">
            <input type="radio" value="{{ $pk }}" x-model="menu.price_position" @change="saveSettings()">
            <span>{{ $pv['label'] }}</span>
        </label>
        @endforeach
    </div>

    {{-- Hidden for a currency with no minor unit: "¥1,200.00" is wrong
         rather than a matter of taste, so there is nothing to decide. --}}
    <label style="display:flex;gap:8px;align-items:center;margin-top:9px;color:var(--text-primary);font-size:13px;"
           x-show="!zeroDecimalCurrency" x-cloak>
        <input type="checkbox" x-model="menu.price_decimals" @change="saveSettings()">
        Show decimals
    </label>

    {{-- The sample, not a description of the sample. --}}
    <p style="margin-top:10px;padding:9px 12px;border-radius:10px;background:var(--bg-glass-input,rgba(127,127,127,.06));font-size:15px;font-weight:700;color:var(--text-primary)"
       x-text="priceSample"></p>
    <p class="rm-note" x-show="menu.price_display === 'symbol' && !currencyHasSymbol" x-cloak>
        No symbol is on file for this currency, so its code is used instead.
    </p>
</div>
