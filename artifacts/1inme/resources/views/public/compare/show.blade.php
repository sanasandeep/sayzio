@extends('public.layouts.site')
@section('title', 'Sayzio vs ' . ($competitor['name'] ?? ''))

@section('content')
@php
    use App\Modules\Common\Support\ComparisonContent;

    /** @var array<string, mixed> $competitor */
    $c       = $competitor;
    $accent  = $c['accent'] ?? '#3d6bff';
    $faqs    = $c['faqs'] ?? [];
    $theyWin = $c['they_win'] ?? [];
    $weWin   = $c['we_win'] ?? [];
    $others  = array_values(array_filter(ComparisonContent::index(), fn ($o) => $o['key'] !== $c['key']));
@endphp

{{-- ─────────────  HERO  ───────────── --}}
<section class="relative pt-20 pb-14 lg:pt-28 lg:pb-16 overflow-hidden">
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none" aria-hidden="true"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <nav class="mb-6 text-xs text-gray-500" aria-label="Breadcrumb">
            <a href="{{ route('site.compare.index') }}" class="hover:text-white transition-colors">Compare</a>
            <span class="mx-2 text-white/20">/</span>
            <span class="text-gray-300">Sayzio vs {{ $c['name'] }}</span>
        </nav>

        {{-- VS chips --}}
        <div data-anim="fade-up" class="flex items-center justify-center gap-3 sm:gap-5 mb-7">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl font-bold text-white"
                  style="background: var(--c1, #3d6bff);">
                <i class="fas fa-bolt"></i> Sayzio
            </span>
            <span class="text-sm font-bold uppercase tracking-widest text-gray-500">vs</span>
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl font-semibold text-gray-100 border border-white/15 bg-white/5">
                <i class="fas {{ $c['icon'] }}" style="color: {{ $accent }};"></i> {{ $c['name'] }}
            </span>
        </div>

        <h1 data-anim="fade-up" class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
            {{ $c['headline'] }}
        </h1>
        @if(!empty($c['intro']))
            <p data-anim="fade-up" class="mt-5 text-lg text-gray-400 max-w-2xl mx-auto leading-relaxed">
                {{ $c['intro'] }}
            </p>
        @endif

        {{-- Score summary --}}
        <div data-anim="fade-up" class="mt-7 inline-flex flex-wrap items-center justify-center gap-2">
            <span class="cmp-badge cmp-badge-ours"><i class="fas fa-bolt"></i> Sayzio · {{ $c['our_score'] }}/{{ $c['total'] }}</span>
            <span class="cmp-badge">{{ $c['name'] }} · {{ $c['rival_score'] }}/{{ $c['total'] }}</span>
            <span class="cmp-badge cmp-badge-ours"><i class="fas fa-trophy text-[10px]"></i> +{{ $c['wins'] }} feature lead</span>
        </div>

        <div data-anim="fade-up" class="mt-7 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ url('/register') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                <i class="fas fa-rocket text-xs"></i> Switch to Sayzio free
            </a>
            <a href="#head-to-head" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                Jump to the table
            </a>
        </div>
    </div>
</section>

{{-- ─────────────  HEAD-TO-HEAD TABLE (shared component, locked rival) ───────────── --}}
@include('public.partials._compare', [
    'compact'     => false,
    'only'        => $c['key'],
    'anchorId'    => 'head-to-head',
    'hideHeading' => true,
])

{{-- ─────────────  WHERE EACH TOOL WINS  ───────────── --}}
<section class="relative pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">An honest take</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Where each tool wins</h2>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">No tool is best at everything. Here's a straight read on when {{ $c['name'] }} is the right call, and where Sayzio pulls ahead.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-5">
            {{-- Where Sayzio wins --}}
            <article class="glass rounded-3xl p-7" data-anim="fade-right">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-10 h-10 rounded-2xl flex items-center justify-center text-white"
                          style="background: var(--c1, #3d6bff);">
                        <i class="fas fa-bolt"></i>
                    </span>
                    <h3 class="text-lg font-bold text-white">Where Sayzio wins</h3>
                </div>
                <ul class="space-y-3">
                    @foreach($weWin as $point)
                        <li class="flex items-start gap-3 text-sm text-gray-300">
                            <i class="fas fa-circle-check mt-0.5" style="color:var(--c4, #1bd4d9);"></i>
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </article>

            {{-- Where the rival wins --}}
            <article class="glass rounded-3xl p-7" data-anim="fade-left">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-10 h-10 rounded-2xl flex items-center justify-center text-white"
                          style="background: {{ $accent }};">
                        <i class="fas {{ $c['icon'] }}"></i>
                    </span>
                    <h3 class="text-lg font-bold text-white">Where {{ $c['name'] }} wins</h3>
                </div>
                <ul class="space-y-3">
                    @foreach($theyWin as $point)
                        <li class="flex items-start gap-3 text-sm text-gray-300">
                            <i class="fas fa-circle-check mt-0.5" style="color: {{ $accent }};"></i>
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </article>
        </div>
    </div>
