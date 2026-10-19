@php
    use App\Modules\Common\Support\SitePagesContent;
    use App\Modules\Admin\Models\AppSetting;
    $heroCopy=SitePagesContent::normalizeHeroCopy((array)AppSetting::get('marketing_hero_copy',[]));
    /*
     * The hero scene.
     *
     * This replaced a seven-row journey panel. Sana, of that panel and of two
     * attempts to enrich it: "concept seems to be good but design looks too
     * old", "i neee cdless of text.. more graphics and animations", and then
     * of a style reference: illustrated scenes, with the real product in them.
     *
     * So the argument is made by a picture rather than a list. Three surfaces
     * from the actual product float over a soft ground -- a fragment of the
     * folder map, a biolink page on a phone, and one customer record -- and
     * the folder map's own dashed rails run BETWEEN them, carrying a
     * travelling dot. Connectedness is shown as motion between real screens
     * instead of asserted in a paragraph.
     *
     * The surfaces are built in CSS, not screenshotted: they stay sharp at
     * any size, they animate, they never go stale when the UI ships, and no
     * customer data is ever baked into a public page.
     *
     * Chip order matches the story left to right: what you already had, where
     * he came from, what he asked, who he is, what he paid, what is running,
     * what is next. The last two stay dashed -- the record is deliberately
     * unfinished, because that is what a live one looks like.
     */
    $chips=[
      ['Design services','fa-store','#5865ef',false],
      ['QR flyer','fa-window-maximize','#8b5cf6',false],
      ['“new logo”','fa-comment-dots','#0ea5b7',false],
      ['Alex Morgan','fa-address-card','#3672df',false],
      ['Paid','fa-file-invoice-dollar','#159b82',false],
      ['In review','fa-list-check','#b16bd7',true],
      ['Next job','fa-arrows-rotate','#db8d27',true],
    ];

    /* Stations on the folder-map fragment. Sana, of the three-row version:
     * "Folder have 3 lines only... here i want to show multiple blocks or
     * rows with animations of how data is connected within it".
     *
     * So six real station kinds, threaded onto one dashed rail, and a pulse
     * walks them in order -- the folder handing a job down its own flow
     * rather than three cards sitting still. The dimmed one is a real state:
     * an unused station is dashed on the map and asks to be filled.
     *
     * The walk is pure CSS: one 6.6s clock, each row offset by its own index,
     * so there is no px arithmetic to drift and no script to keep in sync.
     * It also means the pause button and prefers-reduced-motion reach it the
     * same way they reach every other layer, instead of needing their own
     * branch in journey-hero-script.
     */
    $stations=[
      ['Links','3','fa-link','#5865ef',false],
      ['Campaign','1','fa-bullhorn','#8b5cf6',false],
      ['Forms','2','fa-clipboard-list','#0ea5b7',false],
      ['Contacts','1','fa-address-book','#3672df',false],
      ['Invoices','1','fa-file-invoice-dollar','#159b82',false],
      ['Tasks','0','fa-list-check','#db8d27',true],
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

</div>
<div id="sayzio-journey" class="sj-board sj-board--scene" aria-label="Illustrative picture of a connected business">
<div class="sj-board-head"><span><i class="fas fa-circle-nodes" aria-hidden="true"></i>One business. Everything connected.</span><span class="sj-demo-label">Illustrative journey</span></div>

<div class="sj-scene">
  <span class="sj-blob b1" aria-hidden="true"></span><span class="sj-blob b2" aria-hidden="true"></span><span class="sj-blob b3" aria-hidden="true"></span>
  <span class="sj-dots" aria-hidden="true"></span>

  {{-- The rails are the folder map's own language, now running between the
       screens. Fixed viewBox on a fixed-ratio stage, and every surface is
       placed in percentages of that same stage, so one unit is one unit and
       nothing is measured at runtime. --}}
  <svg class="sj-rails" viewBox="0 0 600 520" aria-hidden="true" focusable="false">
    <path id="sj-rail-0" d="M441 321 C431 379, 411 358, 363 410" stroke="#0ea5b7"/>
    {{-- Rail 1 used to reach up to y=187, which was clear air when the
         folder card held three rows. At six it ends up behind the card,
         so the travelling dot spent half its run hidden. Starts at 302
         now: just under the card's lower edge. --}}
    <path id="sj-rail-1" d="M51 406 C36 372, 41 338, 51 302" stroke="#5865ef"/>
    <path id="sj-rail-2" d="M389 459 C413 459, 472 458, 500 462" stroke="#db8d27"/>
    @foreach([['0','#0ea5b7','3s','0s'],['1','#5865ef','3.5s','0.9s'],['2','#db8d27','4s','1.8s']] as [$rid,$rc,$rdur,$rdelay])
      <circle r="4" fill="{{ $rc }}" opacity="0">
        <animateMotion dur="{{ $rdur }}" begin="{{ $rdelay }}" repeatCount="indefinite" calcMode="linear"><mpath href="#sj-rail-{{ $rid }}"/></animateMotion>
        <animate attributeName="opacity" values="0;1;1;0" dur="{{ $rdur }}" begin="{{ $rdelay }}" repeatCount="indefinite"/>
      </circle>
    @endforeach
  </svg>

  <div class="sj-surface sj-map">
    <div class="mh"><i class="fas fa-diagram-project" aria-hidden="true"></i>Folder &middot; flow</div>
    {{-- The rail runs behind the icon tiles, so the rows read as stops on one
         wire rather than six unrelated cards. --}}
    <span class="rail" aria-hidden="true"></span>
    @foreach($stations as $si => [$sLabel,$sCount,$sIcon,$sColor,$sDim])
      <div class="stn {{ $sDim ? 'dim' : '' }}" style="--sc:{{ $sColor }};--si:{{ $si }}">
        <i class="fas {{ $sIcon }}" aria-hidden="true"></i><b>{{ $sLabel }}</b><s>{{ $sCount }}</s>
      </div>
    @endforeach
  </div>

  {{-- A Link in Bio page, not a loading skeleton. Sana, of the first
       version: "Link in bio page isnt good" -- it was grey bars on a
       gradient, which reads as a page still loading rather than a page
       somebody made. Real avatar, real labels, the handle at the bottom. --}}
  <div class="sj-surface sj-phone" aria-hidden="true"><div class="scr">
    <span class="av">N</span>
    <span class="nm">Northstar Studio <i class="fas fa-circle-check"></i></span>
    <span class="bi">Brand &amp; web design</span>
    <span class="lnk"><i class="fas fa-calendar-check"></i>Book a call</span>
    <span class="lnk"><i class="fas fa-palette"></i>Our services</span>
    <span class="lnk ghost"><i class="fab fa-whatsapp"></i>WhatsApp us</span>
    <span class="lnk ghost"><i class="fas fa-images"></i>Latest work</span>
    <span class="soc"><span><i class="fab fa-instagram"></i></span><span><i class="fab fa-linkedin-in"></i></span><span><i class="fab fa-x-twitter"></i></span></span>
    <span class="pw">sayzio.app/northstar</span>
  </div></div>

  <div class="sj-mini sj-toast"><span class="tk"><i class="fas fa-check" aria-hidden="true"></i></span><span><b>Invoice paid</b><small>Brand identity package</small></span></div>

  <div class="sj-mini sj-anal"><span class="lb">Clicks this week</span>
    <span class="sj-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span></div>

  <p class="sj-bubble">&ldquo;I need a new logo.&rdquo;</p>

  <div class="sj-surface sj-rec2">
    <div class="rh"><span class="ava">AM</span><span><span class="rn">Alex Morgan</span><span class="rs">One customer record</span></span><span class="cnt"><b>5</b>of 7</span></div>
    <div class="chips">
      @foreach($chips as $ci => [$cLabel,$cIcon,$cColor,$cPend])
        <span data-chip="{{ $ci }}" class="{{ $cPend ? 'pend' : '' }}"><i class="fas {{ $cIcon }}" @if(! $cPend) style="background:{{ $cColor }}" @endif aria-hidden="true"></i>{{ $cLabel }}</span>
      @endforeach
    </div>
  </div>

  <p class="sj-zbub">I know this one &mdash; it&rsquo;s Alex.</p>

  <div class="sj-zio-stage">
    @include('home.partials.zio-face',['mouth'=>true,'alt'=>'Zio, your AI helper','size'=>'110px'])
    <span class="sj-zio-spark" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c.7 6.4 4.9 10.6 12 12-7.1 1.4-11.3 5.6-12 12-.7-6.4-4.9-10.6-12-12C7.1 10.6 11.3 6.4 12 0z"/></svg></span>
  </div>
</div>

<div class="sj-board-foot"><button class="sj-playback" type="button" data-journey-toggle aria-label="Pause the animation"><i class="fas fa-pause" aria-hidden="true"></i></button></div>
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
