{{--
    An animated UI scene for one link type, shown in the expanded card.

    Not a screenshot and not an iframe: a small composition of real interface
    parts — rows, fields, bubbles, swatches — that animates once when the
    modal opens, so the panel shows what the type DOES rather than sitting
    still. Everything is CSS; there is no script and no image to load.

    Built from a handful of primitives so twenty-eight scenes stay short and look
    like one family:

      .lts-card   a surface        .lts-row    a list row
      .lts-line   a text line      .lts-chip   a pill
      .lts-btn    a button         .lts-bar    a progress/graph bar

    Motion: each element sets --d (its delay) and picks an animation class.
    Everything is off-state by default and animates to on, so a paused or
    reduced-motion render still shows the finished scene rather than a blank
    panel — see the reduced-motion block, which lands every element on its
    end state with no movement at all.

    `$ltSlug` and `$ltColor` come from the including view.
--}}
@php
    $c = $ltColor ?? '#3d6bff';

    /*
     * The ink that goes ON that accent.
     *
     * .lts-btn.lts-solid used to hardcode color:#fff over background:var(--a).
     * Measured against the 23 accents the live page actually serves, 19 of
     * them failed WCAG AA with white text -- #ffc845 at 1.54:1, #eab308 and
     * #34d399 at 1.92, #22d3ee at 1.81. White works on a deep indigo and
     * nothing else. Accent colours are data (an administrator can add a link
     * type with any colour), so the fix has to be computed, not a corrected
     * palette: pick whichever of white or near-black actually contrasts with
     * this accent, by relative luminance, per WCAG 2.1.
     *
     * Measured across those 23 accents: 19 failures become 3, and the
     * worst case goes from 1.54:1 to 4.42:1. The three that remain are
     * mid-tone indigo/violet (#3d6bff, #6366f1, #8b5cf6) where neither
     * ink reaches 4.5 -- that is the accent's own doing, not the ink's,
     * and all three clear AA-large.
     */
    $ltInk = (function (string $hex): string {
        $dark = '#0b1220';

        $norm = static function (string $h): ?string {
            $h = ltrim(trim($h), '#');
            if (strlen($h) === 3) {
                $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
            }
            return preg_match('/^[0-9a-f]{6}$/i', $h) ? strtolower($h) : null;
        };

        $lum = static function (string $h) use ($norm): float {
            $h = $norm($h) ?? 'ffffff';
            $channel = static function (int $v): float {
                $v /= 255;
                return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
            };
            return 0.2126 * $channel((int) hexdec(substr($h, 0, 2)))
                 + 0.7152 * $channel((int) hexdec(substr($h, 2, 2)))
                 + 0.0722 * $channel((int) hexdec(substr($h, 4, 2)));
        };

        $ratio = static function (float $a, float $b): float {
            return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
        };

        if ($norm($hex) === null) {
            return '#ffffff';
        }

        $l = $lum($hex);

        // Compare against the ink actually shipped, not against pure black:
        // comparing to #000 picks dark for #3d6bff and #6366f1, where white
        // is measurably the better of the two.
        return $ratio($l, $lum($dark)) > $ratio($l, $lum('ffffff')) ? $dark : '#ffffff';
    })($c);

    /*
     * Photography, from the sets the site already ships. Local on purpose —
     * an external placeholder host would put the panel's appearance at the
     * mercy of someone else's uptime, and these are already deployed.
     *
     * Only some of those files are photographs, and the difference matters:
     * hero-roles also contains flat gradient cards with the subject's name
     * printed across them, which read as a missing image once they are
     * cropped into a tile. The photographs are:
     *
     *   portraits    role_artist, role_business, role_coach, role_creator,
     *                role_influencer, role_musician, role_photographer,
     *                role_podcaster
     *   subjects     thumb_album, thumb_artwork, thumb_merch, thumb_photo,
     *                thumb_podcast, thumb_youtube
     *   scenes       marketing/app/restaurant.webp and its neighbours
     *
     * Everything else in hero-roles (food, design, travel, book, code,
     * fitness, stream, writing, course) is a gradient card, and is not used
     * here. Bare names resolve inside hero-roles; a name containing a slash
     * is taken as a path under images/.
     *
     * Used only where the TYPE is about content: a bio page has a face, a
     * menu has a room, a shop has products. The types that are about
     * mechanics rather than content — short links, QR codes, forms, file
     * downloads, text pages — get no photograph, because a decorative one
     * there would say nothing about what the type does.
     */
    $img = fn (string $n) => asset(
        'images/' . (str_contains($n, '/') ? $n : 'hero-roles/' . $n . '.webp')
    );
