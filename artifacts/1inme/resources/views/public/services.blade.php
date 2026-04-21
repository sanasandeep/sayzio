@extends('public.layouts.site')

@section('title', 'What you can do with 1INME')

@section('content')
@php
    $useCases = [
        [
            'icon' => 'fa-bullhorn',
            'tint' => 'from-violet-500/30 to-fuchsia-500/10',
            'title' => 'Marketing channel',
            'tagline' => 'Run campaigns from a single, trackable hub.',
            'desc' => 'Turn every social bio, ad and QR code into a measurable funnel. Spin up campaign landing pages in minutes and watch what converts.',
            'bullets' => [
                'Branded link-in-bio with UTM-friendly short links',
                'Per-link click analytics and traffic sources',
                'Lead-capture forms wired to your audience list',
                'A/B-able CTAs and pinned promo blocks',
            ],
        ],
        [
            'icon' => 'fa-id-badge',
            'tint' => 'from-sky-500/30 to-violet-500/10',
            'title' => 'Personal portfolio',
            'tagline' => 'A polished one-page intro that travels with you.',
            'desc' => 'Showcase work, link your socials and let people reach you — without paying for a custom site or worrying about hosting.',
            'bullets' => [
                'Bio, avatar, headline and contact in one place',
                'Featured projects with images and external links',
                'All your social profiles in a single tap-friendly stack',
                'Custom alias like 1inme.co/yourname',
            ],
        ],
        [
            'icon' => 'fa-people-group',
            'tint' => 'from-fuchsia-500/30 to-pink-500/10',
            'title' => 'Agency / multi-client',
            'tagline' => 'Manage every client from one workspace.',
            'desc' => 'Built for teams who ship for many brands. Keep client assets, links and access cleanly separated without juggling logins.',
            'bullets' => [
                'Workspaces per client with isolated content',
                'Invite teammates with role-based permissions',
                'Shared client vault for credentials and assets',
                'Per-client analytics for white-label reporting',
            ],
        ],
        [
            'icon' => 'fa-star',
            'tint' => 'from-amber-500/30 to-violet-500/10',
            'title' => 'Creator / influencer',
            'tagline' => 'Grow, post and monetize from one biolink.',
            'desc' => 'Replace half a dozen tools with a single creator hub — biolink, posts, follower digests and tip jars all under your handle.',
            'bullets' => [
                'Biolink that doubles as a posting feed',
                'Email digests to bring fans back',
                'Tip jar, paid links and product blocks',
                'Discoverable in the public creator feed',
            ],
        ],
        [
            'icon' => 'fa-store',
            'tint' => 'from-emerald-500/30 to-sky-500/10',
            'title' => 'Small business',
            'tagline' => 'A lightweight site for shops and services.',
            'desc' => 'Get a clean public page with your services, contact info and booking link — no developers, no monthly maintenance.',
            'bullets' => [
                'Services / products list with pricing blocks',
                'Click-to-call, WhatsApp and map directions',
                'Inquiry forms that land straight in your inbox',
                'Embed your scheduling or booking link',
            ],
        ],
        [
            'icon' => 'fa-calendar-days',
            'tint' => 'from-rose-500/30 to-violet-500/10',
            'title' => 'Events',
            'tagline' => 'One link for everything attendees need.',
            'desc' => 'Share schedule, venue, RSVP and updates from a single page that\'s easy to forward, print to a QR code, or pin in a bio.',
            'bullets' => [
                'Event hero with date, location and countdown',
                'RSVP / signup form with capacity tracking',
                'Schedule and speakers in tap-friendly cards',
                'Share-ready QR code for posters and tickets',
            ],
        ],
    ];
@endphp

<section class="relative pt-16 pb-12 lg:pt-24 lg:pb-16 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
    <div class="absolute -top-20 -right-32 w-[480px] h-[480px] rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(236,72,153,0.14) 0%,transparent 70%);"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-white/5 border border-white/10 text-violet-300">
            <i class="fas fa-sparkles"></i> Use cases
        </span>
        <h1 class="mt-5 text-4xl sm:text-5xl font-bold tracking-tight">What you can do with 1INME</h1>
        <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">
            One link, endless setups. Pick the scenario that sounds like you and see how 1INME fits — then spin up your own in a couple of minutes.
        </p>
        <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('register.page') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Get started free</a>
            <a href="{{ route('site.features') }}" class="px-5 py-2.5 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
        </div>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($useCases as $uc)
                <article class="relative group bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-7 flex flex-col hover:border-violet-400/40 transition">
                    <div class="absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition pointer-events-none bg-gradient-to-br {{ $uc['tint'] }}"></div>
                    <div class="relative flex-1 flex flex-col">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $uc['tint'] }} border border-white/10 flex items-center justify-center text-violet-200 text-lg">
                            <i class="fas {{ $uc['icon'] }}"></i>
                        </div>
                        <h2 class="mt-5 text-xl font-bold text-white">{{ $uc['title'] }}</h2>
                        <p class="mt-1 text-sm font-medium text-violet-300">{{ $uc['tagline'] }}</p>
                        <p class="mt-3 text-sm text-gray-400 leading-relaxed">{{ $uc['desc'] }}</p>
                        <ul class="mt-4 space-y-2 text-sm text-gray-300">
                            @foreach($uc['bullets'] as $b)
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check text-violet-400 mt-1 text-xs"></i>
                                    <span>{{ $b }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-6 pt-5 border-t border-white/10">
                            <a href="{{ route('register.page') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-300 hover:text-white">
                                Get started <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-14 rounded-2xl border border-white/10 bg-gradient-to-r from-violet-600/15 via-fuchsia-600/10 to-transparent p-8 sm:p-10 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold">Not sure which fits?</h2>
            <p class="mt-3 text-gray-300 max-w-2xl mx-auto text-sm sm:text-base">
                Start free and switch your setup any time — most people end up using 1INME for two or three of these at once.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('register.page') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Create your 1INME</a>
                <a href="{{ route('site.contact') }}" class="px-5 py-2.5 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Talk to us</a>
            </div>
        </div>
    </div>
</section>
@endsection
