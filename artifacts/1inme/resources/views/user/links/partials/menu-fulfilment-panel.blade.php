{{--
    How orders are handed over, and what that adds to the bill. Shared by
    both menu editors, shown only in order mode.

    Sana, 2026-09-23: "adding of address form, delivery options and other
    settings configurable".

    The charges list is the part worth explaining. The obvious build is
    three fields -- parcel charge, delivery charge, service charge -- and
    it runs out within a week, because a restaurant wants packing AND
    delivery on the same order and the next one wants a late-night
    surcharge. A charge is a row instead: a name, a flat or percentage
    amount, and the handovers it applies to. Three hardcoded fields are the
    same feature with a ceiling on it.

    Parameters:
      $fpIsRestaurant  bool -- decides whether Dine in is offered, and
                       whether the middle option is called Takeaway or Pickup
--}}
<div class="rm-row" x-show="menu.mode === 'order'" x-cloak>
    <label class="rm-label">How orders are handed over</label>
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;">
        @foreach(\App\Modules\User\Support\MenuFulfilment::MODES as $mk => $mv)
            @if($fpIsRestaurant || ! $mv['restaurant_only'])
            <label style="display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:10px;cursor:pointer;border:1px solid var(--border-glass,rgba(127,127,127,.18));"
                   :style="(menu.fulfilment_modes || []).includes('{{ $mk }}') ? 'border-color:#7f9cff;background:rgba(127,156,255,.1);' : ''">
                <input type="checkbox" value="{{ $mk }}" x-model="menu.fulfilment_modes" @change="saveSettings()">
                <span style="font-size:12.5px;font-weight:600;">{{ $fpIsRestaurant ? $mv['label'] : $mv['store_label'] }}</span>
            </label>
            @endif
        @endforeach
    </div>
    {{-- Turning everything off would leave a guest on an order page with no
         way to say how they want their food, so the save path falls back
         rather than storing nothing. Saying so beats the creator finding
         out by looking at their own live page. --}}
    <p class="rm-note" x-show="!(menu.fulfilment_modes || []).length">
        With none chosen, orders are taken as {{ $fpIsRestaurant ? 'dine in' : 'pickup' }}.
    </p>
    <p class="rm-note" x-show="(menu.fulfilment_modes || []).includes('delivery')">
        Delivery asks the customer for an address. The other options do not.
    </p>
</div>

<div class="rm-row" x-show="menu.mode === 'order'" x-cloak>
    <div class="flex justify-between items-center" style="margin-bottom:8px">
        <label class="rm-label" style="margin:0">Charges</label>
        <button class="rm-btn sm ghost" type="button" @click="addCharge()" x-show="(menu.charges||[]).length < 8">
            <i class="fas fa-plus"></i> Charge
        </button>
    </div>

    <template x-if="!(menu.charges || []).length">
        <p class="rm-note" style="margin-top:0">
            A parcel charge, a delivery fee, a service charge. Each one picks which
            handovers it applies to, so one order can carry two and another none.
        </p>
    </template>

    <template x-for="(c, ci) in (menu.charges || [])" :key="'c'+ci">
        <div style="border:1px solid var(--border-glass,rgba(127,127,127,.18));border-radius:12px;padding:10px;margin-bottom:8px">
            <div style="display:flex;gap:6px;align-items:center">
                <input class="rm-input" style="flex:1 1 auto;margin:0" placeholder="Delivery charge"
                       x-model="c.label" @change="saveSettings()">
                <button class="rm-btn sm danger" type="button" @click="removeCharge(ci)"><i class="fas fa-trash"></i></button>
            </div>
            <div style="display:flex;gap:6px;align-items:center;margin-top:6px">
                <select class="rm-input" style="flex:0 0 auto;width:auto;margin:0" x-model="c.type" @change="saveSettings()">
                    <option value="fixed">Flat</option>
                    <option value="percent">% of the order</option>
                </select>
                <input class="rm-input" style="flex:1 1 auto;margin:0" type="number" min="0" step="0.01"
                       x-model="c.amount" @change="saveSettings()">
                <span style="font-size:12.5px;color:var(--text-muted);white-space:nowrap"
                      x-text="c.type === 'percent' ? '%' : currencyCode"></span>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
                @foreach(\App\Modules\User\Support\MenuFulfilment::MODES as $mk => $mv)
                    @if($fpIsRestaurant || ! $mv['restaurant_only'])
                    <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text-primary);cursor:pointer">
                        <input type="checkbox" value="{{ $mk }}" x-model="c.modes" @change="saveSettings()">
                        {{ $fpIsRestaurant ? $mv['label'] : $mv['store_label'] }}
                    </label>
                    @endif
                @endforeach
            </div>
            {{-- A charge that applies to nothing is not saved, and a
                 creator who typed a name and an amount deserves to be told
                 that rather than watch it vanish on reload. --}}
            <p class="rm-note" x-show="!(c.modes || []).length">
                Pick at least one handover, or this charge is not saved.
            </p>
        </div>
    </template>
</div>
