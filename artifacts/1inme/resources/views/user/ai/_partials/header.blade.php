{{-- Shared header for the four AI feature pages — shows the user's
     current coin balance and a top-up shortcut so each feature fails
     informatively when the wallet hits zero. AI usage is charged from
     the coin wallet. --}}
@include('user.partials.page-hero',[
    'title'=>$title,
    'subtitle'=>$subtitle??null,
    'emblemIcon'=>'fa-wand-magic-sparkles',
    'favicon'=>null,'url'=>null,'back'=>route('user.dashboard'),
    'chips'=>[['text'=>$kicker??'AI','icon'=>'fa-wand-magic-sparkles'],['text'=>number_format($balance).' coins','icon'=>'fa-coins']],
    'actions'=>[['label'=>'Top up coins','url'=>route('user.wallet.buy'),'icon'=>'fa-plus','class'=>'btn-ghost']],
])

@php
    // Low-balance nudge: AI runs straight from the coin wallet, so a user
    // whose plan unlocks AI can still walk into the hard
    // InsufficientCoinsForAiException gate mid-action. Warn *before* they
    // spend when the wallet is at/below the shared threshold, and stay
    // silent once they have comfortable headroom.
    $__aiLowThreshold = \App\Services\AI\AiUsageCharger::lowBalanceThreshold();
    $__aiLowBalance = (int) $balance <= $__aiLowThreshold;
@endphp
@if($__aiLowBalance)
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-500/25 bg-amber-500/[0.08] px-4 py-3 text-sm text-amber-200">
        <i class="fas fa-triangle-exclamation mt-0.5 text-amber-300"></i>
        <div class="flex-1">
            @if((int) $balance <= 0)
                You’re out of coins. AI actions are paid from your coin wallet, so they’ll fail until you top up.
            @else
                You have only <span class="font-semibold text-amber-100">{{ number_format($balance) }}</span>
                {{ \Illuminate\Support\Str::plural('coin', (int) $balance) }} left, AI actions are paid from your coin wallet and may run out part-way.
            @endif
            <a href="{{ route('user.wallet.buy') }}" class="font-semibold text-amber-100 underline hover:no-underline">Top up coins</a>.
        </div>
    </div>
@endif

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
