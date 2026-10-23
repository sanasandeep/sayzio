<section class="mkt-related" aria-labelledby="mkt-related-heading">
    <div><p class="mkt-standard-eyebrow">KEEP EXPLORING</p><h2 id="mkt-related-heading">Your next step with Sayzio.</h2></div>
    <div class="mkt-related-grid">
        @foreach(\App\Modules\Common\Support\MarketingPageStandards::related($standard) as $path => $label)
            <a href="{{ url($path) }}"><span>{{ $label }}</span><span aria-hidden="true">↗</span></a>
        @endforeach
    </div>
</section>
