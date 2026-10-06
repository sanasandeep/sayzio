@if(!empty($couponBookingSlots) && !$staffMode)
<details class="coupon-prebooking" style="margin:20px 0;padding:16px;border:1px solid var(--rule);border-radius:12px">
    <summary style="cursor:pointer;font-weight:700">Already have a meal coupon? Prebook a serving</summary>
    <p>Use your purchased coupon code or the code printed with its QR. Booking reserves one serving; staff redeem the coupon when you collect it.</p>
    <form id="couponPrebooking" action="{{ $couponBookingUrl }}" method="post">
        @csrf
        <input class="field" name="coupon_code" placeholder="Coupon code" maxlength="64" required autocomplete="off">
        <input class="field" name="customer_name" placeholder="Your name" maxlength="150" required>
        <input class="field" name="customer_phone" placeholder="Phone number" maxlength="40" type="tel">
        <label>How would you like it?<select class="field" name="fulfilment">
            @foreach($fulModes as $couponBookingMode)
                <option value="{{ $couponBookingMode }}">{{ \App\Modules\User\Support\MenuFulfilment::label($couponBookingMode, $couponBookingRestaurant) }}</option>
            @endforeach
        </select></label>
        <label>Booking time<select class="field" name="wanted_at" required>
            <option value="">Choose a future time</option>
            @foreach($couponBookingSlots as $couponBookingSlot)
                <option value="{{ $couponBookingSlot['value'] }}">{{ $couponBookingSlot['day'] }} · {{ $couponBookingSlot['label'] }}</option>
            @endforeach
        </select></label>
        <textarea class="field" name="customer_address" maxlength="1000" placeholder="Address (required for delivery)"></textarea>
        <p>Additional delivery or packing charges, if applicable, are confirmed by staff.</p>
        <button class="ghost" type="submit">Confirm coupon prebooking</button>
        <p data-booking-message role="status" aria-live="polite"></p>
    </form>
</details>
<script>
(function () {
    var form = document.getElementById('couponPrebooking');
    if (!form) return;
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        var button = form.querySelector('button[type="submit"]');
        var message = form.querySelector('[data-booking-message]');
        button.disabled = true;
        message.textContent = 'Confirming…';
        try {
            var response = await fetch(form.action, {method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, body:new FormData(form)});
            var result = await response.json();
            message.textContent = response.ok ? result.data.message + ' Booking #' + result.data.booking_id : result.error?.message || result.message || 'Booking could not be confirmed.';
            if (response.ok) form.reset();
        } catch (error) { message.textContent = 'Could not reach the server. Please try again.'; }
        finally { button.disabled = false; }
    });
})();
</script>
@endif
