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
        // YYYY-MM date → "Mon YYYY"; full date → "Mon j, YYYY"
        return strlen($date) <= 7 ? date('M Y', $ts) : date('M j, Y', $ts);
    };
@endphp

<section class="relative pt-16 pb-12 lg:pt-24 lg:pb-16 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
    <div class="absolute -bottom-40 -right-32 w-[420px] h-[420px] rounded-full" style="background:radial-gradient(circle,rgba(236,72,153,0.14) 0%,transparent 70%);"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">{{ $intro['heading'] ?? $page->title }}</h1>
        @if(!empty($intro['body']))
            <p class="mt-4 text-lg text-gray-300 max-w-2xl mx-auto leading-relaxed">{{ $intro['body'] }}</p>
        @elseif($page->meta_description)
            <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">{{ $page->meta_description }}</p>
        @endif
    </div>
</section>

@if(!empty($story))
<section class="pb-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @foreach($story as $s)
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8">
                @if(!empty($s['heading']))
                    <h2 class="text-xl sm:text-2xl font-bold mb-3 text-white">{{ $s['heading'] }}</h2>
                @endif
                <div class="prose-light text-gray-300 leading-relaxed">{!! \App\Services\SafeHtml::render($s['body'] ?? '') !!}</div>
            </div>
        @endforeach
    </div>
</section>
@endif

@if(!empty($founder['name']) || !empty($founder['bio']))
<section class="pb-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-center mb-8">Meet the founder</h2>
        <div class="bg-gradient-to-br from-violet-500/10 to-fuchsia-500/5 border border-white/10 rounded-2xl p-6 sm:p-10 flex flex-col sm:flex-row items-center sm:items-start gap-6">
            <div class="shrink-0">
                @if($photo = $personPhoto($founder))
                    <img src="{{ $photo }}" alt="{{ $founder['name'] ?? '' }}" class="w-32 h-32 rounded-full object-cover border-2 border-violet-400/40">
                @else
                    <div class="w-32 h-32 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center text-3xl font-bold text-white border-2 border-violet-400/40">
                        {{ $personInitials($founder) }}
                    </div>
                @endif
            </div>
            <div class="flex-1 text-center sm:text-left">
                <h3 class="text-xl sm:text-2xl font-bold text-white">{{ $founder['name'] ?? '' }}</h3>
                @if(!empty($founder['role']))
                    <p class="text-sm text-violet-300 mt-1 font-medium">{{ $founder['role'] }}</p>
                @endif
                @if(!empty($founder['bio']))
                    <p class="text-gray-300 mt-3 leading-relaxed">{{ $founder['bio'] }}</p>
                @endif
                @php $fl = $founder['links'] ?? []; @endphp
                @if(!empty($fl['twitter']) || !empty($fl['linkedin']))
                    <div class="mt-4 flex gap-3 justify-center sm:justify-start">
                        @if(!empty($fl['twitter']))
                            <a href="{{ $fl['twitter'] }}" target="_blank" rel="noopener" class="text-gray-400 hover:text-white"><i class="fab fa-x-twitter text-lg"></i></a>
                        @endif
                        @if(!empty($fl['linkedin']))
                            <a href="{{ $fl['linkedin'] }}" target="_blank" rel="noopener" class="text-gray-400 hover:text-white"><i class="fab fa-linkedin text-lg"></i></a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endif

@if(!empty($coFounders))
<section class="pb-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-center mb-8">Co-founders</h2>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-5">
            @foreach($coFounders as $p)
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 text-center">
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

@if(!empty($team))
<section class="pb-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-center mb-2">The team</h2>
        <p class="text-center text-gray-400 mb-8">The folks shipping 1INME every week.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            @foreach($team as $p)
                <div class="bg-white/[0.03] border border-white/10 rounded-xl p-4 text-center hover:bg-white/[0.05] transition">
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

@if(!empty($milestones))
<section class="pb-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-center mb-2">Milestones</h2>
        <p class="text-center text-gray-400 mb-10">A short history of how we got here.</p>
        <ol class="relative border-l border-violet-400/30 pl-6 ml-2 space-y-8">
            @foreach($milestones as $m)
                <li class="relative">
                    <span class="absolute -left-[34px] top-1 w-4 h-4 rounded-full bg-violet-500 border-4 border-[#1e2330] ring-2 ring-violet-400/40"></span>
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

@endsection