@endphp
<div class="lts" style="--a:{{ $c }};--a-ink:{{ $ltInk }}">
@switch($ltSlug)

@case('short-link')
    <div class="lts-card lts-rise" style="--d:.05s">
        <div class="lts-lbl">Destination</div>
        <div class="lts-line lts-w90"></div>
        <div class="lts-line lts-w70"></div>
    </div>
    <div class="lts-arrow lts-pop" style="--d:.45s"><i class="fas fa-chevron-down"></i></div>
    <div class="lts-card lts-accent lts-pop" style="--d:.6s">
        <div class="lts-url"><span class="lts-dim">1in.me/</span><strong>spring</strong></div>
    </div>
    <div class="lts-stats lts-rise" style="--d:.85s">
        <div><b class="lts-num">2,841</b><span>clicks</span></div>
        <div><b class="lts-num">38</b><span>countries</span></div>
    </div>
    @break

@case('link-in-bio')
    <img class="lts-avatar lts-pop" style="--d:.05s" src="{{ $img('role_artist-200') }}"
         alt="" width="200" height="200" loading="lazy" decoding="async">
    <div class="lts-line lts-w50 lts-mid lts-rise" style="--d:.15s"></div>
    <div class="lts-socials">
        @foreach(['fa-instagram','fa-youtube','fa-pinterest'] as $i => $ic)
            <span class="lts-soc lts-pop" style="--d:{{ .3 + $i * .08 }}s"><i class="fab {{ $ic }}"></i></span>
        @endforeach
    </div>
    @foreach(['Spring collection','Book a workshop','Studio visits'] as $i => $t)
        <div class="lts-btn lts-rise" style="--d:{{ .55 + $i * .12 }}s">{{ $t }}</div>
    @endforeach
    @break

@case('conversational')
    <div class="lts-bub lts-in lts-rise" style="--d:.1s">Hi! What brings you here?</div>
    <div class="lts-chips">
        @foreach(['Buy something','Just looking'] as $i => $t)
            <span class="lts-chip lts-pop" style="--d:{{ .5 + $i * .12 }}s">{{ $t }}</span>
        @endforeach
    </div>
    <div class="lts-bub lts-out lts-rise" style="--d:.95s">Buy something</div>
    <div class="lts-bub lts-in lts-rise" style="--d:1.25s">Here is the shop, and a code.</div>
    @break

@case('slides')
    <div class="lts-deck">
        @foreach([0,1,2] as $i)
            <div class="lts-slide lts-slide-{{ $i }}">
                <img class="lts-cover" src="{{ $img(['thumb_photo-640','thumb_album-640','thumb_artwork-640'][$i]) }}"
                     alt="" width="640" height="360" loading="lazy" decoding="async">
                <div class="lts-slide-cap">
                    <div class="lts-line lts-w60"></div>
                    <div class="lts-line lts-w80 lts-faint"></div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="lts-dots">
        @foreach([0,1,2] as $i)<span class="lts-dot lts-dot-{{ $i }}"></span>@endforeach
    </div>
    @break

@case('ai-chatbot')
    <div class="lts-bub lts-out lts-rise" style="--d:.1s">Do you ship to Ireland?</div>
    <div class="lts-typing lts-pop" style="--d:.6s"><i></i><i></i><i></i></div>
    <div class="lts-bub lts-in lts-rise" style="--d:1.3s">Yes, 3 to 5 days, tracked.</div>
    <div class="lts-lbl lts-rise" style="--d:1.6s">Answered from your own pages</div>
    @break

