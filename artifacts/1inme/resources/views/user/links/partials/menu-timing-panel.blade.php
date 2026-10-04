{{--
    When a takeaway or delivery order is wanted.

    Sana, 2026-09-28: "if take away, need to tell slots/time / setting
    should have min time for prep / if delivery option... need to tell
    slots/time".

    ---- Off by default ---------------------------------------------------

    Every menu on the platform takes orders today with no timing at all.
    Switching this on for all of them because a feature shipped is a change
    nobody asked for, and the first thing an owner would notice is a new
    question in front of their customers.

    ---- Why prep time sits beside the window ----------------------------

    It is the setting that decides what a guest can actually pick: a
    30-minute prep at 6:52 means the first offered slot is 7:30, not 7:00.
    Putting it on a different card from the window would mean changing one
    and not understanding why the other moved.

    Parameters:
      $tmNoun  'order' or 'request', for the copy
--}}
<div class="rm-card" x-show="menu.mode === 'order' && timingApplies()">
    <h5>Collection and delivery times</h5>
    <p class="text-xs mb-3" style="color:var(--text-muted)">
        Let a customer choose when they want a takeaway or delivery {{ $tmNoun ?? 'order' }}.
        Dine-in is never asked: they are already here.
    </p>

    <div class="rm-row">
        <label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)">
            <input type="checkbox" x-model="timing.enabled" @change="saveSettings()">
            Ask for a time
        </label>
    </div>

    <template x-if="timing.enabled">
        <div>
            <div class="rm-row">
                <label class="rm-label">You can start handing orders over at</label>
                <div class="tm-times">
                    <input class="rm-input" type="time" x-model="timing.open" @change="saveSettings()" aria-label="Opens">
                    <span>and stop at</span>
                    <input class="rm-input" type="time" x-model="timing.close" @change="saveSettings()" aria-label="Closes">
                </div>
                <p class="text-xs mt-2" style="color:var(--text-muted)">
                    A closing time earlier than the opening one is read as running past midnight, so 6pm to 1am works.
                </p>
            </div>

            <div class="rm-row">
                <label class="rm-label">Offer a time every</label>
                <select class="rm-input" x-model.number="timing.interval" @change="saveSettings()">
                    @foreach(\App\Modules\User\Support\MenuHandoverTiming::INTERVALS as $tmEvery)
                        <option value="{{ $tmEvery }}">{{ $tmEvery }} minutes</option>
                    @endforeach
                </select>
            </div>

            <div class="rm-row">
                <label class="rm-label">You need at least this long to get an order ready</label>
                <div class="tm-prep">
                    <input class="rm-input" type="number" min="0" max="360" step="5"
                           x-model.number="timing.prep_minutes" @change="saveSettings()">
                    <span>minutes</span>
                </div>
                <p class="text-xs mt-2 tm-preview" x-text="timingPreview()"></p>
            </div>
        </div>
    </template>
</div>

<style>
    .tm-times { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .tm-times .rm-input { width: auto; flex: 0 1 140px; margin-top: 0; }
    .tm-times span { font-size: 13px; color: var(--text-muted); }
    .tm-prep { display: flex; align-items: center; gap: 8px; }
    .tm-prep .rm-input { width: auto; max-width: 110px; margin-top: 0; }
    .tm-prep span { font-size: 13px; color: var(--text-muted); }
    .tm-preview {
        padding: 7px 10px;
        border-radius: 8px;
        background: var(--bg-subtle, rgba(128,128,128,.1));
        color: var(--text-primary);
    }
</style>
