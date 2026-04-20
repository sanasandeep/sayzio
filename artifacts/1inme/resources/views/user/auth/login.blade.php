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
        <div class="hidden lg:flex flex-1 flex-col justify-center items-center p-12 xl:p-20 relative">
            <div class="max-w-md">
                <a href="{{ route('home') }}" class="inline-flex items-center mb-6 group">
                    @include('common.partials.brand-logo', ['height' => 'h-14'])
                </a>
                <p class="text-xl font-semibold mb-2" style="color: var(--text-secondary);">The link management platform<br><span class="gradient-text">built for growth.</span></p>
                <p class="text-sm leading-relaxed mb-12" style="color: var(--text-dimmed);">Shorten URLs, build Link in Bio pages, generate QR codes, and track every click with powerful analytics.</p>

                <div class="space-y-6">
                    <div class="flex items-start gap-4 group">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 glow-icon transition-all duration-500 group-hover:scale-110" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                            <i class="fas fa-link text-violet-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold mb-0.5" style="color: var(--text-primary);">6 Link Types</p>
                            <p class="text-xs" style="color: var(--text-dimmed);">Short Links, Link in Bio pages, File Share, Contact Cards, Events & more</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 group">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 glow-icon transition-all duration-500 group-hover:scale-110" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);">
                            <i class="fas fa-chart-line text-emerald-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold mb-0.5" style="color: var(--text-primary);">Real-time Analytics</p>
                            <p class="text-xs" style="color: var(--text-dimmed);">Track clicks, devices, locations & referrers in real time</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 group">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 glow-icon transition-all duration-500 group-hover:scale-110" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);">
                            <i class="fas fa-th-large text-amber-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold mb-0.5" style="color: var(--text-primary);">99+ Link in Bio Blocks</p>
                            <p class="text-xs" style="color: var(--text-dimmed);">Build stunning Link in Bio pages with videos, forms, maps & more</p>
                        </div>
                    </div>
                </div>

                <div class="mt-14 flex items-center gap-3">
                    <div class="flex -space-x-2.5">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-400 to-pink-400 border-2 flex items-center justify-center text-white text-[10px] font-bold shadow-md" style="border-color: var(--bg-body);">A</div>
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-400 to-cyan-400 border-2 flex items-center justify-center text-white text-[10px] font-bold shadow-md" style="border-color: var(--bg-body);">M</div>
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-400 to-teal-400 border-2 flex items-center justify-center text-white text-[10px] font-bold shadow-md" style="border-color: var(--bg-body);">K</div>
                    </div>
                    <p class="text-xs" style="color: var(--text-dimmed);">Trusted by <span class="font-bold" style="color: var(--text-muted);">10,000+</span> creators & businesses</p>
                </div>
            </div>
        </div>

        <div class="flex-1 lg:flex-none lg:w-[480px] flex items-center justify-center p-6 lg:p-12 relative">
            <div class="hidden lg:block absolute inset-y-0 left-0 w-px" style="background: linear-gradient(180deg, transparent, var(--border-glass), transparent);"></div>

            <div class="w-full max-w-sm">
                <div class="text-center mb-7 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center">
                        @include('common.partials.brand-logo', ['height' => 'h-10'])
                    </a>
                </div>

                <div class="hidden lg:block mb-7">
                    <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Welcome back</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">We'll email or text you a 6-digit code — no password needed.</p>
                </div>

                <div class="lg:hidden text-center mb-6">
                    <h2 class="text-xl font-bold" style="color: var(--text-primary);">Welcome back</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">Sign in with a one-time code</p>
                </div>

                @if(session('status'))
                    <div class="mb-4 rounded-xl px-3 py-2.5 text-xs" style="background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.20); color: #86efac;">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('user.otp.send') }}" x-data="{ otpType: 'email' }">
                    @csrf
                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <button type="button" @click="otpType = 'email'" :class="otpType === 'email' ? 'border-violet-500/40 text-violet-400' : ''" class="flex-1 py-2 text-xs font-medium rounded-xl border transition-all" :style="otpType !== 'email' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : 'background: rgba(124,58,237,0.08)'">
                                <i class="fas fa-envelope mr-1"></i> Email
                            </button>
                            <button type="button" @click="otpType = 'mobile'" :class="otpType === 'mobile' ? 'border-violet-500/40 text-violet-400' : ''" class="flex-1 py-2 text-xs font-medium rounded-xl border transition-all" :style="otpType !== 'mobile' ? 'background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-muted)' : 'background: rgba(124,58,237,0.08)'">
                                <i class="fas fa-mobile-alt mr-1"></i> Mobile
                            </button>
                        </div>
                        <input type="hidden" name="type" :value="otpType">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);" x-text="otpType === 'email' ? 'Email Address' : 'Mobile Number'"></label>
                            <input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus :placeholder="otpType === 'email' ? 'you@example.com' : '+1234567890'" class="theme-input w-full">
                            @error('identifier')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                            <i class="fas fa-paper-plane text-xs"></i> Send 6-digit Code
                        </button>
                    </div>
                </form>

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
