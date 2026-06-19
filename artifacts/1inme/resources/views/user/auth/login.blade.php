<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen relative overflow-hidden" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="particles" id="login-particles"></div>

    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-[600px] h-[600px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(124,58,237,0.15) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(139,92,246,0.1) 0%, transparent 70%);"></div>
        <div class="absolute top-1/4 right-1/3 w-[350px] h-[350px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%); animation-delay: -8s;"></div>
        <div class="absolute bottom-1/3 left-1/4 w-[250px] h-[250px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(236,72,153,0.06) 0%, transparent 70%); animation-delay: -5s;"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex relative z-10">
        <div class="hidden lg:block flex-1 relative">
            <a href="{{ route('home') }}" class="force-dark-logo absolute top-7 left-7 xl:top-9 xl:left-9 z-30 inline-flex items-center group">
                @include('common.partials.brand-logo', ['height' => 'h-10'])
            </a>
            @include('common.partials.auth-slider', ['variant' => 'page'])
        </div>

        <div class="flex-1 lg:flex-none lg:w-[480px] flex items-center justify-center p-6 lg:p-12 relative">
            <div class="hidden lg:block absolute inset-y-0 left-0 w-px" style="background: linear-gradient(180deg, transparent, var(--border-glass), transparent);"></div>

            <div class="w-full max-w-sm">
                <div class="text-center mb-7 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center">
                        @include('common.partials.brand-logo', ['height' => 'h-10'])
                    </a>
                </div>

                @php
                    $mobileLoginEnabled   = $mobileLoginEnabled ?? false;
                    $emailPasswordEnabled = $emailPasswordEnabled ?? false;
                    $emailOtpEnabled      = $emailOtpEnabled ?? true;
                    $passwordOn = $emailPasswordEnabled;
                    $defaultMethod = old('login_method')
                        ?: ($emailPasswordEnabled ? 'password' : ($emailOtpEnabled ? 'email_otp' : 'mobile'));
                    $methodCount = ($emailPasswordEnabled ? 1 : 0) + ($emailOtpEnabled ? 1 : 0) + ($mobileLoginEnabled ? 1 : 0);
                @endphp
                <div class="hidden lg:block mb-7">
                    <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Welcome back</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">{{ $passwordOn ? 'Sign in to your account.' : "We'll send you a 6-digit code — no password needed." }}</p>
                </div>

                <div class="lg:hidden text-center mb-6">
                    <h2 class="text-xl font-bold" style="color: var(--text-primary);">Welcome back</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">{{ $passwordOn ? 'Sign in to your account' : 'Sign in with a one-time code' }}</p>
                </div>

                @if(session('status'))
                    <div class="mb-4 rounded-xl px-3 py-2.5 text-xs" style="background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.20); color: #86efac;">
                        {{ session('status') }}
                    </div>
                @endif

                <div x-data="{ method: '{{ $defaultMethod }}' }">
                    @if($methodCount > 1)
                    <div class="flex gap-2 mb-4">
                        @if($emailPasswordEnabled)
                        <button type="button" @click="method = 'password'" :class="method === 'password' ? 'border-violet-500/40 text-violet-400' : ''" class="flex-1 py-2 text-xs font-medium rounded-xl border transition-all" :style="method !== 'password' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : 'background: rgba(124,58,237,0.08)'">
                            <i class="fas fa-key mr-1"></i> Password
                        </button>
                        @endif
                        @if($emailOtpEnabled)
                        <button type="button" @click="method = 'email_otp'" :class="method === 'email_otp' ? 'border-violet-500/40 text-violet-400' : ''" class="flex-1 py-2 text-xs font-medium rounded-xl border transition-all" :style="method !== 'email_otp' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : 'background: rgba(124,58,237,0.08)'">
                            <i class="fas fa-envelope mr-1"></i> Email code
                        </button>
                        @endif
                        @if($mobileLoginEnabled)
                        <button type="button" @click="method = 'mobile'" :class="method === 'mobile' ? 'border-violet-500/40 text-violet-400' : ''" class="flex-1 py-2 text-xs font-medium rounded-xl border transition-all" :style="method !== 'mobile' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : 'background: rgba(124,58,237,0.08)'">
                            <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                        </button>
                        @endif
                    </div>
                    @endif

                    @if($emailPasswordEnabled)
                    {{-- Email + password sign-in --}}
                    <form method="POST" action="{{ route('user.login.submit') }}" x-show="method === 'password'" @if($methodCount > 1)x-cloak @endif>
                        @csrf
                        <input type="hidden" name="login_method" value="password">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email Address</label>
                                <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" class="theme-input w-full">
                                @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Password</label>
                                <input type="password" name="password" required placeholder="Your password" autocomplete="current-password" class="theme-input w-full">
                                @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                                <i class="fas fa-arrow-right-to-bracket text-xs"></i> Sign In
                            </button>
                        </div>
                    </form>
                    @endif

                    @if($emailOtpEnabled)
                    {{-- Email one-time-code sign-in --}}
                    <form method="POST" action="{{ route('user.otp.send') }}" x-show="method === 'email_otp'" @if($methodCount > 1)x-cloak @endif>
                        @csrf
                        <input type="hidden" name="login_method" value="email_otp">
                        <input type="hidden" name="type" value="email">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email Address</label>
                                <input type="email" name="identifier" value="{{ old('identifier') }}" required placeholder="you@example.com" class="theme-input w-full">
                                @error('identifier')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                                <i class="fas fa-paper-plane text-xs"></i> Send 6-digit Code
                            </button>
                        </div>
                    </form>
                    @endif

                    @if($mobileLoginEnabled)
                    {{-- WhatsApp one-time-code sign-in --}}
                    <form method="POST" action="{{ route('user.otp.send') }}" x-show="method === 'mobile'" @if($methodCount > 1)x-cloak @endif>
                        @csrf
                        <input type="hidden" name="login_method" value="mobile">
                        <input type="hidden" name="type" value="mobile">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">WhatsApp Number</label>
                                <input type="text" name="identifier" value="{{ old('identifier') }}" required placeholder="+1234567890" class="theme-input w-full">
                                @error('identifier')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                                <p class="mt-1.5 text-[10px]" style="color: var(--text-faint);">
                                    <i class="fab fa-whatsapp mr-0.5"></i> We'll send your code over WhatsApp. Supported country codes: {{ implode(', ', $allowedCountryCodes ?? []) }}.
                                </p>
                            </div>
                            <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                                <i class="fas fa-paper-plane text-xs"></i> Send 6-digit Code
                            </button>
                        </div>
                    </form>
                    @endif
                </div>

                <div class="mt-6 pt-6" style="border-top: 1px solid var(--border-glass);">
                    <p class="text-center text-[10px] uppercase tracking-wider font-bold mb-3" style="color: var(--text-faint);">Or sign in with</p>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        @foreach(['instagram' => 'fab fa-instagram', 'facebook' => 'fab fa-facebook', 'twitter' => 'fab fa-x-twitter', 'linkedin' => 'fab fa-linkedin', 'pinterest' => 'fab fa-pinterest', 'tiktok' => 'fab fa-tiktok'] as $p => $icon)
                            <a href="{{ route('user.social-oauth.login', $p) }}"
                               class="btn-ghost w-full justify-center text-xs py-2"
                               title="Sign in with {{ ucfirst($p === 'twitter' ? 'X' : $p) }}">
                                <i class="{{ $icon }} text-[12px]"></i>
                            </a>
                        @endforeach
                    </div>
                    <p class="text-center text-[10px] uppercase tracking-wider font-bold mb-3" style="color: var(--text-faint);">Quick access</p>
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
                    <a href="{{ route('user.register') }}" class="text-violet-400 hover:text-violet-300 font-semibold transition-colors">Sign up free</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var c = document.getElementById('login-particles');
        if(!c) return;
        for(var i = 0; i < 20; i++){
            var p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random()*100+'%';
            p.style.animationDuration = (12+Math.random()*20)+'s';
            p.style.animationDelay = Math.random()*15+'s';
            p.style.width = p.style.height = (1+Math.random()*3)+'px';
            p.style.opacity = 0.15+Math.random()*0.35;
            c.appendChild(p);
        }
    })();
    </script>
</body>
</html>
