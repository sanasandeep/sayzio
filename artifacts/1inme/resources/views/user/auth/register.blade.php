<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - {{ config('app.name') }}</title>
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
                <p class="text-sm mt-1" style="color: var(--text-dimmed);">Create your free account</p>
            </div>

            <form method="POST" action="{{ route('user.register.submit') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Full Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="John Doe" class="theme-input w-full">
                        @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" class="theme-input w-full">
                        @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Mobile <span style="color: var(--text-faint);">(optional)</span></label>
                        <input type="text" name="mobile" value="{{ old('mobile') }}" placeholder="+1234567890" class="theme-input w-full">
                        @error('mobile')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Password</label>
                        <input type="password" name="password" required placeholder="Min 8 characters" class="theme-input w-full">
                        @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Confirm Password</label>
                        <input type="password" name="password_confirmation" required placeholder="Repeat password" class="theme-input w-full">
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                        Create Account <i class="fas fa-arrow-right text-[10px] ml-1"></i>
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-xs" style="color: var(--text-dimmed);">
                Already have an account?
                <a href="{{ route('user.login') }}" class="text-purple-400 hover:text-purple-300 font-semibold transition-colors">Sign in</a>
            </p>
        </div>
    </div>
</body>
</html>
