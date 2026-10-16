{{-- The one call to action on /pricing.

     There used to be a question form here (purpose, pages, seats, three
     checkboxes) and two text links beside it. The form asked a visitor to
     describe themselves before being shown a price, which is work in exchange
     for nothing they could not get by scrolling, and the links competed with
     the button for the same click.

     Now the button is the whole interaction: press it, Zio spends a moment,
     a different set of plans arrives. The pause is theatre and is allowed to
     be, but the copy is not allowed to claim an analysis that is not
     happening. "Reading the room" is a wink; "analysing your history" would
     be a claim about data nobody is reading. --}}
<div class="pricing-curator" :aria-busy="curating">
    <div class="curator-invitation">
        <img src="{{ asset('branding/icon-96.webp') }}" width="56" height="56" alt="Zio">
        <div>
            <h2>Find your fit with Zio.</h2>
            <p>One press and Zio lines up a set of plans for you.</p>
            <small>No AI call or coins. Saved only in this tab session. Press again any time for a different set.</small>
        </div>
        <div class="curator-actions">
            <button type="button" class="sy-btn" :disabled="curating"
                    @click="startCuration(@js($anotherSetUrl))">
                <span x-show="!curating">&#10022; Curate with Zio</span>
                <span x-show="curating" x-cloak>Working&hellip;</span>
            </button>
        </div>
    </div>

    <div x-show="curating" x-cloak class="curator-loading" role="status" aria-live="polite">
        <div class="curator-orbit">
            <img src="{{ asset('branding/icon-96.webp') }}" width="64" height="64" alt="">
        </div>
        <strong x-text="curationPhase"></strong>
        <span>Zio is picking a set of plans for you.</span>
        <div class="curator-dots" aria-hidden="true"><i></i><i></i><i></i></div>
    </div>
</div>