@case('restaurant-menu')
    {{-- The room, not the dishes: there is no food photograph in the shipped
         set, and a gradient card behind a dish name looks like an image that
         failed to load. A photograph of the place carries the same idea. --}}
    <div class="lts-banner lts-rise" style="--d:.05s">
        <img class="lts-cover" src="{{ $img('marketing/app/restaurant.webp') }}" alt=""
             width="640" height="360" loading="lazy" decoding="async">
        <span class="lts-banner-name">Olive &amp; Ember</span>
        <span class="lts-cart lts-pop" style="--d:1.1s">2</span>
    </div>
    @foreach([['Wood-fired Focaccia','7.50'],['Margherita Pizza','14.00'],['Burrata &amp; Tomato','12.00']] as $i => $d)
        <div class="lts-row lts-thin lts-rise" style="--d:{{ .25 + $i * .13 }}s">
            <span class="lts-grow">{!! $d[0] !!}</span>
            <b class="lts-price">{{ $d[1] }}</b>
        </div>
    @endforeach
    <div class="lts-btn lts-solid lts-pop" style="--d:.95s">Add to order</div>
    @break

@case('store-menu')
    <div class="lts-grid">
        @foreach([['Hoodie','thumb_merch-320'],['Print','thumb_artwork-320'],['Poster','thumb_photo-320'],['Vinyl','thumb_album-320']] as $i => $t)
            <div class="lts-tile lts-pop" style="--d:{{ .1 + $i * .1 }}s">
                <img class="lts-thumb" src="{{ $img($t[1]) }}" alt="" width="320" height="320"
                     loading="lazy" decoding="async">
                <span>{{ $t[0] }}</span>
            </div>
        @endforeach
    </div>
    <div class="lts-btn lts-solid lts-rise" style="--d:.7s">Request order</div>
    @break

@case('file-share')
    <div class="lts-card lts-rise" style="--d:.1s">
        <div class="lts-file"><i class="fas fa-file-pdf"></i><span>price-list-2026.pdf</span></div>
        <div class="lts-track"><span class="lts-fill"></span></div>
        <div class="lts-lbl lts-right">4.2 MB</div>
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:1.1s">Download</div>
    @break

@case('event')
    <div class="lts-datecard lts-pop" style="--d:.1s">
        <b>14</b><span>MAR</span>
    </div>
    <div class="lts-line lts-w70 lts-mid lts-rise" style="--d:.4s"></div>
    <div class="lts-line lts-w50 lts-mid lts-faint lts-rise" style="--d:.55s"></div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.85s">Add to calendar</div>
    @break

@case('calendar')
    <div class="lts-month">
        @foreach(range(1,21) as $d)
            <span class="lts-day {{ in_array($d, [4,9,15,18]) ? 'lts-day-on' : '' }} lts-pop"
                  style="--d:{{ .05 + $d * .015 }}s"></span>
        @endforeach
    </div>
    <div class="lts-lbl lts-rise" style="--d:.6s">Four dates this month</div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.8s">Subscribe</div>
    @break

@case('contact-card')
    <div class="lts-card lts-rise" style="--d:.05s">
        <img class="lts-avatar lts-sm" src="{{ $img('role_business-200') }}" alt=""
             width="200" height="200" loading="lazy" decoding="async">
        <div class="lts-line lts-w60"></div>
        @foreach(['fa-phone','fa-envelope','fa-globe'] as $i => $ic)
            <div class="lts-row lts-thin lts-rise" style="--d:{{ .35 + $i * .12 }}s">
                <i class="fas {{ $ic }}"></i><span class="lts-line lts-w70"></span>
            </div>
        @endforeach
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.9s">Save contact</div>
    @break

@case('resume-portfolio')
    <div class="lts-resume-head lts-rise" style="--d:.05s">
        <img class="lts-avatar lts-sm" src="{{ $img('role_photographer-200') }}" alt=""
             width="200" height="200" loading="lazy" decoding="async">
        <div class="lts-grow"><div class="lts-line lts-w60"></div></div>
    </div>
    @foreach(['Experience','Education','Skills'] as $i => $t)
        <div class="lts-sect lts-rise" style="--d:{{ .2 + $i * .15 }}s">
            <span class="lts-lbl">{{ $t }}</span>
            <div class="lts-line lts-w80"></div>
            <div class="lts-line lts-w60 lts-faint"></div>
        </div>
    @endforeach
    <div class="lts-btn lts-solid lts-pop" style="--d:.75s">Download PDF</div>
    @break

