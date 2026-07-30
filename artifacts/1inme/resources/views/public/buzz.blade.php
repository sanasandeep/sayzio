@extends('public.layouts.site')
@section('title', $page->title ?? 'Buzz: social proof for your biolink')

@section('content')
@php
    // Seeded feature copy (admin-editable via the SitePage row). Each
    // section renders as an explainer card; icons are matched by order.
    $sections = is_array($page->sections ?? null) ? array_values($page->sections) : [];
    $sectionIcons = ['fa-bolt', 'fa-plug-circle-check', 'fa-sliders', 'fa-user-shield', 'fa-palette', 'fa-code'];

    // Live-notification mock for the hero: the kinds of events Buzz surfaces.
    $mockEvents = [
        ['icon' => 'fa-user-plus',    'tint' => 'from-blue-500 to-cyan-400',     'title' => 'Nadia just followed you',       'meta' => 'Berlin · 2 min ago',   'fresh' => true],
        ['icon' => 'fa-cart-shopping','tint' => 'from-emerald-500 to-cyan-500',  'title' => 'Someone bought "Preset Pack"',  'meta' => 'Austin · 6 min ago',   'fresh' => false],
        ['icon' => 'fa-eye',          'tint' => 'from-fuchsia-500 to-pink-500',  'title' => '38 people viewed your page',    'meta' => 'In the last hour',     'fresh' => false],
        ['icon' => 'fa-envelope-open-text', 'tint' => 'from-amber-500 to-pink-500', 'title' => 'New form submission',        'meta' => 'Mumbai · 12 min ago',  'fresh' => false],
    ];
    $ctaUrl = trim((string) ($page->cta_url ?? '')) ?: '/register';
    $ctaLabel = trim((string) ($page->cta_label ?? '')) ?: 'Turn on Buzz on your page';
@endphp

{{-- HERO --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-blue-500/10 border border-blue-400/20 text-blue-300">
                <i class="fas fa-bolt-lightning text-[10px]"></i> Buzz
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                Social proof, <span class="grad-text">live on your page</span>.
            </h1>
            <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                {{ $page->meta_description ?? 'Buzz shows live signups, visits and purchases on your Sayzio biolink page so visitors see real momentum and are more likely to act.' }}
            </p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                @guest
                    <a href="{{ $ctaUrl }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">{{ $ctaLabel }}</a>
                @else
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Open your dashboard</a>
                @endguest
                <a href="{{ route('site.pricing') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See plans</a>
            </div>
            <div class="mt-10 grid grid-cols-3 gap-6 max-w-md" data-anim="fade-up" data-stagger>
                <div><div class="text-2xl font-bold"><span data-count="7"></span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-1">Notification types</div></div>
                <div><div class="text-2xl font-bold">0<span class="text-blue-300"> code</span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-1">Setup required</div></div>
                <div><div class="text-2xl font-bold"><span data-count="1"></span> click</div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-1">To turn on</div></div>
            </div>
        </div>
        <div data-anim="fade-left" class="relative">
            <div class="bg-white/[0.03] border border-white/10 rounded-3xl p-5 sm:p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Live on your biolink</div>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-300"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Live</span>
                </div>
                <div class="space-y-3" data-anim="fade-up" data-stagger>
                    @foreach($mockEvents as $e)
                        <div class="flex items-center gap-3 bg-[#11101c] border {{ $e['fresh'] ? 'border-blue-400/40' : 'border-white/10' }} rounded-2xl p-3 pr-4">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $e['tint'] }} flex items-center justify-center text-white shrink-0">
                                <i class="fas {{ $e['icon'] }} text-sm"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-white truncate">{{ $e['title'] }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $e['meta'] }}</div>
                            </div>
                            @if($e['fresh'])
                                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-300 bg-blue-500/10 border border-blue-400/20 rounded-full px-2 py-0.5">New</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-[11px] text-gray-500">Names masked, locations coarse — visitors can dismiss any popup.</p>
            </div>
        </div>
    </div>
</section>

{{-- FEATURE SECTIONS (seeded copy) --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">How <span class="grad-text">Buzz</span> works</h2>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Tasteful, real-time notifications that show visitors the room is busy — built into every Sayzio biolink.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
            @foreach($sections as $i => $s)
                <div class="group bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 hover:border-blue-400/40 rounded-2xl p-6 transition">
                    <div class="w-11 h-11 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center text-blue-200 mb-4 group-hover:scale-110 transition">
                        <i class="fas {{ $sectionIcons[$i % count($sectionIcons)] }}"></i>
                    </div>
                    <h3 class="text-base font-bold text-white">{{ $s['heading'] ?? '' }}</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ $s['body'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- PLAN-METERED VIEWS --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div data-anim="fade-right">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-500/20 border border-blue-400/30 text-blue-300 mb-4">
                    <i class="fas fa-gauge-high"></i>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Fair, plan-metered views</h2>
                <p class="mt-4 text-gray-400 leading-relaxed">Buzz views are included with your plan and metered monthly, so you always know what you're getting. When a month's allowance is used up, Buzz simply pauses until the next cycle — no surprise charges, and your page keeps working exactly as before.</p>
                <a href="{{ route('site.pricing') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-blue-300 hover:text-white">Compare plan allowances <i class="fas fa-arrow-right text-xs"></i></a>
            </div>
            <div data-anim="fade-left">
                <div class="bg-white/[0.03] border border-white/10 rounded-3xl p-6">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-white">Buzz views this month</span>
                        <span class="text-gray-400">6,420 / 10,000</span>
                    </div>
                    <div class="mt-3 h-2.5 rounded-full bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full grad-bar" style="width:64%"></div>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">Usage resets automatically each billing period. Upgrade any time for a bigger allowance.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Let visitors feel the momentum</h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Turn on Buzz from your dashboard, pick the events you want to surface, and watch trust — and conversions — climb.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    @guest
                        <a href="{{ $ctaUrl }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">{{ $ctaLabel }}</a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Open your dashboard</a>
                    @endguest
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Explore all features</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Get conversion tips like this in your inbox.',
    'subtext' => 'Feature launches and growth playbooks: pick email, WhatsApp Channel, or DM.',
    'source'  => 'buzz',
])
@endsection
