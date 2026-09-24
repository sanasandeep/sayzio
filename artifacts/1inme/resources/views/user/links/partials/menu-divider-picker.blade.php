{{--
    The divider between items. Shared by both menu editors.

    Sana, 2026-09-23, listing what his printed card has: "menu dividers".
    The hairline got a COLOUR in the last change and still had exactly one
    shape and no way off.

    Shown only for the List layout, and that is the honest part: the other
    four have no divider to restyle -- Cards, Photo grid and Showcase draw
    each item as a bordered card, and Compact already separates its rows
    with leader dots. A control that is offered everywhere and works in one
    place out of five is the bug this whole week has been about, so it is
    offered where it works.

    Parameters: none. Binds to `menu.divider` and `saveSettings()`, which
    both editors already define.
--}}
<div class="rm-row" x-show="menu.layout === 'list'" x-cloak>
    <label class="rm-label">Divider</label>
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;">
        @foreach(\App\Modules\User\Support\MenuPresentation::DIVIDERS as $dk => $dv)
        <label style="display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:10px;cursor:pointer;border:1px solid var(--border-glass,rgba(127,127,127,.18));"
               :style="menu.divider === '{{ $dk }}' ? 'border-color:#7f9cff;background:rgba(127,156,255,.1);' : ''"
               title="{{ $dv['hint'] }}">
            <input type="radio" value="{{ $dk }}" x-model="menu.divider" @change="saveSettings()">
            <span style="font-size:12.5px;font-weight:600;">{{ $dv['label'] }}</span>
        </label>
        @endforeach
    </div>
    <p class="text-xs mt-2" style="color:var(--text-muted)" x-text="dividerHint"></p>
</div>