</section>

{{-- ─────────────  MIGRATION / SWITCH CTA  ───────────── --}}
<section class="relative pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-10 relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-40" aria-hidden="true"></div>
            <div class="relative">
                <div class="text-center mb-8">
                    <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">Switching is painless</div>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Move from {{ $c['name'] }} in minutes</h2>
                </div>
                <div class="grid sm:grid-cols-3 gap-5">
                    @php
                        $steps = [
                            ['icon' => 'fa-user-plus', 'title' => 'Create your free Sayzio', 'body' => 'Sign up with an email or phone number, no credit card, no trial clock.'],
                            ['icon' => 'fa-arrows-rotate', 'title' => 'Rebuild or import your links', 'body' => 'Recreate your page with drag-and-drop blocks, or bulk-import your existing links.'],
                            ['icon' => 'fa-share-nodes', 'title' => 'Point your link & go live', 'body' => 'Aim your custom domain or Link in Bio at Sayzio and your audience never notices the move.'],
                        ];
                    @endphp
                    @foreach($steps as $i => $s)
                        <div class="text-center sm:text-left" data-anim="fade-up" data-stagger>
                            <div class="inline-flex w-11 h-11 rounded-2xl items-center justify-center text-white mb-3"
                                 style="background: {{ $accent }};">
                                <i class="fas {{ $s['icon'] }}"></i>
                            </div>
                            <div class="text-sm font-bold text-white">{{ $i + 1 }}. {{ $s['title'] }}</div>
                            <p class="mt-1.5 text-sm text-gray-400 leading-relaxed">{{ $s['body'] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-9 text-center">
                    <a href="{{ url('/register') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Start your free Sayzio
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─────────────  FAQ  ───────────── --}}
@if(!empty($faqs))
<section class="relative pb-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">FAQ</div>
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Sayzio vs {{ $c['name'] }}: common questions</h2>
        </div>
        <div class="space-y-3" data-anim="fade-up" data-stagger>
            @foreach($faqs as $faq)
                <details class="group glass rounded-2xl p-5 open:pb-6" id="{{ ComparisonContent::anchor($faq['q'] ?? '') }}">
                    <summary class="flex items-center justify-between gap-4 cursor-pointer list-none">
                        <span class="text-base font-semibold text-white">{{ $faq['q'] ?? '' }}</span>
                        <span class="shrink-0 w-7 h-7 rounded-full border border-white/15 flex items-center justify-center text-gray-300 group-open:rotate-45 transition">
                            <i class="fas fa-plus text-[10px]"></i>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-400 leading-relaxed">{{ $faq['a'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ─────────────  OTHER COMPARISONS  ───────────── --}}
@if(!empty($others))
<section class="relative pb-24">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">Keep comparing</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">See how Sayzio stacks up against <span class="grad-text">other tools</span>.</h3>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($others as $o)
                <a href="{{ route('site.compare.show', ['competitor' => $o['key']]) }}" class="group glass rounded-2xl p-5 lift block">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                              style="background: {{ $o['accent'] }};">
                            <i class="fas {{ $o['icon'] }}"></i>
                        </span>
                        <div>
                            <div class="text-sm font-bold text-white">Sayzio vs {{ $o['name'] }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">+{{ $o['wins'] }} feature lead</div>
                        </div>
                    </div>
                    <div class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold" style="color: {{ $o['accent'] }};">
                        Compare <i class="fas fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition"></i>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="text-center mt-8">
            <a href="{{ route('site.compare.index') }}" class="text-sm font-semibold text-gray-300 hover:text-white inline-flex items-center gap-2">
                View all comparisons <i class="fas fa-arrow-right text-[11px]"></i>
            </a>
        </div>
    </div>
</section>
@endif

@include('public.partials.subscribe-block', [
    'heading' => 'Thinking of switching? Get the playbook.',
    'subtext' => 'Pick how you want to hear from us: email, WhatsApp Channel, or DM. Occasional notes on getting more from your link, no fluff.',
    'source'  => 'compare-' . $c['key'],
])
@endsection
