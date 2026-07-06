<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign-in revoked - Sayzio</title>
    <script>
    (function(){
        const saved = localStorage.getItem('1inme_theme');
        if(saved !== 'dark') document.documentElement.classList.add('light-mode');
    })();
    </script>
    <style>
        :root {
            color-scheme: dark;
            --bg-body: #0a0a0f;
            --bg-card: rgba(255,255,255,0.04);
            --border-glass: rgba(255,255,255,0.10);
            --text-primary: #ffffff;
            --text-muted: #94a3b8;
        }
        html.light-mode {
            color-scheme: light;
            --bg-body: #f4f6fa;
            --bg-card: #ffffff;
            --border-glass: #dbdfe9;
            --text-primary: #071437;
            --text-muted: #4b5675;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4" style="background: var(--bg-body, #0a0a0f);">
    <div class="max-w-md w-full rounded-xl shadow p-8" style="background: var(--bg-card, rgba(255,255,255,0.04)); border: 1px solid var(--border-glass, rgba(255,255,255,0.10));">
        <div class="text-2xl font-bold text-blue-600 mb-6">Sayzio</div>
        <h1 class="text-xl font-semibold mb-3" style="color: var(--text-primary, #ffffff);">Sign-in revoked</h1>
        <p class="text-sm leading-relaxed mb-4" style="color: var(--text-muted, #94a3b8);">
            We've signed that session out, logged every other device off your account, and cleared your password.
        </p>
        <p class="text-sm leading-relaxed mb-6" style="color: var(--text-muted, #94a3b8);">
            To get back in, use <strong>Forgot password</strong> on the sign-in page to set a brand-new one.
        </p>
        <a href="{{ route('user.login') }}"
           class="inline-block bg-blue-600 text-white font-medium px-5 py-2.5 rounded-lg hover:bg-blue-700">
            Go to sign-in
        </a>
    </div>
</body>
</html>