@case('bizs-profile')
@case('business-profile')
    <div class="lts-card lts-rise" style="--d:.05s">
        <div class="lts-line lts-w50"></div>
        <div class="lts-row lts-thin"><i class="fas fa-clock"></i><span>Open until 6pm</span></div>
        <div class="lts-row lts-thin"><i class="fas fa-location-dot"></i><span>12 Rua das Flores</span></div>
    </div>
    <div class="lts-grid lts-grid-3">
        @foreach(['thumb_photo-320','thumb_artwork-320','thumb_merch-320'] as $i => $t)
            <img class="lts-thumb lts-pop" style="--d:{{ .4 + $i * .1 }}s" src="{{ $img($t) }}"
                 alt="" width="320" height="320" loading="lazy" decoding="async">
        @endforeach
    </div>
    @break

@case('reviews-page')
    @foreach([[5,'role_coach-200'],[5,'role_musician-200'],[4,'role_creator-200']] as $i => $r)
        <div class="lts-review lts-rise" style="--d:{{ .1 + $i * .16 }}s">
            <div class="lts-review-head">
                <img class="lts-avatar lts-xs" src="{{ $img($r[1]) }}" alt=""
                     width="200" height="200" loading="lazy" decoding="async">
                <span class="lts-stars">
                    @for($st = 0; $st < 5; $st++)<i class="fas fa-star {{ $st < $r[0] ? '' : 'lts-star-off' }}"></i>@endfor
                </span>
            </div>
            <div class="lts-line lts-w80"></div>
        </div>
    @endforeach
    <div class="lts-score lts-pop" style="--d:.75s"><b class="lts-num">4.8</b><span>average</span></div>
    @break

@case('brand-press-kit')
    <div class="lts-swatches">
        @foreach(['#3E3AE0','#1BD4D9','#FBBF24','#F43F5E','#10B981'] as $i => $sw)
            <span class="lts-sw lts-pop" style="--d:{{ .08 + $i * .09 }}s;background:{{ $sw }}"></span>
        @endforeach
    </div>
    <div class="lts-card lts-rise" style="--d:.55s">
        <div class="lts-lbl">Typeface</div>
        <div class="lts-type">Aa</div>
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.85s">Download logos</div>
    @break

@case('paid-page')
    <div class="lts-locked">
        <img class="lts-cover lts-blur" src="{{ $img('thumb_photo-640') }}" alt=""
             width="640" height="360" loading="lazy" decoding="async">
        <span class="lts-lock lts-pop" style="--d:.5s"><i class="fas fa-lock"></i></span>
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.9s">Unlock for $9</div>
    @break

@case('qr-code')
    {{-- A 9x9 matrix: the three finder squares in their real corners, and a
         deterministic scatter for the rest. `$i % 7` drew clean vertical
         stripes, which reads as a barcode rather than a QR code. --}}
    @php
        $qrOn = function (int $r, int $c): bool {
            $finder = fn ($fr, $fc) => $r >= $fr && $r <= $fr + 2 && $c >= $fc && $c <= $fc + 2
                && ! ($r === $fr + 1 && $c === $fc + 1);
            if ($finder(0, 0) || $finder(0, 6) || $finder(6, 0)) { return true; }
            if ($r < 3 && $c < 3 || $r < 3 && $c > 5 || $r > 5 && $c < 3) { return false; }
            return (($r * 7 + $c * 3 + ($r * $c) % 5) % 3) !== 0;
        };
    @endphp
    <div class="lts-qr lts-pop" style="--d:.1s">
        @for($r = 0; $r < 9; $r++)
            @for($c = 0; $c < 9; $c++)
                <span class="lts-qp {{ $qrOn($r, $c) ? 'lts-qp-on' : '' }}"
                      style="--d:{{ .2 + ($r * 9 + $c) * .008 }}s"></span>
            @endfor
        @endfor
    </div>
    <div class="lts-lbl lts-rise" style="--d:.9s">Re-point it any time</div>
    @break

@case('forms')
    @foreach(['Your name','Email','Which session'] as $i => $t)
        <div class="lts-field lts-rise" style="--d:{{ .08 + $i * .14 }}s">
            <span class="lts-lbl">{{ $t }}</span>
            <span class="lts-input"><i class="lts-caret"></i></span>
        </div>
    @endforeach
    <div class="lts-btn lts-solid lts-pop" style="--d:.7s">Submit</div>
    <div class="lts-sent lts-pop" style="--d:1.25s"><i class="fas fa-check"></i> Saved to your inbox</div>
    @break

