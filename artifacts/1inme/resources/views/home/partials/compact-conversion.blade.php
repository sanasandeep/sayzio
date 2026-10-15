@php
$publicFree = collect($plans ?? [])->first(fn($plan) => !empty($plan['is_free']));
$publicPaid = $cheapestPaidPlan ?? collect($plans ?? [])->reject(fn($plan) => !empty($plan['is_free']))->sortBy(fn($plan) => (int) ($plan['monthly']['amount_minor'] ?? PHP_INT_MAX))->first();
$homeQuestions = [
 ['q'=>'What is Sayzio?', 'a'=>'Sayzio connects your online presence with customer work: pages and links, enquiries, CRM, sales, projects and support, with optional AI tools along the way.'],
 ['q'=>'Can I use Sayzio with my existing workflow?', 'a'=>'Start with the tools you need, then connect your pages, contacts, plans and project resources as your workflow grows. Available integrations and limits depend on the feature and your plan.'],
 ['q'=>'Do I have to use AI?', 'a'=>'No. You can enter and edit content yourself. When you choose AI generation, review the coin estimate before you continue and review the result before using it.'],
 ['q'=>'What happens after a customer gets in touch?', 'a'=>'Keep the enquiry and customer details organized, manage the agreed sale, and connect the delivery work to a project. You choose the records and actions to connect; the example above illustrates the flow.'],
];
$homeFaqSchema = \App\Modules\Common\Support\MarketingSchema::faqPage($homeQuestions);
@endphp
<section id="pricing" class="ch-section ch-pricing" aria-labelledby="ch-pricing-heading"><div><span class="ch-eyebrow">START WITH WHAT YOU NEED</span><h2 id="ch-pricing-heading">Room to start.<br>Room to grow.</h2><p>Compare feature limits and AI allowances on the full pricing page.</p><a class="ch-link" href="{{ route('site.pricing') }}">View all plans <span aria-hidden="true">→</span></a></div><div class="ch-prices">
@if($publicFree)<article><span>{{ $publicFree['name'] }}</span><h3>Free</h3><p>Explore the essentials.</p></article>@endif
@if($publicPaid)<article><span>Paid plans from</span><h3>{{ $publicPaid['monthly']['formatted'] }}<small> / month</small></h3><p>More capacity as your needs grow.</p></article>@else<article><h3>Find your plan</h3><p>See current prices and availability.</p></article>@endif
</div></section>
<section id="faq" class="ch-section ch-faq" aria-labelledby="ch-faq-heading"><div><span class="ch-eyebrow">A FEW QUICK ANSWERS</span><h2 id="ch-faq-heading">Before you begin.</h2><a class="ch-link" href="{{ route('site.faqs') }}">More questions <span aria-hidden="true">→</span></a></div><div>@foreach($homeQuestions as $question)<details><summary>{{ $question['q'] }}<span aria-hidden="true">+</span></summary><p>{{ $question['a'] }}</p></details>@endforeach</div></section>
@if($homeFaqSchema)<script type="application/ld+json">{!! json_encode(\App\Modules\Common\Support\MarketingSchema::graph([$homeFaqSchema]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>@endif
<section id="cta-final" class="ch-section ch-final" aria-labelledby="ch-final-heading"><span class="ch-eyebrow">YOUR NEXT STEP</span><h2 id="ch-final-heading">Bring your business together.</h2><p>Start with a page. Connect the customer journey as you grow.</p>@guest<a class="ch-button" href="{{ route('register.page') }}">Get started <span aria-hidden="true">→</span></a>@else<a class="ch-button" href="{{ route('user.dashboard') }}">Go to dashboard <span aria-hidden="true">→</span></a>@endguest</section>
