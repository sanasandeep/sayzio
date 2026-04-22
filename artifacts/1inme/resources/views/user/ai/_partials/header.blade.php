{{-- Shared header for the four AI feature pages — shows the user's
     current AI credit balance and a top-up shortcut so each feature
     fails informatively when balance hits zero. --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <p class="text-xs uppercase tracking-wider text-white/40">{{ $kicker ?? 'AI' }}</p>
        <h1 class="text-2xl font-bold text-white mt-1">{{ $title }}</h1>
        @isset($subtitle)<p class="text-sm text-white/50 mt-1">{{ $subtitle }}</p>@endisset
    </div>
    <div class="text-right">
        <p class="text-xs text-white/40">Balance</p>
        <p class="text-2xl font-bold text-violet-300">{{ number_format($balance) }} <span class="text-sm">✦</span></p>
        <a href="{{ route('user.ai-credits.show') }}" class="text-xs text-violet-300 hover:underline">Top up →</a>
    </div>
</div>

@if(session('error'))
    <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
        {{ session('error') }}
    </div>
@endif
@if(session('status'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
        {{ session('status') }}
    </div>
@endif
