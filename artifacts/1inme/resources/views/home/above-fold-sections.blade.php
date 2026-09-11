{{-- ==================== SERVER-RENDERED ABOVE-THE-FOLD SECTIONS ====================

     These three sections used to arrive with the rest of the page, in the
     /home/sections fragment fetched by JavaScript after first paint. They are
     rendered into the initial HTML now, and only these three.

     WHY. Measured on the live homepage on 2026-09-11, the initial HTML
     contained:

         h2 headings      0
         h3 headings      0
         internal links   1

     Every heading, all the body copy and nearly every internal link on the
     homepage lived behind the fetch. Google does render JavaScript, but as a
     second pass: slower, queued, and not guaranteed. A crawler's first look at
     the homepage of a 375,000-user product was one <h1> and a spinner.

     WHY THESE THREE, and not more. They are the three that say what Sayzio is
     -- the proof band (who uses it, and the 1IN.ME -> Sayzio rebrand), what
     you can create (the 18 link types, which is the keyword surface), and who
     it is for. Together they carry the headings and the internal links that
     were missing, and they cost one cached lookup to render. Everything after
     them is demo, pricing and FAQ: worth having, not worth blocking the first
     paint for, and still deferred.

     WHAT THIS COSTS. index() now resolves the admin-editable link-type list.
     That is HomePageCache::linkTypes() -- its own small cache key, kept warm
     by `home:warm-caches`, deliberately NOT the per-currency payload, because
     that one builds the whole plan matrix on a miss and the homepage's first
     response must never pay for plans it does not show.

     SCOPED TO THE CLASSIC DESIGN. This file renders only when
     marketing_home_design is 'classic' -- see the 'above' key in
     HomeController::DESIGNS. The other six designs deliberately tell a
     different, shorter story below the fold; stacking these three on top of
     one of them would state the classic page's case twice.

     If you add a section here, weigh it against first paint. The reason the
     rest of the page is deferred has not gone away. --}}

{{-- The "1IN.ME is Sayzio" banner that ran here has merged into the proof
     band below. It was a bordered box making a brand claim, sitting directly
     above a second bordered box holding the numbers that back that claim;
     they are one statement and now they are one section. See
     public/partials/marketing-trust-band.blade.php. --}}
{{-- The marquee strip that ran here — a solid indigo bar, white caps and a
     star between every item — has moved into the hero and been restyled to
     sit on the page's own white. It said the same ten things twice on one
     page, and this was the louder of the two. See .zio-strip in
     home/partials/hero.blade.php. The $__skipMarquee flag that used to sit
     here went with it — nothing ever read it. --}}

{{-- ============================ PROOF BAND (brand lockup + trust numbers) ============================ --}}
@include('public.partials.marketing-trust-band')

{{-- ============================ WHAT YOU CAN CREATE (LINK TYPES) ============================ --}}
@include('home.partials.create-showcase')


