@php
    use App\Modules\Common\Support\SitePagesContent;
    use App\Modules\Admin\Models\AppSetting;
    $heroCopy=SitePagesContent::normalizeHeroCopy((array)AppSetting::get('marketing_hero_copy',[]));
    $heroBadges=SitePagesContent::normalizeHeroBadges((array)AppSetting::get('marketing_hero_badges',[])) ?: SitePagesContent::heroBadgesDefault();
    $journey=[
      ['Your business','Identity & knowledge','fa-store','Northstar Studio','Brand Kit · Services · AI Minds','Start with your services, brand identity and business knowledge.','#5865ef'],
      ['Pages & marketing','Get discovered','fa-window-maximize','Your services, online','Business page · Campaign · QR','Create your pages and marketing materials, then share them with your audience.','#8b5cf6'],
      ['Enquiry','A visitor gets in touch','fa-comment-dots','Alex: “I need a new logo.”','New enquiry · Design services','Capture enquiries through forms, chat and booking pages.','#0ea5b7'],
      ['CRM','Keep the conversation moving','fa-address-card','Alex Morgan','Contact · Requirements · Follow-up','Organize customer details and conversations so you know what to do next.','#3672df'],
      ['Sale','Agree the work','fa-file-invoice-dollar','Brand identity package','Invoice · Paid','Manage the sale, invoice and payment information for the agreed work.','#159b82'],
      ['Delivery & support','Turn a promise into progress','fa-list-check','Alex’s brand project','Design ✓ · Review · Delivery','Keep client projects, tasks, delivery and customer communication organized.','#b16bd7'],
      ['Repeat customer','Build a lasting relationship','fa-arrows-rotate','Alex: “Let’s do our website.”','Customer · Next opportunity','Continue the relationship through support, follow-ups and future work.','#db8d27'],
    ];
@endphp
<section id="hero" class="sj-hero sec-first" aria-labelledby="hero-h">
<div class="sj-grid-bg" aria-hidden="true"></div>
<div class="sj-layout">
<div class="sj-copy">
<span class="sj-eyebrow"><i class="fas fa-sparkles" aria-hidden="true"></i>Your business. Connected.</span>
<h1 id="hero-h">{!! SitePagesContent::heroHeadlineHtml($heroCopy) !!}</h1>
<p class="sj-subheading">{!! SitePagesContent::heroSubheadingHtml($heroCopy) !!}</p>
<div class="sj-ctas">
@guest
<a href="{{ route('register.page') }}" class="sj-primary" onclick="if(window.trackMarketingEvent)window.trackMarketingEvent('landing_home_cta','hero_journey_start');if(window.Alpine){event.preventDefault();window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))}">Start free <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
@else
<a href="{{ route('user.dashboard') }}" class="sj-primary">Go to dashboard <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
@endguest
<a href="#sayzio-journey" class="sj-secondary" data-journey-replay><i class="fas fa-play" aria-hidden="true"></i>See how Sayzio works</a>
</div>
<div class="sj-proof">@foreach($heroBadges as $badge)<span><i class="{{ !empty($badge['pulse'])?'pulse-dot':'' }}" aria-hidden="true"></i><b>{{ $badge['value'] }}</b> {{ $badge['label'] }}</span>@endforeach</div>
</div>
<div id="sayzio-journey" class="sj-board" aria-label="Illustrative customer journey">
<div class="sj-board-head"><span><i class="fas fa-route" aria-hidden="true"></i>One connected customer journey</span><span class="sj-demo-label">Illustrative journey</span></div>
<ol class="sj-stages">
@foreach($journey as $i=>[$label,$caption,$icon,$example,$meta,$description,$color])
<li class="sj-stage {{ $i===0?'is-active':'' }}" style="--stage-color:{{ $color }}"><button type="button" class="sj-stage-button" data-journey-step="{{ $i }}" aria-pressed="{{ $i===0?'true':'false' }}" aria-describedby="sj-description-{{ $i }}"><span class="sj-step-number">{{ $i+1 }}</span><span class="sj-stage-icon"><i class="fas {{ $icon }}" aria-hidden="true"></i></span><span class="sj-stage-copy"><strong>{{ $label }}</strong><small>{{ $caption }}</small></span><span class="sj-mini-card"><b>{{ $example }}</b><small>{{ $meta }}</small></span></button><span id="sj-description-{{ $i }}" class="sr-only">{{ $description }}</span></li>
@endforeach
</ol>
<div class="sj-explanation" aria-live="off">@foreach($journey as $i=>$stage)<p data-journey-description="{{ $i }}" @if($i!==0) hidden @endif>{{ $stage[5] }}</p>@endforeach</div>
<div class="sj-board-foot"><div class="sj-zio">@include('home.partials.zio-face',['mouth'=>true,'alt'=>'Zio, your AI helper','size'=>'110px'])<span><strong>AI helps along the way</strong><small>Create, plan, answer and assist.</small></span></div><button class="sj-playback" type="button" data-journey-toggle aria-label="Pause journey animation"><i class="fas fa-pause" aria-hidden="true"></i></button></div>
<p class="sj-demo-note">Example only. Connect tools and review actions as needed.</p>
</div>
</div>
    @php
        /*
         * The items are admin-editable, under Marketing Settings -> "Hero
         * capability marquee". Read here rather than passed in, the same way
         * this partial already reads Zio's hero lines: the marquee belongs to
         * the hero, and threading it through HomeController and every one of
         * the seven fragment designs to reach one <span> would be more moving
         * parts than the feature is worth.
         *
         * `?:` on the normalised result, not `??` on the setting: an admin who
         * deletes every row should get the shipped list back rather than an
         * empty band -- there is no meaningful "no marquee" state, and the
         * band's height is part of the hero's grid.
         */
        try {
            $__marquee = \App\Modules\Common\Support\SitePagesContent::normalizeHeroMarquee(
                (array) \App\Modules\Admin\Models\AppSetting::get('marketing_hero_marquee', [])
            );
        } catch (\Throwable $e) {
            $__marquee = [];
        }
        $__marquee = $__marquee ?: \App\Modules\Common\Support\SitePagesContent::heroMarqueeDefault();
    @endphp
    <div class="zio-strip" aria-hidden="true">
        <div class="zio-strip-track">
            @for($__m = 0; $__m < 2; $__m++)
                <div class="zio-strip-run">
                    @foreach($__marquee as $__item)
                        <span class="zio-strip-item"><i class="fas {{ $__item['icon'] }}"></i>{{ $__item['label'] }}</span>
                    @endforeach
                </div>
            @endfor
        </div>
    </div>

@include('home.partials.zio-legacy-styles')
@include('home.partials.journey-hero-styles')
@include('home.partials.journey-hero-script')
</section>
