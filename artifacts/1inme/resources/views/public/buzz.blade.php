@extends('public.layouts.site')
@section('title', $page->title ?? 'Buzz')

@section('content')
@php
    $press = [
        ['outlet' => 'TechCrunch', 'date' => 'Mar 2026', 'headline' => '1INME wants to be the calm in the link-in-bio storm.', 'href' => '#'],
        ['outlet' => 'The Verge',  'date' => 'Feb 2026', 'headline' => 'Every creator’s second favourite app.',                'href' => '#'],
        ['outlet' => 'YourStory',  'date' => 'Jan 2026', 'headline' => 'How a Hyderabad team built a tool that scales.',       'href' => '#'],
        ['outlet' => 'Forbes',     'date' => 'Dec 2025', 'headline' => 'The next-gen Link in Bio is built for serious creators.',  'href' => '#'],
    ];
    $awards = [
        ['title' => 'Product Hunt — #1 of the day', 'date' => 'Sep 2025', 'icon' => 'fa-trophy'],
        ['title' => 'Best of 2025 · Creator Tools', 'date' => 'Dec 2025', 'icon' => 'fa-medal'],
        ['title' => 'Indie SaaS Award · Design',    'date' => 'Nov 2025', 'icon' => 'fa-star'],
    ];
    $testimonials = [
        ['name' => 'Maya Daly',     'role' => 'Storyteller, 24K followers', 'quote' => 'I used to juggle four tools. Now everything runs from one tab — and I can actually see what is working.', 'tint' => 'from-violet-500 to-fuchsia-500'],
        ['name' => 'Rajiv Khanna',  'role' => 'Indie musician',             'quote' => 'My fans land on a single beautiful page that shows the new EP, my tour and my Patreon. Click-throughs doubled.', 'tint' => 'from-cyan-500 to-violet-500'],
        ['name' => 'Sara Mendez',   'role' => 'Boutique owner',             'quote' => 'The QR code on every order box brings people back to a special drops page. It feels custom — but I built it in an afternoon.', 'tint' => 'from-pink-500 to-amber-500'],
        ['name' => 'Olu Adesina',   'role' => 'Career coach',               'quote' => '1INME made my newsletter, course waitlist and bookings live in one place. Conversions are way up.', 'tint' => 'from-emerald-500 to-cyan-500'],
    ];
    $logos = ['TechCrunch', 'The Verge', 'YourStory', 'Forbes', 'Product Hunt', 'Indie Hackers', 'Wired', 'FastCompany'];
@endphp

