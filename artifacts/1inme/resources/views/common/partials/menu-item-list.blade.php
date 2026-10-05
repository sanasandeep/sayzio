{{--
    One list of menu items, in whichever of the five layouts the creator
    picked. The class on the wrapper is the entire difference between them
    -- see common/partials/menu-layout-css.

    Shared by the restaurant menu and the store menu, which differ in two
    things and two only: what the sold-out flag is called, and which cart
    object the buttons call. Both are parameters rather than a second copy
    of the markup.

    Parameters:
      $miItems    Collection of items/products, already filtered and sorted
      $miLayout   layout key
      $miFmt      callable(price): string
      $miOrder    bool
      $miNs       'RM' | 'SM'
      $miSoldKey  'is_sold_out' | 'is_out_of_stock'
      $miDivider  divider key -- only List has a divider to restyle
--}}
<div class="items lay-{{ $miLayout }} div-{{ $miDivider }}">
@foreach($miItems as $miItem)
    @php $miSold = (bool) $miItem->{$miSoldKey}; @endphp
    <div class="item {{ $miSold ? 'soldout' : '' }}" data-menu-search-item
         data-search-text="{{ $miItem->name.' '.$miItem->description }}"
         data-search-category="{{ $miItem->category_id }}" data-search-price="{{ $miItem->price }}"
         data-search-sold="{{ $miSold ? '1' : '0' }}"
         data-search-marks="{{ json_encode(array_column($miItem->marksForDisplay(), 'label')) }}">
        @if($miItem->photo_url)<img class="photo" src="{{ $miItem->photo_url }}" alt="" loading="lazy">@endif
        <div class="info">
            <div class="name">{{ $miItem->name }}</div>
            {{-- Their own line, not inside the name. Inline reads nicely
                 until a dish carries four of them, and then in the
                 leader-dots layout the name grows past what the row can
                 hold and the PRICE wraps to the next line while the dots
                 run on to the edge. A row of their own costs one line and
                 behaves the same in all five layouts. --}}
            @include('common.partials.menu-item-marks', ['mkMarks' => $miItem->marksForDisplay()])
            @if($miItem->description)<div class="desc">{{ $miItem->description }}</div>@endif
            <div class="price">{{ $miFmt($miItem->price) }}</div>
            @if($miItem->bulk_price !== null && $miItem->coupon_from !== null)
                <div class="qty-rule">{{ $miFmt($miItem->bulk_price) }} each for {{ $miItem->coupon_from }}+ servings</div>
            @endif
            {{-- The rule, before they tap Add rather than after. A dish
                 sold in trays of ten that says nothing is a dish whose
                 first refusal arrives at checkout. Items with no rule
                 print nothing, which is every item today. --}}
            @php $miRule = \App\Modules\User\Support\MenuBulkOrder::label($miItem); @endphp
            @if($miRule !== '')<div class="qty-rule">{{ $miRule }}</div>@endif
            @if($miOrder && ! $miSold)
                <div class="addrow" data-add="{{ $miItem->id }}"
                     data-name="{{ e($miItem->name) }}" data-price="{{ $miItem->price }}"
                     data-bulk-price="{{ $miItem->bulk_price }}" data-coupon-from="{{ $miItem->coupon_from }}"
                     data-min="{{ (int) ($miItem->min_quantity ?? 1) }}"
                     data-max="{{ $miItem->max_quantity !== null ? (int) $miItem->max_quantity : '' }}">
                    <button class="add" type="button" onclick="{{ $miNs }}.add({{ $miItem->id }})">Add</button>
                    <span data-stepper="{{ $miItem->id }}" style="display:none;">
                        <button class="qbtn" type="button" onclick="{{ $miNs }}.dec({{ $miItem->id }})">−</button>
                        <span class="qty" data-qty="{{ $miItem->id }}">0</span>
                        <button class="qbtn" type="button" onclick="{{ $miNs }}.inc({{ $miItem->id }})">+</button>
                    </span>
                    {{-- Where "that is as many as you can order" goes. On
                         the row itself: the cart's error line is two taps
                         away and the guest is looking at the + they just
                         pressed. --}}
                    <small class="qty-cap" data-cap="{{ $miItem->id }}" role="status" style="display:none"></small>
                </div>
            @endif
        </div>
    </div>
@endforeach
</div>
