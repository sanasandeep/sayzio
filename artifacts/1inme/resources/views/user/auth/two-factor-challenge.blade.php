<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-factor verification - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Space Grotesk','system-ui','sans-serif']}}}}</script>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen relative overflow-hidden" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="min-h-screen flex items-center justify-center p-6 relative z-10">
        <div class="w-full max-w-sm text-center">
            <div class="mb-7">
                <a href="{{ route('home') }}" class="inline-flex items-center justify-center">@include('common.partials.brand-logo', ['height' => 'h-12'])</a>
            </div>

            <div class="w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-4" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                <i class="fas fa-shield-halved text-violet-400 text-xl"></i>
            </div>
            <h2 class="text-lg font-bold mb-1" style="color: var(--text-primary);">Two-factor verification</h2>
            <p class="text-xs mb-6" style="color: var(--text-dimmed);">Enter the 6-digit code from your authenticator app, or use a recovery code.</p>

            @if($errors->any())
                <div class="mb-4 p-3 rounded-xl text-red-400 text-xs font-medium" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('user.account.two-factor.challenge.verify') }}">
                @csrf
                <div class="mb-4">
                    <input type="text" name="code" required autofocus
                           placeholder="000000"
                           class="theme-input w-full text-center text-2xl tracking-[0.4em] font-bold py-3.5">
                </div>
                <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                    Verify &amp; sign in
                </button>
            </form>

            <p class="mt-4 text-xs">
                <a href="{{ route('user.login') }}" class="text-violet-400 hover:text-violet-300 font-semibold">
                    <i class="fas fa-arrow-left text-[10px] mr-1"></i> Cancel
                </a>
            </p>
        </div>
    </div>
</body>
</html>