{{-- HERO --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-violet-500/10 border border-violet-400/20 text-violet-300">
                <i class="fas fa-bullhorn text-[10px]"></i> {{ $page->title ?? 'Buzz' }}
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                People are <span class="grad-text">talking</span>.
            </h1>
            <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                {{ $page->meta_description ?? 'Press, awards, customer love and the tiny moments that keep us shipping. A round-up of everything happening around 1INME.' }}
            </p>
            <div class="mt-8 grid grid-cols-3 gap-6 max-w-md" data-anim="fade-up" data-stagger>
                <div><div class="text-3xl font-bold"><span data-count="40" data-count-suffix="+"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-1">Press features</div></div>
                <div><div class="text-3xl font-bold"><span data-count="9"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-1">Awards</div></div>
                <div><div class="text-3xl font-bold"><span data-count="4.9"></span><span class="text-violet-300">/5</span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-1">Customer rating</div></div>
            </div>
        </div>
        <div data-anim="fade-left" data-tilt="5" class="relative">
            <div class="img-frame img-tilt aspect-[16/10]">
                <img src="{{ asset('images/marketing/buzz/hero.png') }}" alt="Press coverage and editorial features">
            </div>
            <div class="absolute -bottom-5 -right-5 bg-[#11101c] border border-white/10 rounded-2xl p-3 pr-4 flex items-center gap-3 shadow-2xl float-y">
                <div class="w-10 h-10 rounded-xl bg-[#7c3aed] flex items-center justify-center text-white"><i class="fas fa-trophy"></i></div>
                <div class="text-xs"><div class="font-semibold text-white">#1 Product of the Day</div><div class="text-gray-400">Product Hunt</div></div>
            </div>
        </div>
    </div>
</section>

{{-- LOGO MARQUEE --}}
<section class="pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-center text-xs font-semibold uppercase tracking-[.25em] text-gray-500 mb-6" data-anim="fade-up">As featured in</p>
        <div class="marquee-mask overflow-hidden">
            <div class="marquee">
                @foreach(array_merge($logos, $logos) as $l)
                    <div class="text-2xl font-bold text-gray-500/80 hover:text-white transition whitespace-nowrap" style="font-family:'Space Grotesk',serif">{{ $l }}</div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- PRESS GRID --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between flex-wrap gap-4 mb-8" data-anim="fade-up">
            <div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">In the press</h2>
                <p class="mt-2 text-gray-400">Recent stories about 1INME and the people behind it.</p>
            </div>
            <a href="{{ route('site.contact') }}" class="text-sm font-semibold text-violet-300 hover:text-white">Press &amp; partnerships <i class="fas fa-arrow-right text-xs ml-1"></i></a>
        </div>
        <div class="grid md:grid-cols-2 gap-5" data-anim="fade-up" data-stagger>
            @foreach($press as $p)
                <a href="{{ $p['href'] }}" class="group bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 hover:border-violet-400/40 rounded-2xl overflow-hidden transition flex flex-col sm:flex-row">
                    <div class="img-frame rounded-none border-0 aspect-[16/10] sm:aspect-auto sm:w-44 shrink-0">
                        <img src="{{ asset('images/marketing/buzz/press.png') }}" alt="Press article preview">
                    </div>
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="text-xs font-semibold uppercase tracking-wider text-violet-300">{{ $p['outlet'] }} · {{ $p['date'] }}</div>
                        <h3 class="mt-2 text-lg font-bold text-white leading-snug group-hover:text-violet-200">{{ $p['headline'] }}</h3>
                        <span class="mt-auto pt-3 text-sm text-violet-400">Read story <i class="fas fa-arrow-up-right-from-square text-xs ml-1"></i></span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- AWARDS --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-[1fr_1.2fr] gap-10 items-center">
            <div data-anim="fade-right">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">A few <span class="grad-text">trophies</span> on the shelf</h2>
                <p class="mt-3 text-gray-400 leading-relaxed">We do not chase awards, but they help us know what is landing. A short list of recent recognition.</p>
                <div class="mt-7 space-y-3" data-anim="fade-up" data-stagger>
                    @foreach($awards as $a)
                        <div class="flex items-center gap-4 bg-white/[0.03] border border-white/10 rounded-2xl p-4 hover:border-violet-400/40 transition">
                            <div class="w-12 h-12 rounded-xl bg-[#7c3aed] border border-white/10 flex items-center justify-center text-white">
                                <i class="fas {{ $a['icon'] }} text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-white">{{ $a['title'] }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">{{ $a['date'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6">
                <div class="img-frame img-tilt aspect-[4/3]">
                    <img src="{{ asset('images/marketing/buzz/awards.png') }}" alt="Awards">
                </div>
            </div>
        </div>
    </div>
</section>

{{-- TESTIMONIALS --}}
<section class="pb-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Customer love</h2>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Stories from people who run their entire creator business on 1INME.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-5" data-anim="fade-up" data-stagger>
            @foreach($testimonials as $t)
                <figure class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 hover:border-violet-400/40 transition relative">
                    <i class="fas fa-quote-left absolute top-4 right-5 text-violet-400/30 text-3xl"></i>
                    <blockquote class="text-gray-200 leading-relaxed">“{{ $t['quote'] }}”</blockquote>
                    <figcaption class="mt-5 flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-gradient-to-br {{ $t['tint'] }} flex items-center justify-center text-white text-sm font-bold">{{ strtoupper(mb_substr($t['name'],0,1)) }}</span>
                        <div>
                            <div class="text-sm font-semibold text-white">{{ $t['name'] }}</div>
                            <div class="text-xs text-gray-400">{{ $t['role'] }}</div>
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Want to write about us?</h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Drop us a line — we love talking shop with journalists, bloggers and podcasters.</p>
                <div class="mt-7"><a href="{{ route('site.contact') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Get in touch</a></div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Follow 1INME news as it happens.',
    'subtext' => 'Press, partnerships, and product launches — pick email, WhatsApp Channel, or DM.',
    'source'  => 'buzz',
])
@endsection
