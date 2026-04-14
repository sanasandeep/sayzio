<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
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
        <div class="absolute top-1/3 right-1/4 w-[300px] h-[300px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(99,102,241,0.06) 0%, transparent 70%); animation-delay: -7s;"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex relative z-10">
        <div class="hidden lg:flex flex-1 flex-col justify-center items-center p-12 xl:p-20 relative">
            <div class="max-w-md">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-4xl font-bold tracking-tight mb-3">
                    <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
                </a>
                <p class="text-lg font-medium mb-2" style="color: var(--text-secondary);">The link management platform built for growth.</p>
                <p class="text-sm leading-relaxed mb-10" style="color: var(--text-dimmed);">Shorten URLs, build bio pages, generate QR codes, and track every click with powerful analytics.</p>

                <div class="space-y-5">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);">
                            <i class="fas fa-link text-purple-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold mb-0.5" style="color: var(--text-primary);">6 Link Types</p>
                            <p class="text-xs" style="color: var(--text-dimmed);">URL shortener, bio links, file links, vCards, events & more</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);">
                            <i class="fas fa-chart-line text-emerald-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold mb-0.5" style="color: var(--text-primary);">Real-time Analytics</p>
                            <p class="text-xs" style="color: var(--text-dimmed);">Track clicks, devices, locations & referrers in real time</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);">
                            <i class="fas fa-th-large text-amber-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold mb-0.5" style="color: var(--text-primary);">99+ Bio Link Blocks</p>
                            <p class="text-xs" style="color: var(--text-dimmed);">Build stunning bio pages with videos, forms, maps & more</p>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex items-center gap-3">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 border-2 flex items-center justify-center text-white text-[10px] font-bold" style="border-color: var(--bg-body);">A</div>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-cyan-400 border-2 flex items-center justify-center text-white text-[10px] font-bold" style="border-color: var(--bg-body);">M</div>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-teal-400 border-2 flex items-center justify-center text-white text-[10px] font-bold" style="border-color: var(--bg-body);">K</div>
                    </div>
                    <p class="text-xs" style="color: var(--text-dimmed);">Trusted by <span class="font-semibold" style="color: var(--text-muted);">10,000+</span> creators & businesses</p>
                </div>
            </div>
        </div>

        <div class="flex-1 lg:flex-none lg:w-[480px] flex items-center justify-center p-6 lg:p-12">
            <div class="w-full max-w-sm" x-data="{ mode: 'password' }">
                <div class="text-center mb-7 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                        <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
                    </a>
                </div>

                <div class="hidden lg:block mb-7">
                    <h2 class="text-xl font-bold" style="color: var(--text-primary);">Welcome back</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">Sign in to your account to continue</p>
                </div>

                <div class="lg:hidden text-center mb-6">
                    <h2 class="text-xl font-bold" style="color: var(--text-primary);">Welcome back</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">Sign in to continue</p>
                </div>

                <div class="glass rounded-2xl p-1.5 mb-6">
                    <div class="flex">
                        <button @click="mode = 'password'" :class="mode === 'password' ? 'btn-primary shadow-lg' : ''" class="flex-1 py-2 text-sm font-medium rounded-xl transition-all" :style="mode !== 'password' ? 'color: var(--text-muted)' : ''">Password</button>
                        <button @click="mode = 'otp'" :class="mode === 'otp' ? 'btn-primary shadow-lg' : ''" class="flex-1 py-2 text-sm font-medium rounded-xl transition-all" :style="mode !== 'otp' ? 'color: var(--text-muted)' : ''">OTP Login</button>
                    </div>
                </div>

                <div x-show="mode === 'password'" x-cloak x-transition>
                    <form method="POST" action="{{ route('user.login.submit') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com"
                                       class="theme-input w-full">
                                @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Password</label>
                                <input type="password" name="password" required placeholder="Enter your password"
                                       class="theme-input w-full">
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-dimmed);">
                                    <input type="checkbox" name="remember" class="rounded text-purple-500 focus:ring-purple-500/40 w-3.5 h-3.5" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                                    Remember me
                                </label>
                                <a href="{{ route('user.password.request') }}" class="text-xs font-medium text-purple-400 hover:text-purple-300 transition-colors">Forgot?</a>
                            </div>

                            <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                                Sign In
                                <i class="fas fa-arrow-right text-[10px] ml-1"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <div x-show="mode === 'otp'" x-cloak x-transition>
                    <form method="POST" action="{{ route('user.otp.send') }}" x-data="{ otpType: 'email' }">
                        @csrf
                        <div class="space-y-4">
                            <div class="flex gap-2">
                                <button type="button" @click="otpType = 'email'" :class="otpType === 'email' ? 'border-purple-500/40 text-purple-400' : ''" class="flex-1 py-2 text-xs font-medium rounded-lg border transition" :style="otpType !== 'email' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : 'background: rgba(139,92,246,0.08)'">
                                    <i class="fas fa-envelope mr-1"></i> Email
                                </button>
                                <button type="button" @click="otpType = 'mobile'" :class="otpType === 'mobile' ? 'border-purple-500/40 text-purple-400' : ''" class="flex-1 py-2 text-xs font-medium rounded-lg border transition" :style="otpType !== 'mobile' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : 'background: rgba(139,92,246,0.08)'">
                                    <i class="fas fa-mobile-alt mr-1"></i> Mobile
                                </button>
                            </div>
                            <input type="hidden" name="type" :value="otpType">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);" x-text="otpType === 'email' ? 'Email Address' : 'Mobile Number'"></label>
                                <input type="text" name="identifier" required :placeholder="otpType === 'email' ? 'you@example.com' : '+1234567890'" class="theme-input w-full">
                                @error('identifier')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                                <i class="fas fa-paper-plane text-xs"></i> Send OTP
                            </button>
                        </div>
                    </form>
                </div>

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

                <p class="mt-6 text-center text-xs" style="color: var(--text-dimmed);">
                    Don't have an account?
                    <a href="{{ route('user.register') }}" class="text-purple-400 hover:text-purple-300 font-semibold transition-colors">Sign up free</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
