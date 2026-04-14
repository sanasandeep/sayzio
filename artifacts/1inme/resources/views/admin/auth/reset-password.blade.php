<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Admin</title>
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
            <p class="text-purple-300/60 mt-2">Set New Admin Password</p>
        </div>

        <div class="backdrop-blur-xl glass rounded-2xl p-8">
            <h2 class="text-xl font-semibold font-semibold mb-6" style="color: var(--text-primary);">Reset Password</h2>

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-red-300 text-sm">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                        <input type="password" name="password" required autofocus
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg theme-text-primary placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg theme-text-primary placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                    </div>
                    <button type="submit" class="w-full bg-purple-600 text-white py-2.5 rounded-lg font-medium hover:bg-purple-700 transition shadow-lg shadow-purple-600/20">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
