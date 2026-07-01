@extends('user.layouts.app')
@section('title', 'AI Marketing Strategist')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => 'AI Marketing Strategist',
        'subtitle' => 'Your AI digital performer — feed in your own Sayzio data and get an organic + paid plan built around real features.',
        'balance'  => $balance,
    ])

    <div class="flex justify-end mb-5">
        <a href="{{ route('user.ai.marketing-strategist.create') }}"
           class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
            <i class="fas fa-wand-magic-sparkles mr-1"></i> New strategy
        </a>
    </div>

    @if($strategies->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
            <i class="fas fa-bullseye text-3xl text-blue-300/70"></i>
            <h2 class="text-lg font-semibold text-white mt-4">No strategies yet</h2>
            <p class="text-sm text-white/50 mt-1 max-w-md mx-auto">
                Choose which of your data to share, set a goal, and the strategist will draft a
                marketing plan you can refine and act on with one click.
            </p>
            <a href="{{ route('user.ai.marketing-strategist.create') }}"
               class="inline-block mt-5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Build your first strategy
            </a>
        </div>
    @else
        <ul class="space-y-3">
            @foreach($strategies as $s)
                <li>
                    <a href="{{ route('user.ai.marketing-strategist.show', $s->id) }}"
                       class="block rounded-2xl border border-white/10 bg-white/[0.03] p-4 hover:bg-white/[0.06] transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="text-white font-semibold truncate">{{ $s->title }}</h3>
                                <p class="text-sm text-white/50 mt-1 line-clamp-2">{{ $s->goalSummary(160) }}</p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach((array) $s->sources as $src)
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-white/5 text-white/50">
                                            {{ \App\Services\AI\MarketingStrategistService::SOURCES[$src]['label'] ?? $src }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-[11px] text-white/30">{{ $s->created_at?->diffForHumans() }}</span>
                                @if((int) $s->credits_spent > 0)
                                    <p class="text-[11px] text-blue-300/70 mt-1">{{ number_format($s->credits_spent) }} coins</p>
                                @endif
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
