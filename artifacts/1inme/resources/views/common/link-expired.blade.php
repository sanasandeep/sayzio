@php
    // The controller may pass an explicit reason (e.g. 'banned_country').
    // Otherwise we derive it from the link state so the page always
    // explains the most specific cause.
    $reason = $reason ?? (isset($link) ? $link->unavailabilityReason() : null) ?? 'unavailable';

    $tz       = isset($link) ? (($link->settings['timezone'] ?? null) ?: 'UTC') : 'UTC';
    $nextOpen = ($reason === 'closed_hours' && isset($link)) ? $link->nextActiveWindowOpening() : null;

    $presets = [
        'expired'        => ['title' => 'Link Expired',       'icon' => 'clock',    'tint' => 'amber',  'msg' => 'This link has reached its expiration date and is no longer available.'],
        'limit_reached'  => ['title' => 'Click Limit Reached','icon' => 'gauge',    'tint' => 'amber',  'msg' => 'This link has reached its maximum number of allowed clicks.'],
        'scheduled'      => ['title' => 'Not Yet Available',  'icon' => 'calendar', 'tint' => 'sky',    'msg' => 'This link is scheduled to go live soon. Please check back later.'],
        'inactive'       => ['title' => 'Link Disabled',      'icon' => 'pause',    'tint' => 'slate',  'msg' => 'This link has been turned off by its owner.'],
        'closed_hours'   => ['title' => 'Currently Closed',   'icon' => 'moon',     'tint' => 'violet', 'msg' => 'This link is only active during specific hours and is currently outside its scheduled window.'],
        'banned_country' => ['title' => 'Not Available Here', 'icon' => 'globe',    'tint' => 'rose',   'msg' => 'This link is not accessible from your region.'],
        'unavailable'    => ['title' => 'Link Unavailable',   'icon' => 'alert',    'tint' => 'amber',  'msg' => 'This link is no longer available.'],
    ];
    $p = $presets[$reason] ?? $presets['unavailable'];

    $tints = [
        'amber'  => ['bg' => 'rgba(245,158,11,0.10)', 'br' => 'rgba(245,158,11,0.25)', 'fg' => 'text-amber-400',  'glow' => 'rgba(245,158,11,0.10)'],
        'sky'    => ['bg' => 'rgba(56,189,248,0.10)', 'br' => 'rgba(56,189,248,0.25)', 'fg' => 'text-sky-400',    'glow' => 'rgba(56,189,248,0.10)'],
        'slate'  => ['bg' => 'rgba(148,163,184,0.10)','br' => 'rgba(148,163,184,0.25)','fg' => 'text-slate-300',  'glow' => 'rgba(148,163,184,0.08)'],
        'violet' => ['bg' => 'rgba(124,58,237,0.10)', 'br' => 'rgba(124,58,237,0.25)', 'fg' => 'text-violet-400', 'glow' => 'rgba(124,58,237,0.12)'],
        'rose'   => ['bg' => 'rgba(244,63,94,0.10)',  'br' => 'rgba(244,63,94,0.25)',  'fg' => 'text-rose-400',   'glow' => 'rgba(244,63,94,0.10)'],
    ];
    $t = $tints[$p['tint']] ?? $tints['amber'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $p['title'] }} - Sayzio</title>
    @include('common.partials.default-icons')
    @if(isset($link) && $link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #0a0b10; }
        h1 { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; letter-spacing: -0.022em; }
        .card { background: #13141b; border: 1px solid #1f2128; box-shadow: 0 12px 40px rgba(0,0,0,0.4); }
        .glow { background: radial-gradient(ellipse 700px 500px at 50% 30%, {{ $t['glow'] }}, transparent 60%); position: fixed; inset: 0; pointer-events: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-white">
    <div class="glow"></div>
    <div class="w-full max-w-md relative z-10">
        <div class="card rounded-2xl p-10 text-center">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5"
                 style="background: {{ $t['bg'] }}; border: 1px solid {{ $t['br'] }};">
                <svg class="w-8 h-8 {{ $t['fg'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    @switch($p['icon'])
                        @case('clock')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            @break
                        @case('calendar')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            @break
                        @case('moon')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                            @break
                        @case('globe')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.6 9h16.8M3.6 15h16.8M11 3a17 17 0 000 18m2-18a17 17 0 010 18M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            @break
                        @case('pause')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            @break
                        @case('gauge')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            @break
                        @default
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @endswitch
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">{{ $p['title'] }}</h1>
            <p class="text-white/50 text-sm leading-relaxed mb-6">{{ $p['msg'] }}</p>

            @if($nextOpen)
                <div class="rounded-xl px-4 py-3 mb-6 text-left"
                     style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.2);">
                    <div class="text-[10px] uppercase tracking-wider text-violet-300/70 font-bold mb-0.5">Next opens</div>
                    <div class="text-sm text-white font-medium">
                        {{ $nextOpen->isToday() ? 'Today at ' . $nextOpen->format('g:i A')
                           : ($nextOpen->isTomorrow() ? 'Tomorrow at ' . $nextOpen->format('g:i A')
                           : $nextOpen->format('D, M j · g:i A')) }}
                    </div>
                    <div class="text-[11px] text-white/40 mt-0.5">{{ $tz }} · {{ $nextOpen->diffForHumans(['parts' => 2, 'short' => true]) }}</div>
                </div>
            @endif

            <div class="text-xs text-white/30">
                Powered by <a href="{{ url('/') }}" class="text-violet-400 hover:text-violet-300 font-medium">Sayzio</a>
            </div>
        </div>
    </div>
</body>
</html>
