<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Unavailable - 1INME</title>
    @if(isset($link) && $link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #0a0b10; }
        h1 { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; letter-spacing: -0.022em; }
        .card { background: #13141b; border: 1px solid #1f2128; box-shadow: 0 12px 40px rgba(0,0,0,0.4); }
        .glow { background: radial-gradient(ellipse 700px 500px at 50% 30%, rgba(124,58,237,0.10), transparent 60%); position: fixed; inset: 0; pointer-events: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-white">
    <div class="glow"></div>
    <div class="w-full max-w-md relative z-10">
        <div class="card rounded-2xl p-10 text-center">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5"
                 style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.25);">
                <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Link Unavailable</h1>
            <p class="text-white/50 text-sm leading-relaxed mb-6">
                @php
                    $msg = 'This link is no longer available.';
                    if (isset($link)) {
                        if ($link->expires_at && $link->expires_at->isPast()) {
                            $msg = 'This link has expired and is no longer accessible.';
                        } elseif ($link->isScheduledFuture()) {
                            $msg = 'This link is not yet available. Please check back later.';
                        } elseif (!$link->is_active) {
                            $msg = 'This link has been deactivated by its owner.';
                        }
                    }
                @endphp
                {{ $msg }}
            </p>
            <div class="text-xs text-white/30">
                Powered by <a href="{{ url('/') }}" class="text-violet-400 hover:text-violet-300 font-medium">1INME</a>
            </div>
        </div>
    </div>
</body>
</html>
