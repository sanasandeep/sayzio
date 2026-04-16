<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Required - 1INME</title>
    @if(isset($link) && $link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Space Grotesk', system-ui, sans-serif; background: #0a0612; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(24px) saturate(1.2); border: 1px solid rgba(255,255,255,0.06); box-shadow: 0 4px 32px rgba(0,0,0,0.4); }
        .bg-mesh { position: fixed; inset: 0; pointer-events: none; z-index: 0; background: radial-gradient(ellipse 600px 400px at 15% 20%, rgba(27,132,255,0.07), transparent), radial-gradient(ellipse 500px 350px at 85% 75%, rgba(62,151,255,0.05), transparent); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-white">
    <div class="bg-mesh"></div>
    <div class="w-full max-w-sm relative z-10">
        <div class="glass rounded-2xl p-8 text-center">
            <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white mb-2">Password Required</h1>
            <p class="text-white/40 text-sm mb-6">This link is password protected. Enter the password to continue.</p>

            @if(isset($error))
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-sm px-4 py-2 rounded-xl mb-4">{{ $error }}</div>
            @endif

            <form method="POST" action="{{ route('redirect.handle', $link->alias) }}">
                @csrf
                <input type="password" name="password" placeholder="Enter password"
                       class="w-full rounded-[0.625rem] px-3.5 py-2.5 text-sm text-white placeholder-white/30 mb-4 outline-none transition-all" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.06);" required autofocus>
                <button type="submit" class="w-full py-2.5 rounded-[0.625rem] text-sm font-semibold text-white transition-all" style="background: linear-gradient(135deg, #3e97ff, #1b84ff); box-shadow: 0 4px 16px rgba(27,132,255,0.25);">Continue</button>
            </form>
        </div>
        <p class="text-center text-xs text-white/20 mt-4">Powered by <span class="text-white/30">1IN</span><span class="text-blue-400/60">ME</span></p>
    </div>
    @include('common.partials.pixel-scripts', ['link' => $link])
</body>
</html>
