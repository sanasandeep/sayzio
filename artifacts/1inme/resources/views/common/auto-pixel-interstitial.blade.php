@php
    /**
     * Auto-pixel interstitial. Loads the workspace's configured Meta /
     * TikTok / Google Ads pixel scripts, fires PageView + a custom
     * `LinkClick` event with the link slug as a parameter, then
     * window.location.replace()s to the destination. Meta-refresh acts
     * as a 1.5 s safety net for visitors with JS disabled or a slow
     * network. Page must stay under ~5 KB on the wire.
     */
    $pixels       = $pixels       ?? [];
    $providers    = $providers    ?? [];
    $destination  = $destination  ?? '/';
    $alias        = $alias        ?? '';
    $workspaceName = $workspaceName ?? '';
    $beaconUrl    = $beaconUrl    ?? '';
@endphp<!doctype html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
<meta charset="utf-8">
<meta http-equiv="refresh" content="1.5;url={{ $destination }}">
<meta name="referrer" content="no-referrer-when-downgrade">
<title>Loading…</title>
<style>html,body{margin:0;height:100%;background:#0b0b0c;color:#eaeaea;font:14px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif}.w{display:flex;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:8px}.s{width:22px;height:22px;border:2px solid #444;border-top-color:#eaeaea;border-radius:50%;animation:r .8s linear infinite}@keyframes r{to{transform:rotate(360deg)}}.m{opacity:.7;font-size:12px}a{color:#9ad}</style>
</head>
<body>
<div class="w">
  <div class="s" aria-hidden="true"></div>
  <div>Loading…</div>
  <div class="m">
    Tracking by {{ $workspaceName ?: 'this creator' }} -
    <noscript>continue: <a href="{{ $destination }}">go to destination</a></noscript>
  </div>
</div>
@if(!empty($pixels['meta_id']))
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', @json($pixels['meta_id']));
fbq('track', 'PageView');
fbq('trackCustom', 'LinkClick', {alias: @json($alias)});
</script>
@endif
@if(!empty($pixels['tiktok_id']))
<script>
!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var i=document.createElement("script");i.type="text/javascript";i.async=!0;i.src=r+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(i,a)};
ttq.load(@json($pixels['tiktok_id']));ttq.page();ttq.track('ClickButton',{contents:[{content_id:@json($alias)}]});}(window,document,'ttq');
</script>
@endif
@if(!empty($pixels['google_id']))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($pixels['google_id']) }}"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',@json($pixels['google_id']));
@if(!empty($pixels['google_label']))
gtag('event','conversion',{send_to:@json($pixels['google_id'].'/'.$pixels['google_label']),transaction_id:@json($alias)});
@endif
</script>
@endif
<script>
(function(){
  var dest = @json($destination);
  var providers = @json($providers);
  try {
    if (providers && providers.length && @json($beaconUrl)) {
      var data = JSON.stringify({providers: providers});
      var url = @json($beaconUrl);
      if (navigator.sendBeacon) {
        try { navigator.sendBeacon(url, new Blob([data], {type: 'application/json'})); }
        catch(e) { fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:data, keepalive:true}); }
      } else {
        fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:data, keepalive:true});
      }
    }
  } catch(e) {}
  // Give pixel scripts a brief moment to fire before navigating away.
  setTimeout(function(){ window.location.replace(dest); }, 350);
})();
</script>
</body>
</html>
