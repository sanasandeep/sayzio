{{--
    The body of a menu: sections, their sub-sections, and the items in
    each. One copy, used by common/restaurant-menu and common/store-menu.

    Sana, 2026-09-23: "cats and sub cats" -- pointing at his own printed
    Priyumm Tiffins card, where "Tiffins" is a heading and Idli, Dosa and
    Vada sit beneath it. Every real menu card is built that way and this
    product could not express it at all.

    These two pages had the SAME twenty-line item loop written out twice,
    which is how the store menu spent a week missing fixes the restaurant
    menu got. Extracting it is the point, not a side effect: the structural
    guard in TheMenuCardCanHaveSectionsTest fails if either page grows its
    own copy back.

    What the tree is, and which parts of it are visible, is decided once in
    MenuTree -- including the inheritance rule (an item inside a hidden
    sub-section is hidden), which is exactly the kind of thing that drifts
    when Blade and Alpine each work it out for themselves.

    Parameters:
      $msTree     array from MenuTree::build()
      $msLayout   layout key, for the `.items.lay-*` class
      $msFmt      callable(price): string
      $msOrder    bool -- order mode draws the add/stepper row
      $msNs       'RM' | 'SM' -- the page's cart JS namespace
      $msSoldKey  'is_sold_out' | 'is_out_of_stock'
      $msDivider  divider key
      $msEmpty    what to say when there is nothing to show
--}}
@forelse($msTree as $msSection)
    @php $msCat = $msSection['category']; @endphp
    <div class="cat">
        <h2>{{ $msCat->name }}</h2>
        @if($msCat->description)<p class="cdesc">{{ $msCat->description }}</p>@endif

        {{-- A section's own items, above its sub-sections. A card that
             reads "Tiffins / Idli / Dosa" puts the loose items first and
             the named groups after, which is also how they are drawn. --}}
        @if($msSection['items']->isNotEmpty())
            @include('common.partials.menu-item-list', [
                'miItems'   => $msSection['items'],
                'miLayout'  => $msLayout,
                'miFmt'     => $msFmt,
                'miOrder'   => $msOrder,
                'miNs'      => $msNs,
                'miSoldKey' => $msSoldKey,
                'miDivider' => $msDivider,
            ])
        @endif

        @foreach($msSection['subs'] as $msSub)
            <div class="subcat">
                <h3>{{ $msSub['category']->name }}</h3>
                @if($msSub['category']->description)<p class="cdesc">{{ $msSub['category']->description }}</p>@endif
                @include('common.partials.menu-item-list', [
                    'miItems'   => $msSub['items'],
                    'miLayout'  => $msLayout,
                    'miFmt'     => $msFmt,
                    'miOrder'   => $msOrder,
                    'miNs'      => $msNs,
                    'miSoldKey' => $msSoldKey,
                    'miDivider' => $msDivider,
                ])
            </div>
        @endforeach
    </div>
@empty
    <div class="empty">{{ $msEmpty }}</div>
@endforelse
