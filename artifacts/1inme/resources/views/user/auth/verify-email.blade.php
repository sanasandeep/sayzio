<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Space Grotesk', 'sans-serif'] } } } }</script>
</head>
<body class="bg-[#0f0a1a] min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="absolute inset-0">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[400px] h-[400px] bg-violet-500/15 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                <span class="text-white">1IN</span><span class="text-purple-400">ME</span>
            </a>
        </div>

        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8 text-center">
            <div class="w-16 h-16 bg-purple-600/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-envelope text-purple-400 text-2xl"></i>
            </div>
            <h2 class="text-xl font-semibold text-white mb-2">Verify Your Email</h2>
            <p class="text-gray-400 text-sm mb-6">We've sent a verification link to your email. Please check your inbox and click the link to verify.</p>

            @if(session('status'))
                <div class="mb-4 p-3 bg-green-500/20 border border-green-500/30 rounded-lg text-green-300 text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('user.verification.send') }}">
                @csrf
                <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                    Resend Verification Email
                </button>
            </form>

            <div class="mt-4">
                <a href="{{ route('user.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-400">Skip for now</a>
            </div>
        </div>
    </div>
</body>
</html>
