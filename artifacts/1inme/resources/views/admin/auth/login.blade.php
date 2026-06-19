<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen relative overflow-hidden" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -right-32 w-[500px] h-[500px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(124,58,237,0.12) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-24 -left-24 w-[400px] h-[400px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%);"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex items-center justify-center p-6 relative z-10">
        <div class="w-full max-w-sm">
            <div class="text-center mb-7">
                <a href="{{ route('home') }}" class="inline-flex items-center justify-center">@include('common.partials.brand-logo', ['height' => 'h-12'])</a>
                <div class="mt-2 inline-flex items-center gap-1.5">
                    <div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: rgba(124,58,237,0.12); border: 1px solid rgba(124,58,237,0.15);">
                        <i class="fas fa-shield-alt text-violet-400 text-[8px]"></i>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Admin Panel</span>
                </div>
            </div>

            <div class="mb-6">
                <h2 class="text-lg font-bold" style="color: var(--text-primary);">Admin Sign In</h2>
                <p class="text-xs mt-0.5" style="color: var(--text-dimmed);">Access the management dashboard</p>
            </div>

            @if(session('error'))
                <div class="mb-4 p-3 rounded-xl text-red-400 text-xs font-medium flex items-center gap-2" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="admin@example.com" class="theme-input w-full">
                        @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Password</label>
                        <input type="password" name="password" required placeholder="Enter your password" class="theme-input w-full">
                        @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-dimmed);">
                            <input type="checkbox" name="remember" class="rounded text-violet-500 focus:ring-violet-500/40 w-3.5 h-3.5" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                            Remember me
                        </label>
                        <a href="{{ route('admin.password.request') }}" class="text-xs font-medium text-violet-400 hover:text-violet-300 transition-colors">Forgot?</a>
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                        Sign In <i class="fas fa-arrow-right text-[10px] ml-1"></i>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6" style="border-top: 1px solid var(--border-glass);">
                <p class="text-center text-[10px] uppercase tracking-wider font-semibold mb-3" style="color: var(--text-faint);">Quick access</p>
                <div class="grid grid-cols-2 gap-2">
                    <form method="POST" action="{{ route('user.demo.login') }}">
                        @csrf
                        <button type="submit" class="btn-ghost w-full justify-center text-xs py-2">
                            <i class="fas fa-user text-[10px]"></i> Demo User
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.demo.login') }}">
                        @csrf
                        <button type="submit" class="btn-ghost w-full justify-center text-xs py-2">
                            <i class="fas fa-shield-alt text-[10px]"></i> Demo Admin
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
