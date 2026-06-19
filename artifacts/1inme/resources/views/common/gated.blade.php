@php
    $title = $link->title ?? 'This page is gated';
    $messages = [
        'registered'  => 'This page is for registered members. Please log in to view.',
        'followers'   => 'Follow the creator to unlock this page.',
        'subscribers' => 'Subscribe to the creator to unlock this page.',
    ];
    $msg = $messages[$reason] ?? 'You do not have access to this page.';
@endphp
<!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Locked</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-screen flex items-center justify-center text-white" style="background: radial-gradient(ellipse at top, #1e1b4b 0%, #020617 60%);">
    <main class="max-w-md w-full mx-auto p-6 text-center">
        <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-8 backdrop-blur-sm">
            <div class="w-16 h-16 mx-auto mb-5 rounded-full flex items-center justify-center"
                 style="background: rgba(99,102,241,.15); color:#a5b4fc;">
                <i class="fas fa-{{ $reason === 'subscribers' ? 'gem' : ($reason === 'followers' ? 'user-plus' : 'lock') }} text-2xl"></i>
            </div>
            <h1 class="text-xl font-bold mb-2">{{ $title }}</h1>
            <p class="text-sm text-white/60 mb-6">{{ $msg }}</p>

            @if($reason === 'registered')
                <a href="{{ url('/login') }}"
                   class="inline-block w-full px-5 py-3 rounded-2xl bg-indigo-500 hover:bg-indigo-400 text-white font-semibold text-sm">
                    <i class="fas fa-sign-in-alt mr-2"></i>Log in to continue
                </a>
            @elseif($reason === 'followers')
                <p class="text-xs text-white/50 mb-3">Open <strong>{{ '@' . ($link->user->handle ?? $link->user->name ?? 'creator') }}</strong>'s biolink and tap <em>Follow</em> to unlock.</p>
                @if($link->user)
                    <a href="{{ route('creators.index') }}" class="inline-block w-full px-5 py-3 rounded-2xl bg-fuchsia-500 hover:bg-fuchsia-400 text-white font-semibold text-sm">
                        <i class="fas fa-user-plus mr-2"></i>Find the creator
                    </a>
                @endif
            @elseif($reason === 'subscribers')
                <p class="text-xs text-white/50 mb-3">This is paid/subscriber-only content from <strong>{{ $link->user->name ?? 'the creator' }}</strong>.</p>
                <a href="{{ route('creators.index') }}" class="inline-block w-full px-5 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-white font-semibold text-sm">
                    <i class="fas fa-gem mr-2"></i>Subscribe to unlock
                </a>
            @endif
        </div>
        <p class="text-[11px] mt-4 text-white/30">Powered by 1INME</p>
    </main>
</body>
</html>
