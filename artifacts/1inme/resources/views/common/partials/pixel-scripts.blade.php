@php
    // When the cookie-consent system is gating non-essential scripts on
    // biolinks, render each pixel as a plain text/plain block tagged with
    // the consent category it belongs to. The consent JS upgrades the
    // tag back to text/javascript only after the visitor opts in.
    // Google Analytics counts as 'analytics'; everything else is treated
    // as a marketing pixel.
    $__ccCfg = \App\Modules\Common\Support\CookieConsentConfig::get();
    $__ccGate = !empty($__ccCfg['enabled'])
        && !empty($__ccCfg['scope_biolink'])
        && !empty($__ccCfg['block_until_consent']);
    $__catFor = function($type) {
        return $type === 'google_analytics' ? 'analytics' : 'marketing';
    };
    $__attrs = function($type) use ($__ccGate, $__catFor) {
        if (!$__ccGate) return '';
        return ' type="text/plain" data-consent-category="' . e($__catFor($type)) . '"';
    };
@endphp
@if(isset($link) && $link->pixels->count())
@foreach($link->pixels as $pixel)
    @if($pixel->type === 'facebook')
    <script{!! $__attrs($pixel->type) !!}>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $pixel->pixel_id }}');fbq('track','PageView');</script>
    @if(!$__ccGate)<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $pixel->pixel_id }}&ev=PageView&noscript=1"/></noscript>@endif
    @elseif($pixel->type === 'google_analytics')
    <script{!! $__attrs($pixel->type) !!} async src="https://www.googletagmanager.com/gtag/js?id={{ $pixel->pixel_id }}"></script>
    <script{!! $__attrs($pixel->type) !!}>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $pixel->pixel_id }}');</script>
    @elseif($pixel->type === 'google_tag_manager')
    <script{!! $__attrs($pixel->type) !!}>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $pixel->pixel_id }}');</script>
    @elseif($pixel->type === 'linkedin')
    <script{!! $__attrs($pixel->type) !!} type="text/javascript">_linkedin_partner_id="{{ $pixel->pixel_id }}";window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(_linkedin_partner_id);</script>
    <script{!! $__attrs($pixel->type) !!} type="text/javascript">(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName("script")[0];var b=document.createElement("script");b.type="text/javascript";b.async=true;b.src="https://snap.licdn.com/li.lms-analytics/insight.min.js";s.parentNode.insertBefore(b,s);})(window.lintrk);</script>
    @elseif($pixel->type === 'twitter')
    <script{!! $__attrs($pixel->type) !!}>!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');twq('config','{{ $pixel->pixel_id }}');</script>
    @elseif($pixel->type === 'pinterest')
    <script{!! $__attrs($pixel->type) !!}>!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version="3.0";var t=document.createElement("script");t.async=!0,t.src=e;var r=document.getElementsByTagName("script")[0];r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");pintrk('load','{{ $pixel->pixel_id }}');pintrk('page');</script>
    @elseif($pixel->type === 'tiktok')
    <script{!! $__attrs($pixel->type) !!}>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load('{{ $pixel->pixel_id }}');ttq.page();}(window,document,'ttq');</script>
    @elseif($pixel->type === 'snapchat')
    <script{!! $__attrs($pixel->type) !!} type='text/javascript'>(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init','{{ $pixel->pixel_id }}',{});snaptr('track','PAGE_VIEW');</script>
    @elseif($pixel->type === 'quora')
    <script{!! $__attrs($pixel->type) !!}>!function(q,e,v,n,t,s){if(q.qp)return;n=q.qp=function(){n.qp?n.qp.apply(n,arguments):n.queue.push(arguments)};n.queue=[];t=document.createElement(e);t.async=!0;t.src=v;s=document.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,'script','https://a.quora.com/qevents.js');qp('init','{{ $pixel->pixel_id }}');qp('track','ViewContent');</script>
    @elseif($pixel->type === 'custom')
    <!-- Custom pixel: {{ e($pixel->name) }} -->
    <script{!! $__attrs($pixel->type) !!}>
    (function() {
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.sandbox = 'allow-scripts';
        iframe.srcdoc = @json($pixel->pixel_id);
        document.body.appendChild(iframe);
    })();
    </script>
    @endif
@endforeach
@endif
