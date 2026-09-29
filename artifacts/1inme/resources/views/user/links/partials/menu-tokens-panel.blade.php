{{--
    Order numbers.

    Sana, 2026-09-28: "i need token no. to be generated for each order...
    need options in setting like: reset tokeno. by day, week, month or all
    time".

    ---- Why the default is "every day" and not "never" -------------------

    Because a counter-service kitchen calls out "fourteen" and nobody wants
    to be calling out "four thousand two hundred and six" by March. Daily
    is what an owner who never opens this card would expect, so it is what
    they get.

    ---- Why the reset says when it next happens --------------------------

    "Every week" is ambiguous -- from Monday? from the day I switched it
    on? -- and an owner cannot tell which by looking. So the card says the
    date the next run starts, in their own timezone, which is also the
    thing that decides it.

    Parameters:
      $tkNoun  'order' or 'request', for the copy
--}}
<div class="rm-card" x-show="menu.mode === 'order'">
    <h5>Order numbers</h5>
    <p class="text-xs mb-3" style="color:var(--text-muted)">
        A plain number on every {{ $tkNoun ?? 'order' }}, counting up from 1. The customer
        sees it on the confirmation, big, and can copy it.
    </p>

    <div class="rm-row">
        <label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)">
            <input type="checkbox" x-model="tokens.enabled" @change="saveSettings()">
            Give every {{ $tkNoun ?? 'order' }} a number
        </label>
    </div>

    <div class="rm-row" x-show="tokens.enabled">
        <label class="rm-label">Start again from 1</label>
        <select class="rm-input" x-model="tokens.reset" @change="saveSettings()">
            @foreach(\App\Modules\User\Support\MenuOrderToken::RESETS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <p class="text-xs mt-2" style="color:var(--text-muted)" x-text="tokenHint()"></p>
    </div>

    <p class="text-xs mt-2" style="color:var(--text-faint)">
        Numbers already given out are never reused within the same run, even if two
        {{ ($tkNoun ?? 'order').'s' === 'orders' ? 'orders' : 'requests' }} land in the same second.
    </p>
</div>

<script>
@once
{{-- The owner's own timezone decides where one run of numbers ends and
     the next begins, so it is what the hint counts in. --}}
window.MENU_TOKEN_TZ = @json(auth()->user()?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault());
@endonce
</script>
