<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>We&rsquo;re upgrading &mdash; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
    @include('common.partials.auth-glass-style')
    <style>
        .up-wrap { font-family: 'Space Grotesk', system-ui, sans-serif; }
        .up-hero {
            background: linear-gradient(100deg, var(--text-primary) 10%, #7d9bff 45%, #e29bff 90%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .up-badge {
            display:inline-flex; align-items:center; gap:8px;
            padding:6px 14px; border-radius:999px; font-size:11px; font-weight:600;
            letter-spacing:0.12em; text-transform:uppercase;
            background: rgba(125,155,255,0.12);
            border:1px solid rgba(125,155,255,0.32);
            color: var(--accent);
        }
        .up-dot { width:8px; height:8px; border-radius:999px; background:#7d9bff;
            box-shadow:0 0 10px #7d9bff; animation: up-pulse 1.8s ease-in-out infinite; }
        .up-feature {
            position:relative; display:flex; align-items:flex-start; gap:.85rem;
            background: var(--bg-glass-light); border:1px solid var(--border-glass);
            border-radius:1rem; padding:.9rem 1rem;
            opacity:0; transform: translateY(14px);
            animation: up-rise .7s cubic-bezier(.2,.7,.2,1) forwards;
        }
        .up-feature:hover { border-color: var(--border-glass-light); }
        .up-feature .ic {
            flex-shrink:0;
            width:38px; height:38px; border-radius:11px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:16px;
        }
        .up-feature:nth-child(1) { animation-delay:.10s } .up-feature:nth-child(1) .ic { background:rgba(125,155,255,0.14); color:#7d9bff; }
        .up-feature:nth-child(2) { animation-delay:.20s } .up-feature:nth-child(2) .ic { background:rgba(226,155,255,0.14); color:#e29bff; }
        .up-feature:nth-child(3) { animation-delay:.30s } .up-feature:nth-child(3) .ic { background:rgba(52,211,153,0.14); color:#34d399; }
        .up-feature:nth-child(4) { animation-delay:.40s } .up-feature:nth-child(4) .ic { background:rgba(103,232,249,0.14); color:#67e8f9; }
        @keyframes up-pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
        @keyframes up-rise { to { opacity:1; transform:translateY(0); } }
        @media (prefers-reduced-motion: reduce) {
            .up-feature { opacity:1 !important; transform:none !important; animation:none !important; }
            .up-dot { animation:none !important; }
        }
    </style>
</head>
<body class="min-h-screen relative overflow-x-hidden up-wrap" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="particles" id="login-particles"></div>

    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-[600px] h-[600px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(61,107,255,0.15) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(92,131,255,0.1) 0%, transparent 70%);"></div>
        <div class="absolute top-1/4 right-1/3 w-[350px] h-[350px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%); animation-delay: -8s;"></div>
        <div class="absolute bottom-1/3 left-1/4 w-[250px] h-[250px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(236,72,153,0.06) 0%, transparent 70%); animation-delay: -5s;"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex relative z-10">
        <div class="hidden lg:block flex-1 relative lg:sticky lg:top-0 lg:h-screen lg:self-start lg:overflow-hidden">
            <a href="{{ route('home') }}" class="force-dark-logo absolute top-7 left-7 xl:top-9 xl:left-9 z-30 inline-flex items-center group">
                @include('common.partials.brand-logo', ['height' => 'h-10'])
            </a>
            @include('common.partials.auth-slider', ['variant' => 'page'])
        </div>

        <div class="flex-1 lg:flex-none lg:w-[480px] flex p-6 lg:p-12 relative">
            <div class="hidden lg:block absolute inset-y-0 left-0 w-px" style="background: linear-gradient(180deg, transparent, var(--border-glass), transparent);"></div>

            <div class="w-full max-w-sm m-auto auth-glass-card">
                <div class="text-center mb-7 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center">
                        @include('common.partials.brand-logo', ['height' => 'h-10'])
                    </a>
                </div>

                <div class="text-center lg:text-left mb-6">
                    <span class="up-badge"><span class="up-dot"></span> Upgrading &middot; Back soon</span>
                    <h1 class="up-hero font-bold mt-5 text-2xl sm:text-3xl leading-tight">
                        We&rsquo;re upgrading {{ config('app.name') }}.
                    </h1>
                    <p class="mt-3 text-sm" style="color: var(--text-muted);">
                        We&rsquo;ve temporarily paused new sign-ups while we put the finishing touches on something better. New accounts will reopen shortly, thanks for your patience.
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-bolt"></i></span>
                        <div>
                            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Faster, smoother everything</h3>
                            <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--text-dimmed);">A rebuilt core so your links, biolinks and dashboards load quicker than ever.</p>
                        </div>
                    </div>
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-wand-magic-sparkles"></i></span>
                        <div>
                            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Smarter AI tools</h3>
                            <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--text-dimmed);">New AI-powered building and insights to help your pages convert and grow.</p>
                        </div>
                    </div>
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-chart-line"></i></span>
                        <div>
                            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Deeper analytics</h3>
                            <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--text-dimmed);">Richer tracking and reports so you always know what&rsquo;s working.</p>
                        </div>
                    </div>
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-palette"></i></span>
                        <div>
                            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Fresh customization</h3>
                            <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--text-dimmed);">More themes, blocks and branding controls to make every page truly yours.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-7 rounded-2xl px-5 py-5 text-center lg:text-left" style="background: rgba(125,155,255,0.07); border:1px solid rgba(125,155,255,0.18);">
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Already have an account?</p>
                    <p class="text-xs mt-1" style="color: var(--text-dimmed);">Existing members aren&rsquo;t affected, sign in and keep going as usual.</p>
                    <a href="{{ route('user.login') }}" class="btn-primary justify-center mt-4 px-6 py-2.5 text-sm">
                        <i class="fas fa-arrow-right-to-bracket text-[12px]"></i> Log in to your account
                    </a>
                </div>

                <p class="mt-6 text-center lg:text-left text-xs" style="color: var(--text-faint);">
                    &copy; {{ date('Y') }} {{ config('app.name') }} &middot; We&rsquo;ll be back better than ever.
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
