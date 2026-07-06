@extends('public.layouts.site')
@section('title', $page->title ?? 'AI Dashboard')

@section('content')
@php
    $accent = '#3d6bff';
    $presets = $presets ?? [];
    $sections = (array) ($page->sections ?? []);
    $faqs = [
        ['q' => 'Does turning on a preset delete any of my data?', 'a' => 'No. Presets only change which widgets are visible on your dashboard. Every metric keeps recording in the background, and you can switch presets or go back to the full Overview at any time.'],
        ['q' => 'What does "Design with AI" actually build?', 'a' => 'You describe what you want to see in a sentence, and the AI designer picks and arranges widgets from your existing dashboard catalog — it never invents new data or charts, only chooses from what your account already has.'],
        ['q' => 'Can I still customize a preset after choosing one?', 'a' => 'Yes. Presets are a fast starting point — you can add or remove individual widgets afterwards, or ask the AI designer to refine the layout further.'],
        ['q' => 'Is this available on mobile?', 'a' => 'Yes — the same presets, AI designer and widget catalog are available from the dashboard on the Sayzio mobile app.'],
    ];
@endphp

<style>
    .aid-mesh { position: relative; isolation: isolate; }
    .aid-mesh::before {
        content:""; position:absolute; inset:-20%;
        background: radial-gradient(circle at 20% 20%, rgba(61,107,255,.14), transparent 55%),
                    radial-gradient(circle at 80% 10%, rgba(27,212,217,.10), transparent 50%);
        filter: blur(10px); pointer-events:none; z-index:-1;
    }
    .aid-pill {
        display:inline-flex; align-items:center; gap:8px;
        font-size:.72rem; font-weight:800; letter-spacing:.18em; text-transform:uppercase;
        color:#90acff; background:rgba(61,107,255,.10); border:1px solid rgba(61,107,255,.25);
        padding:.4rem 1rem; border-radius:999px;
    }
    .aid-dot { width:6px; height:6px; border-radius:999px; background:#3d6bff; animation: aidPulse 2.4s ease-in-out infinite; }
    @keyframes aidPulse { 0%,100% { opacity:.4; transform:scale(.85); } 50% { opacity:1; transform:scale(1.15); } }

    .aid-preset-card {
        position:relative; border-radius:1.25rem; padding:1.5rem;
        background: rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.08);
        transition: transform .25s ease, border-color .25s ease, background .25s ease;
    }
    .aid-preset-card:hover { transform: translateY(-4px); border-color: rgba(61,107,255,.4); background: rgba(255,255,255,.05); }
    .aid-preset-icon {
        width:2.75rem; height:2.75rem; border-radius:.9rem; display:flex; align-items:center; justify-content:center;
        background: linear-gradient(135deg, #3d6bff, #6e61ff); color:#fff; box-shadow: 0 14px 30px -12px rgba(61,107,255,.6);
    }
    .aid-widget-chip {
        display:inline-block; font-size:.68rem; font-weight:600; color:#a5b4fc;
        background: rgba(61,107,255,.08); border:1px solid rgba(61,107,255,.18);
        padding:.2rem .55rem; border-radius:999px; margin:.15rem .25rem .15rem 0;
    }

    @media (prefers-reduced-motion: reduce) {
        .aid-dot { animation:none !important; }
        .aid-preset-card { transition:none !important; }
    }
</style>

{{-- ===================== HERO ===================== --}}
<section class="aid-mesh relative pt-20 pb-14 lg:pt-28 lg:pb-16 overflow-hidden">
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="reveal aid-pill"><span class="aid-dot" aria-hidden="true"></span> AI-Powered Dashboard</div>
        <h1 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mt-5 mb-5">
            {{ $page->title ?? 'A dashboard that only shows what matters to' }} <span class="grad-text">you.</span>
        </h1>
        <p class="reveal rd-2 text-lg text-gray-400 max-w-2xl mx-auto">
            {{ $page->meta_description ?? 'Pick a curated preset, or just describe what you want to see and let AI design your layout — built entirely from widgets you already have.' }}
        </p>
        <div class="reveal rd-3 mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ $page->cta_url ?? route('register.page') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                {{ $page->cta_label ?? 'Customize my dashboard' }} <i class="fas fa-arrow-right text-xs"></i>
            </a>
            <a href="#aid-presets" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-full text-sm font-bold border border-white/15 hover:border-white/30 transition">
                See the presets <i class="fas fa-arrow-down text-xs"></i>
            </a>
        </div>
    </div>
</section>

{{-- ===================== DESIGN WITH AI FLOW ===================== --}}
<section class="py-16 lg:py-20 relative" aria-labelledby="aid-flow-h">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:{{ $accent }}">Design with AI</div>
            <h2 id="aid-flow-h" class="reveal rd-1 text-3xl sm:text-4xl font-bold tracking-tight mb-4">
                Describe it once. <span class="grad-text">AI arranges it.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">
                No drag-and-drop required — just tell the dashboard designer what you care about most.
            </p>
        </div>
        <div class="reveal rd-3">
            @include('common.partials.ai-dashboard-demo', ['presets' => $presets, 'variant' => 'rich'])
        </div>
    </div>
</section>

{{-- ===================== 5 PRESETS ===================== --}}
<section id="aid-presets" class="py-16 lg:py-20 relative" aria-labelledby="aid-presets-h">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:{{ $accent }}">5 Curated Presets</div>
            <h2 id="aid-presets-h" class="reveal rd-1 text-3xl sm:text-4xl font-bold tracking-tight mb-4">
                Start from a layout <span class="grad-text">built for your focus.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">
                Every preset groups the widgets that matter most for that view — switch between them in one tap.
            </p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($presets as $i => $preset)
                <div class="reveal rd-{{ min($i + 1, 6) }} aid-preset-card">
                    <div class="aid-preset-icon mb-4">
                        <i class="fas {{ $preset['icon'] ?? 'fa-gauge-high' }}"></i>
                    </div>
                    <h3 class="text-base font-bold mb-1.5">{{ $preset['label'] ?? '' }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed mb-3">{{ $preset['description'] ?? '' }}</p>
                    <div>
                        @foreach(array_slice($preset['widgets'] ?? [], 0, 5) as $widget)
                            <span class="aid-widget-chip">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $widget)) }}</span>
                        @endforeach
                        @if(count($preset['widgets'] ?? []) > 5)
                            <span class="aid-widget-chip">+{{ count($preset['widgets']) - 5 }} more</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== RICH SECTIONS (from SitePagesContent) ===================== --}}
