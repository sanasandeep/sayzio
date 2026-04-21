@extends('public.layouts.site')
@section('content')
@php
    $sections = $page->visibleSections();
    $intro = $sections[0] ?? null;
    $story = array_slice($sections, 1);
    $founder = $extra['founder'] ?? [];
    $coFounders = $extra['co_founders'] ?? [];
    $team = $extra['team'] ?? [];
    $milestones = $extra['milestones'] ?? [];

    $personPhoto = function (array $p) {
        $url = trim((string)($p['photo'] ?? ''));
        return $url !== '' ? $url : null;
    };
    $personInitials = function (array $p) {
        $name = trim((string)($p['name'] ?? ''));
        if ($name === '') return '?';
        $parts = preg_split('/\s+/', $name);
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        return strtoupper($a . $b) ?: '?';
    };
    $milestoneLabel = function (string $date) {
        if ($date === '') return '';
        $ts = strtotime($date);
        if ($ts === false) return $date;
        return strlen($date) <= 7 ? date('M Y', $ts) : date('M j, Y', $ts);
    };
    $defaultFounderPhoto = asset('images/marketing/about/founder.png');
    $valueCards = [
        ['icon' => 'fa-bolt',         'title' => 'Ship fast, ship calm', 'desc' => 'New things every week, never on a Friday at 5pm.'],
        ['icon' => 'fa-users',        'title' => 'Creators first',       'desc' => 'Every line of code earns its keep by helping a creator.'],
        ['icon' => 'fa-shield-halved','title' => 'Privacy by default',   'desc' => 'No spying, no shady resale, no dark patterns.'],
        ['icon' => 'fa-globe',        'title' => 'Built remote-first',   'desc' => 'A small team across three timezones, talking by writing.'],
    ];
@endphp

