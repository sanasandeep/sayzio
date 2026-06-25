@extends('user.layouts.app')
@section('title', 'Buy Coins')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Buy coins</h1>
            <p class="text-sm text-white/40">Top up your wallet. Prices are in {{ $currency }}.</p>
        </div>
        <a href="{{ route('user.wallet.show') }}" class="text-sm text-violet-300 hover:underline">← Wallet</a>
    </div>

    @if($packages->isEmpty())
        <div class="text-center text-white/40 py-12">No coin packages are available right now.</div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($packages as $row)
            @php $pkg = $row['model']; $priced = $row['priced']; @endphp
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <h3 class="text-white font-semibold">{{ $pkg->name }}</h3>
                @if($pkg->description)<p class="text-xs text-white/40 mt-1">{{ $pkg->description }}</p>@endif
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-amber-300">{{ number_format($pkg->coin_amount) }}</span>
                    <span class="text-white/40">coins</span>
                </div>
                @if($pkg->bonus_coins > 0)
                    <p class="text-xs text-emerald-300 mt-1">+{{ number_format($pkg->bonus_coins) }} bonus coins</p>
                @endif
                @php $orig = $pkg->originalPriceDisplay($currency, (int)($priced['amount_minor'] ?? 0)); @endphp
                <div class="my-4 flex items-baseline gap-2">
                    @if($orig)<span class="text-lg text-white/40 line-through">{{ $orig['formatted'] }}</span>@endif
                    <span class="text-2xl font-bold text-white">{{ $priced['formatted'] }}</span>
                </div>

                <form method="POST" action="{{ route('user.wallet.buy.handoff') }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="coin_package_id" value="{{ $pkg->id }}">
                    <select name="gateway" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
                        <option value="">Pay with…</option>
                        @foreach($gateways as $g)
                            <option value="{{ $g->slug() }}">{{ $g->displayName() }}</option>
                        @endforeach
                    </select>
                    <button class="w-full px-4 py-2.5 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700">Buy now</button>
                </form>
            </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