@if(!empty($sections))
<section class="py-16 lg:py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        @foreach($sections as $i => $section)
            @if(!empty($section['heading']) || !empty($section['body']))
                <div class="reveal rd-{{ min(($i % 6) + 1, 6) }} bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8">
                    @if(!empty($section['heading']))
                        <h3 class="text-xl font-bold mb-3">{{ $section['heading'] }}</h3>
                    @endif
                    @if(!empty($section['body']))
                        <p class="text-gray-400 leading-relaxed">{{ $section['body'] }}</p>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</section>
@endif

{{-- ===================== FAQ ===================== --}}
<section class="py-16 lg:py-20" aria-labelledby="aid-faq-h">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 id="aid-faq-h" class="reveal text-3xl sm:text-4xl font-bold tracking-tight text-center mb-10">Questions, answered</h2>
        <div class="space-y-4" x-data="{ open: 0 }">
            @foreach($faqs as $i => $faq)
                <div class="reveal rd-{{ min($i + 1, 6) }} bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden">
                    <button type="button" class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left" @click="open = (open === {{ $i }} ? null : {{ $i }})" :aria-expanded="open === {{ $i }}">
                        <span class="font-semibold text-sm sm:text-base">{{ $faq['q'] }}</span>
                        <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse class="px-5 pb-4 text-sm text-gray-400 leading-relaxed">
                        {{ $faq['a'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== CTA BAND ===================== --}}
<section class="py-16 lg:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal glass rounded-3xl p-10 sm:p-14 text-center relative overflow-hidden">
            <div class="absolute -top-20 -right-20 w-64 h-64 rounded-full opacity-25" style="background:{{ $accent }};"></div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">Build a dashboard that fits <span class="grad-text">how you work.</span></h2>
            <p class="text-gray-400 max-w-xl mx-auto mb-8">Pick a preset, or let AI design one for you — either way, you're two clicks away from a cleaner dashboard.</p>
            <a href="{{ $page->cta_url ?? route('register.page') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-sm font-bold">
                {{ $page->cta_label ?? 'Customize my dashboard' }} <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>
@endsection
