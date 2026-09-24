{{--
    How the card is SET, as opposed to how its items are laid out: where
    the section titles sit, and where the prices do. Shared by both menu
    editors.

    Sana, 2026-09-23, with his printed Priyumm Tiffins card in hand:
    "design and style of cats and sub cats", "pricing on same column or new
    column", "design looks of menu section".

    These are separate from Layout on purpose. Layout decides how one ITEM
    is drawn -- photo beside it, above it, or not at all. These decide how
    the CARD is set, and every combination of the two is valid: a menu with
    photos can still run its prices down the right edge with leader dots,
    which is the thing his card does on every line and which, until now,
    only the Compact layout could do.

    Parameters: none. Binds to `menu.heading_style`, `menu.price_style` and
    `saveSettings()`, which both editors define.
--}}
<div class="rm-row">
    <label class="rm-label">Section titles</label>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;">
        @foreach(\App\Modules\User\Support\MenuPresentation::HEADINGS as $hk => $hv)
        <label style="display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:10px;cursor:pointer;border:1px solid var(--border-glass,rgba(127,127,127,.18));"
               :style="menu.heading_style === '{{ $hk }}' ? 'border-color:#7f9cff;background:rgba(127,156,255,.1);' : ''"
               title="{{ $hv['hint'] }}">
            <input type="radio" value="{{ $hk }}" x-model="menu.heading_style" @change="saveSettings()">
            <span style="font-size:12.5px;font-weight:600;">{{ $hv['label'] }}</span>
        </label>
        @endforeach
    </div>
    <p class="text-xs mt-2" style="color:var(--text-muted)" x-text="headingHint"></p>
    {{-- A filled band brings its own ink, so the heading colour on the
         Colours card stops applying. Saying so beats a creator picking a
         colour twice and concluding the picker is broken. --}}
    <p class="rm-note" x-show="menu.heading_style === 'banner'">
        A banner paints its own text, so the heading colour below will not apply to it.
    </p>
</div>

<div class="rm-row">
    <label class="rm-label">Prices</label>
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;">
        @foreach(\App\Modules\User\Support\MenuPresentation::PRICES as $pk => $pv)
        <label style="display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:10px;cursor:pointer;border:1px solid var(--border-glass,rgba(127,127,127,.18));"
               :style="menu.price_style === '{{ $pk }}' ? 'border-color:#7f9cff;background:rgba(127,156,255,.1);' : ''"
               title="{{ $pv['hint'] }}">
            <input type="radio" value="{{ $pk }}" x-model="menu.price_style" @change="saveSettings()">
            <span style="font-size:12.5px;font-weight:600;">{{ $pv['label'] }}</span>
        </label>
        @endforeach
    </div>
    <p class="text-xs mt-2" style="color:var(--text-muted)" x-text="priceHint"></p>
    {{-- Two layouts stack a full-width photo over a padded text block, so a
         price pinned to the right of that block would sit under the picture
         with nothing to line up against. The control stays offered -- it
         applies again the moment the layout changes -- and says what it is
         doing instead of quietly doing nothing. --}}
    <p class="rm-note" x-show="['grid','showcase'].includes(menu.layout) && menu.price_style !== 'inline'">
        This layout puts the photo above the text, so prices stay under the name here.
    </p>
</div>
