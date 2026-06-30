@extends('public.layouts.site')
@section('title', 'Form Builder')

@php
    $accent = '#3d6bff';
    $features = [
        [
            'icon'  => 'fa-shapes',
            'title' => '21 field types',
            'desc'  => 'Short and long text, email, phone, number, dropdowns, multiple choice, checkboxes, star ratings, dates, times, file uploads, hidden fields and more — drag, drop and reorder them however you like.',
        ],
        [
            'icon'  => 'fa-palette',
            'title' => 'Designed to match your brand',
            'desc'  => 'Control colors, fonts, spacing, button styles, backgrounds and corner radius. Every form looks like it belongs on your page — no code, no generic third-party styling.',
        ],
        [
            'icon'  => 'fa-bell-concierge',
            'title' => 'Instant submission alerts',
            'desc'  => 'Get notified the moment someone responds — by email, SMS or webhook. Pipe leads straight into your CRM, Slack, Zapier or any endpoint you point us at.',
        ],
        [
            'icon'  => 'fa-link',
            'title' => 'Embed in any biolink',
            'desc'  => 'Drop a form block onto your Link in Bio page and start collecting responses the instant you publish. No separate landing page to build or maintain.',
        ],
        [
            'icon'  => 'fa-table-list',
            'title' => 'Every response in one place',
            'desc'  => 'Submissions are captured, timestamped and organised so you can review, follow up and act on them without digging through your inbox.',
        ],
        [
            'icon'  => 'fa-shield-halved',
            'title' => 'Spam-resistant by default',
            'desc'  => 'Built-in honeypot protection and validation keep junk out, so the responses you collect are real people you actually want to hear from.',
        ],
    ];
    $faqAnchors = [
        ['q' => 'How many fields can a form have?',        'href' => route('site.features') . '#cat-forms'],
        ['q' => 'Can I get submissions by webhook?',        'href' => route('site.features') . '#cat-forms'],
        ['q' => 'Where do form responses go?',              'href' => route('site.features') . '#cat-forms'],
    ];
@endphp

@section('content')
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                      style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: {{ $accent }};">
                    <i class="fas fa-list-check text-[10px]"></i> Form Builder
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    Collect anything,
                    <span class="block grad-text">right from your page.</span>
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    A drag-and-drop form builder with 21 field types, full design control, and instant email, SMS and webhook notifications on every submission — embeddable in any biolink in seconds.
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    @guest
                        <a href="{{ route('register.page') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                            <i class="fas fa-rocket text-xs"></i> Build a form free
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                            <i class="fas fa-rocket text-xs"></i> Go to your dashboard
                        </a>
                    @endguest
                    <a href="{{ route('site.features') }}#cat-forms" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        See all field types
                    </a>
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[16/10] flex items-center justify-center"
                     style="background:{{ $accent }}1f;">
                    <i class="fas fa-list-check text-[120px] opacity-80" style="color: {{ $accent }};"></i>
                </div>
                <div class="absolute -bottom-6 -left-6 bg-[#11101c] border border-white/10 rounded-2xl p-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                         style="background: {{ $accent }};">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">New submission received</div>
                        <div class="text-xs text-gray-400">Emailed to you · webhook fired</div>
                    </div>
                </div>
                <div class="absolute -top-5 -right-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y" style="animation-delay:-3s">
                    <span class="w-2.5 h-2.5 rounded-full pulse-dot" style="background: {{ $accent }};"></span>
                    <span class="text-xs font-semibold text-gray-200">21 field types</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="relative pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-5">
            @foreach($features as $i => $f)
                <article class="glass rounded-3xl p-7 lift relative overflow-hidden" data-anim="fade-up" data-stagger>
                    <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20"
                         style="background: {{ $accent }};"></div>
                    <div class="relative w-11 h-11 rounded-2xl flex items-center justify-center mb-4 text-white"
                         style="background: {{ $accent }}; box-shadow: 0 12px 30px -12px {{ $accent }};">
                        <i class="fas {{ $f['icon'] }}"></i>
                    </div>
                    <h2 class="relative text-xl font-bold mb-3 leading-snug">{{ $f['title'] }}</h2>
                    <p class="relative text-sm text-gray-300 leading-relaxed">{{ $f['desc'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-10 relative overflow-hidden grid md:grid-cols-[1fr_auto] gap-6 items-center" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[.2em] mb-2" style="color: {{ $accent }};">Works with your plan</div>
                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Start building forms free. Unlock SMS &amp; webhook alerts on paid plans.</h3>
                <p class="mt-3 text-gray-400 max-w-2xl">Free accounts can build and embed forms with email notifications. SMS and webhook delivery, plus higher submission allowances, unlock on a paid plan.</p>
            </div>
            <div class="relative flex flex-wrap gap-3">
                <a href="{{ route('site.pricing') }}" class="px-5 py-3 rounded-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold whitespace-nowrap">See plans</a>
                <a href="{{ route('site.premium-features') }}" class="px-5 py-3 rounded-full border border-white/15 text-gray-200 hover:bg-white/5 text-sm font-semibold whitespace-nowrap">Premium features</a>
            </div>
        </div>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">FAQ</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Common questions about the <span class="grad-text">form builder</span>.</h3>
        </div>
        <div class="space-y-3" data-anim="fade-up" data-stagger>
            @foreach($faqAnchors as $faq)
                <a href="{{ $faq['href'] }}" class="group glass rounded-2xl p-5 flex items-center justify-between gap-4 hover:border-white/20">
                    <span class="text-base font-semibold text-white">{{ $faq['q'] }}</span>
                    <span class="shrink-0 w-7 h-7 rounded-full border border-white/15 flex items-center justify-center text-gray-300 group-hover:translate-x-0.5 transition">
                        <i class="fas fa-arrow-right text-[10px]"></i>
                    </span>
                </a>
            @endforeach
            <div class="text-center pt-4">
                <a href="{{ route('site.faqs') }}" class="text-sm font-semibold" style="color: {{ $accent }};">See all FAQs <i class="fas fa-arrow-right text-[10px]"></i></a>
            </div>
        </div>
    </div>
</section>

<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Your next lead <span class="grad-text">is one form away.</span></h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Spin up a free Sayzio, build a form in minutes, and embed it on your Link in Bio to start collecting responses today.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    @guest
                        <a href="{{ route('register.page') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Get started free</a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Go to your dashboard</a>
                    @endguest
                    <a href="{{ route('login.page') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Log in</a>
                    <a href="{{ route('site.features') }}#cat-forms" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Explore form features</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Tips for forms that actually convert.',
    'subtext' => 'Once-a-month notes on capturing more leads, fewer drop-offs and better follow-up on Sayzio — email, WhatsApp Channel, or DM, your call.',
    'source'  => 'forms',
])
@endsection
