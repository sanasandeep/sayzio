{{--
    "After the order is placed" -- the one control that decides what the
    guest sees the moment their order goes through. Shared by the restaurant
    and store editors, because the last four of these that were written
    twice drifted apart within the week.

    Sana, 2026-09-28: "i need option to link any page url foir order
    confirmation page... also if no page, then option should be there for
    message like thank you... make it flexible".

    Expects Alpine state `confirm` = {mode, url, message, headline} and a
    `saveSettings()` on the component, both of which every menu editor has.
--}}
<div class="rm-card" x-show="menu.mode === 'order'">
    <h5>After the order is placed</h5>
    <p class="text-xs mb-3" style="color:var(--text-muted)">
        What the customer sees the moment their order goes through. The order still lands on your dashboard whichever you pick.
    </p>

    <div class="rm-row">
        @foreach(\App\Modules\User\Support\MenuConfirmation::modes() as $key => $mode)
        <label class="rm-confirm-opt" :class="{ 'on': confirm.mode === '{{ $key }}' }">
            <input type="radio" value="{{ $key }}" x-model="confirm.mode" @change="saveSettings()">
            <span>
                <strong>{{ $mode['label'] }}</strong>
                <em>{{ $mode['hint'] }}</em>
            </span>
        </label>
        @endforeach
    </div>

    {{-- The heading sits above all three, because someone showing the bill
         may still want it to say "Thanks, Priya!" rather than the default. --}}
    <div class="rm-row">
        <label class="rm-label">Heading (optional)</label>
        <input class="rm-input" x-model="confirm.headline" maxlength="80"
               placeholder="{{ $confirmHeadlinePlaceholder ?? 'Order placed 🎉' }}"
               @change="saveSettings()">
    </div>

    <template x-if="confirm.mode === 'message'">
        <div class="rm-row">
            <label class="rm-label">Your message</label>
            <textarea class="rm-input" rows="4" maxlength="600" x-model="confirm.message"
                      placeholder="Thank you! We'll have this ready in about 20 minutes. Pay at the counter."
                      @change="saveSettings()"></textarea>
            <p class="text-xs mt-2" style="color:var(--text-muted)">
                Shown in place of the estimated bill. Line breaks are kept.
            </p>
        </div>
    </template>

    <template x-if="confirm.mode === 'url'">
        <div class="rm-row">
            <label class="rm-label">Page address</label>
            <input class="rm-input" type="url" x-model="confirm.url" maxlength="2048"
                   placeholder="https://yoursite.com/thank-you" @change="saveSettings()">
            <p class="text-xs mt-2" style="color:var(--text-muted)">
                Must start with http:// or https://. The customer is taken there instead of seeing the bill, so put the order details they need on that page.
            </p>
            {{-- Said plainly rather than left to be discovered on a live
                 menu: the mode is ignored until the address is usable. --}}
            <p class="text-xs mt-2" style="color:var(--text-muted)"
               x-show="!/^https?:\/\/.+/i.test(confirm.url || '')">
                Until this is filled in, customers keep seeing the estimated bill.
            </p>
        </div>
    </template>
</div>

<style>
    .rm-confirm-opt {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        padding: 10px 12px;
        border: 1px solid var(--border-subtle, rgba(128,128,128,.28));
        border-radius: 10px;
        cursor: pointer;
        color: var(--text-primary);
    }
    .rm-confirm-opt + .rm-confirm-opt { margin-top: 8px; }
    .rm-confirm-opt.on { border-color: var(--accent, #6366f1); }
    .rm-confirm-opt input { margin-top: 3px; flex: none; }
    .rm-confirm-opt strong { display: block; font-size: 13.5px; font-weight: 600; }
    .rm-confirm-opt em {
        display: block;
        margin-top: 2px;
        font-style: normal;
        font-size: 12px;
        line-height: 1.45;
        color: var(--text-muted);
    }
    /* The textarea inherits .rm-input, which was written for one-line
       fields; without this it comes out as tall as the page. */
    textarea.rm-input { min-height: 84px; resize: vertical; line-height: 1.45; }
</style>
