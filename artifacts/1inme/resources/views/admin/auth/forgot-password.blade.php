<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Admin</title>
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

            <div class="w-full max-w-sm">
                <div class="text-center mb-7 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center">
                        @include('common.partials.brand-logo', ['height' => 'h-10'])
                    </a>
                </div>

                <div class="flex justify-center lg:justify-start mb-4">
                    <div class="inline-flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: rgba(61,107,255,0.12); border: 1px solid rgba(61,107,255,0.15);">
                            <i class="fas fa-shield-alt text-blue-400 text-[8px]"></i>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Admin Panel</span>
                    </div>
                </div>

                <div class="hidden lg:block mb-7">
                    <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Admin Password Reset</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">Enter your admin email to receive a reset link.</p>
                </div>

                <div class="lg:hidden text-center mb-6">
                    <h2 class="text-xl font-bold" style="color: var(--text-primary);">Admin Password Reset</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">Enter your admin email to receive a reset link</p>
                </div>

                @if(session('status'))
                    <div class="mb-4 p-3 rounded-xl text-emerald-400 text-xs font-medium flex items-center gap-2" style="border: 1px solid rgba(16,185,129,0.15); background: rgba(16,185,129,0.06);">
                        <i class="fas fa-check-circle"></i> {{ session('status') }}
                    </div>
                @endif

                @if(session('delivery_error'))
                    <div class="mb-4 p-3 rounded-xl text-amber-400 text-xs font-medium flex items-start gap-2" style="border: 1px solid rgba(251,191,36,0.2); background: rgba(251,191,36,0.06);">
                        <i class="fas fa-exclamation-triangle mt-0.5 shrink-0"></i>
                        <span>{{ session('delivery_error') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-3 rounded-xl text-red-400 text-xs font-medium" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);">
                        @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                    </div>
                @endif

                @if(session('resend_throttled'))
                    <div class="mb-4 p-3 rounded-xl text-blue-400 text-xs font-medium flex items-center gap-2" style="border: 1px solid rgba(61,107,255,0.15); background: rgba(61,107,255,0.06);">
                        <i class="fas fa-clock"></i> Please wait {{ session('resend_throttled') }} second(s) before requesting another link.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.password.email') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email</label>
                            <input type="email" name="email" value="{{ old('email', session('reset_email_sent_to')) }}" required autofocus placeholder="admin@example.com" class="theme-input w-full">
                        </div>
                        <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                            Send Reset Link <i class="fas fa-arrow-right text-[10px] ml-1"></i>
                        </button>
                    </div>
                </form>

                @if(session('reset_email_sent_to'))
                    <div class="mt-4 pt-4" style="border-top: 1px solid var(--border-glass);">
                        <p class="text-xs mb-2" style="color: var(--text-dimmed);">Didn't receive the email? Check your spam folder, or resend the link.</p>
                        <form method="POST" action="{{ route('admin.password.resend') }}">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('reset_email_sent_to') }}">
                            <button type="submit" class="w-full py-2 text-xs font-semibold rounded-xl transition-colors flex items-center justify-center gap-1.5" style="color: var(--text-secondary); border: 1px solid var(--border-glass); background: var(--bg-card);">
                                <i class="fas fa-redo text-[9px]"></i> Resend reset link
                            </button>
                        </form>
                    </div>
                @endif

                <p class="mt-6 text-center text-xs">
                    <a href="{{ route('admin.login') }}" class="text-blue-400 hover:text-blue-300 font-semibold transition-colors">
                        <i class="fas fa-arrow-left text-[10px] mr-1"></i> Back to login
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
</body>
</html>
