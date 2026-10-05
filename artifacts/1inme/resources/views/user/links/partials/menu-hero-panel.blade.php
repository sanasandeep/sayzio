{{--
    The top of the menu page: the name, and the ordering badge under it.

    Sana, 2026-10-04: "Priyumm tiffins and order at table both..... can be
    optional hidden.. also alignment and color and style changes....".

    ---- Why hiding your own restaurant's name is reasonable --------------

    Because it is already in the logo block above it. The menu page renders
    creator blocks above the title, and the first thing a restaurant puts
    there is its logo -- with the name in it. So the page said the name
    twice, once as artwork and once as an h1 underneath, with no way to drop
    the second. That is the exact page in his screenshot.

    The badge is the same from the other end: "Order at table" earns its
    place on a QR code taped to a table and says nothing on a menu somebody
    opened from Instagram.

    Parameters:
      $hpBadgeLabel  what the badge says on this page type, for the copy
--}}
@php
    $hpBadgeLabel = $hpBadgeLabel ?? 'Order at table';
@endphp
<div class="rm-row">
    <label class="rm-label">Page heading</label>
    <p style="font-size:11px;opacity:.6;margin:-2px 0 8px;">
        The name and the badge at the top of your page. Hide the name if your logo block already has it.
    </p>

    <label class="hp-check">
        <input type="checkbox" x-model="menu.hero_title_hidden" @change="saveSettings()">
        <span>Hide the page name</span>
    </label>
    <label class="hp-check">
        <input type="checkbox" x-model="menu.hero_badge_hidden" @change="saveSettings()">
        <span>Hide the “{{ $hpBadgeLabel }}” badge</span>
    </label>
    {{-- Said out loud, because "hidden" sounds like "removed" and the
         difference matters to somebody worried about being found. --}}
    <p class="hp-note" x-show="menu.hero_title_hidden">
        Still used for your page title, link previews and search. Only hidden on the page.
    </p>

    <div class="hp-grid">
        <div>
            <div class="hp-head">Alignment</div>
            <div class="rm-opts">
                @foreach(\App\Modules\User\Support\MenuHero::ALIGNMENTS as $alKey => $alLabel)
                <label class="rm-opt" :class="{ 'on': menu.hero_align === '{{ $alKey }}' }">
                    <input type="radio" value="{{ $alKey }}" x-model="menu.hero_align" @change="saveSettings()">
                    <span>{{ $alLabel }}</span>
                </label>
                @endforeach
            </div>
        </div>
        <div>
            <div class="hp-head">Name size</div>
            <div class="rm-opts">
                @foreach(\App\Modules\User\Support\MenuHero::SIZES as $szKey => $szMeta)
                <label class="rm-opt" :class="{ 'on': menu.hero_size === '{{ $szKey }}' }">
                    <input type="radio" value="{{ $szKey }}" x-model="menu.hero_size" @change="saveSettings()">
                    <span>{{ $szMeta['label'] }}</span>
                </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Same shape as the item colours below: blank inherits, and the
         reset arrow only appears once there is something to reset. --}}
    <div class="rm-colour" x-show="!menu.hero_title_hidden">
        <input type="color" :value="menu.hero_title_color || '#888888'"
               @change="menu.hero_title_color = $event.target.value; saveSettings()">
        <span class="txt">
            <b>Name colour</b>
            <small>Inherits the page text colour when unset.</small>
        </span>
        <button type="button" class="rm-act" title="Back to inheriting the page text colour"
                x-show="menu.hero_title_color"
                @click="menu.hero_title_color = ''; saveSettings()"><i class="fas fa-rotate-left"></i></button>
    </div>
    <div class="rm-colour" x-show="!menu.hero_badge_hidden">
        <input type="color" :value="menu.hero_badge_color || '#888888'"
               @change="menu.hero_badge_color = $event.target.value; saveSettings()">
        <span class="txt">
            <b>Badge colour</b>
            <small>Defaults to the accent colour.</small>
        </span>
        <button type="button" class="rm-act" title="Back to the accent colour"
                x-show="menu.hero_badge_color"
                @click="menu.hero_badge_color = ''; saveSettings()"><i class="fas fa-rotate-left"></i></button>
    </div>
</div>

<style>
    .hp-check {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-primary);
        font-size: 12.5px;
        margin-bottom: 6px;
        cursor: pointer;
    }
    .hp-note {
        font-size: 11px;
        color: var(--text-muted);
        margin: 2px 0 0 24px;
        line-height: 1.45;
    }
    .hp-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 12px;
    }
    @media (max-width: 520px) {
        .hp-grid { grid-template-columns: 1fr; }
    }
    .hp-head {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-faint);
        margin-bottom: 5px;
    }
</style>