@case('text-page')
    @foreach([90,75,85,60] as $i => $w)
        <div class="lts-line lts-w{{ $w }} lts-rise" style="--d:{{ .08 + $i * .1 }}s"></div>
    @endforeach
    <div class="lts-btn lts-solid lts-pop" style="--d:.6s"><i class="fas fa-copy"></i> Copy</div>
    @break

@case('real-estate')
    {{-- The spec strip is what makes a listing a listing. No property
         photograph ships with the site -- hero-roles/role_realtor is a letter
         on a gradient, not a house -- so the structure carries it, the same
         call the restaurant scene makes about food. --}}
    <div class="lts-card lts-rise" style="--d:.05s">
        <div class="lts-lbl">Asking price</div>
        <b class="lts-num">&euro;285,000</b>
        <div class="lts-chips">
            @foreach(['3 bed','2 bath','94 m&sup2;'] as $i => $t)
                <span class="lts-chip lts-pop" style="--d:{{ .3 + $i * .09 }}s">{!! $t !!}</span>
            @endforeach
        </div>
    </div>
    <div class="lts-row lts-thin lts-rise" style="--d:.65s">
        <i class="fas fa-location-dot"></i><span class="lts-grow">12 Rua das Flores</span>
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.85s">Book a viewing</div>
    @break

@case('education-training')
    {{-- Lessons ticking off is the thing a course page does that a plain
         page does not, so the scene shows two done and one waiting. --}}
    @foreach([['Welcome &amp; setup', true],['Core techniques', true],['Your first project', false]] as $i => $m)
        <div class="lts-row lts-thin lts-rise" style="--d:{{ .08 + $i * .13 }}s">
            <i class="fas {{ $m[1] ? 'fa-circle-check' : 'fa-circle' }}"></i>
            <span class="lts-grow {{ $m[1] ? 'lts-dim' : '' }}">{!! $m[0] !!}</span>
        </div>
    @endforeach
    <div class="lts-track lts-rise" style="--d:.5s"><span class="lts-fill lts-fw" style="--fw:67%"></span></div>
    <div class="lts-lbl lts-rise" style="--d:.65s">2 of 3 lessons done</div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.85s">Continue course</div>
    @break

@case('communities-clubs')
    {{-- A club is its members, so this is one of the few types where a
         photograph says something: real portraits from the shipped set. --}}
    <div class="lts-stack lts-rise" style="--d:.05s">
        @foreach(['role_coach-200','role_creator-200','role_musician-200'] as $i => $m)
            <img class="lts-avatar lts-xs lts-pop" style="--d:{{ .12 + $i * .09 }}s"
                 src="{{ $img($m) }}" alt="" width="200" height="200" loading="lazy" decoding="async">
        @endforeach
        <span class="lts-more lts-pop" style="--d:.42s">+128 members</span>
    </div>
    <div class="lts-card lts-rise" style="--d:.55s">
        <div class="lts-lbl">Next meetup</div>
        <div class="lts-row lts-thin"><i class="fas fa-calendar-day"></i><span class="lts-grow">Thursday, 7pm</span></div>
        <div class="lts-row lts-thin"><i class="fas fa-location-dot"></i><span class="lts-grow">The Old Printworks</span></div>
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.95s">Join the club</div>
    @break

@case('nonprofits-campaigns')
    {{-- The bar is the page. 8,420 of 12,000 is 70%, and --fw carries that
         through to the fill, so the picture and the number agree. --}}
    <div class="lts-card lts-rise" style="--d:.05s">
        <div class="lts-lbl">Raised so far</div>
        <b class="lts-num">&euro;8,420</b>
        <div class="lts-track"><span class="lts-fill lts-fw" style="--fw:70%"></span></div>
        <div class="lts-lbl lts-right">of &euro;12,000 goal</div>
    </div>
    <div class="lts-chips">
        @foreach(['&euro;10','&euro;25','&euro;50'] as $i => $t)
            <span class="lts-chip lts-pop" style="--d:{{ .55 + $i * .1 }}s">{!! $t !!}</span>
        @endforeach
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.95s">Donate</div>
    @break

