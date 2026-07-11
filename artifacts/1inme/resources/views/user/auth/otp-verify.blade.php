<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify OTP - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen relative overflow-hidden" style="background: var(--bg-body);">
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
        <div class="hidden lg:block flex-1 relative">
            <a href="{{ route('home') }}" class="force-dark-logo absolute top-7 left-7 xl:top-9 xl:left-9 z-30 inline-flex items-center group">
                @include('common.partials.brand-logo', ['height' => 'h-10'])
            </a>
            @include('common.partials.auth-slider', ['variant' => 'page'])
        </div>

        <div class="flex-1 lg:flex-none lg:w-[480px] flex items-center justify-center p-6 lg:p-12 relative">
            <div class="hidden lg:block absolute inset-y-0 left-0 w-px" style="background: linear-gradient(180deg, transparent, var(--border-glass), transparent);"></div>

            <div class="w-full max-w-sm" data-ajax-group>
                <div class="text-center mb-7 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center">
                        @include('common.partials.brand-logo', ['height' => 'h-10'])
                    </a>
                </div>

                <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-4 mx-auto lg:mx-0" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                    <i class="fas fa-key text-blue-400 text-xl"></i>
                </div>
                <div class="text-center lg:text-left mb-6">
                    <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Enter Verification Code</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">We sent a 6-digit code to your {{ session('otp_type') === 'mobile' ? 'WhatsApp' : 'email' }}.</p>
                </div>

                {{-- Status flash / AJAX status (used by both verify and resend forms) --}}
                <div class="mb-4 p-3 rounded-xl text-blue-400 text-xs font-medium" style="border: 1px solid rgba(61,107,255,0.15); background: rgba(61,107,255,0.06);" data-ajax-status @if(!session('status')) hidden @endif>
                    {{ session('status') }}
                </div>

                @if(session('otp_demo_reveal'))
                    <div class="mb-4 p-3 rounded-xl text-amber-300 text-xs font-semibold" style="border: 1px solid rgba(245,158,11,0.25); background: rgba(245,158,11,0.08);">
                        <i class="fas fa-flask text-[10px] mr-1"></i> {{ session('otp_demo_reveal') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('user.otp.verify') }}" data-ajax>
                    @csrf
                    <div class="mb-3 p-3 rounded-xl text-red-400 text-xs font-medium" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);" data-general-err @if(!$errors->any()) hidden @endif>
                        @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                    </div>
                    <div class="mb-4">
                        <input type="text" name="code" maxlength="6" placeholder="000000" required autofocus
                               class="theme-input w-full text-center text-2xl tracking-[0.5em] font-bold py-3.5">
                        <p class="mt-1 text-xs text-red-400" data-err="code" hidden></p>
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                        Verify &amp; Login
                    </button>
                </form>

                <form method="POST" action="{{ route('user.otp.resend') }}" class="mt-4 text-center lg:text-left" data-ajax>
                    @csrf
                    <div class="mb-2 p-2 rounded-xl text-red-400 text-xs font-medium" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);" data-general-err hidden></div>
                    <button type="submit" class="text-xs font-medium text-blue-400 hover:text-blue-300 transition-colors">
                        <i class="fas fa-rotate-right text-[10px] mr-1"></i> Resend code
                    </button>
                </form>

                <p class="mt-4 text-xs text-center lg:text-left">
                    <a href="{{ route('user.login') }}" class="text-blue-400 hover:text-blue-300 font-semibold transition-colors">
                        <i class="fas fa-arrow-left text-[10px] mr-1"></i> Use a different {{ session('otp_type') === 'mobile' ? 'number' : 'email' }}
                    </a>
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
    <script src="{{ asset('js/auth-ajax.js') }}"></script>
</body>
</html>
