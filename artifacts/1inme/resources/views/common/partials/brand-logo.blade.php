{{--
    Brand logo partial — renders the Sayzio wordmark image with light/dark
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
    $alt     = $alt     ?? config('app.name', 'Sayzio');

    // Inline max-height guard: prevents the logo from ballooning to its natural
    // image size during a slow/cold page load before the Vite CSS bundle applies
    // the Tailwind height utility.  max-height (not height) is used so the
    // Tailwind class still controls the exact rendered size once CSS loads.
    $__heightPx = [
        'h-4'  => '16px', 'h-5'  => '20px', 'h-6'  => '24px',
        'h-7'  => '28px', 'h-8'  => '32px', 'h-9'  => '36px',
        'h-10' => '40px', 'h-11' => '44px', 'h-12' => '48px',
        'h-14' => '56px', 'h-16' => '64px',
    ];
    $__maxH = $__heightPx[$height] ?? '40px';
    $__sizeStyle = 'max-height:' . $__maxH . ';width:auto;';

    // Host-aware: on a non-primary global domain these resolve to that
    // domain's own logos; everywhere else they are the platform logos.
    $__brand = \App\Modules\Common\Support\DomainBranding::logos();

    if ($variant === 'icon') {
        $url = $__brand['icon'];
        $tag = '<img src="' . e($url) . '" alt="' . e($alt) . '" class="' . e($height) . ' w-auto rounded-lg object-cover" style="' . $__sizeStyle . '">';
        echo $href ? '<a href="' . e($href) . '" class="inline-flex items-center">' . $tag . '</a>' : $tag;
    } else {
        $lightUrl = $__brand['logo_light'];
        $darkUrl  = $__brand['logo_dark'];
@endphp
@if($href)<a href="{{ $href }}" class="inline-flex items-center">@endif
    <img src="{{ $lightUrl }}" alt="{{ $alt }}" class="brand-logo brand-logo--light {{ $height }} w-auto" style="{{ $__sizeStyle }}">
    <img src="{{ $darkUrl }}"  alt="{{ $alt }}" class="brand-logo brand-logo--dark {{ $height }} w-auto" style="{{ $__sizeStyle }}">
@if($href)</a>@endif
@php } @endphp
