{{--
    GA4 + Meta Pixel snippets for marketing pages only.
    Each block renders only when its admin-configured ID is present.
--}}
@php
    $__ga4 = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_ga4_id', ''));
    $__pixel = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_meta_pixel_id', ''));
    $__validId = fn ($id) => $id !== '' && preg_match('/^[A-Za-z0-9\-_]+$/', $id) === 1;
@endphp
@if($__validId($__ga4))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $__ga4 }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($__ga4), { anonymize_ip: true });
    </script>
@endif
@if($__pixel !== '' && preg_match('/^[0-9]+$/', $__pixel))
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @json($__pixel));
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none" alt=""
             src="https://www.facebook.com/tr?id={{ $__pixel }}&ev=PageView&noscript=1"/>
    </noscript>
@endif
