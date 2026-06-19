<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Age verification — {{ $creator->name }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-slate-950 min-h-screen text-slate-100 flex items-center justify-center px-4 py-10">
    <div class="max-w-lg w-full bg-slate-900/80 backdrop-blur border border-white/10 rounded-2xl shadow-2xl p-8 text-center">
        <div class="inline-flex w-14 h-14 rounded-full bg-rose-600/20 text-rose-300 items-center justify-center mb-5">
            <i class="fas fa-shield-halved text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-white">This profile contains 18+ content</h1>
        <p class="text-slate-300 text-sm mt-3 leading-relaxed">
            <strong class="text-white">{{ $creator->name }}</strong>
            (<span class="text-slate-400">@{{ $creator->handle }}</span>) has marked their profile as
            adult content. You must confirm you are at least 18 years old (or the age of majority in
            your jurisdiction) to continue.
        </p>
        <p class="text-xs text-slate-500 mt-4">
            We'll remember your choice on this device for {{ \App\Modules\Common\Services\AgeGate::DAYS }} days.
        </p>

        <form method="POST" action="{{ route('age-gate.confirm') }}" class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
            @csrf
            <input type="hidden" name="handle" value="{{ $creator->handle }}">
            <input type="hidden" name="confirm" value="1">
            <button type="submit"
                    class="w-full px-5 py-3 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold inline-flex items-center justify-center gap-2">
                <i class="fas fa-check"></i> I am 18 or older
            </button>
            <a href="{{ route('age-gate.leave') }}"
               class="w-full px-5 py-3 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-slate-200 text-sm font-semibold inline-flex items-center justify-center gap-2">
                <i class="fas fa-arrow-left"></i> I am under 18
            </a>
        </form>

        <p class="text-[11px] text-slate-500 mt-6 leading-relaxed">
            By confirming, you agree this is a free, lawful expression of consent and you understand
            the content on this profile may include adult themes. Continuing in violation of local
            law is your own responsibility.
        </p>
    </div>
</body>
</html>
