{{--
    Lightweight shell for the public monetization pages
    (subscribe / manage / checkout-preview). Mirrors the head/CSS
    of the standalone creator-profile.blade.php so the viewer-login
    modal and styling match seamlessly.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle ?? 'Subscribe' }} — {{ config('app.name', '1INME') }}</title>
<meta name="robots" content="noindex,nofollow">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
<style>
    :root {
        --text-primary: #0f172a;
        --text-secondary: #334155;
        --text-faint: #64748b;
        --border-color: rgba(15,23,42,0.08);
        --bg-card: #ffffff;
    }
    body { background: #f8fafc; }
    [x-cloak] { display: none !important; }
</style>
</head>
<body class="text-slate-900 min-h-screen">
@include('common.partials.viewer-login-modal')
@yield('content')
</body>
</html>
