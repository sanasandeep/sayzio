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
    <div class="item {{ $miSold ? 'soldout' : '' }}">
        @if($miItem->photo_url)<img class="photo" src="{{ $miItem->photo_url }}" alt="" loading="lazy">@endif
        <div class="info">
            <div class="name">{{ $miItem->name }}</div>
            @if($miItem->description)<div class="desc">{{ $miItem->description }}</div>@endif
            <div class="price">{{ $miFmt($miItem->price) }}</div>
            @if($miOrder && ! $miSold)
                <div class="addrow" data-add="{{ $miItem->id }}"
                     data-name="{{ e($miItem->name) }}" data-price="{{ $miItem->price }}">
                    <button class="add" type="button" onclick="{{ $miNs }}.add({{ $miItem->id }})">Add</button>
                    <span data-stepper="{{ $miItem->id }}" style="display:none;">
                        <button class="qbtn" type="button" onclick="{{ $miNs }}.dec({{ $miItem->id }})">−</button>
                        <span class="qty" data-qty="{{ $miItem->id }}">0</span>
                        <button class="qbtn" type="button" onclick="{{ $miNs }}.inc({{ $miItem->id }})">+</button>
                    </span>
                </div>
            @endif
        </div>
    </div>
@endforeach
</div>
