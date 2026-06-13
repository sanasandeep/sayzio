<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Admin</title>
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
        <div class="absolute -top-32 -right-32 w-[500px] h-[500px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(124,58,237,0.12) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-24 -left-24 w-[400px] h-[400px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%);"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex items-center justify-center p-6 relative z-10">
        <div class="w-full max-w-sm">
            <div class="text-center mb-7">
                <a href="{{ route('home') }}" class="inline-flex items-center justify-center">@include('common.partials.brand-logo', ['height' => 'h-12'])</a>
                <p class="text-sm mt-1" style="color: var(--text-dimmed);">Set new admin password</p>
            </div>

            @if($errors->any())
                <div class="mb-4 p-3 rounded-xl text-red-400 text-xs font-medium" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">New Password</label>
                        <input type="password" name="password" required autofocus placeholder="Min 8 characters" class="theme-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Confirm Password</label>
                        <input type="password" name="password_confirmation" required placeholder="Repeat password" class="theme-input w-full">
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
