<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - {{ config('app.name') }}</title>
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
            <p class="text-purple-300/60 mt-2">Create your account</p>
        </div>

        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8">
            <h2 class="text-xl font-semibold text-white mb-6">Get started for free</h2>

            <form method="POST" action="{{ route('user.register.submit') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                        @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                        @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Mobile <span class="text-gray-500">(optional)</span></label>
                        <input type="text" name="mobile" value="{{ old('mobile') }}" placeholder="+1234567890"
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                        @error('mobile')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                        @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                    </div>

                    <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                        Create Account
                    </button>
                </div>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-gray-500">
            Already have an account?
            <a href="{{ route('user.login') }}" class="text-purple-400 hover:text-purple-300 font-medium">Sign in</a>
        </p>
    </div>
</body>
</html>
