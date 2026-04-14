<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Space Grotesk', 'sans-serif'] } } } }</script>
</head>
<body class="bg-[#0f0a1a] min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="absolute inset-0">
        <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-purple-800/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-indigo-600/15 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                <span class="text-white">1IN</span><span class="text-purple-400">ME</span>
            </a>
            <p class="text-purple-300/60 mt-2">Admin Password Reset</p>
        </div>

        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8">
            <div class="w-14 h-14 bg-purple-600/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-lock text-purple-400 text-xl"></i>
            </div>
            <h2 class="text-xl font-semibold text-white mb-2 text-center">Forgot Password?</h2>
            <p class="text-gray-400 text-sm mb-6 text-center">Enter your admin email to receive a reset link.</p>

            @if(session('status'))
                <div class="mb-4 p-3 bg-green-500/20 border border-green-500/30 rounded-lg text-green-300 text-sm">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-red-300 text-sm">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.password.email') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                </div>
                <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                    Send Reset Link
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-6">
                <a href="{{ route('admin.login') }}" class="text-purple-400 hover:text-purple-300">
                    <i class="fas fa-arrow-left mr-1"></i> Back to login
                </a>
            </p>
        </div>
    </div>
</body>
</html>
