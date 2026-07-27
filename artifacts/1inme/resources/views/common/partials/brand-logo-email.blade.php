{{--
    Email brand logo.

    Email clients can't toggle the CSS light/dark variants the in-page
    brand-logo partial relies on, so this renders a SINGLE admin-configured
    logo as an absolute-URL <img> — the light-background wordmark, since these
    transactional emails use light backgrounds. Falls back to the app-name
    text wordmark when no logo is configured (or the path is empty).

    Variables:
        $height  int     Rendered image height in px (default 28)
        $alt     string  Override alt text
--}}
@php
    $__brand  = \App\Modules\Common\Support\DomainBranding::logos();
    $__height = $height ?? 28;
    $__alt    = $alt ?? config('app.name', 'Sayzio');

    $__raw = trim((string) ($__brand['logo_light'] ?? ''));
    $__src = $__raw === ''
        ? ''
        : \App\Modules\Common\Support\PlatformHosts::outboundUrl(
            preg_match('#^https?://#i', $__raw) ? $__raw : url($__raw)
        );
@endphp
@if($__src !== '')
<img src="{{ $__src }}" alt="{{ $__alt }}" height="{{ $__height }}" style="height:{{ $__height }}px; width:auto; display:inline-block; border:0; outline:none; text-decoration:none;">
@else
<span style="display:inline-block; font-size:22px; font-weight:700; color:#2563eb; letter-spacing:0.5px;">{{ $__alt }}</span>
@endif
