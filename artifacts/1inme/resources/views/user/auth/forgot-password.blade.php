<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Space Grotesk','system-ui','sans-serif']}}}}</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen relative overflow-hidden" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(139,92,246,0.12) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-24 -right-24 w-[400px] h-[400px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(168,85,247,0.08) 0%, transparent 70%);"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex items-center justify-center p-6 relative z-10">
        <div class="w-full max-w-sm">
            <div class="text-center mb-7">
                <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                    <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
                </a>
            </div>

            <div class="text-center mb-6">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-3" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);">
                    <i class="fas fa-lock text-purple-400"></i>
                </div>
                <h2 class="text-lg font-bold" style="color: var(--text-primary);">Forgot Password?</h2>
                <p class="text-xs mt-1" style="color: var(--text-dimmed);">Enter your email and we'll send you a reset link.</p>
            </div>

            @if(session('status'))
                <div class="mb-4 p-3 rounded-xl text-emerald-400 text-xs font-medium flex items-center gap-2" style="border: 1px solid rgba(16,185,129,0.15); background: rgba(16,185,129,0.06);">
                    <i class="fas fa-check-circle"></i> {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 rounded-xl text-red-400 text-xs font-medium" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('user.password.email') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com" class="theme-input w-full">
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                        Send Reset Link
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-xs">
                <a href="{{ route('user.login') }}" class="text-purple-400 hover:text-purple-300 font-semibold transition-colors">
                    <i class="fas fa-arrow-left text-[10px] mr-1"></i> Back to login
                </a>
            </p>
        </div>
    </div>
</body>
</html>
