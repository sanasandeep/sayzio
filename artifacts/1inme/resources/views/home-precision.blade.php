<!DOCTYPE html>
<html lang="en" class="{{ (($_COOKIE['1inme_theme'] ?? null) === 'light') ? 'light-mode' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Safari uses theme-color to tint the browser toolbar. Always dark so the
         tab bar matches the brand regardless of the site's light/dark mode. --}}
    <meta name="theme-color" content="#0a0a14">
    @php
        $__seo = \App\Modules\Common\Support\MarketingSeo::resolveForView(['seoKey' => 'home']);
        // Short keyword-focused home designs carry their own SEO cluster
        // (title/description/keywords) so picking a design also retargets
        // search. Classic returns null and keeps the generic home SEO.
        if ($__designSeo = \App\Modules\Common\Controllers\HomeController::activeDesignSeo()) {
            $__seo = array_merge($__seo, $__designSeo);
        }
    @endphp
    <title>{{ \App\Modules\Common\Support\MarketingSeo::documentTitle($__seo['title'], ' — ') }}</title>
    <meta name="description" content="{{ $__seo['description'] }}">
    @if(($__seo['keywords'] ?? '') !== '')
        <meta name="keywords" content="{{ $__seo['keywords'] }}">
    @endif
    @php
        $__schema = \App\Modules\Common\Support\MarketingSchema::forView([
            'seoKey' => 'home',
            'title'  => $__seo['title'] ?? null,
            'url'    => \App\Modules\Common\Support\PlatformHosts::canonicalUrl(),
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($__schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @include('public.partials.marketing-share-meta')
    @include('public.partials.marketing-tracking')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('common.partials.default-icons')
    @include('common.partials.fontawesome')
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketing-anim.css') }}?v=18">
    @vite(['resources/js/marketing-anim.js'])
    <script>
        // Fire-and-forget marketing-CTA tracking shared by every home-page
        // "Sign up free" button so we can see which placement converts.
        window.trackMarketingEvent = function (source, target) {
            try {
                var url = '{{ route('marketing-events.track') }}';
                var data = new FormData();
                data.append('source', source);
                data.append('target', target);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(url, data);
                } else {
                    fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
                }
            } catch (e) { /* fire-and-forget */ }
        };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
/* ==========================================================
   Precision: a full-page marketing home design.
   Scoped under .sz so nothing here can reach the shared
   marketing CSS, Tailwind utilities or the admin surfaces.
   Light is the base; html.light-mode is this site's light
   switch, so the dark palette is the default and .light-mode
   overrides it, matching every other marketing page.
   ========================================================== */
.sz{
  --sz-bg:#080B1E; --sz-bg2:#0D1130; --sz-card:#0F1435;
  --sz-rule:rgba(255,255,255,.10); --sz-rule2:rgba(255,255,255,.18);
  --sz-ink:#F2F4FF; --sz-ink2:#A7AECF; --sz-ink3:#7B82A8;
  --sz-blue:#3858F8; --sz-violet:#7040F8; --sz-sky:#70B0F8; --sz-cyan:#22C3E6; --sz-pink:#C86BF0;
  --sz-indigo:#6C7BFF; --sz-teal:#19C4C4; --sz-emerald:#28D69A; --sz-amber:#F0A61E;
  --sz-coral:#FF7A57; --sz-rose:#FF5C86; --sz-plum:#B15CFF; --sz-lime:#E5FF6B;
  --sz-pad:clamp(18px,4vw,44px); --sz-col:1180px;
  --sz-shadow:0 26px 60px -34px rgba(0,0,0,.75);
  color:var(--sz-ink); background:var(--sz-bg);
  font-family:'Instrument Sans','Space Grotesk',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  font-size:16px; line-height:1.55;
}
html.light-mode .sz{
  --sz-bg:#FFFFFF; --sz-bg2:#F7F8FC; --sz-card:#FFFFFF;
  --sz-rule:#E6E8F2; --sz-rule2:#D2D6E6;
  --sz-ink:#0B1033; --sz-ink2:#4E5680; --sz-ink3:#8B91B0;
  --sz-indigo:#4F46E5; --sz-teal:#0EA5A5; --sz-emerald:#10B981; --sz-amber:#E08A00;
  --sz-coral:#F1603C; --sz-rose:#E23D6E; --sz-plum:#9333EA;
  --sz-shadow:0 26px 60px -34px rgba(11,16,51,.28);
}
.sz *{box-sizing:border-box}
.sz a{color:inherit;text-decoration:none}
.sz h1,.sz h2,.sz h3,.sz h4,.sz p,.sz figure,.sz blockquote,.sz dl,.sz dd{margin:0}
.sz .col{max-width:var(--sz-col);margin:0 auto;border-left:1px solid var(--sz-rule);border-right:1px solid var(--sz-rule);position:relative}
.sz .pad{padding-inline:var(--sz-pad)}
.sz .sec{padding-block:clamp(52px,6vw,90px)}
.sz .hr{border-top:1px solid var(--sz-rule);position:relative}
.sz .hr::before,.sz .hr::after{content:"";position:absolute;top:-4px;width:9px;height:9px;border:1px solid var(--sz-rule2);border-radius:2px;background:var(--sz-bg)}
.sz .hr::before{left:-5px} .sz .hr::after{right:-5px}
.sz .h1{font-size:clamp(34px,4.2vw,53px);letter-spacing:-.035em;font-weight:700;line-height:1.06;text-wrap:balance}
.sz .h2{font-size:clamp(26px,3.1vw,40px);letter-spacing:-.032em;font-weight:700;line-height:1.1;text-wrap:balance}
.sz .lead{font-size:clamp(17px,1.4vw,19.5px);color:var(--sz-ink2);max-width:52ch;line-height:1.5}
.sz .tnum{font-variant-numeric:tabular-nums}
.sz .btn{display:inline-flex;align-items:center;gap:8px;font-size:15.5px;font-weight:600;padding:12px 22px;border-radius:11px;background:var(--sz-blue);color:#fff;border:1px solid transparent;transition:transform .16s ease,filter .16s ease}
.sz .btn:hover{transform:translateY(-1px);filter:brightness(1.06)}
.sz .btn.ghost{background:transparent;color:var(--sz-ink);border-color:var(--sz-rule2)}
.sz .arrow{font-weight:600;display:inline-flex;align-items:center;gap:7px;color:var(--sz-blue)}
.sz .arrow s{text-decoration:none;transition:transform .18s ease}
.sz .arrow:hover s{transform:translateX(3px)}
.sz .eyeb{grid-column:1/-1;display:flex;align-items:center;gap:9px;font-size:11.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--k,var(--sz-indigo));margin:0 0 -6px}
.sz .eyeb i{width:7px;height:7px;border-radius:2px;background:currentColor;flex:none}
.sz .eyeb::after{content:"";flex:1;height:1px;background:var(--sz-rule);max-width:120px}
.sz .sec-head{display:grid;gap:11px;margin-bottom:clamp(24px,2.6vw,36px)}
@media (min-width:900px){.sz .sec-head{grid-template-columns:1.12fr .88fr;align-items:end;column-gap:44px;row-gap:11px}}
.sz .sec-head h2{position:relative;padding-bottom:14px}
.sz .sec-head h2::after{content:"";position:absolute;left:0;bottom:0;width:56px;height:3px;border-radius:3px;background:var(--k,var(--sz-blue))}
/* hero */
.sz .hero{position:relative;overflow:hidden;padding-block:clamp(46px,6vw,86px) clamp(40px,5vw,72px)}
.sz .hero .wash{position:absolute;inset:-30% -20% auto auto;width:760px;height:760px;border-radius:50%;pointer-events:none;
  background:radial-gradient(circle,color-mix(in srgb,var(--sz-violet) 22%,transparent),transparent 62%)}
.sz .hero-in{position:relative;z-index:2;max-width:min(600px,68%)}
@media (max-width:900px){.sz .hero-in{max-width:100%}}
@media (min-width:1000px){.sz .hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.92fr);gap:40px;align-items:center}.sz .hero-in{max-width:none}}
.sz .kicker{font-size:11.5px;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:var(--sz-ink2);margin-bottom:20px;display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.sz .kicker s{width:5px;height:5px;border-radius:50%;text-decoration:none;background:var(--sz-rule2)}
.sz .kicker s:nth-of-type(1){background:var(--sz-indigo)}.sz .kicker s:nth-of-type(2){background:var(--sz-teal)}
.sz .kicker s:nth-of-type(3){background:var(--sz-amber)}.sz .kicker s:nth-of-type(4){background:var(--sz-plum)}
.sz .hero h1 em{display:block;font-style:normal;color:var(--sz-ink2);font-weight:500;font-size:.485em;line-height:1.28;letter-spacing:-.018em;margin-top:16px;max-width:26ch}
.sz .hero .lead{margin-top:20px}
.sz .row{display:flex;gap:11px;flex-wrap:wrap;margin-top:28px}
.sz .trust{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:22px;font-size:13px;color:var(--sz-ink3)}
.sz .trust span{display:inline-flex;align-items:center;gap:7px}
.sz .trust svg{width:14px;height:14px;stroke:var(--k,var(--sz-blue));stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.sz .hero-art{position:relative;z-index:3;display:none;justify-self:center}
@media (min-width:1000px){.sz .hero-art{display:block}}
.sz .device{width:264px;border-radius:30px;padding:8px;background:var(--sz-card);border:1px solid var(--sz-rule2);box-shadow:var(--sz-shadow)}
.sz .device .scr{border-radius:23px;overflow:hidden;background:var(--sz-bg)}
.sz .urlchip{position:absolute;z-index:6;top:-34px;left:50%;transform:translateX(-50%);white-space:nowrap;background:var(--sz-card);border:1px solid var(--sz-rule2);border-radius:999px;padding:6px 14px;font-size:11.5px;font-weight:600;color:var(--sz-ink2);box-shadow:var(--sz-shadow)}
.sz .urlchip b{color:var(--sz-ink);font-weight:700}
.sz .tag{position:absolute;z-index:5;background:var(--sz-card);border:1px solid var(--sz-rule2);border-radius:13px;padding:10px 14px;font-size:11.5px;color:var(--sz-ink3);box-shadow:var(--sz-shadow)}
.sz .tag b{display:block;font-size:16px;font-weight:600;color:var(--sz-ink);letter-spacing:-.03em;margin-top:1px}
.sz .tag i{width:6px;height:6px;border-radius:50%;background:var(--sz-cyan);display:inline-block;margin-right:6px}
.sz .tag.t1{top:15%;left:-26%} .sz .tag.t2{bottom:14%;right:-22%}
/* numbers band */
.sz .band{display:grid;grid-template-columns:repeat(2,1fr)}
@media (min-width:820px){.sz .band{grid-template-columns:repeat(4,1fr)}}
.sz .band div{padding:22px var(--sz-pad);border-top:1px solid var(--sz-rule);border-right:1px solid var(--sz-rule);position:relative}
.sz .band div:last-child{border-right:0}
@media (max-width:819px){.sz .band div:nth-child(2n){border-right:0}}
.sz .band div::before{content:"";position:absolute;top:-1px;left:var(--sz-pad);width:34px;height:3px;border-radius:3px;background:var(--k,var(--sz-blue))}
.sz .band b{display:block;font-size:clamp(22px,2.4vw,29px);font-weight:600;letter-spacing:-.035em;margin-top:6px}
.sz .band span{display:block;font-size:13px;color:var(--sz-ink3);margin-top:6px}
/* running band of link types */
.sz .marq{position:relative;overflow:hidden;border-top:1px solid var(--sz-rule);padding-block:14px}
.sz .marq::before,.sz .marq::after{content:"";position:absolute;top:0;bottom:0;width:90px;z-index:2}
.sz .marq::before{left:0;background:linear-gradient(90deg,var(--sz-bg),transparent)}
.sz .marq::after{right:0;background:linear-gradient(270deg,var(--sz-bg),transparent)}
.sz .marq .track{display:flex;gap:10px;width:max-content;animation:szrun 46s linear infinite}
.sz .marq:hover .track{animation-play-state:paused}
.sz .marq .track span{display:inline-flex;align-items:center;gap:8px;white-space:nowrap;font-size:13.5px;font-weight:600;padding:9px 16px;border:1px solid var(--sz-rule);border-radius:999px;color:var(--sz-ink2)}
.sz .marq .track span i{width:7px;height:7px;border-radius:50%;background:var(--k,var(--sz-blue));flex:none}
@keyframes szrun{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media (prefers-reduced-motion:reduce){.sz .marq .track{animation:none}}

/* full-bleed flat colour blocks, the Linktree technique */
.sz .block{margin-inline:calc(50% - 50vw);padding-inline:calc(50vw - 50% + var(--sz-pad));padding-block:clamp(52px,6vw,88px);background:var(--blk);color:var(--blk-ink);position:relative;overflow:hidden}
.sz .blk-grid{display:grid;gap:34px;align-items:center;position:relative;z-index:2}
@media (min-width:940px){.sz .blk-grid{grid-template-columns:.95fr 1.05fr}}
.sz .block .h2,.sz .block .lead{color:var(--blk-ink)}
.sz .block .h2 em{font-style:normal;color:var(--blk-accent)}
.sz .block .h2::after{display:none}
.sz .block .lead{opacity:.86}
.sz .btn-blk{display:inline-flex;align-items:center;gap:8px;font-size:15.5px;font-weight:700;padding:13px 24px;border-radius:999px;background:var(--blk-accent);color:var(--blk);margin-top:24px}
.sz .blob{position:absolute;border-radius:50%;z-index:0;background:rgba(255,255,255,.09)}
.sz .blob.b1{width:280px;height:280px;top:-70px;right:-40px}
.sz .blob.b2{width:180px;height:180px;bottom:-60px;left:-30px}
.sz .fanstack{position:relative;height:340px}
.sz .fcard{position:absolute;width:200px;border-radius:20px;padding:14px;box-shadow:0 26px 50px -26px rgba(0,0,0,.5)}
.sz .fcard .av{width:36px;height:36px;border-radius:50%;background:#fff;display:grid;place-items:center;box-shadow:0 8px 16px -8px rgba(0,0,0,.45)}
.sz .fcard .av svg{width:20px;height:20px;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.sz .fcard .ln{height:9px;border-radius:5px;background:rgba(255,255,255,.55);margin-top:10px}
.sz .fcard .ln.s{width:62%} .sz .fcard .ln.m{width:86%}
.sz .fcard .bar{height:26px;border-radius:9px;background:rgba(255,255,255,.28);margin-top:11px}
.sz .fcard.c1{background:#E23D6E;left:0;top:96px;transform:rotate(-7deg)}
.sz .fcard.c2{background:#4F46E5;left:74px;top:52px;transform:rotate(-2deg)}
.sz .fcard.c3{background:#0EA5A5;left:148px;top:16px;transform:rotate(3deg)}
.sz .fcard.c4{background:#9333EA;left:222px;top:66px;transform:rotate(8deg)}
@media (max-width:620px){.sz .fanstack{height:290px}.sz .fcard{width:158px}.sz .fcard.c2{left:52px}.sz .fcard.c3{left:104px}.sz .fcard.c4{left:156px}}
.sz .urlpill{position:absolute;left:8px;bottom:6px;background:#fff;color:#0B1033;font-size:15px;font-weight:600;padding:11px 20px;border-radius:999px;box-shadow:0 18px 34px -18px rgba(0,0,0,.55);transform:rotate(-2deg)}
.sz .urlpill b{font-weight:800}
.sz .urlpill::before{content:"";position:absolute;left:50%;top:-124px;width:2px;height:124px;background:repeating-linear-gradient(180deg,rgba(255,255,255,.85) 0 6px,transparent 6px 12px)}
.sz .tiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;position:relative;z-index:2}
.sz .tile{border-radius:22px;padding:20px;color:#fff;position:relative;overflow:hidden;min-height:150px;display:flex;flex-direction:column;justify-content:flex-end;transition:transform .2s ease}
.sz .tile:hover{transform:translateY(-4px)}
.sz .tile::after{content:"";position:absolute;right:-38px;bottom:-38px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.10)}
.sz .tile b{font-size:clamp(26px,3.2vw,38px);font-weight:700;letter-spacing:-.04em;line-height:1;position:relative;z-index:2}
.sz .tile span{font-size:13.5px;opacity:.85;margin-top:6px;position:relative;z-index:2}
.sz .tile svg{position:absolute;top:16px;left:20px;z-index:2}
.sz .tile.wide{grid-column:span 2}
.sz .t-amber{background:#E08A00}.sz .t-indigo{background:#3E3AE0}.sz .t-plum{background:#8B2FD9}
.sz .pinboard{position:relative;z-index:2}
.sz .pinboard .shotcard{border-radius:16px;overflow:hidden;border:6px solid #fff;box-shadow:0 40px 70px -34px rgba(0,0,0,.55);transform:rotate(-1.6deg)}
.sz .sticker{position:absolute;border-radius:999px;padding:10px 16px;font-size:13.5px;font-weight:700;box-shadow:0 16px 30px -14px rgba(0,0,0,.5);z-index:3}
.sz .sticker.s1{top:-18px;left:22%;background:#E5FF6B;color:#16240B;transform:rotate(-5deg)}
.sz .sticker.s2{bottom:-16px;right:8%;background:#fff;color:#0B1033;transform:rotate(4deg)}
/* product cards, browser frame, callouts, devices */
.sz .cards{display:grid;border-top:1px solid var(--sz-rule)}
@media (min-width:860px){.sz .cards{grid-template-columns:repeat(3,1fr)}}
.sz .pc{padding:26px var(--sz-pad) 30px;border-right:1px solid var(--sz-rule);position:relative;transition:background .2s ease}
.sz .pc:last-child{border-right:0}
.sz .pc:hover{background:color-mix(in srgb,var(--k,var(--sz-indigo)) 8%,transparent)}
.sz .pc h3{font-size:19px;font-weight:600;letter-spacing:-.02em}
.sz .pc p{font-size:14.5px;color:var(--sz-ink2);margin-top:8px;max-width:36ch}
.sz .browser{border:1px solid var(--sz-rule2);border-radius:14px;overflow:hidden;box-shadow:var(--sz-shadow);background:var(--sz-card)}
.sz .browser .bar{display:flex;align-items:center;gap:7px;padding:11px 14px;background:var(--sz-bg2);border-bottom:1px solid var(--sz-rule)}
.sz .browser .bar i{width:9px;height:9px;border-radius:50%;background:var(--sz-rule2)}
.sz .browser .bar .url{margin-left:12px;font-size:12px;color:var(--sz-ink3);background:var(--sz-card);border:1px solid var(--sz-rule);border-radius:7px;padding:5px 12px}
.sz .pinned{position:relative}
.sz .pin{position:absolute;z-index:3;display:none}
@media (min-width:1100px){.sz .pin{display:block}}
.sz .pin .dot{width:13px;height:13px;border-radius:50%;background:var(--k);box-shadow:0 0 0 5px color-mix(in srgb,var(--k) 22%,transparent);position:absolute}
.sz .pin .note{position:absolute;left:26px;top:-14px;background:var(--sz-card);border:1px solid var(--sz-rule2);border-radius:9px;padding:9px 12px;font-size:12.5px;font-weight:600;white-space:nowrap;box-shadow:var(--sz-shadow)}
.sz .pin .note s{display:block;font-weight:500;color:var(--sz-ink3);text-decoration:none;font-size:11.5px;margin-top:2px}
.sz .pin.p1{top:30%;left:31%} .sz .pin.p2{top:60%;left:11%}
.sz .pin.p3{top:52%;right:3%} .sz .pin.p3 .note{left:auto;right:26px}
.sz .cap{font-size:12.5px;color:var(--sz-ink3);margin-top:16px}
.sz .cap b{color:var(--sz-ink2);white-space:nowrap}
.sz .badges{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}
.sz .badges span{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;padding:8px 14px;border:1px solid var(--sz-rule);border-radius:999px;color:var(--sz-ink2)}
.sz .badges span::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--k,var(--sz-blue))}
.sz .devices{position:relative;display:grid;place-items:center;padding-block:24px 8px}
.sz .laptop{width:min(100%,780px)}
.sz .laptop .lid{border:10px solid #0B1033;border-radius:14px;overflow:hidden;background:#0B1033;box-shadow:var(--sz-shadow)}
.sz .laptop .base{height:11px;border-radius:0 0 12px 12px;background:linear-gradient(180deg,#182047,#0B1033);margin:0 -3%}
.sz .laptop .foot{height:5px;width:62%;margin:0 auto;border-radius:0 0 18px 18px;background:rgba(11,16,51,.35);filter:blur(1px)}
.sz .steps{display:grid;gap:26px}
@media (min-width:820px){.sz .steps{grid-template-columns:repeat(3,1fr)}}
.sz .step{border-top:1px solid var(--sz-rule);padding-top:22px;position:relative}
.sz .step u{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;color:#fff;background:var(--k);text-decoration:none;position:absolute;top:-13px;left:0}
.sz .step h4{font-size:17px;font-weight:600;letter-spacing:-.02em;margin-top:14px}
.sz .step p{font-size:14.5px;color:var(--sz-ink2);margin-top:8px}
.sz .feats{display:grid;gap:0;border-top:1px solid var(--sz-rule)}
@media (min-width:820px){.sz .feats{grid-template-columns:repeat(3,1fr)}}
.sz .feat{padding:26px var(--sz-pad) 30px;border-right:1px solid var(--sz-rule);border-bottom:1px solid var(--sz-rule)}
.sz .feat:nth-child(3n){border-right:0}
.sz .feat u{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:var(--k);text-decoration:none}
.sz .feat u svg{width:19px;height:19px;stroke:#fff;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sz .feat h4{font-size:16.5px;font-weight:600;margin-top:14px;letter-spacing:-.02em}
.sz .feat p{font-size:14px;color:var(--sz-ink2);margin-top:7px}
.sz .quote{display:grid;gap:22px;padding-block:clamp(34px,4vw,58px)}
@media (min-width:900px){.sz .quote{grid-template-columns:1.25fr .75fr;align-items:center}}
.sz .quote blockquote{font-size:clamp(19px,2.1vw,26px);letter-spacing:-.028em;line-height:1.32;font-weight:500}
.sz .quote .who{display:flex;align-items:center;gap:12px;margin-top:20px}
.sz .quote .who .pic{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--sz-amber),var(--sz-rose));flex:none}
.sz .quote .who b{display:block;font-size:14.5px;font-weight:600}
.sz .quote .who span{display:block;font-size:13px;color:var(--sz-ink3)}
.sz .sample{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--sz-ink3);border:1px solid var(--sz-rule2);border-radius:999px;padding:2px 7px;margin-left:7px;vertical-align:1px}
.sz .close{position:relative;overflow:hidden;padding-block:clamp(52px,6vw,88px);margin-inline:calc(50% - 50vw);padding-inline:calc(50vw - 50% + var(--sz-pad));background:#3E3AE0;color:#fff}
.sz .close .h2,.sz .close .lead{color:#fff}
.sz .close .lead{opacity:.82}
.sz .close .btn{background:var(--sz-lime);color:#16240B}
.sz .close .arrow{color:var(--sz-lime)}
.sz .close-in{position:relative;z-index:2;display:grid;gap:22px;align-items:center}
@media (min-width:900px){.sz .close-in{grid-template-columns:1.3fr .7fr}}
.sz .faq details{border-bottom:1px solid var(--sz-rule)}
.sz .faq summary{list-style:none;cursor:pointer;padding:17px 0;font-size:15.5px;font-weight:600;display:flex;justify-content:space-between;gap:16px;letter-spacing:-.015em}
.sz .faq summary::-webkit-details-marker{display:none}
.sz .faq summary::after{content:"+";font-size:19px;font-weight:400;color:var(--sz-ink3);line-height:1}
.sz .faq details[open] summary::after{content:"\2212"}
.sz .faq p{font-size:14.5px;color:var(--sz-ink2);padding-bottom:18px;max-width:70ch}

    </style>
@include('components.marketing.shots.styles')
</head>
<body class="overflow-x-hidden">

@include('common.partials.announcement-banner', ['surface' => 'site', 'fixed' => true])
@include('public.partials.header', ['useModal' => true, 'fixed' => true])

<main class="sz" id="sz-top">
<div class="col">

  {{-- ============================ HERO ============================ --}}
  <section class="hero pad">
    <div class="wash" aria-hidden="true"></div>
    <div class="hero-in">
      <p class="kicker">Link in bio <s></s> Short links <s></s> QR codes <s></s> Forms <s></s> AI</p>
      <h1 class="h1">One address for everything you share.
        <em>Pages, menus, QR codes, forms, and an AI that answers for you.</em></h1>
      <p class="lead">Free forever, no card. Change what sits behind your link whenever you like; the link you printed, posted or gave someone keeps working.</p>
      <div class="row">
        <a class="btn" href="{{ route('user.register') }}" onclick="window.trackMarketingEvent &amp;&amp; window.trackMarketingEvent('precision_hero', 'register')">Start now</a>
        <a class="btn ghost" href="#sz-build">See what you can build</a>
      </div>
      <p class="trust">
        <span style="--k:var(--sz-emerald)"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>No card required</span>
        <span style="--k:var(--sz-teal)"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>Free plan never expires</span>
        <span style="--k:var(--sz-amber)"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>Cancel anytime</span>
      </p>
    </div>

    <div class="hero-art" aria-hidden="true">
      <span class="urlchip">sayzio.app/<b>oliveandember</b></span>
      <div class="device">
        <div class="scr">@include('components.marketing.shots.menu')</div>
      </div>
      <div class="tag t1">Clicks today<b class="tnum">1,284</b></div>
      <div class="tag t2"><i></i>Zio replies<b class="tnum">42</b></div>
    </div>
  </section>

  {{-- ==================== NUMBERS, FROM ADMIN SITE STATS ====================
       Never hard-coded here. Admin > Site stats is the single source, the same
       one the classic home page's trust band reads, so the figures on this page
       can never drift from the ones on every other page. No stats saved means
       no band, rather than a band of invented numbers. --}}
  @php
      try {
          $szStats = \App\Modules\Admin\Models\SiteStat::cachedActive()->take(4)->values();
      } catch (\Throwable $e) {
          $szStats = collect();
      }
      $szStatKeys = ['var(--sz-indigo)', 'var(--sz-emerald)', 'var(--sz-amber)', 'var(--sz-rose)'];
  @endphp
  @if($szStats->isNotEmpty())
  <div class="band">
      @foreach($szStats as $szI => $szStat)
          <div style="--k:{{ $szStatKeys[$szI % 4] }}">
              <b class="tnum">{{ $szStat->value }}{{ $szStat->suffix ?? '' }}</b>
              <span>{{ $szStat->label }}</span>
          </div>
      @endforeach
  </div>
  @endif

  {{-- ============ RUNNING BAND OF LINK TYPES ============ --}}
  <div class="marq" aria-hidden="true">
    <div class="track" id="sz-marq"></div>
  </div>

  <script>
  (function () {
      // The running band is built here rather than typed out twice: the track
      // has to hold the list back to back so the loop has no visible seam.
      var types = [
          ['Link in Bio', 'indigo'], ['Restaurant menu', 'amber'], ['QR code', 'teal'],
          ['Store menu', 'coral'], ['Zio AI chat', 'plum'], ['Contact form', 'emerald'],
          ['Short link', 'blue'], ['Booking page', 'teal'], ['Event page', 'rose'],
          ['File share', 'sky'], ['Digital card', 'plum'], ['Portfolio', 'amber'],
          ['Newsletter', 'emerald'], ['Slides', 'coral'], ['Calendar', 'indigo'],
          ['Résumé', 'blue'], ['Payment link', 'rose'], ['Feedback form', 'teal']
      ];
      var track = document.getElementById('sz-marq');
      if (!track) { return; }
      var html = types.map(function (t) {
          return '<span style="--k:var(--sz-' + t[1] + ')"><i></i>' + t[0] + '</span>';
      }).join('');
      track.innerHTML = html + html;
  })();
  </script>

</div>
</main>

<div id="home-deferred" data-src="{{ route('home.sections') }}" aria-busy="true" style="min-height:70vh">
    <div id="home-deferred-loading" style="display:flex;align-items:center;justify-content:center;padding:6rem 1rem;">
        <span style="width:28px;height:28px;border-radius:50%;border:3px solid rgba(120,140,255,.25);border-top-color:#3d6bff;animation:spinSlow 0.8s linear infinite;display:inline-block" aria-hidden="true"></span>
        <span class="sr-only">Loading more…</span>
    </div>
    <noscript>
        {{-- No JS: the fragment can't be fetched. Keep the page useful with
             direct links to the same content on always-full pages. --}}
        <div style="max-width:42rem;margin:0 auto;padding:3rem 1.5rem;text-align:center">
            <p style="margin-bottom:1rem">Explore everything Sayzio offers:</p>
            <p><a href="{{ route('site.features') }}" style="text-decoration:underline">All features</a> ·
               <a href="{{ route('site.pricing') }}" style="text-decoration:underline">Pricing</a> ·
               <a href="{{ route('site.how-it-works') }}" style="text-decoration:underline">How it works</a></p>
        </div>
    </noscript>
</div>
<script>
    (function () {
        var box = document.getElementById('home-deferred');
        if (!box) return;
        var started = false;
        function execScripts(root) {
            // Scripts inserted via innerHTML never execute — recreate each
            // one in place so section runtimes (demos, Alpine helpers) run.
            var scripts = root.querySelectorAll('script');
            Array.prototype.forEach.call(scripts, function (old) {
                var s = document.createElement('script');
                Array.prototype.forEach.call(old.attributes, function (a) { s.setAttribute(a.name, a.value); });
                s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
        }
        function load() {
            if (started) return;
            started = true;
            fetch(box.getAttribute('data-src'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            }).then(function (html) {
                box.innerHTML = html;
                box.style.minHeight = '';
                box.removeAttribute('aria-busy');
                execScripts(box);
                if (window.homeEnhance) window.homeEnhance(box);
                if (window.marketingAnimScan) window.marketingAnimScan(box);
                window.dispatchEvent(new CustomEvent('home:sections-loaded'));
                // If the visitor arrived with an in-page anchor (e.g. /#features),
                // honor it now that the target exists.
                if (location.hash.length > 1) {
                    var t = null;
                    try { t = document.querySelector(location.hash); } catch (e) {}
                    if (t) t.scrollIntoView({ block: 'start' });
                }
            }).catch(function () {
                // Fetch failed — allow a retry on the next trigger.
                started = false;
            });
        }
        // Earliest of: window load (+tiny delay so it never competes with
        // above-the-fold work), first interaction, or a failsafe timer.
        ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
            window.addEventListener(ev, load, { once: true, passive: true });
        });
        if (document.readyState === 'complete') setTimeout(load, 50);
        else window.addEventListener('load', function () { setTimeout(load, 50); });
        setTimeout(load, 3000);
    })();
</script>

{{-- ============================ FOOTER ============================ --}}
@include('public.partials.footer')

@include('common.partials.global-shortcuts')

<script>
    document.documentElement.classList.add('js');
    // Idempotent enhancement pass over `root` (defaults to the whole
    // document). Runs once at DOMContentLoaded for the initial header/hero
    // markup and AGAIN over the injected deferred sections (see the
    // #home-deferred loader). Every element is stamped via dataset flags so
    // re-running never double-observes or double-binds.
    window.homeEnhance = function (root) {
        root = root || document;
        const pick = (sel) => Array.prototype.filter.call(
            root.querySelectorAll(sel), el => !el.dataset.homeEnhanced || !el.dataset.homeEnhanced.includes(sel)
        );
        const stamp = (el, sel) => { el.dataset.homeEnhanced = (el.dataset.homeEnhanced || '') + '|' + sel; };

        const reveals = pick('.reveal');
        reveals.forEach(el => stamp(el, '.reveal'));
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -10px 0px' });
            reveals.forEach(el => observer.observe(el));
            // Safety net for elements the observer might miss — but NEVER
            // force showcase cards visible on load; they must stay gated on
            // real intersection so their entrance/alive motion only fires
            // once the grid scrolls into view.
            setTimeout(() => reveals.forEach(el => {
                if (!el.classList.contains('showcase-card')) el.classList.add('visible');
            }), 250);
        } else {
            reveals.forEach(el => el.classList.add('visible'));
        }

        // Toggle sc-alive on showcase cards so their continuous glow/blob
        // animations only run while the card is on screen and pause once it
        // scrolls out of view (saves GPU/battery on low-power devices). The
        // one-time entrance reveal stays gated on .visible above and is never
        // removed, so scrolling back in never re-triggers the entrance keyframe.
        // Skipped under prefers-reduced-motion (those animations are already
        // killed in CSS), keeping the page fully static.
        const showcaseCards = pick('.showcase-card');
        showcaseCards.forEach(el => stamp(el, '.showcase-card'));
        const scReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (showcaseCards.length && !scReducedMotion && 'IntersectionObserver' in window) {
            const scObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('sc-alive', entry.isIntersecting);
                });
            }, { threshold: 0.1 });
            showcaseCards.forEach(el => scObserver.observe(el));
        }

        // Toggle pp-in-view on pillar preview blocks so their subtle animations
        // only run while the card is on screen (and pause when scrolled away).
        const pillarPreviews = pick('.pillar-preview');
        pillarPreviews.forEach(el => stamp(el, '.pillar-preview'));
        if (pillarPreviews.length && 'IntersectionObserver' in window) {
            const ppObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('pp-in-view', entry.isIntersecting);
                });
            }, { threshold: 0.15 });
            pillarPreviews.forEach(el => ppObserver.observe(el));
        } else {
            pillarPreviews.forEach(el => el.classList.add('pp-in-view'));
        }

        // Toggle aud-in-view on audience cards so their subtle animations
        // only run while the card is on screen.
        const audCards = pick('.audience-card');
        audCards.forEach(el => stamp(el, '.audience-card'));
        if (audCards.length && 'IntersectionObserver' in window) {
            const audObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('aud-in-view', entry.isIntersecting);
                });
            }, { threshold: 0.2 });
            audCards.forEach(el => audObserver.observe(el));
        } else {
            audCards.forEach(el => el.classList.add('aud-in-view'));
        }

        pick('a[href^="#"]').forEach(anchor => {
            stamp(anchor, 'a[href^="#"]');
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href.length < 2) return;
                const target = document.querySelector(href);
                if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });

        // (Hero phone parallax is gated by IntersectionObserver in
        // resources/views/home/partials/hero.blade.php so it only runs while
        // the hero is on screen.)

        // Showcase grid ("what you can create"): cursor-following spotlight
        // per card, isolated to the hovered card. Skipped entirely under
        // prefers-reduced-motion (no cursor-driven motion) and on touch/coarse
        // pointer devices (no hover, so no listeners doing useless work).
        const showcaseFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!scReducedMotion && showcaseFinePointer) {
            showcaseCards.forEach(card => {
                card.addEventListener('pointermove', (e) => {
                    const rect = card.getBoundingClientRect();
                    card.style.setProperty('--sc-x', ((e.clientX - rect.left) / rect.width * 100) + '%');
                    card.style.setProperty('--sc-y', ((e.clientY - rect.top) / rect.height * 100) + '%');
                });
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.homeEnhance());
    } else {
        window.homeEnhance();
    }
</script>
@include('common.partials.cookie-consent', ['surface' => 'site'])
@include('common.partials.site-assistant', ['surface' => 'marketing'])
</body>
</html>
