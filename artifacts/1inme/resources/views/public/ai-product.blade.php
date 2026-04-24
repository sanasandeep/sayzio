@extends('public.layouts.site')
@section('title', $page->title ?? 'AI suite')

@section('content')
@php
    use App\Modules\Common\Support\SitePagesContent;

    $aiMeta = [
        'ai-chatbot' => [
            'eyebrow' => 'AI Chatbot',
            'tagline' => 'A 24/7 chatbot for your biolink — trained on you, on-brand, never asleep.',
            'icon'    => 'fa-comments',
            'accent'  => '#7c3aed',
        ],
        'ai-agent' => [
            'eyebrow' => 'AI Agent',
            'tagline' => 'A multi-step AI teammate that actually gets things done.',
            'icon'    => 'fa-robot',
            'accent'  => '#1bd4d9',
        ],
        'ai-widget' => [
            'eyebrow' => 'AI Widget',
            'tagline' => 'Embed an AI assistant on any website in one snippet.',
            'icon'    => 'fa-window-restore',
            'accent'  => '#e94e8c',
        ],
        'ai-voice-assistant' => [
            'eyebrow' => 'AI Voice Assistant',
            'tagline' => "Pick up every call in your voice — never miss another lead.",
            'icon'    => 'fa-headset',
            'accent'  => '#ff8a3c',
        ],
    ];
    $slug    = $aiSlug ?? $page->slug;
    $meta    = $aiMeta[$slug] ?? $aiMeta['ai-chatbot'];
    $sections = collect(is_array($page->sections) ? $page->sections : [])
        ->map(fn ($s) => [
            'heading' => trim((string) ($s['heading'] ?? '')),
            'body'    => trim((string) ($s['body'] ?? '')),
        ])
        ->filter(fn ($s) => $s['heading'] !== '' || $s['body'] !== '')
        ->values();
    $ctaLabel = trim((string) ($page->cta_label ?? '')) ?: 'Try it free';
    $ctaUrl   = trim((string) ($page->cta_url ?? '')) ?: '/register';
    $unlockedOn = $unlockedOn ?? [];
    $faqs = $faqs ?? [];
    $testimonials = $testimonials ?? [];
@endphp

{{-- ─────────────  HERO  ───────────── --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                      style="background: {{ $meta['accent'] }}1a; border-color: {{ $meta['accent'] }}33; color: {{ $meta['accent'] }};">
                    <i class="fas {{ $meta['icon'] }} text-[10px]"></i> {{ $meta['eyebrow'] }}
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    {{ $page->title ?? $meta['eyebrow'] }}
                    <span class="block grad-text">{{ $meta['tagline'] }}</span>
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    {{ $page->meta_description }}
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ $ctaUrl }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> {{ $ctaLabel }}
                    </a>
                    <a href="{{ route('site.pricing') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        See plans
                    </a>
                </div>
                @if(!empty($unlockedOn))
                    <p class="mt-5 text-xs text-gray-500">
                        <i class="fas fa-lock-open text-[10px] mr-1" style="color: {{ $meta['accent'] }};"></i>
                        Included on {{ implode(', ', $unlockedOn) }} plans.
                    </p>
                @endif
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[16/10] flex items-center justify-center"
                     style="background:linear-gradient(135deg, {{ $meta['accent'] }}33, rgba(255,255,255,.02));">
                    <i class="fas {{ $meta['icon'] }} text-[120px] opacity-80" style="color: {{ $meta['accent'] }};"></i>
                </div>
                <div class="absolute -bottom-6 -left-6 bg-[#11101c] border border-white/10 rounded-2xl p-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                         style="background:linear-gradient(135deg, {{ $meta['accent'] }}, #1bd4d9);">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">Live in minutes</div>
                        <div class="text-xs text-gray-400">No setup, no scripts</div>
                    </div>
                </div>
                <div class="absolute -top-5 -right-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y" style="animation-delay:-3s">
                    <span class="w-2.5 h-2.5 rounded-full pulse-dot" style="background: {{ $meta['accent'] }}; color: {{ $meta['accent'] }}66;"></span>
                    <span class="text-xs font-semibold text-gray-200">AI · always on</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─────────────  FEATURES (sections from page) ───────────── --}}
@if($sections->isNotEmpty())
<section class="relative pb-24">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-5">
            @foreach($sections as $i => $s)
                <article class="glass rounded-3xl p-7 lift relative overflow-hidden" data-anim="fade-up" data-stagger>
                    <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20"
                         style="background:radial-gradient(circle, {{ $meta['accent'] }}, transparent 70%);"></div>
                    <div class="relative w-11 h-11 rounded-2xl flex items-center justify-center mb-4 text-white"
                         style="background: linear-gradient(135deg, {{ $meta['accent'] }}, #7c3aed); box-shadow: 0 12px 30px -12px {{ $meta['accent'] }};">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    @if($s['heading'] !== '')
                        <h2 class="relative text-xl font-bold mb-3 leading-snug">{{ $s['heading'] }}</h2>
                    @endif
                    @if($s['body'] !== '')
                        <p class="relative text-sm text-gray-300 leading-relaxed">{{ $s['body'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ─────────────  CROSS-SELL — other AI products ───────────── --}}
<section class="pb-24">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $meta['accent'] }};">The rest of the AI suite</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">More AI built into <span class="grad-text">1INME</span>.</h3>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach(SitePagesContent::aiProductSlugs() as $otherSlug)
                @if($otherSlug === $slug) @continue @endif
                @php $om = $aiMeta[$otherSlug] ?? null; @endphp
                @if(!$om) @continue @endif
                <a href="{{ route('site.' . $otherSlug) }}" class="group glass rounded-2xl p-5 lift block">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white mb-3"
                         style="background: linear-gradient(135deg, {{ $om['accent'] }}, #7c3aed);">
                        <i class="fas {{ $om['icon'] }}"></i>
                    </div>
                    <div class="text-sm font-bold text-white">{{ $om['eyebrow'] }}</div>
                    <div class="text-xs text-gray-400 mt-1 leading-snug">{{ $om['tagline'] }}</div>
                    <div class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold" style="color: {{ $om['accent'] }};">
                        Learn more <i class="fas fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition"></i>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ─────────────  TESTIMONIALS  ───────────── --}}
@if(!empty($testimonials))
    @include('public.partials.testimonials', [
        'testimonials' => $testimonials,
        'eyebrow' => 'What people say',
        'heading' => 'Teams shipping faster with the 1INME AI suite.',
    ])
@endif

{{-- ─────────────  FAQ  ───────────── --}}
@if(!empty($faqs))
<section class="pb-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $meta['accent'] }};">FAQ</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Common questions about <span class="grad-text">{{ $meta['eyebrow'] }}</span>.</h3>
        </div>
        <div class="space-y-3" data-anim="fade-up" data-stagger>
            @foreach($faqs as $i => $faq)
                <details class="group glass rounded-2xl p-5 open:pb-6">
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

{{-- ─────────────  CTA BAND  ───────────── --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Ready to put <span class="grad-text">{{ $meta['eyebrow'] }}</span> to work?</h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Spin up your free 1INME, turn on the AI suite, and let it answer, qualify and follow up while you build.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ $ctaUrl }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">{{ $ctaLabel }}</a>
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'New AI features, the moment they ship.',
    'subtext' => 'Pick how you want to hear from us — email, WhatsApp Channel, or DM. Once-a-month notes on the AI suite, no fluff.',
    'source'  => $slug,
])
@endsection
