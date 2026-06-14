<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Space Grotesk','system-ui','sans-serif']}}}}</script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen relative overflow-hidden" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(124,58,237,0.12) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-24 -right-24 w-[400px] h-[400px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(139,92,246,0.08) 0%, transparent 70%);"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex items-center justify-center p-6 relative z-10">
        <div class="w-full max-w-sm text-center">
            <div class="mb-7">
                <a href="{{ route('home') }}" class="inline-flex items-center justify-center">@include('common.partials.brand-logo', ['height' => 'h-12'])</a>
            </div>

            <div class="w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-4" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                <i class="fas fa-key text-violet-400 text-xl"></i>
            </div>
            <h2 class="text-lg font-bold mb-1" style="color: var(--text-primary);">Enter Verification Code</h2>
            <p class="text-xs mb-6" style="color: var(--text-dimmed);">We sent a 6-digit code to your {{ session('otp_type') === 'mobile' ? 'WhatsApp' : 'email' }}</p>

            @if(session('status'))
                <div class="mb-4 p-3 rounded-xl text-violet-400 text-xs font-medium" style="border: 1px solid rgba(124,58,237,0.15); background: rgba(124,58,237,0.06);">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 rounded-xl text-red-400 text-xs font-medium" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('user.otp.verify') }}">
                @csrf
                <div class="mb-4">
                    <input type="text" name="code" maxlength="6" placeholder="000000" required autofocus
                           class="theme-input w-full text-center text-2xl tracking-[0.5em] font-bold py-3.5">
                </div>
                <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                    Verify & Login
                </button>
            </form>

            <form method="POST" action="{{ route('user.otp.resend') }}" class="mt-4">
                @csrf
                <button type="submit" class="text-xs font-medium text-violet-400 hover:text-violet-300 transition-colors">
                    <i class="fas fa-rotate-right text-[10px] mr-1"></i> Resend code
                </button>
            </form>

            <p class="mt-4 text-xs">
                <a href="{{ route('user.login') }}" class="text-violet-400 hover:text-violet-300 font-semibold transition-colors">
                    <i class="fas fa-arrow-left text-[10px] mr-1"></i> Use a different {{ session('otp_type') === 'mobile' ? 'number' : 'email' }}
                </a>
            </p>
        </div>
    </div>
</body>
</html>