{{-- HERO --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold">
                <i class="fas fa-heart text-[10px]"></i> About
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                {{ $intro['heading'] ?? $page->title }}
            </h1>
            @if(!empty($intro['body']))
                <p class="mt-5 text-lg text-gray-300 max-w-xl leading-relaxed">{{ $intro['body'] }}</p>
            @elseif($page->meta_description)
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">{{ $page->meta_description }}</p>
            @endif
            <div class="mt-8 flex items-center gap-6 text-sm" data-anim="fade-up" data-stagger>
                <div><div class="text-3xl font-bold"><span data-count="120000" data-count-suffix="+"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">Creators served</div></div>
                <div class="w-px h-10 bg-white/10"></div>
                <div><div class="text-3xl font-bold"><span data-count="3"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">Years young</div></div>
                <div class="w-px h-10 bg-white/10 hidden sm:block"></div>
                <div class="hidden sm:block"><div class="text-3xl font-bold"><span data-count="9"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">Teammates</div></div>
            </div>
        </div>
        <div data-anim="fade-left" data-tilt="5" class="relative">
            <div class="img-frame img-tilt aspect-[16/10]">
                <img src="{{ asset('images/marketing/about/hero.png') }}" alt="The 1INME studio in Hyderabad">
            </div>
            <div class="absolute -bottom-5 -left-5 bg-[#11101c] border border-white/10 rounded-2xl p-3 pr-4 flex items-center gap-3 shadow-2xl float-y">
                <i class="fas fa-location-dot text-violet-400"></i>
                <div class="text-xs"><div class="font-semibold text-white">Hyderabad · India</div><div class="text-gray-400">Remote-friendly</div></div>
            </div>
        </div>
    </div>
</section>

{{-- VALUES --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">What we believe in</h2>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Four ideas that show up in every line of code, support reply, and roadmap call.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5" data-anim="fade-up" data-stagger>
            @foreach($valueCards as $v)
                <div class="bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 hover:border-violet-400/40 rounded-2xl p-6 transition-all duration-300 hover:-translate-y-1">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/15 border border-violet-400/30 flex items-center justify-center text-violet-200 mb-4">
                        <i class="fas {{ $v['icon'] }}"></i>
                    </div>
                    <h3 class="text-base font-bold text-white">{{ $v['title'] }}</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ $v['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- STORY --}}
@if(!empty($story))
<section class="pb-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-[1.1fr_1fr] gap-10 items-start">
        <div class="space-y-6">
            @foreach($story as $s)
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8" data-anim="fade-up">
                    @if(!empty($s['heading']))
                        <h2 class="text-xl sm:text-2xl font-bold mb-3 text-white">{{ $s['heading'] }}</h2>
                    @endif
                    <div class="prose-light text-gray-300 leading-relaxed">{!! \App\Services\SafeHtml::render($s['body'] ?? '') !!}</div>
                </div>
            @endforeach
        </div>
        <div class="space-y-5 lg:sticky lg:top-24">
            <div class="img-frame aspect-[4/3]" data-anim="fade-left" data-tilt="4">
                <img src="{{ asset('images/marketing/about/office.png') }}" alt="Our office">
            </div>
            <div class="img-frame aspect-[4/3]" data-anim="fade-left" data-tilt="4">
                <img src="{{ asset('images/marketing/about/values.png') }}" alt="Working at 1INME">
            </div>
        </div>
    </div>
</section>
@endif

{{-- TEAM PHOTO BAND --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="img-frame aspect-[16/7]" data-anim="fade-up" data-tilt="3">
            <img src="{{ asset('images/marketing/about/team.png') }}" alt="The 1INME team">
        </div>
    </div>
</section>

{{-- FOUNDER --}}
@if(!empty($founder['name']) || !empty($founder['bio']))
<section class="pb-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8" data-anim="fade-up">
        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-8 tracking-tight">Meet the founder</h2>
        <div class="bg-gradient-to-br from-violet-500/10 to-fuchsia-500/5 border border-white/10 rounded-3xl p-6 sm:p-10 grid sm:grid-cols-[auto_1fr] gap-6 sm:gap-10 items-center">
            <div class="shrink-0 mx-auto sm:mx-0">
                @php $founderPhoto = $personPhoto($founder) ?? $defaultFounderPhoto; @endphp
                <div class="relative">
                    <img src="{{ $founderPhoto }}" alt="{{ $founder['name'] ?? '' }}" class="w-40 h-40 rounded-full object-cover border-2 border-violet-400/40 shadow-2xl">
                    <div class="absolute -bottom-2 -right-2 w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 border-4 border-[#1e2330] flex items-center justify-center text-white">
                        <i class="fas fa-crown text-sm"></i>
                    </div>
                </div>
            </div>
            <div class="text-center sm:text-left">
                <h3 class="text-2xl sm:text-3xl font-bold text-white">{{ $founder['name'] ?? '' }}</h3>
                @if(!empty($founder['role']))
                    <p class="text-sm text-violet-300 mt-1 font-medium uppercase tracking-wider">{{ $founder['role'] }}</p>
                @endif
                @if(!empty($founder['bio']))
                    <p class="text-gray-300 mt-4 leading-relaxed">{{ $founder['bio'] }}</p>
                @endif
                @php $fl = $founder['links'] ?? []; @endphp
                @if(!empty($fl['twitter']) || !empty($fl['linkedin']))
                    <div class="mt-4 flex gap-3 justify-center sm:justify-start">
                        @if(!empty($fl['twitter']))
                            <a href="{{ $fl['twitter'] }}" target="_blank" rel="noopener" class="w-9 h-9 rounded-lg bg-white/5 hover:bg-violet-600 border border-white/10 flex items-center justify-center text-gray-300 hover:text-white transition"><i class="fab fa-x-twitter"></i></a>
                        @endif
                        @if(!empty($fl['linkedin']))
                            <a href="{{ $fl['linkedin'] }}" target="_blank" rel="noopener" class="w-9 h-9 rounded-lg bg-white/5 hover:bg-violet-600 border border-white/10 flex items-center justify-center text-gray-300 hover:text-white transition"><i class="fab fa-linkedin"></i></a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endif

{{-- CO-FOUNDERS --}}
@if(!empty($coFounders))
<section class="pb-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-8 tracking-tight" data-anim="fade-up">Co-founders</h2>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
            @foreach($coFounders as $p)
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 text-center hover:border-violet-400/40 transition hover:-translate-y-1 duration-300">
                    @if($photo = $personPhoto($p))
                        <img src="{{ $photo }}" alt="{{ $p['name'] ?? '' }}" class="w-24 h-24 rounded-full object-cover mx-auto border-2 border-white/10">
                    @else
                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-sky-500 to-violet-500 flex items-center justify-center text-xl font-bold text-white mx-auto border-2 border-white/10">
                            {{ $personInitials($p) }}
                        </div>
                    @endif
                    <h3 class="mt-4 text-lg font-bold text-white">{{ $p['name'] ?? '' }}</h3>
                    @if(!empty($p['role']))<p class="text-xs text-violet-300 mt-1 font-medium uppercase tracking-wider">{{ $p['role'] }}</p>@endif
                    @if(!empty($p['bio']))<p class="mt-3 text-sm text-gray-300 leading-relaxed">{{ $p['bio'] }}</p>@endif
                    @php $links = $p['links'] ?? []; @endphp
                    @if(!empty($links['twitter']) || !empty($links['linkedin']))
                        <div class="mt-3 flex gap-3 justify-center">
                            @if(!empty($links['twitter']))<a href="{{ $links['twitter'] }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-white"><i class="fab fa-x-twitter"></i></a>@endif
                            @if(!empty($links['linkedin']))<a href="{{ $links['linkedin'] }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-white"><i class="fab fa-linkedin"></i></a>@endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- TEAM --}}
@if(!empty($team))
<section class="pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-2 tracking-tight" data-anim="fade-up">The team</h2>
        <p class="text-center text-gray-400 mb-8" data-anim="fade-up">The folks shipping 1INME every week.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" data-anim="fade-up" data-stagger>
            @foreach($team as $p)
                <div class="bg-white/[0.03] border border-white/10 rounded-xl p-4 text-center hover:bg-white/[0.05] hover:border-violet-400/40 transition">
                    @if($photo = $personPhoto($p))
                        <img src="{{ $photo }}" alt="{{ $p['name'] ?? '' }}" class="w-16 h-16 rounded-full object-cover mx-auto">
                    @else
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-fuchsia-500/70 to-violet-500/70 flex items-center justify-center text-sm font-bold text-white mx-auto">
                            {{ $personInitials($p) }}
                        </div>
                    @endif
                    <div class="mt-3 text-sm font-semibold text-white">{{ $p['name'] ?? '' }}</div>
                    @if(!empty($p['role']))<div class="text-[11px] text-violet-300 mt-0.5 uppercase tracking-wider">{{ $p['role'] }}</div>@endif
                    @if(!empty($p['bio']))<p class="mt-2 text-xs text-gray-400 leading-snug">{{ $p['bio'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- MILESTONES --}}
@if(!empty($milestones))
<section class="pb-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8" data-anim="fade-up">
        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-2 tracking-tight">Milestones</h2>
        <p class="text-center text-gray-400 mb-10">A short history of how we got here.</p>
        <ol class="relative border-l border-violet-400/30 pl-6 ml-2 space-y-8">
            @foreach($milestones as $m)
                <li class="relative" data-anim="fade-right">
                    <span class="absolute -left-[34px] top-1 w-4 h-4 rounded-full bg-violet-500 border-4 border-[#1e2330] ring-2 ring-violet-400/40 pulse-dot text-violet-400/40"></span>
                    @if(!empty($m['date']))
                        <div class="text-xs uppercase tracking-wider text-violet-300 font-semibold">{{ $milestoneLabel($m['date']) }}</div>
                    @endif
                    @if(!empty($m['title']))<h3 class="text-lg font-bold text-white mt-1">{{ $m['title'] }}</h3>@endif
                    @if(!empty($m['description']))<p class="text-sm text-gray-300 mt-1 leading-relaxed">{{ $m['description'] }}</p>@endif
                </li>
            @endforeach
        </ol>
    </div>
</section>
@endif

{{-- CTA --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Want to build with us?</h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Whether you are a creator with feedback or a developer who wants to join, we love hearing from you.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Try 1INME free</a>
                    <a href="{{ route('site.contact') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Say hello</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
