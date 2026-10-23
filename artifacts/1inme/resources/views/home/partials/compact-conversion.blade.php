@php
$publicFree = collect($plans ?? [])->first(fn($plan) => !empty($plan['is_free']));
$publicPaid = $cheapestPaidPlan ?? collect($plans ?? [])->reject(fn($plan) => !empty($plan['is_free']))->sortBy(fn($plan) => (int) ($plan['monthly']['amount_minor'] ?? PHP_INT_MAX))->first();
/* The "paid plans from" figure, in the currency the VISITOR resolves to --
 * and it moves when the footer's currency switcher moves.
 *
 * It read `$publicPaid['monthly']['formatted']`, which was one currency for
 * everybody. That key is built by the payload cache, which is stored per
 * currency but used to resolve prices from the ambient request rather than
 * from the currency it was being built for -- and the scheduled warmer that
 * writes these keys runs on the CLI, with no session and no IP, so it
 * resolved to the default and wrote USD figures into the INR entry. An
 * Indian visitor read "$4.00 / month" against a catalogue that starts at
 * Rs 167, clicked INR in the footer, and nothing moved: this section had no
 * listener either, so the only control the page offers for the figure could
 * not reach it.
 *
 * `prices` carries both currencies in the same shape the /pricing cards
 * read, so the server renders the visitor's own currency and the footer's
 * event swaps it with no reload. The old key stays as the fallback, for a
 * payload cached before `prices` existed. */
$paidPrices = [];
$paidFrom = null;
if ($publicPaid) {
    $paidPrices = $publicPaid['prices'] ?? [];
    $paidFrom = $paidPrices[$currency ?? 'USD']['monthly']['formatted']
        ?? ($publicPaid['monthly']['formatted'] ?? null);
}
/* The tiles said "Explore the essentials." and "More capacity as your needs
 * grow." -- 34 words for the whole pricing section, and not one fact a
 * visitor could decide on. The limits below are read from the SAME plan
 * features the /pricing cards read, using the same -1 means unlimited
 * convention, so the two pages describe a plan identically and neither can
 * drift when a limit changes in admin. Nothing here is typed by hand. */
$planLimits = function ($plan) {
    $features = $plan['features'] ?? [];
    $limit = function (string $key, string $one, string $many) use ($features) {
        if (! array_key_exists($key, $features)) { return null; }
        $value = (int) $features[$key];
        return $value === -1
            ? 'Unlimited ' . $many
            : number_format($value) . ' ' . ($value === 1 ? $one : $many);
    };
    return collect([
        $limit('max_biolinks', 'Link in Bio page', 'Link in Bio pages'),
        $limit('max_links', 'short link', 'short links'),
        (int) ($features['included_coins_monthly'] ?? 0) > 0
            ? number_format($features['included_coins_monthly']) . ' coins / month'
            : $limit('max_seats_per_workspace', 'workspace seat', 'workspace seats'),
    ])->filter()->values();
};
/* The questions, and the FAQPage JSON-LD built from them, used to live
 * here. This partial is part of the DEFERRED fragment, so that schema was
 * emitted into a document a crawler's first pass never sees. The copy now
 * lives in HomeFaq and the head emits the schema; this renders the
 * visible list from the same source. */
$homeQuestions = \App\Modules\Common\Support\HomeFaq::CLASSIC;
@endphp
<section id="pricing" class="ch-section ch-pricing" aria-labelledby="ch-pricing-heading"><div><span class="ch-eyebrow">START WITH WHAT YOU NEED</span><h2 id="ch-pricing-heading">Room to start.<br>Room to grow.</h2><p>Compare feature limits and AI allowances on the full pricing page. Coins are the pay-as-you-go balance AI generation draws on, spent only when you need more than your plan includes.</p><a class="ch-link" href="{{ route('site.pricing') }}">View all plans <span aria-hidden="true">→</span></a></div><div class="ch-prices">
@if($publicFree)<article><span>{{ $publicFree['name'] }}</span><h3>Free</h3><p>Free forever, no card required.</p>@if($planLimits($publicFree)->isNotEmpty())<ul class="ch-plan-limits">@foreach($planLimits($publicFree) as $limit)<li>{{ $limit }}</li>@endforeach</ul>@endif</article>@endif
@if($publicPaid && $paidFrom)<article x-data='{ currency: @json($currency ?? 'USD'), prices: @json($paidPrices), fallback: @json($paidFrom) }' @inme-currency.window="currency = $event.detail.c"><span>Paid plans from</span><h3><span x-text="prices[currency]?.monthly?.formatted || fallback">{{ $paidFrom }}</span><small> / month</small></h3><p>More capacity as your needs grow.</p>@if($planLimits($publicPaid)->isNotEmpty())<ul class="ch-plan-limits">@foreach($planLimits($publicPaid) as $limit)<li>{{ $limit }}</li>@endforeach</ul>@endif</article>@else<article><h3>Find your plan</h3><p>See current prices and availability.</p></article>@endif
</div></section>
<section id="faq" class="ch-section ch-faq" aria-labelledby="ch-faq-heading"><div><span class="ch-eyebrow">A FEW QUICK ANSWERS</span><h2 id="ch-faq-heading">Before you begin.</h2><a class="ch-link" href="{{ route('site.faqs') }}">More questions <span aria-hidden="true">→</span></a></div><div>@foreach($homeQuestions as $question)<details><summary>{{ $question['q'] }}<span aria-hidden="true">+</span></summary><p>{{ $question['a'] }}</p></details>@endforeach</div></section>
<section id="cta-final" class="ch-section ch-final" aria-labelledby="ch-final-heading"><span class="ch-eyebrow">YOUR NEXT STEP</span><h2 id="ch-final-heading">Bring your business together.</h2><p>Start with a page. Connect the customer journey as you grow.</p>@guest<a class="ch-button" href="{{ route('register.page') }}">Get started <span aria-hidden="true">→</span></a>@else<a class="ch-button" href="{{ route('user.dashboard') }}">Go to dashboard <span aria-hidden="true">→</span></a>@endguest</section>
