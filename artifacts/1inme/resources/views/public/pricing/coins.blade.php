@extends('public.layouts.site')

@section('title', 'Coin packages')

@section('content')
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-3">
            <div class="text-xs font-bold uppercase tracking-[.2em] text-amber-400">Coin packages</div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">Top up coins, unlock add-ons.</h1>
            <p class="text-lg text-gray-400">Coins let you activate paid add-ons on demand without changing plans.</p>
            @include('public.pricing._currency_badge', [
                'currency'       => $currency,
                'currencySource' => $currencySource,
                'user'           => $user,
                'switchRoute'    => 'upgrade.public.switch-currency',
            ])
        </div>

        @if(!$wallet_enabled)
            <div class="max-w-2xl mx-auto mt-12 rounded-2xl border border-amber-400/20 bg-amber-400/5 p-6 text-amber-200">
                Coin top-ups aren't enabled on this site yet — check back soon.
            </div>
        @elseif($packages->isEmpty())
            <div class="max-w-2xl mx-auto mt-12 rounded-2xl border border-white/10 bg-white/[0.02] p-6 text-gray-400 text-center">
                No coin packages are available right now.
            </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mt-12">
            @foreach($packages as $row)
                @php $pkg = $row['model']; @endphp
                <div class="relative rounded-2xl border border-white/10 bg-white/[0.02] p-6 flex flex-col">
                    @if($pkg->bonus_coins > 0)
                        <div class="absolute -top-3 right-6 px-3 py-1 bg-amber-400 text-[#1e2330] text-[10px] font-bold rounded-full uppercase tracking-wider">+{{ number_format($pkg->bonus_coins) }} bonus</div>
                    @endif
                    <div class="text-xs uppercase tracking-wider text-gray-400">{{ $pkg->name }}</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-semibold text-white">{{ number_format($row['total_coins']) }}</span>
                        <span class="text-amber-300">🪙 coins</span>
                    </div>
                    @if($pkg->bonus_coins > 0)
                        <div class="text-[11px] text-gray-500">{{ number_format($pkg->coin_amount) }} base + {{ number_format($pkg->bonus_coins) }} bonus</div>
                    @endif
                    @if($pkg->description)
                        <p class="text-sm text-gray-400 mt-3 flex-grow">{{ $pkg->description }}</p>
                    @else
                        <div class="flex-grow"></div>
                    @endif
                    <div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between">
                        <span class="text-2xl font-bold text-white">{{ $row['formatted'] ?? '—' }}</span>
                        @auth
                            <a href="{{ route('user.wallet.buy') }}" class="px-4 py-2 bg-amber-400 text-[#1e2330] rounded-xl text-sm font-bold hover:bg-amber-300">Buy now</a>
                        @else
                            <a href="{{ route('user.register') }}" class="px-4 py-2 bg-amber-400 text-[#1e2330] rounded-xl text-sm font-bold hover:bg-amber-300">Sign up to buy</a>
                        @endauth
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        <div class="mt-14 text-center">
            <p class="text-gray-400">Coins are useful, but a subscription is usually a better deal for steady use.</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('site.pricing') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium">
                    <i class="fas fa-tags text-violet-400"></i> Compare subscription plans
                </a>
                <a href="{{ route('site.premium-features') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium">
                    <i class="fas fa-star text-violet-400"></i> See premium features
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
