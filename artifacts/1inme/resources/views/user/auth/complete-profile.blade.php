<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Complete Your Profile — {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-black text-white" style="font-family: 'Space Grotesk', sans-serif;">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-16"
         x-data="{ name: '', busy: false, error: '' }">

        {{-- Logo --}}
        <div class="mb-10">
            <a href="{{ route('home') }}">
                <img src="/img/brand/wordmark-light.svg" alt="{{ config('app.name') }}" class="h-8 mx-auto" onerror="this.style.display='none'">
                <span class="text-2xl font-bold tracking-tight" x-show="!$el.previousElementSibling || $el.previousElementSibling.style.display === 'none'">
                    {{ config('app.name') }}
                </span>
            </a>
        </div>

        {{-- Card --}}
        <div class="w-full max-w-sm bg-white/5 border border-white/10 rounded-2xl p-8 backdrop-blur-sm">
            <h1 class="text-2xl font-bold tracking-tight mb-1">One last step</h1>
            <p class="text-sm text-white/60 mb-6">Choose the name your followers will see.</p>

            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/20 px-4 py-3 text-sm text-red-300">
                    {{ $errors->first('name') }}
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 rounded-xl bg-green-500/10 border border-green-500/20 px-4 py-3 text-sm text-green-300">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('user.complete.profile.save') }}"
                  x-on:submit="busy = true">
                @csrf

                <div class="mb-5">
                    <label for="name" class="block text-sm font-medium text-white/80 mb-1.5">
                        Display name
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', Auth::user()?->name ?? '') }}"
                        required
                        minlength="2"
                        maxlength="120"
                        autofocus
                        autocomplete="name"
                        placeholder="Your name"
                        class="w-full rounded-xl bg-white/10 border border-white/15 px-4 py-3 text-sm text-white placeholder-white/30
                               focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500,#7c3aed)] focus:border-transparent
                               transition-all duration-150"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="busy"
                    class="w-full rounded-xl bg-[var(--color-primary-600,#7c3aed)] hover:bg-[var(--color-primary-500,#8b5cf6)]
                           text-white font-semibold text-sm px-4 py-3 transition-all duration-150
                           disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span x-show="!busy">Continue</span>
                    <span x-show="busy" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Saving…
                    </span>
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-white/40">
                You can always change this later in your profile settings.
            </p>
        </div>

        {{-- Sign out escape hatch --}}
        <form method="POST" action="{{ route('user.logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="text-xs text-white/30 hover:text-white/50 transition-colors">
                Sign out
            </button>
        </form>
    </div>
</body>
</html>
