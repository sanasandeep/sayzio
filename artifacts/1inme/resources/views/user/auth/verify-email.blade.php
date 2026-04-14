<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - {{ config('app.name') }}</title>
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
        <div class="w-full max-w-sm text-center">
            <div class="mb-7">
                <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                    <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
                </a>
            </div>

            <div class="w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-4" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);">
                <i class="fas fa-envelope text-purple-400 text-xl"></i>
            </div>
            <h2 class="text-lg font-bold mb-1" style="color: var(--text-primary);">Verify Your Email</h2>
            <p class="text-xs mb-6" style="color: var(--text-dimmed);">We've sent a verification link to your email. Please check your inbox and click the link to verify.</p>

            @if(session('status'))
                <div class="mb-4 p-3 rounded-xl text-emerald-400 text-xs font-medium flex items-center justify-center gap-2" style="border: 1px solid rgba(16,185,129,0.15); background: rgba(16,185,129,0.06);">
                    <i class="fas fa-check-circle"></i> {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('user.verification.send') }}">
                @csrf
                <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                    Resend Verification Email
                </button>
            </form>

            <div class="mt-4">
                <a href="{{ route('user.dashboard') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold transition-colors">Skip for now</a>
            </div>
        </div>
    </div>
</body>
</html>
