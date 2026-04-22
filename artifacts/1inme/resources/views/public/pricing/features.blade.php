@extends('public.layouts.site')

@section('title', 'Premium features')

@section('content')
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-3">
            <div class="text-xs font-bold uppercase tracking-[.2em] text-violet-400">Premium features</div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">Everything that unlocks on a paid plan.</h1>
            <p class="text-lg text-gray-400">Plain-English explanations of every premium capability and which plans include it.</p>
        </div>

        <div class="mt-14 space-y-10">
            @foreach($grouped as $group => $items)
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-violet-400 mb-3">{{ $group }}</h2>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.02] divide-y divide-white/5">
                        @foreach($items as $item)
                            <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 items-start">
                                <div>
                                    <div class="text-white font-semibold">{{ $item['name'] }}</div>
                                    <p class="text-sm text-gray-400 mt-1">{{ $item['description'] }}</p>
                                </div>
                                <div class="flex flex-wrap gap-1.5 sm:justify-end">
                                    @if(empty($item['unlocked_by']))
                                        <span class="text-[11px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/5 text-gray-500">Not unlocked by any plan</span>
                                    @else
                                        @foreach($item['unlocked_by'] as $slug)
                                            @php $name = $planMeta[$slug]['name'] ?? ucfirst($slug); @endphp
                                            <span class="text-[11px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300 border border-violet-500/20">{{ $name }}</span>
                                        @endforeach
                                    @endif
                                    @if(!empty($item['unit']))
                                        <span class="text-[11px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/5 text-gray-400">{{ $item['unit'] }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-14 text-center">
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('site.pricing') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-violet-600 text-white hover:bg-violet-700 text-sm font-bold">
                    <i class="fas fa-tags"></i> See pricing plans
                </a>
                <a href="{{ route('site.coins') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium">
                    <i class="fas fa-coins text-amber-400"></i> Coin packages
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
