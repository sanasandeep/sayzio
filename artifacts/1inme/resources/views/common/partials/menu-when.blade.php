{{--
    The "when do you want it" row in the order sheet.

    Sana, 2026-09-28: "if take away, need to tell slots/time".

    ---- As soon as possible is first and preselected ---------------------

    It is what most people want and the only option that needs no thought.
    Making somebody pick a clock time to order a dosa now is a worse
    checkout than the one this replaces.

    ---- Why the list is built on the server ------------------------------

    The slots depend on the kitchen's opening window, its interval, its prep
    time and ITS clock. Computing them in the browser would use the guest's
    clock, which is the one thing here that is not authoritative: a phone
    an hour out of sync would offer times the kitchen refuses on submit.

    The list is rendered once with the page. A guest who leaves the sheet
    open for an hour can submit a slot that has since passed, which the
    server refuses with a message naming the problem -- better than a page
    that silently books them something else.

    Parameters:
      $whSlots  the slot list from MenuHandoverTiming::slots()
--}}
@if(! empty($whSlots))
    <div class="when-row" id="whenRow" style="display:none">
        <label class="when-lab" for="fWhen">When would you like it?</label>
        <select class="field" id="fWhen">
            <option value="">As soon as possible</option>
            @php $whDay = null; @endphp
            @foreach($whSlots as $whSlot)
                @if($whSlot['day'] !== $whDay)
                    @if($whDay !== null)</optgroup>@endif
                    <optgroup label="{{ $whSlot['day'] }}">
                    @php $whDay = $whSlot['day']; @endphp
                @endif
                <option value="{{ $whSlot['value'] }}">{{ $whSlot['label'] }}</option>
            @endforeach
            @if($whDay !== null)</optgroup>@endif
        </select>
    </div>

    <style>
        .when-row { margin-top: 10px; }
        .when-lab {
            display: block;
            font-size: 12.5px;
            opacity: .7;
            margin-bottom: 4px;
        }
    </style>
@endif
