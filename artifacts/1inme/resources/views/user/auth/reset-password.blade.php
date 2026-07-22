<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password - Sayzio</title>
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
            --bg-card: #ffffff;
            --border-glass: #dbdfe9;
            --text-primary: #071437;
            --text-muted: #4b5675;
            --bg-input: #f8fafc;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4" style="background: var(--bg-body, #0a0a0f);">
    <div class="max-w-md w-full rounded-xl shadow p-8" style="background: var(--bg-card); border: 1px solid var(--border-glass);">
        <div class="text-2xl font-bold text-blue-600 mb-6">Sayzio</div>
        <h1 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Set a new password</h1>
        <p class="text-sm leading-relaxed mb-6" style="color: var(--text-muted);">
            Choose a new password for your account. You'll be signed out everywhere else.
        </p>

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('user.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Email address</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required placeholder="you@example.com"
                   class="w-full rounded-lg px-3 py-2.5 text-sm mb-4"
                   style="background: var(--bg-input); border: 1px solid var(--border-glass); color: var(--text-primary);">

            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">New password</label>
            <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"
                   class="w-full rounded-lg px-3 py-2.5 text-sm mb-4"
                   style="background: var(--bg-input); border: 1px solid var(--border-glass); color: var(--text-primary);">

            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Confirm new password</label>
            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Repeat the password"
                   class="w-full rounded-lg px-3 py-2.5 text-sm mb-6"
                   style="background: var(--bg-input); border: 1px solid var(--border-glass); color: var(--text-primary);">

            <button type="submit" class="w-full bg-blue-600 text-white font-medium px-5 py-2.5 rounded-lg hover:bg-blue-700">
                Reset password
            </button>
        </form>

        <p class="text-sm mt-6" style="color: var(--text-muted);">
            <a href="{{ route('user.login') }}" class="text-blue-500 font-medium hover:underline">Back to sign-in</a>
        </p>
    </div>
</body>
</html>
