<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>We&rsquo;re upgrading &mdash; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
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
        .up-card {
            position:relative; overflow:hidden;
            background: var(--bg-glass); border:1px solid var(--border-glass);
            border-radius:1.25rem; box-shadow: var(--card-shadow);
        }
        html:not(.light-mode) .up-card { backdrop-filter: blur(24px) saturate(140%); -webkit-backdrop-filter: blur(24px) saturate(140%); }
        .up-feature {
            position:relative;
            background: var(--bg-glass-light); border:1px solid var(--border-glass);
            border-radius:1rem; padding:1.1rem 1.15rem;
            opacity:0; transform: translateY(14px);
            animation: up-rise .7s cubic-bezier(.2,.7,.2,1) forwards;
        }
        .up-feature:hover { border-color: var(--border-glass-light); }
        .up-feature .ic {
            width:42px; height:42px; border-radius:12px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:18px; margin-bottom:.7rem;
        }
        .up-feature:nth-child(1) { animation-delay:.10s } .up-feature:nth-child(1) .ic { background:rgba(125,155,255,0.14); color:#7d9bff; }
        .up-feature:nth-child(2) { animation-delay:.20s } .up-feature:nth-child(2) .ic { background:rgba(226,155,255,0.14); color:#e29bff; }
        .up-feature:nth-child(3) { animation-delay:.30s } .up-feature:nth-child(3) .ic { background:rgba(52,211,153,0.14); color:#34d399; }
        .up-feature:nth-child(4) { animation-delay:.40s } .up-feature:nth-child(4) .ic { background:rgba(103,232,249,0.14); color:#67e8f9; }
        .up-glow {
            position:absolute; border-radius:50%; filter:blur(110px); pointer-events:none; z-index:0;
        }
        @keyframes up-pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
        @keyframes up-rise { to { opacity:1; transform:translateY(0); } }
        @keyframes up-float { 0%,100%{transform:translate(0,0)} 50%{transform:translate(0,-22px)} }
        .animate-up-float { animation: up-float 9s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) {
            .up-feature { opacity:1 !important; transform:none !important; animation:none !important; }
            .up-dot, .animate-up-float { animation:none !important; }
        }
    </style>
</head>
<body class="min-h-screen relative overflow-hidden up-wrap" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="up-glow animate-up-float" style="top:-120px; left:-100px; width:520px; height:520px; background: radial-gradient(circle, rgba(61,107,255,0.16) 0%, transparent 70%);"></div>
        <div class="up-glow animate-up-float" style="bottom:-120px; right:-90px; width:440px; height:440px; background: radial-gradient(circle, rgba(215,109,255,0.12) 0%, transparent 70%); animation-delay:-4s;"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex items-center justify-center p-6 lg:p-10 relative z-10">
        <div class="w-full max-w-2xl">
            <div class="flex justify-center mb-8">
                <a href="{{ route('home') }}" class="inline-flex items-center justify-center">
                    @include('common.partials.brand-logo', ['height' => 'h-11'])
                </a>
            </div>

            <div class="up-card p-7 sm:p-10">
                <div class="text-center">
                    <span class="up-badge"><span class="up-dot"></span> Upgrading &middot; Back soon</span>
                    <h1 class="up-hero font-bold mt-5 text-3xl sm:text-[2.6rem] leading-tight">
                        We&rsquo;re upgrading {{ config('app.name') }}.
                    </h1>
                    <p class="mt-3 text-sm sm:text-base max-w-lg mx-auto" style="color: var(--text-muted);">
                        We&rsquo;ve temporarily paused new sign-ups while we put the finishing touches on something better. New accounts will reopen shortly — thanks for your patience.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 sm:gap-4 mt-8">
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-bolt"></i></span>
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Faster, smoother everything</h3>
                        <p class="text-xs mt-1 leading-relaxed" style="color: var(--text-dimmed);">A rebuilt core so your links, biolinks and dashboards load quicker than ever.</p>
                    </div>
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-wand-magic-sparkles"></i></span>
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Smarter AI tools</h3>
                        <p class="text-xs mt-1 leading-relaxed" style="color: var(--text-dimmed);">New AI-powered building and insights to help your pages convert and grow.</p>
                    </div>
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-chart-line"></i></span>
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Deeper analytics</h3>
                        <p class="text-xs mt-1 leading-relaxed" style="color: var(--text-dimmed);">Richer tracking and reports so you always know what&rsquo;s working.</p>
                    </div>
                    <div class="up-feature">
                        <span class="ic"><i class="fas fa-palette"></i></span>
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Fresh customization</h3>
                        <p class="text-xs mt-1 leading-relaxed" style="color: var(--text-dimmed);">More themes, blocks and branding controls to make every page truly yours.</p>
                    </div>
                </div>

                <div class="mt-9 rounded-2xl px-5 py-5 text-center" style="background: rgba(125,155,255,0.07); border:1px solid rgba(125,155,255,0.18);">
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Already have an account?</p>
                    <p class="text-xs mt-1" style="color: var(--text-dimmed);">Existing members aren&rsquo;t affected — sign in and keep going as usual.</p>
                    <a href="{{ route('user.login') }}" class="btn-primary justify-center mt-4 px-6 py-2.5 text-sm">
                        <i class="fas fa-arrow-right-to-bracket text-[12px]"></i> Log in to your account
                    </a>
                </div>
            </div>

            <p class="mt-6 text-center text-xs" style="color: var(--text-faint);">
                &copy; {{ date('Y') }} {{ config('app.name') }} &middot; We&rsquo;ll be back better than ever.
            </p>
        </div>
    </div>
</body>
</html>
