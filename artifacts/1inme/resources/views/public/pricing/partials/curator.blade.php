<div class="pricing-curator" :aria-busy="matching">
    <div class="curator-invitation">
        <img src="{{ asset('branding/icon-96.webp') }}" width="56" height="56" alt="Zio">
        <div><h2>Find your fit with Zio.</h2><p>Tell us what you need. Get an instant match from our existing plans.</p><small>No AI call or coins. Saved only in this tab session.</small></div>
        <div class="curator-actions"><button type="button" class="sy-btn" @click="matcherOpen = !matcherOpen" :aria-expanded="matcherOpen" aria-controls="pricing-matcher-form">✦ Curate with Zio</button><a class="curator-text-button" href="{{ $anotherSetUrl }}" rel="nofollow">Show me another set</a><button type="button" class="curator-text-button" @click="showAllPlans()">See all plans</button></div>
    </div>
    <form id="pricing-matcher-form" x-show="matcherOpen && !matching" x-cloak @submit.prevent="curatePlans()" class="curator-form">
        <div class="curator-fields">
            <label>What are you building?<select x-model="answers.purpose"><option value="bio">My bio or personal brand</option><option value="business">A business presence</option><option value="team">A team workspace</option><option value="developer">An API integration</option></select></label>
            <label>Link in Bio pages<select x-model="answers.pages"><option value="1">1 page</option><option value="3">Up to 3</option><option value="10">Up to 10</option><option value="25">Up to 25</option><option value="100">Up to 100</option></select></label>
            <label>People in your workspace<select x-model="answers.seats"><option value="1">Just me</option><option value="3">Up to 3</option><option value="10">Up to 10</option><option value="25">Up to 25</option></select></label>
        </div>
        <fieldset><legend>Anything else you need?</legend><div class="curator-options"><label><input type="checkbox" x-model="answers.domain"> Custom domain</label><label><input type="checkbox" x-model="answers.payments"> Paid forms</label><label><input type="checkbox" x-model="answers.api"> API access</label></div></fieldset>
        <div class="curator-submit"><p>Matches are ordered by regular price for your selected currency and billing cycle. Offers and taxes are shown on the plan.</p><button type="submit" class="sy-btn" :disabled="matching">Find my plans <span aria-hidden="true">→</span></button></div>
    </form>
    <div x-show="matching" x-cloak class="curator-loading" role="status" aria-live="polite">
        <div class="curator-orbit"><img src="{{ asset('branding/icon-96.webp') }}" width="64" height="64" alt=""></div><strong>Zio is matching your requirements</strong><span>Checking page limits, seats and features.</span><div class="curator-dots" aria-hidden="true"><i></i><i></i><i></i></div>
    </div>
    <div x-ref="curationResults" x-show="matches.length && !matching" x-cloak class="curator-results" aria-live="polite">
        <div class="curator-result-heading"><h3 x-text="matches.some(m => m.exact) ? 'Your matching plans' : 'Closest options: no exact match found'"></h3><button type="button" class="curator-text-button" @click="matcherOpen = true">Refine answers</button></div>
        <div class="curator-result-grid"><template x-for="(match, index) in matches" :key="match.slug"><button type="button" @click="openMatchedPlan(match.id)"><small x-text="match.exact ? (index === 0 ? 'Lowest regular price' : 'More capacity') : 'Review missing requirements'"></small><strong x-text="match.name"></strong><span x-text="match.reason"></span><b>View plan ↗</b></button></template></div>
        <p>These are existing plans, not custom quotes. Compare the full details below before choosing.</p>
    </div>
</div>