@case('contact-directory')
    {{-- A directory is people and a way to reach them, so: a search bar, and
         rows that carry the two actions rather than just naming a team. --}}
    <div class="lts-row lts-thin lts-rise" style="--d:.05s">
        <i class="fas fa-search"></i><span class="lts-line lts-w60"></span>
    </div>
    @foreach([['Billing','role_business-200'],['Support','role_coach-200'],['Press','role_creator-200']] as $i => $d)
        <div class="lts-row lts-rise" style="--d:{{ .25 + $i * .13 }}s">
            <img class="lts-avatar lts-xs" src="{{ $img($d[1]) }}" alt=""
                 width="200" height="200" loading="lazy" decoding="async">
            <span class="lts-grow">{{ $d[0] }}</span>
            <span class="lts-acts">
                <span class="lts-act"><i class="fas fa-phone"></i></span>
                <span class="lts-act"><i class="fas fa-envelope"></i></span>
            </span>
        </div>
    @endforeach
    @break

@case('salon-spa')
    {{-- Duration beside price is what separates a treatment list from a menu
         of dishes, so the middle column is minutes, not a description. --}}
    @foreach([['Cut &amp; finish','45 min','38'],['Colour','90 min','75'],['Facial','30 min','28']] as $i => $t)
        <div class="lts-row lts-thin lts-rise" style="--d:{{ .08 + $i * .13 }}s">
            <span class="lts-grow">{!! $t[0] !!}</span>
            <span class="lts-lbl">{{ $t[1] }}</span>
            <b class="lts-price">{{ $t[2] }}</b>
        </div>
    @endforeach
    <div class="lts-row lts-thin lts-rise" style="--d:.55s">
        <img class="lts-avatar lts-xs" src="{{ $img('role_business-200') }}" alt=""
             width="200" height="200" loading="lazy" decoding="async">
        <span class="lts-grow">with Marta</span>
    </div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.9s">Book</div>
    @break

@case('service-booking')
    {{-- The moment worth showing is the slot being taken, so one chip is
         already chosen and the button names it back. --}}
    <div class="lts-lbl lts-rise" style="--d:.05s">Thursday 14 March</div>
    <div class="lts-chips">
        @foreach(['09:00','10:30','12:00','14:30','16:00','17:30'] as $i => $t)
            <span class="lts-chip {{ $t === '14:30' ? 'lts-chip-on' : '' }} lts-pop"
                  style="--d:{{ .15 + $i * .07 }}s">{{ $t }}</span>
        @endforeach
    </div>
    <div class="lts-lbl lts-rise" style="--d:.65s">45 minutes &middot; video call</div>
    <div class="lts-btn lts-solid lts-pop" style="--d:.85s">Confirm 14:30</div>
    @break

@case('updates-page')
    {{-- Dated entries, newest first -- the shape of a page somebody keeps
         coming back to rather than reads once. --}}
    @foreach([['Today','New'],['2 Mar',''],['24 Feb','']] as $i => $u)
        <div class="lts-sect lts-rise" style="--d:{{ .08 + $i * .14 }}s">
            <span class="lts-lbl">{{ $u[0] }}@if($u[1])<span class="lts-chip lts-chip-on lts-tiny">{{ $u[1] }}</span>@endif</span>
            <div class="lts-line lts-w80"></div>
            <div class="lts-line lts-w60 lts-faint"></div>
        </div>
    @endforeach
    <div class="lts-btn lts-solid lts-pop" style="--d:.7s">Subscribe</div>
    @break

@default
    {{-- The safety net, for a type added to the catalogue before it has a
         scene of its own. It carries `lts-generic` so
         HomeFormatCatalogueTest can assert that nothing SHIPPED lands here:
         eight formats quietly rendered this placeholder for an unknown
         stretch, which is exactly the kind of gap a count cannot see. --}}
    <div class="lts-generic">
        <div class="lts-avatar lts-pop" style="--d:.05s"></div>
        @foreach([80,65,72] as $i => $w)
            <div class="lts-line lts-w{{ $w }} lts-mid lts-rise" style="--d:{{ .2 + $i * .12 }}s"></div>
        @endforeach
        <div class="lts-btn lts-solid lts-pop" style="--d:.6s">Open</div>
    </div>
@endswitch
</div>
