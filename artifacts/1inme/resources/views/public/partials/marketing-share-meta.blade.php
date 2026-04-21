{{--
    Open Graph + Twitter Card meta tags for marketing pages.
    Inputs (any of):
      $shareTitle, $shareDescription, $shareImage, $shareUrl, $shareType
    Falls back to $page->title/meta_description, AppSetting default share image,
    canonical URL, and 'website' type.
--}}
@php
    $__appName = config('app.name', '1INME');
    $__shareTitle = $shareTitle
        ?? ($page->title ?? null)
        ?? $__appName;
    $__shareDescription = $shareDescription
        ?? ($page->meta_description ?? null)
        ?? '';
    $__defaultShareImage = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_default_share_image', ''));
    $__shareImage = trim((string) ($shareImage ?? ''));
    if ($__shareImage === '' && isset($page) && is_array($page->extra ?? null)) {
        $__shareImage = trim((string) ($page->extra['share_image'] ?? ''));
    }
    if ($__shareImage === '') {
        $__shareImage = $__defaultShareImage;
    }
    $__shareUrl = $shareUrl ?? request()->url();
    $__shareType = $shareType ?? 'website';
@endphp
<link rel="canonical" href="{{ $__shareUrl }}">
<meta property="og:site_name" content="{{ $__appName }}">
<meta property="og:type" content="{{ $__shareType }}">
<meta property="og:title" content="{{ $__shareTitle }}">
<meta property="og:description" content="{{ $__shareDescription }}">
<meta property="og:url" content="{{ $__shareUrl }}">
@if($__shareImage !== '')
    <meta property="og:image" content="{{ $__shareImage }}">
@endif
<meta name="twitter:card" content="{{ $__shareImage !== '' ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $__shareTitle }}">
<meta name="twitter:description" content="{{ $__shareDescription }}">
@if($__shareImage !== '')
    <meta name="twitter:image" content="{{ $__shareImage }}">
@endif
