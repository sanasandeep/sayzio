<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Space Grotesk', 'sans-serif'] } } } }</script>
    @include('common.partials.theme-styles')
</head>
<body class="bg-transparent min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="absolute inset-0">
        <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-purple-800/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-indigo-600/15 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block text-3xl font-bold tracking-tight">
                <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
            </a>
            <p class="text-purple-300/60 mt-2">Admin Panel</p>
        </div>

        <div class="backdrop-blur-xl glass rounded-2xl p-8">
            <div class="flex items-center gap-2 mb-6">
                <div class="w-8 h-8 bg-purple-600/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-shield-alt text-purple-400 text-sm"></i>
                </div>
                <h2 class="text-xl font-semibold " style="color: var(--text-primary);">Admin Sign In</h2>
            </div>

            @if(session('error'))
                <div class="mb-4 p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-red-300 text-sm">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg theme-text-primary placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                        @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg theme-text-primary placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                        @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm theme-text-muted">
                            <input type="checkbox" name="remember" class="rounded border-gray-600 bg-white/5 text-purple-600 focus:ring-purple-500">
                            Remember me
                        </label>
                        <a href="{{ route('admin.password.request') }}" class="text-sm text-purple-400 hover:text-purple-300">Forgot password?</a>
                    </div>

                    <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                        Sign In
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-white/10">
                <p class="text-center text-xs text-gray-500 mb-3">Quick access</p>
                <form method="POST" action="{{ route('admin.demo.login') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-gray-300 hover:bg-purple-600/20 hover:border-purple-500/30 hover:text-purple-300 transition">
                        <i class="fas fa-shield-alt"></i> Demo Admin Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
