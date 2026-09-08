{{--
    Precision design, everything below the hero. Rendered by
    HomeController::sections() and injected into the shell after first paint,
    the same contract every other home design uses.

    Two rules this file follows and should keep following:
      * No hard-coded numbers that also live somewhere else. Plans come from
        the shared pricing partial, FAQs come from the admin FAQ rows.
      * No screenshots of a real account. Every product visual is drawn.
--}}
@include('components.marketing.shots.styles')
<div class="sz">
<div class="col">

  {{-- ============ COLOUR BLOCK: SHARE IT ANYWHERE ============ --}}
  <section class="block" style="--blk:#3E3AE0;--blk-ink:#FFFFFF;--blk-accent:#E5FF6B">
    <span class="blob b1" aria-hidden="true"></span>
    <span class="blob b2" aria-hidden="true"></span>
    <div class="blk-grid">
      <div>
        <h2 class="h2">Share it <em>anywhere</em> you like.</h2>
        <p class="lead" style="margin-top:16px">Put your Sayzio address in every bio, on every poster, at the bottom of every email. One address, everywhere you show up, and you can change what it opens whenever you want.</p>
        <a class="btn-blk" href="{{ route('user.register') }}">Claim yours free</a>
      </div>
      <div class="fanstack" aria-hidden="true">
        <div class="fcard c1">
          <span class="av"><svg viewBox="0 0 24 24" stroke="#E23D6E"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".9" fill="#E23D6E" stroke="none"/></svg></span><span class="ln m"></span><span class="ln s"></span><span class="bar"></span>
        </div>
        <div class="fcard c2">
          <span class="av"><svg viewBox="0 0 24 24" stroke="#4F46E5"><rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10.5 9.5l4.5 2.5-4.5 2.5z"/></svg></span><span class="ln m"></span><span class="ln s"></span><span class="bar"></span>
        </div>
        <div class="fcard c3">
          <span class="av"><svg viewBox="0 0 24 24" stroke="#0EA5A5"><rect x="3.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.4"/><path d="M13.5 13.5h3M20.5 13.5v3M13.5 20.5h7"/></svg></span><span class="ln m"></span><span class="ln s"></span><span class="bar"></span>
        </div>
        <div class="fcard c4">
          <span class="av"><svg viewBox="0 0 24 24" stroke="#9333EA"><path d="M20 12a8 8 0 01-11.9 7L4 20l1-4.1A8 8 0 1120 12z"/><path d="M9 10.5c.5 2 2.5 4 4.5 4.5"/></svg></span><span class="ln m"></span><span class="ln s"></span><span class="bar"></span>
        </div>
        <span class="urlpill">sayzio.app/<b>oliveandember</b></span>
      </div>
    </div>
  </section>

  {{-- ============ WHAT YOU BUILD ============ --}}
  <section class="sec pad" id="sz-build">
    <div class="sec-head">
      <p class="eyeb" style="--k:var(--sz-indigo)"><i></i>What a Sayzio link can be</p>
      <h2 class="h2" style="--k:var(--sz-indigo)">One link, eighteen shapes.</h2>
      <p class="lead">A Sayzio address is not fixed to one kind of page. Point it at whatever the moment needs, and repoint it later without reprinting anything.</p>
    </div>
  </section>
  <div class="cards">
    <div class="pc" style="--k:var(--sz-indigo)">
      <h3>Link in Bio</h3>
      <p>A drag and drop page holding your products, videos, booking and socials, with a deep block library and themes.</p>
    </div>
    <div class="pc" style="--k:var(--sz-amber)">
      <h3>Menus and storefronts</h3>
      <p>Photos, categories and prices, plus table-side ordering by QR that drops orders straight into your dashboard.</p>
    </div>
    <div class="pc" style="--k:var(--sz-plum)">
      <h3>Zio, answering for you</h3>
      <p>An AI trained on your own pages that replies to visitors at two in the morning, on your page and on WhatsApp.</p>
    </div>
  </div>

  {{-- ============ THE PRODUCT ITSELF ============ --}}
  <section class="sec pad hr">
    <div class="sec-head">
      <p class="eyeb" style="--k:var(--sz-amber)"><i></i>Inside the product</p>
      <h2 class="h2" style="--k:var(--sz-amber)">Everything on one screen.</h2>
      <p class="lead">Clicks, scans, sources, hours and every link you have made, on a demo workspace so the numbers here are sample numbers.</p>
    </div>
    <div class="pinned">
      <div class="browser">
        <div class="bar"><i></i><i></i><i></i><span class="url">sayzio.app/user/dashboard</span></div>
        @include('components.marketing.shots.dashboard')
      </div>
      <div class="pin p1" style="--k:var(--sz-indigo)"><span class="dot"></span><span class="note">The four numbers that matter<s>Clicks, scans, visitors, Zio replies</s></span></div>
      <div class="pin p2" style="--k:var(--sz-amber)"><span class="dot"></span><span class="note">Seven days of clicks and scans<s>Peak day marked, scans dashed</s></span></div>
      <div class="pin p3" style="--k:var(--sz-rose)"><span class="dot"></span><span class="note">Where the clicks came from<s>By referrer, live</s></span></div>
    </div>
    <p class="cap"><b>Figure 1.</b> The dashboard, shown on a demo workspace.</p>
    <div class="badges">
      <span style="--k:var(--sz-indigo)">Dark and light</span>
      <span style="--k:var(--sz-teal)">Built for phones first</span>
      <span style="--k:var(--sz-amber)">Loads in under a second</span>
      <span style="--k:var(--sz-emerald)">No training needed</span>
    </div>
  </section>

  {{-- ============ EVERY SCREEN ============ --}}
  <section class="sec pad hr">
    <div class="sec-head">
      <p class="eyeb" style="--k:var(--sz-coral)"><i></i>Desktop, phone and print</p>
      <h2 class="h2" style="--k:var(--sz-coral)">One account, every screen.</h2>
      <p class="lead">Build on a laptop, and the page your visitor opens on a phone was designed for a phone in the first place.</p>
    </div>
    <div class="devices">
      <div class="laptop" aria-hidden="true">
        <div class="lid">@include('components.marketing.shots.dashboard')</div>
        <div class="base"></div>
        <div class="foot"></div>
      </div>
    </div>
    <p class="cap" style="margin-top:26px"><b>Figure 2.</b> The dashboard on a laptop.</p>
  </section>

  {{-- ============ THREE STEPS ============ --}}
  <section class="sec pad hr">
    <div class="sec-head">
      <p class="eyeb" style="--k:var(--sz-teal)"><i></i>Getting set up</p>
      <h2 class="h2" style="--k:var(--sz-teal)">Three steps, then it runs itself.</h2>
      <p class="lead">Claiming an address takes about a minute. Everything after that is optional.</p>
    </div>
    <div class="steps">
      <div class="step" style="--k:var(--sz-teal)"><u>1</u><h4>Claim your address</h4><p>Pick your handle. It is yours across every link type and survives every change you make behind it.</p></div>
      <div class="step" style="--k:var(--sz-indigo)"><u>2</u><h4>Point it somewhere</h4><p>Build a page, shorten a URL, generate a QR code or open a form. Change the destination later without reprinting anything.</p></div>
      <div class="step" style="--k:var(--sz-plum)"><u>3</u><h4>Let Zio answer</h4><p>Your AI reads your own content and replies to visitors while you sleep, on the page and on WhatsApp.</p></div>
    </div>
  </section>

  {{-- ============ COLOUR BLOCK: WHAT IS WORKING ============ --}}
  <section class="block" style="--blk:#EAF0E6;--blk-ink:#16240B;--blk-accent:#16240B">
    <div class="blk-grid">
      <div>
        <h2 class="h2">Know exactly <em>what is working</em>.</h2>
        <p class="lead" style="margin-top:16px">Every click, scan and reply in one place, by source, country and hour. Watch which link earns its spot and quietly retire the ones that do not.</p>
        <div class="tiles" style="margin-top:26px">
          <div class="tile t-amber">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
            <b class="tnum">3,527</b><span>clicks, all time</span>
          </div>
          <div class="tile t-indigo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 2.6 15 0 18M12 3c-2.6 2.6-2.6 15 0 18"/></svg>
            <b class="tnum">67</b><span>countries reached</span>
          </div>
          <div class="tile t-plum wide">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h3M21 14v3M14 21h7"/></svg>
            <b class="tnum">1,284</b><span>QR scans this month, counted separately from clicks</span>
          </div>
        </div>
      </div>
      <div class="pinboard">
        <span class="sticker s1">82 links, 4 folders</span>
        <div class="shotcard">@include('components.marketing.shots.links')</div>
        <span class="sticker s2">Export to CSV &#8599;</span>
      </div>
    </div>
  </section>

  {{-- ============ FEATURES ============ --}}
  <section class="sec pad" id="sz-features">
    <div class="sec-head">
      <p class="eyeb" style="--k:var(--sz-emerald)"><i></i>The full feature list</p>
      <h2 class="h2" style="--k:var(--sz-emerald)">Everything behind the link.</h2>
      <p class="lead">Eighteen link types and the systems around them. No feature hidden behind a sales call, and every limit published.</p>
    </div>
  </section>
  <div class="feats">
    <div class="feat" style="--k:var(--sz-indigo)"><u><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.5.5l3-3a5 5 0 00-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 00-7.5-.5l-3 3a5 5 0 007 7L12.2 19"/></svg></u><h4>Short links</h4><p>Branded URLs you can repoint any time, with expiry dates, click limits and passwords.</p></div>
    <div class="feat" style="--k:var(--sz-teal)"><u><svg viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.4"/><path d="M13.5 13.5h3M20.5 13.5v3M13.5 20.5h7"/></svg></u><h4>QR code studio</h4><p>Designed codes that keep working after you change where they point. Scans tracked separately from clicks.</p></div>
    <div class="feat" style="--k:var(--sz-plum)"><u><svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 01-11.9 7L4 20l1-4.1A8 8 0 1120 12z"/></svg></u><h4>Zio AI suite</h4><p>Chatbot, agent, voice and WhatsApp, all reading from the content you have already written.</p></div>
    <div class="feat" style="--k:var(--sz-amber)"><u><svg viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></svg></u><h4>Analytics</h4><p>Clicks, scans and views by source, country, device and hour, with CSV export and bot filtering.</p></div>
    <div class="feat" style="--k:var(--sz-coral)"><u><svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="3"/><path d="M7.5 9h9M7.5 13h5"/></svg></u><h4>Forms and inbox</h4><p>Dozens of field types with conditional logic, landing in one inbox alongside chat and WhatsApp.</p></div>
    <div class="feat" style="--k:var(--sz-rose)"><u><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0111 0"/><path d="M16 6.2a3 3 0 010 5.6M18 15.4a5 5 0 013 3.6"/></svg></u><h4>Teams and workspaces</h4><p>Separate workspaces per brand or client, real roles, and billing per workspace.</p></div>
  </div>

  {{-- ============ PRICING, FROM THE SHARED PARTIAL ============
       Real plans, real currency switching, real tax overlay. Never retyped
       here, so this page can never disagree with /pricing or with admin. --}}
  <section class="sec pad hr" id="sz-plans">
    <div class="sec-head">
      <p class="eyeb" style="--k:var(--sz-plum)"><i></i>Plans and pricing</p>
      <h2 class="h2" style="--k:var(--sz-plum)">Start free. Stay free.</h2>
      <p class="lead">A free forever page, and paid tiers when you outgrow it. Every limit is published before you pay.</p>
    </div>
  </section>
  @include('home.partials.pricing')

  {{-- ============ FAQ, FROM THE ADMIN FAQ ROWS ============ --}}
  @php
      try {
          $szFaqs = \App\Modules\Common\Models\FaqItem::where('page_slug', 'home')
              ->orderBy('sort_order')->get();
      } catch (\Throwable $e) {
          $szFaqs = collect();
      }
  @endphp
  @if($szFaqs->isNotEmpty())
  <section class="sec pad hr">
    <div class="sec-head">
      <p class="eyeb" style="--k:var(--sz-rose)"><i></i>Common questions</p>
      <h2 class="h2" style="--k:var(--sz-rose)">Before you pick.</h2>
      <p class="lead">Answered without a sales call.</p>
    </div>
    <div class="faq">
      @foreach($szFaqs as $szI => $szFaq)
        <details @if($szI === 0) open @endif>
          <summary>{{ $szFaq->question }}</summary>
          <p>{{ $szFaq->answer }}</p>
        </details>
      @endforeach
    </div>
  </section>
  @endif

  {{-- ============ CLOSING BANNER ============ --}}
  <section class="close pad">
    <div class="close-in">
      <div>
        <h2 class="h2">Your address is waiting.</h2>
        <p class="lead" style="margin-top:14px">Take sayzio.app/yourname before someone else does. No card, no countdown, no trial to expire.</p>
      </div>
      <div style="display:flex;gap:11px;flex-wrap:wrap">
        <a class="btn" href="{{ route('user.register') }}" onclick="window.trackMarketingEvent &amp;&amp; window.trackMarketingEvent('precision_close', 'register')">Start now</a>
        <a class="arrow" href="{{ route('site.contact') }}">Talk to us <s>&rarr;</s></a>
      </div>
    </div>
  </section>

</div>
</div>
