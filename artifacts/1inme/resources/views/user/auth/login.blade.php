<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Space Grotesk', 'sans-serif'] } } } }</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden" style="background: var(--bg-body);">
    <div class="absolute inset-0">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[400px] h-[400px] bg-violet-500/15 rounded-full blur-[100px]"></div>
    </div>

    <div class="absolute top-4 right-4 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="w-full max-w-md relative z-10" x-data="{ mode: 'password' }">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
            </a>
            <p class="text-purple-300/60 mt-2">Your links, one place</p>
        </div>

        <div class="glass backdrop-blur-xl rounded-2xl p-8">
            <h2 class="text-xl font-semibold mb-6" style="color: var(--text-primary);">Welcome back</h2>

            <div class="flex rounded-lg p-1 mb-6" style="background: var(--bg-glass-input);">
                <button @click="mode = 'password'" :class="mode === 'password' ? 'bg-purple-600 text-white shadow-lg' : ''" class="flex-1 py-2 text-sm font-medium rounded-md transition-all" :style="mode !== 'password' ? 'color: var(--text-muted)' : ''">Password</button>
                <button @click="mode = 'otp'" :class="mode === 'otp' ? 'bg-purple-600 text-white shadow-lg' : ''" class="flex-1 py-2 text-sm font-medium rounded-md transition-all" :style="mode !== 'otp' ? 'color: var(--text-muted)' : ''">OTP Login</button>
            </div>

            <div x-show="mode === 'password'" x-cloak x-transition>
                <form method="POST" action="{{ route('user.login.submit') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-muted);">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com"
                                   class="w-full px-4 py-2.5 rounded-lg outline-none transition focus:ring-2 focus:ring-purple-500 focus:border-purple-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-muted);">Password</label>
                            <input type="password" name="password" required placeholder="••••••••"
                                   class="w-full px-4 py-2.5 rounded-lg outline-none transition focus:ring-2 focus:ring-purple-500 focus:border-purple-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm" style="color: var(--text-muted);">
                                <input type="checkbox" name="remember" class="rounded text-purple-600 focus:ring-purple-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                                Remember me
                            </label>
                            <a href="{{ route('user.password.request') }}" class="text-sm text-purple-400 hover:text-purple-300">Forgot password?</a>
                        </div>

                        <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                            Sign In
                        </button>
                    </div>
                </form>
            </div>

            <div x-show="mode === 'otp'" x-cloak x-transition>
                <form method="POST" action="{{ route('user.otp.send') }}" x-data="{ otpType: 'email' }">
                    @csrf
                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <button type="button" @click="otpType = 'email'" :class="otpType === 'email' ? 'bg-purple-600/20 border-purple-500 text-purple-300' : ''" class="flex-1 py-2 text-xs font-medium rounded-lg border transition" :style="otpType !== 'email' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : ''">
                                <i class="fas fa-envelope mr-1"></i> Email
                            </button>
                            <button type="button" @click="otpType = 'mobile'" :class="otpType === 'mobile' ? 'bg-purple-600/20 border-purple-500 text-purple-300' : ''" class="flex-1 py-2 text-xs font-medium rounded-lg border transition" :style="otpType !== 'mobile' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : ''">
                                <i class="fas fa-mobile-alt mr-1"></i> Mobile
                            </button>
                        </div>

                        <input type="hidden" name="type" :value="otpType">

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-muted);" x-text="otpType === 'email' ? 'Email Address' : 'Mobile Number'"></label>
                            <input type="text" name="identifier" required
                                   :placeholder="otpType === 'email' ? 'you@example.com' : '+1234567890'"
                                   class="w-full px-4 py-2.5 rounded-lg outline-none transition focus:ring-2 focus:ring-purple-500 focus:border-purple-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            @error('identifier')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                            <i class="fas fa-paper-plane mr-2"></i>Send OTP
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 pt-6" style="border-top: 1px solid var(--border-glass);">
                <p class="text-center text-xs mb-3" style="color: var(--text-dimmed);">Quick access</p>
                <div class="grid grid-cols-2 gap-2">
                    <form method="POST" action="{{ route('user.demo.login') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm transition hover:bg-purple-600/20 hover:border-purple-500/30 hover:text-purple-300" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                            <i class="fas fa-user"></i> Demo User
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.demo.login') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm transition hover:bg-purple-600/20 hover:border-purple-500/30 hover:text-purple-300" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                            <i class="fas fa-shield-alt"></i> Demo Admin
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <p class="mt-6 text-center text-sm" style="color: var(--text-dimmed);">
            Don't have an account?
            <a href="{{ route('user.register') }}" class="text-purple-400 hover:text-purple-300 font-medium">Sign up</a>
        </p>
    </div>
</body>
</html>