{{-- ==================== ZONE · WHO IT'S FOR & HOW IT WORKS ==================== --}}
{{-- ============================ AUDIENCE (CREATORS / BUSINESSES / NETWORKING) ============================ --}}
@php
    /*
     * Each audience carries the card's three lines AND the panel's content.
     *
     * The panel used to be a clone of the card, so opening one of these showed
     * the same eyebrow, the same headline and the same sentence at a larger
     * size -- an animation, not an answer. `lead`, `left`, `right` and `stats`
     * are the modal's own, and `key` picks which product mock is drawn beside
     * them.
     *
     * `g1`/`g2` are stated per audience rather than inherited from the row
     * cycle: a panel is opened on its own, away from its neighbours, so the
     * positional cycle that keeps a ROW varied has nothing to vary against.
     */
    $__audiences = [
        [
            'key'     => 'creators',
            'eyebrow' => 'Creators',
            'title'   => 'Turn followers into fans, and income.',
            'desc'    => 'One link for every drop, with tips, products, DMs, scheduled posts and an AI coach to keep you growing.',
            'icon'    => 'fa-microphone-lines',
            'cta'     => 'Build my creator page',
            'g1' => '#1bd4d9', 'g2' => '#3d6bff',
            'lead'    => 'A follower who taps your bio link has already decided they are interested. The question is what they find when they get there: a list of links pointing away from you, or somewhere they can actually buy the thing, tip you, or say something.',
            'left'    => [
                'h' => 'What goes on the page',
                'i' => [
                    ['fa-check', '<strong>Every drop in one place</strong>, reordered whenever you like, with no app update and no new URL to post.'],
                    ['fa-check', '<strong>Tips and paid pages</strong>, so support does not have to leave your page to reach you.'],
                    ['fa-check', '<strong>DMs built in.</strong> A message about a brand deal lands somewhere you can find it again, not in a request folder.'],
                    ['fa-check', '<strong>Scheduled posts</strong>, so a launch goes live at the hour you planned it rather than the hour you remembered.'],
                ],
            ],
            'right'   => [
                'h' => 'What it does for you',
                'i' => [
                    ['fa-chart-line', '<strong>You find out which link earns.</strong> Per-block clicks, by source, so "post more" becomes "post more of that".'],
                    ['fa-wand-magic-sparkles', '<strong>The AI Coach reads the week for you</strong> and hands back one change worth making, with the number behind it.'],
                    ['fa-mobile-screen', '<strong>It looks like you.</strong> Themes, fonts and your own colours, not a template with your face on it.'],
                    ['fa-link', '<strong>One URL, forever.</strong> Change everything behind it without reprinting a bio.'],
                ],
            ],
            'stats'   => [['1', 'link in your bio'], ['0', 'code required'], ['Free', 'to start'] ],
        ],
        [
            'key'     => 'business',
            'eyebrow' => 'Businesses',
            'title'   => 'A landing page, storefront &amp; CRM in one.',
            'desc'    => 'Branded short links, QR codes for packaging &amp; print, custom domains, forms and team workspaces.',
            'icon'    => 'fa-store',
            'cta'     => 'Start my business page',
            'g1' => '#3d6bff', 'g2' => '#7c5cff',
            'lead'    => 'The awkward part of a small marketing stack is the seams: the link shortener does not know about the landing page, the form does not know about the CRM, and the QR code on the packaging cannot be changed once it is printed. Sayzio is the same object doing all four.',
            'left'    => [
                'h' => 'What you can run on it',
                'i' => [
                    ['fa-check', '<strong>Branded short links</strong> on your own domain, so a campaign URL reads as yours from the first character.'],
                    ['fa-check', '<strong>Dynamic QR codes.</strong> Print once; change where it points whenever the offer does.'],
                    ['fa-check', '<strong>Forms that collect leads</strong> straight into your contacts, with email, SMS or webhook on every submission.'],
                    ['fa-check', '<strong>Team workspaces</strong>, so the intern who made the link is not the only person who can edit it.'],
                ],
            ],
            'right'   => [
                'h' => 'Why it holds up',
                'i' => [
                    ['fa-shield-halved', '<strong>Nothing to host.</strong> We serve the domain end to end and renew the certificate.'],
                    ['fa-chart-simple', '<strong>Reporting that separates.</strong> A different alias per channel, one page behind all of them.'],
                    ['fa-users', '<strong>Roles, not shared passwords.</strong> Add and remove people without rotating a login.'],
                    ['fa-boxes-stacked', '<strong>It scales down too.</strong> One product, one page, one QR is a perfectly good use of it.'],
                ],
            ],
            'stats'   => [['1', 'DNS record'], ['&#8734;', 'aliases per page'], ['5-30m', 'to go live']],
        ],
        [
            'key'     => 'networking',
            'eyebrow' => 'Networking pros',
            'title'   => 'Your digital business card, and then some.',
            'desc'    => 'Tap-to-share NFC tags, dynamic QR codes, instant DMs and a live visitor map of who&rsquo;s engaging.',
            'icon'    => 'fa-id-badge',
            'cta'     => 'Make my smart card',
            'g1' => '#1bd4d9', 'g2' => '#3d6bff',
            'lead'    => 'A paper card ends the moment it goes in a pocket. You do not know whether it was kept, and the other person has to type your details in by hand before anything happens. A tap does the typing, and tells you it happened.',
            'left'    => [
                'h' => 'How you hand it over',
                'i' => [
                    ['fa-check', '<strong>Tap an NFC tag</strong> and your card opens on their phone. No app on either side.'],
                    ['fa-check', '<strong>Or show the QR</strong>, which works on any camera and can be reprinted without becoming wrong.'],
                    ['fa-check', '<strong>They save you as a contact</strong> in one tap, as a proper vCard with your photo, not a screenshot.'],
                    ['fa-check', '<strong>Message you from the card</strong>, so a follow-up does not depend on either of you remembering.'],
                ],
            ],
            'right'   => [
                'h' => 'What you get back',
                'i' => [
                    ['fa-map-location-dot', '<strong>You see who engaged</strong>, and roughly where, so the conference stand is worth measuring.'],
                    ['fa-pen-to-square', '<strong>Change jobs without reprinting.</strong> The card updates; the tag stays the same.'],
                    ['fa-clock', '<strong>It is current by definition.</strong> An old card in someone\'s wallet still opens today\'s details.'],
                    ['fa-share-nodes', '<strong>One card, every channel.</strong> Email, phone, socials and calendar behind a single tap.'],
                ],
            ],
            'stats'   => [['1', 'tap to share'], ['0', 'apps to install'], ['Live', 'visitor map']],
        ],
    ];
@endphp
@include('home.partials.audience-visual-style')
<section id="audience" class="sec-rule py-20 lg:py-28 relative overflow-hidden" aria-labelledby="audience-h">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">Built for you</div>
            <h2 id="audience-h" class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-4">
                Built for <span class="grad-text">creators, brands &amp; networking pros.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">Pick the one that fits you. The same AI-powered, all-in-one toolkit powers all three.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-5 card-row">
            @foreach($__audiences as $i => $a)
                {{-- One card per section carries the CTA ribbon, cut down to a
                     corner wedge. The middle one here, so the accent lands in
                     the centre of the row rather than at one end. --}}
                <article class="audience-card reveal rd-{{ $i + 1 }} glass rounded-3xl p-7 tilt relative overflow-hidden flex flex-col{{ $i === 1 ? ' card-ribbon-host' : '' }}">
                    @if($i === 1)
                        @include('home.partials.card-ribbon', ['shape' => 'steps', 'variant' => 'teal'])
                    @endif
                    {{-- The 192px blurred colour disc that used to sit in this
                         corner is gone. It had already been switched off in
                         `surfaces.blade.php` (`display:none !important`), so it
                         was markup and an inline hex producing nothing. --}}
                    <div class="card-ico aud-icon relative w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style="animation-delay:{{ $i * 0.4 }}s;">
                        <i class="fas {{ $a['icon'] }} text-xl text-white" style="animation-delay:{{ $i * 0.5 }}s;"></i>
                    </div>
                    {{-- The eyebrow follows the card's own gradient rather than a
                         third hue of its own, so the chip above it and the word
                         under it agree. --}}
                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-2" style="color: var(--g1, #3d6bff);">{{ $a['eyebrow'] }}</div>
                    <h3 class="relative text-xl font-bold mb-3 leading-snug">{!! $a['title'] !!}</h3>
                    <p class="relative text-sm text-gray-400 leading-relaxed mb-6 flex-1">{!! $a['desc'] !!}</p>
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','audience'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="relative btn-bounce inline-flex items-center justify-center gap-2 px-5 py-2.5 grad-bar text-white rounded-full text-sm font-bold self-start">
                        {{ $a['cta'] }} <i class="aud-arrow fas fa-arrow-right text-[10px]" style="animation-delay:{{ $i * 0.3 }}s;"></i>
                    </button>

                    {{-- Opening one of these used to show the card again, larger.
                         Same eyebrow, same headline, same one sentence -- the
                         modal was an animation rather than an answer, which is
                         what the generic clone path gives a card whose content
                         is already only three lines. --}}
                    <template class="xc-detail">
                        <div class="xcd" style="--g1: {{ $a['g1'] }}; --g2: {{ $a['g2'] }};">
                            <div class="xcd-main">
                                <span class="xcd-ico" aria-hidden="true"><i class="fas {{ $a['icon'] }}"></i></span>
                                <h3 class="xcd-title">{!! $a['title'] !!}</h3>
                                <p class="xcd-lead">{!! $a['lead'] !!}</p>

                                <div class="xm-cols">
                                    @foreach([$a['left'], $a['right']] as $__col)
                                        <div>
                                            <h4 class="xm-h">{{ $__col['h'] }}</h4>
                                            <ul class="xm-list">
                                                @foreach($__col['i'] as [$__ic, $__tx])
                                                    <li><i class="fas {{ $__ic }}"></i><span>{!! $__tx !!}</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>

                                <dl class="xcd-stats">
                                    @foreach($a['stats'] as [$__v, $__l])
                                        <div><dt>{!! $__v !!}</dt><dd>{{ $__l }}</dd></div>
                                    @endforeach
                                </dl>

                                <button type="button" class="xcd-cta"
                                        onclick="window.trackMarketingEvent&&window.trackMarketingEvent('home_audience_modal','{{ $a['key'] }}');window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))">
                                    {{ $a['cta'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>

                            <div class="xcd-visual" style="--g1: {{ $a['g1'] }}; --g2: {{ $a['g2'] }};">
                                @include('home.partials.audience-visual', ['key' => $a['key']])
                            </div>
                        </div>
                    </template>
                </article>
            @endforeach
        </div>
    </div>
</section>
