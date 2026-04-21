@extends('public.layouts.site')
@section('title', $page->title ?? 'Workspace & Team')

@section('content')
@php
    $features = [
        ['icon' => 'fa-people-group', 'title' => 'Roles &amp; permissions', 'desc' => 'Owner, Admin, Editor, Analyst — granular controls on every link, biolink and analytics view. Invite a freelancer for one campaign and revoke access in a tap.', 'img' => asset('images/marketing/workspace-team/roles.png')],
        ['icon' => 'fa-rectangle-list',   'title' => 'Activity &amp; audit log', 'desc' => 'See exactly who changed what and when. Searchable, filterable, exportable — perfect for compliance and post-mortems.', 'img' => asset('images/marketing/workspace-team/audit.png')],
    ];
    $highlights = [
        ['icon' => 'fa-shield-halved', 'title' => 'SSO ready',          'desc' => 'Enable Google or SAML SSO for any workspace on the Team plan.'],
        ['icon' => 'fa-key',           'title' => 'Scoped tokens',      'desc' => 'Issue per-integration API tokens, rotate or revoke any time.'],
        ['icon' => 'fa-clock-rotate-left', 'title' => 'Time-bound invites','desc' => 'Auto-expire guest access after a campaign or quarter.'],
        ['icon' => 'fa-bell',          'title' => 'Slack &amp; email alerts','desc' => 'Get pinged the second a teammate publishes or schedules.'],
        ['icon' => 'fa-flag',          'title' => 'Approval flows',     'desc' => 'Optional review step before a biolink or campaign goes live.'],
        ['icon' => 'fa-globe',         'title' => 'Multi-brand spaces', 'desc' => 'Manage many brands from one login with separate billing.'],
    ];
@endphp

{{-- HERO --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-[1.05fr_1fr] gap-12 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-violet-500/10 border border-violet-400/20 text-violet-300">
                    <i class="fas fa-people-group text-[10px]"></i> {{ $page->title ?? 'Workspace & Team' }}
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    Built for the way <span class="grad-text">your team actually works</span>.
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    {{ $page->meta_description ?? 'Invite teammates, agencies and clients with the right permissions. Track every change. Switch between brand spaces in one click.' }}
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Start a workspace</a>
                    <a href="{{ route('site.contact') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Talk to sales</a>
                </div>
                <div class="mt-10 grid grid-cols-3 gap-6 max-w-md" data-anim="fade-up" data-stagger>
                    <div><div class="text-2xl font-bold"><span data-count="14"></span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-1">Roles &amp; presets</div></div>
                    <div><div class="text-2xl font-bold"><span data-count="200" data-count-suffix="+"></span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-1">Audit event types</div></div>
                    <div><div class="text-2xl font-bold"><span data-count="99.99" data-count-suffix="%"></span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-1">Uptime SLA</div></div>
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="5" class="relative">
                <div class="img-frame img-tilt aspect-[5/4]">
                    <img src="{{ asset('images/marketing/workspace-team/hero.png') }}" alt="Team collaborating">
                </div>
                <div class="absolute -bottom-5 -left-5 bg-[#11101c] border border-white/10 rounded-2xl p-3 pr-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="flex -space-x-2">
                        <span class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 ring-2 ring-[#11101c] flex items-center justify-center text-xs font-bold">M</span>
                        <span class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-500 to-violet-500 ring-2 ring-[#11101c] flex items-center justify-center text-xs font-bold">A</span>
                        <span class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-pink-500 ring-2 ring-[#11101c] flex items-center justify-center text-xs font-bold">R</span>
                        <span class="w-8 h-8 rounded-full bg-white/10 ring-2 ring-[#11101c] flex items-center justify-center text-xs font-semibold">+5</span>
                    </div>
                    <div class="text-xs"><div class="font-semibold text-white">8 active members</div><div class="text-gray-500">3 online now</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- BIG FEATURES --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        @foreach($features as $i => $f)
            @php $reverse = $i % 2 === 1; @endphp
            <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div class="{{ $reverse ? 'lg:order-2' : '' }}" data-anim="{{ $reverse ? 'fade-left' : 'fade-right' }}">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/20 border border-violet-400/30 text-violet-300 mb-4">
                        <i class="fas {{ $f['icon'] }}"></i>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">{!! $f['title'] !!}</h2>
                    <p class="mt-4 text-gray-400 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
                <div class="{{ $reverse ? 'lg:order-1' : '' }}" data-anim="{{ $reverse ? 'fade-right' : 'fade-left' }}" data-tilt="5">
                    <div class="img-frame img-tilt aspect-[4/3]">
<<<<<<< HEAD
                        <img src="{{ $f['img'] }}" alt="{{ strip_tags($f['title']) }} preview">
=======
                        <img src="{{ $f['img'] }}" alt="">
>>>>>>> 60d7746 (Saved your changes before starting work)
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- HIGHLIGHT GRID --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Everything else <span class="grad-text">teams actually need</span></h2>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Battle-tested controls so the marketing, ops and finance folks all stay happy.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
            @foreach($highlights as $h)
                <div class="group bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 hover:border-violet-400/40 rounded-2xl p-6 transition">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/15 border border-violet-400/30 flex items-center justify-center text-violet-200 mb-4 group-hover:scale-110 transition">
                        <i class="fas {{ $h['icon'] }}"></i>
                    </div>
                    <h3 class="text-base font-bold text-white">{!! $h['title'] !!}</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">{!! $h['desc'] !!}</p>
                </div>
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
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Bring the whole team on board</h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Free for two seats forever. Add more whenever you need them — no awkward sales call.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Create your workspace</a>
                    <a href="{{ route('site.contact') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Talk to us</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
