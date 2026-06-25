<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Link expired - Sayzio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4" style="background: var(--bg-glass, #f8fafc);">
    <div class="max-w-md w-full rounded-xl shadow p-8" style="background: var(--bg-card, #fff);">
        <div class="text-2xl font-bold text-blue-600 mb-6">Sayzio</div>
        <h1 class="text-xl font-semibold mb-3" style="color: var(--text-primary, #071437);">This link can't be used</h1>
        <p class="text-sm leading-relaxed mb-6" style="color: var(--text-muted, #475569);">{{ $reason ?? 'This security link is no longer valid.' }}</p>
        <a href="{{ route('user.login') }}"
           class="inline-block bg-blue-600 text-white font-medium px-5 py-2.5 rounded-lg hover:bg-blue-700">
            Go to sign-in
        </a>
    </div>
</body>
</html>
