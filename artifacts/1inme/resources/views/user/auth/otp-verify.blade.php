<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - {{ config('app.name') }}</title>
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

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
            </a>
        </div>

        <div class="glass backdrop-blur-xl rounded-2xl p-8 text-center">
            <div class="w-16 h-16 bg-purple-600/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-key text-purple-400 text-2xl"></i>
            </div>
            <h2 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Enter Verification Code</h2>
            <p class="text-sm mb-6" style="color: var(--text-muted);">We sent a 6-digit code to your {{ session('otp_type', 'email') }}</p>

            @if(session('status'))
                <div class="mb-4 p-3 bg-purple-600/20 border border-purple-500/30 rounded-lg text-purple-300 text-sm">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-red-300 text-sm">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('user.otp.verify') }}">
                @csrf
                <div class="mb-4">
                    <input type="text" name="code" maxlength="6" placeholder="000000" required autofocus
                           class="w-full text-center text-3xl tracking-[0.5em] font-bold px-4 py-4 rounded-lg outline-none transition focus:ring-2 focus:ring-purple-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                </div>
                <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                    Verify & Login
                </button>
            </form>

            <p class="mt-6 text-sm">
                <a href="{{ route('user.login') }}" class="text-purple-400 hover:text-purple-300">
                    <i class="fas fa-arrow-left mr-1"></i> Back to login
                </a>
            </p>
        </div>
    </div>
</body>
</html>
