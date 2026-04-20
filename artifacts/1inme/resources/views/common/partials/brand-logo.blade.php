{{--
    Brand logo partial — renders the 1INME wordmark image with light/dark
    variants and an optional fallback wordmark when no image is configured.

    Variables:
        $variant   string  'wordmark' (default) or 'icon'
        $height    string  Tailwind height class for the rendered image (e.g. 'h-7')
        $href      string  Optional anchor; if provided wraps the logo in <a>
        $alt       string  Override alt text
--}}
@php
    $variant = $variant ?? 'wordmark';
    $height  = $height  ?? 'h-7';
    $href    = $href    ?? null;
    $alt     = $alt     ?? config('app.name', '1INME');

    if ($variant === 'icon') {
        $url = \App\Modules\Admin\Models\AppSetting::get(
            'brand_icon_url',
            '/branding/icon.jpg'
        );
        $tag = '<img src="' . e($url) . '" alt="' . e($alt) . '" class="' . e($height) . ' w-auto rounded-lg object-cover">';
        echo $href ? '<a href="' . e($href) . '" class="inline-flex items-center">' . $tag . '</a>' : $tag;
    } else {
        $lightUrl = \App\Modules\Admin\Models\AppSetting::get(
            'brand_logo_light_url',
            '/branding/logo-light.png'
        );
        $darkUrl = \App\Modules\Admin\Models\AppSetting::get(
            'brand_logo_dark_url',
            '/branding/logo-dark.png'
        );
@endphp
@if($href)<a href="{{ $href }}" class="inline-flex items-center">@endif
    <img src="{{ $lightUrl }}" alt="{{ $alt }}" class="brand-logo brand-logo--light {{ $height }} w-auto">
    <img src="{{ $darkUrl }}"  alt="{{ $alt }}" class="brand-logo brand-logo--dark {{ $height }} w-auto">
@if($href)</a>@endif
@php } @endphp
