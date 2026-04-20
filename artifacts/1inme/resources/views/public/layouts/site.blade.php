<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $page->title ?? '1INME') — {{ config('app.name', '1INME') }}</title>
    <meta name="description" content="{{ $page->meta_description ?? '' }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Space Grotesk','sans-serif'] } } } }
    </script>
    <style>
        body { background:#1e2330; color:#fff; font-family:'Space Grotesk', sans-serif; }
        [x-cloak]{display:none!important}
        .prose-light p { margin-bottom:.75rem; line-height:1.65; color:#d1d5db; }
        .prose-light a { color:#a78bfa; text-decoration:underline; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">

@include('public.partials.header', ['useModal' => $useModal ?? false])

<main class="flex-1">
    @yield('content')
</main>

@include('public.partials.footer')

@stack('scripts')
</body>
</html>
