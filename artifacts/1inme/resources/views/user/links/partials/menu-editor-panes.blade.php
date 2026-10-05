{{--
    The three panes of a menu editor.

    Sana, 2026-10-05: "move settings column to another tab menu menu..
    (rename settings to better name).. this way it will look uniform and
    all will be looking same layout type".

    ---- Why "Settings" was the wrong word -------------------------------

    Because it held two unrelated jobs in one 320px column: how the page
    LOOKS -- colours, layout, dividers, the heading -- and how ordering
    WORKS -- mode, currency, handover, tables, coupons, tax. Somebody
    changing a price format had to scroll past the divider picker to find
    it. Calling the whole column "Settings" is what let those two sit in
    one list for as long as they did.

    So the panes are named after the questions they answer: Items, How the
    menu looks, How ordering works.

    ---- Not links --------------------------------------------------------

    These switch inside one Alpine scope rather than navigating. The menu
    editor holds its entire state -- sections, items, unsaved settings,
    open modals -- in one component, and three routes would mean three
    copies of that state and three chances for them to disagree. The URL
    still carries `?pane=` so a reload, a bookmark and the back button all
    land where the creator was.

    Parameters:
      $mepPane   the pane open on load, from ?pane= (validated by the page)
      $mepItems  what this page type calls its contents: 'Items' | 'Products'
--}}
@include('user.links.partials.pill-tabs')
@php
    $mepItems = $mepItems ?? 'Items';
    $mepTabs = [
        'items'    => ['icon' => 'fa-list', 'label' => $mepItems],
        'design'   => ['icon' => 'fa-palette', 'label' => 'How it looks'],
        'ordering' => ['icon' => 'fa-receipt', 'label' => 'How ordering works'],
    ];
@endphp
<div class="pill-tabs-scroll mb-6">
    <div class="pill-tabs">
        @foreach($mepTabs as $mepKey => $mepTab)
        <button type="button"
                class="pill-tab"
                :class="pane === '{{ $mepKey }}' ? 'is-active' : ''"
                @click="setPane('{{ $mepKey }}')">
            <i class="fas {{ $mepTab['icon'] }} text-[10px]"></i>
            <span>{{ $mepTab['label'] }}</span>
        </button>
        @endforeach
    </div>
</div>

@once
<script>
/**
 * The pane state, shared by both menu editors.
 *
 * Mixed into the editor's own Alpine component rather than wrapping it, so
 * everything in a pane still sees the one component that holds the menu --
 * `menu`, `sections`, `saveSettings()` and the rest -- exactly as it did
 * when they were all in one column.
 */
function menuEditorPanes(initial) {
    return {
        pane: initial || 'items',

        /**
         * Switch, and put it in the URL.
         *
         * replaceState rather than pushState: a creator flipping between
         * "how it looks" and the items is not navigating, and making each
         * flip a history entry means the back button walks them through
         * every glance before it leaves the page.
         */
        setPane(next) {
            this.pane = next;
            try {
                var url = new URL(window.location.href);
                if (next === 'items') { url.searchParams.delete('pane'); }
                else { url.searchParams.set('pane', next); }
                window.history.replaceState(window.history.state, '', url.toString());
            } catch (e) {
                // A URL this browser will not parse is no reason to refuse
                // to switch panes.
            }
        },
    };
}
</script>
@endonce
