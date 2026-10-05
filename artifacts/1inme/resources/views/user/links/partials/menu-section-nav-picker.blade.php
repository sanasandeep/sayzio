{{--
    How a customer gets down a long menu.

    Sana, 2026-10-05: "Section jumping - suggest multi different layout type
    options: horizontal scroll tabs with all, select drop down, verticle tab
    with icon display or number (default)" and "section headings should have
    numbers default, option with selecting icons also".

    Both controls live together because they are one decision: the marker a
    section carries is drawn in the jump bar AND on the heading, so picking
    them in two different places is how they end up disagreeing.

    Everything here loops the catalogue rather than listing options, so a
    fifth nav shape or a fourth marker appears in this editor the afternoon
    it is added — the same rule the colours and layouts follow.
--}}
<div class="rm-field">
    <label class="rm-label">Section jumping</label>
    <p class="rm-help">
        A phone shows one section at a time, so a long card is a scroll with no map.
        This is the map.
    </p>

    <div class="rm-opts two">
        @foreach(\App\Modules\User\Support\MenuSectionNav::NAVS as $snK => $snV)
            <label class="rm-opt"
                   :class="{ 'on': (menu.section_nav || '{{ \App\Modules\User\Support\MenuSectionNav::DEFAULT_NAV }}') === '{{ $snK }}' }"
                   title="{{ $snV['hint'] }}">
                <input type="radio" value="{{ $snK }}" x-model="menu.section_nav" @change="saveSettings()">
                <span>{{ $snV['label'] }}</span>
            </label>
        @endforeach
    </div>
    <p class="text-xs mt-2" style="color:var(--text-muted)" x-text="sectionNavHint"></p>
</div>

<div class="rm-field" x-show="(menu.section_nav || 'tabs') !== 'none'">
    <label class="rm-label">Section navigation colours</label>
    <p class="rm-help">Text, fill and outline for section tabs, the dropdown and the side rail.</p>
    @foreach(\App\Modules\User\Support\MenuSectionNav::COLOURS as $snColourKey => $snColour)
        <div class="rm-colour">
            <input type="color" aria-label="{{ $snColour['label'] }}"
                   :value="menu.{{ $snColourKey }} || '{{ $snColour['default'] }}'"
                   @change="menu.{{ $snColourKey }} = $event.target.value; saveSettings()">
            <span class="txt"><b>{{ $snColour['label'] }}</b></span>
            <button type="button" class="rm-act" title="Reset {{ $snColour['label'] }}"
                    @click="menu.{{ $snColourKey }} = '{{ $snColour['default'] }}'; saveSettings()">
                <i class="fas fa-rotate-left"></i>
            </button>
        </div>
    @endforeach
</div>

<div class="rm-field">
    <label class="rm-label">Section markers</label>
    <p class="rm-help">
        Drawn both in the jump bar and beside each section heading, so the two always agree.
    </p>

    <div class="rm-opts two">
        @foreach(\App\Modules\User\Support\MenuSectionNav::MARKERS as $smK => $smV)
            <label class="rm-opt"
                   :class="{ 'on': (menu.section_marker || '{{ \App\Modules\User\Support\MenuSectionNav::DEFAULT_MARKER }}') === '{{ $smK }}' }"
                   title="{{ $smV['hint'] }}">
                <input type="radio" value="{{ $smK }}" x-model="menu.section_marker" @change="saveSettings()">
                <span>{{ $smV['label'] }}</span>
            </label>
        @endforeach
    </div>
    <p class="text-xs mt-2" style="color:var(--text-muted)" x-text="sectionMarkerHint"></p>

    {{-- Said here rather than discovered later: a creator who picks Icons
         and has not set any would otherwise see numbers and think the
         setting did nothing. --}}
    <p class="text-xs mt-1" style="color:var(--text-faint)"
       x-show="(menu.section_marker || '') === 'icon'">
        Give a section its icon from the ⋯ menu beside its name. Sections without one keep their number.
    </p>
</div>

@once
<script>
    // The icon catalogue, for the per-section picker. One source, so a
    // seventeenth icon reaches the picker without a second edit.
    window.MENU_SECTION_ICONS = @json(\App\Modules\User\Support\MenuSectionNav::ICONS);
</script>
@endonce
