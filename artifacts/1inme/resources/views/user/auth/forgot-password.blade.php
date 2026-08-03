<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password - Sayzio</title>
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
            --bg-input: rgba(255,255,255,0.05);
        }
        html.light-mode {
            color-scheme: light;
            --bg-body: #f4f6fa;
            --bg-card: rgba(255,255,255,0.62);
            --border-glass: rgba(15,23,42,0.09);
            --text-primary: #071437;
            --text-muted: #4b5675;
            --bg-input: #f8fafc;
        }
        .auth-orb { position:absolute; border-radius:9999px; pointer-events:none; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden" style="background: var(--bg-body, #0a0a0f);">
    <div class="auth-orb -top-32 -left-32 w-[520px] h-[520px]" style="background: radial-gradient(circle, rgba(61,107,255,0.16) 0%, transparent 70%);"></div>
    <div class="auth-orb -bottom-32 -right-32 w-[460px] h-[460px]" style="background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 70%);"></div>
    <div class="max-w-md w-full rounded-3xl p-8 relative" style="background: var(--bg-card); border: 1px solid var(--border-glass); backdrop-filter: blur(26px) saturate(1.4); -webkit-backdrop-filter: blur(26px) saturate(1.4); box-shadow: 0 30px 70px -40px rgba(0,0,0,0.55);">
        <div class="text-2xl font-bold text-blue-600 mb-6">Sayzio</div>
        <h1 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Forgot your password?</h1>
        <p class="text-sm leading-relaxed mb-6" style="color: var(--text-muted);">
            Enter the email on your account and we'll send you a link to set a new password.
        </p>

        @if(session('status'))
            <div class="mb-4 p-3 rounded-lg text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #10b981;">
                {{ session('status') }}
            </div>
        @endif
        @if(session('delivery_error'))
            <div class="mb-4 p-3 rounded-lg text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                {{ session('delivery_error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('user.password.email') }}">
            @csrf
            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Email address</label>
            <input type="email" name="email" value="{{ old('email', session('reset_email_sent_to')) }}" required placeholder="you@example.com"
                   class="w-full rounded-lg px-3 py-2.5 text-sm mb-4"
                   style="background: var(--bg-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            <button type="submit" class="w-full bg-blue-600 text-white font-medium px-5 py-2.5 rounded-lg hover:bg-blue-700">
                Send reset link
            </button>
        </form>

        <p class="text-sm mt-6" style="color: var(--text-muted);">
            Remembered it? <a href="{{ route('user.login') }}" class="text-blue-500 font-medium hover:underline">Back to sign-in</a>
        </p>
    </div>
</body>
</html>
