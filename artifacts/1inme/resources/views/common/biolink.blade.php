<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if($link->seo_title)
        <title>{{ $link->seo_title }}</title>
        <meta property="og:title" content="{{ $link->seo_title }}">
    @else
        <title>{{ $link->title ?: '1INME Bio Link' }}</title>
    @endif
    @if($link->seo_description)
        <meta name="description" content="{{ $link->seo_description }}">
        <meta property="og:description" content="{{ $link->seo_description }}">
    @endif
    @if($link->seo_image)
        <meta property="og:image" content="{{ $link->seo_image }}">
    @endif
    @if($link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">
        <div class="mb-8">
            <div class="w-20 h-20 bg-white/20 backdrop-blur rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl font-bold text-white">{{ strtoupper(substr($link->title ?: 'B', 0, 1)) }}</span>
            </div>
            <h1 class="text-2xl font-bold text-white">{{ $link->title ?: 'Bio Link' }}</h1>
            @if($link->seo_description)
                <p class="text-white/80 text-sm mt-2">{{ $link->seo_description }}</p>
            @endif
        </div>

        <div class="space-y-3">
            <div class="bg-white/10 backdrop-blur border border-white/20 rounded-xl p-6 text-white text-sm">
                <p>This bio link page is being set up. Check back soon!</p>
            </div>
        </div>

        <p class="text-white/40 text-xs mt-8">Powered by 1INME</p>
    </div>

    @include('common.partials.pixel-scripts', ['link' => $link])
</body>
</html>
