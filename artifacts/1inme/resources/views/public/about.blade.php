@extends('public.layouts.site')
@section('content')
@php
    $sections = $page->visibleSections();
    $intro = $sections[0] ?? null;
    $story = array_slice($sections, 1);
    $extraArr = is_array($extra ?? null) ? $extra : [];
    $founder = $extraArr['founder'] ?? [];
    $coFounders = $extraArr['co_founders'] ?? [];
    $team = $extraArr['team'] ?? [];
    $milestones = $extraArr['milestones'] ?? [];

    // New editable groups — every leaf has a literal fallback so the
    // page keeps rendering even if the admin blanks a field or the
    // entire $extra column is empty (fresh install).
    $hero          = is_array($extraArr['hero']           ?? null) ? $extraArr['hero']           : [];
    $valuesCfg     = is_array($extraArr['values']         ?? null) ? $extraArr['values']         : [];
    $storyImages   = is_array($extraArr['story_images']   ?? null) ? $extraArr['story_images']   : [];
    $sectionTitles = is_array($extraArr['section_titles'] ?? null) ? $extraArr['section_titles'] : [];
    $ctaCfg        = is_array($extraArr['cta']            ?? null) ? $extraArr['cta']            : [];

    $or = function ($v, $fallback) {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? $fallback : $v;
    };

    // Hero defaults (single source of truth for the literal fallbacks).
    $heroBadgeLabel    = $or($hero['badge_label']       ?? '', 'About');
    $heroBadgeIcon     = $or($hero['badge_icon']        ?? '', 'fa-heart');
    $heroSideImage     = $or($hero['side_image']        ?? '', asset('images/marketing/about/hero.png'));
    $heroSideImageAlt  = $or($hero['side_image_alt']    ?? '', 'The 1INME studio in Hyderabad');
    $heroLocTitle      = $or($hero['location_title']    ?? '', 'Hyderabad · India');
    $heroLocSubtitle   = $or($hero['location_subtitle'] ?? '', 'Remote-friendly');
    $heroLocIcon       = $or($hero['location_icon']     ?? '', 'fa-location-dot');

    $defaultHeroStats = [
        ['value' => '120000', 'suffix' => '+', 'label' => 'Creators served', 'visible' => true],
        ['value' => '3',      'suffix' => '',  'label' => 'Years young',     'visible' => true],
        ['value' => '9',      'suffix' => '',  'label' => 'Teammates',       'visible' => true],
    ];
    $heroStats = (array)($hero['stats'] ?? $defaultHeroStats);
    $visibleHeroStats = [];
    foreach ($heroStats as $s) {
        if (!is_array($s)) continue;
        $value = trim((string)($s['value'] ?? ''));
        $label = trim((string)($s['label'] ?? ''));
        $visible = array_key_exists('visible', $s) ? (bool)$s['visible'] : true;
        if (!$visible) continue;
        if ($value === '' && $label === '') continue;
        $visibleHeroStats[] = [
            'value'  => $value,
            'suffix' => (string)($s['suffix'] ?? ''),
            'label'  => $label,
        ];
    }

    // Values section defaults.
    $valuesHeading    = $or($valuesCfg['heading']    ?? '', 'What we believe in');
    $valuesSubheading = $or($valuesCfg['subheading'] ?? '', 'Four ideas that show up in every line of code, support reply, and roadmap call.');
    $defaultValueCards = [
        ['icon' => 'fa-bolt',          'title' => 'Ship fast, ship calm', 'desc' => 'New things every week, never on a Friday at 5pm.'],
        ['icon' => 'fa-users',         'title' => 'Creators first',       'desc' => 'Every line of code earns its keep by helping a creator.'],
        ['icon' => 'fa-shield-halved', 'title' => 'Privacy by default',   'desc' => 'No spying, no shady resale, no dark patterns.'],
        ['icon' => 'fa-globe',         'title' => 'Built remote-first',   'desc' => 'A small team across three timezones, talking by writing.'],
    ];
    // If the admin removed all four cards (key present, empty array),
    // hide the row entirely. If the key is absent, fall back to defaults.
    if (array_key_exists('cards', $valuesCfg) && is_array($valuesCfg['cards'])) {
        $valueCards = array_values($valuesCfg['cards']);
    } else {
        $valueCards = $defaultValueCards;
    }

    // Story images.
    $storyOffice = is_array($storyImages['office']    ?? null) ? $storyImages['office']    : [];
    $storyValues = is_array($storyImages['values']    ?? null) ? $storyImages['values']    : [];
    $storyTeam   = is_array($storyImages['team_band'] ?? null) ? $storyImages['team_band'] : [];
    $officeUrl   = $or($storyOffice['url'] ?? '', asset('images/marketing/about/office.png'));
    $officeAlt   = $or($storyOffice['alt'] ?? '', 'Our office');
    $valuesUrl   = $or($storyValues['url'] ?? '', asset('images/marketing/about/values.png'));
    $valuesAlt   = $or($storyValues['alt'] ?? '', 'Working at 1INME');
    $teamBandUrl = $or($storyTeam['url']   ?? '', asset('images/marketing/about/team.png'));
    $teamBandAlt = $or($storyTeam['alt']   ?? '', 'The 1INME team');

    // Lower section titles.
    $founderTitle           = $or($sectionTitles['founder']             ?? '', 'Meet the founder');
    $coFoundersTitle        = $or($sectionTitles['co_founders']         ?? '', 'Co-founders');
    $teamTitle              = $or($sectionTitles['team_title']          ?? '', 'The team');
    $teamSubtitle           = $or($sectionTitles['team_subtitle']       ?? '', 'The folks shipping 1INME every week.');
    $milestonesTitle        = $or($sectionTitles['milestones_title']    ?? '', 'Milestones');
    $milestonesSubtitle     = $or($sectionTitles['milestones_subtitle'] ?? '', 'A short history of how we got here.');

    // CTA: empty URL falls back to the named route.
    $ctaHeading    = $or($ctaCfg['heading']         ?? '', 'Want to build with us?');
    $ctaBody       = $or($ctaCfg['body']            ?? '', 'Whether you are a creator with feedback or a developer who wants to join, we love hearing from you.');
    $ctaPrimaryLbl = $or($ctaCfg['primary_label']   ?? '', 'Try 1INME free');
    $ctaPrimaryUrl = trim((string)($ctaCfg['primary_url']   ?? ''));
    if ($ctaPrimaryUrl === '') $ctaPrimaryUrl = route('register.page');
    $ctaSecondaryLbl = $or($ctaCfg['secondary_label'] ?? '', 'Say hello');
    $ctaSecondaryUrl = trim((string)($ctaCfg['secondary_url'] ?? ''));
    if ($ctaSecondaryUrl === '') $ctaSecondaryUrl = route('site.contact');

    // Lower-section render order. The admin can re-order the seven
    // lower sections of /about; we sanitise their saved list here so a
    // partial or stale value still renders every section exactly once,
    // and an empty value falls back to the canonical default order.
    $defaultLowerOrder = \App\Modules\Common\Support\SitePagesContent::aboutLowerSectionSlugs();
    $savedLowerOrder = (array)($extraArr['section_order'] ?? []);
    $cleanLowerOrder = [];
    $seenLowerSlugs = [];
    foreach ($savedLowerOrder as $slugCandidate) {
        if (!is_string($slugCandidate)) continue;
        $slugCandidate = trim($slugCandidate);
        if (!in_array($slugCandidate, $defaultLowerOrder, true)) continue;
        if (in_array($slugCandidate, $seenLowerSlugs, true)) continue;
        $cleanLowerOrder[] = $slugCandidate;
        $seenLowerSlugs[] = $slugCandidate;
    }
    if (!empty($cleanLowerOrder)) {
        foreach ($defaultLowerOrder as $slugCandidate) {
            if (!in_array($slugCandidate, $seenLowerSlugs, true)) {
                $cleanLowerOrder[] = $slugCandidate;
            }
        }
        $lowerOrder = $cleanLowerOrder;
    } else {
        $lowerOrder = $defaultLowerOrder;
    }

    // Per-section visibility map. Admins can toggle individual lower
    // sections off without losing their content; missing or non-bool
    // entries default to visible (true) so a stale/partial value never
    // silently hides a section.
    $savedSectionVisibility = (array)($extraArr['section_visibility'] ?? []);
    $sectionVisible = [];
    foreach ($defaultLowerOrder as $slugCandidate) {
        if (array_key_exists($slugCandidate, $savedSectionVisibility)) {
            $sectionVisible[$slugCandidate] = filter_var($savedSectionVisibility[$slugCandidate], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        } else {
            $sectionVisible[$slugCandidate] = true;
        }
    }
    // Drop any slug whose visibility is false so the @switch loop below
    // never even renders (or reserves spacing for) a hidden section.
    $lowerOrder = array_values(array_filter($lowerOrder, function ($slugCandidate) use ($sectionVisible) {
        return $sectionVisible[$slugCandidate] ?? true;
    }));

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
@endphp

{{-- HERO --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            @if($heroBadgeLabel !== '')
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold">
                    @if($heroBadgeIcon !== '')<i class="fas {{ $heroBadgeIcon }} text-[10px]"></i>@endif {{ $heroBadgeLabel }}
                </span>
            @endif
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                {{ $intro['heading'] ?? $page->title }}
            </h1>
            @if(!empty($intro['body']))
                <p class="mt-5 text-lg text-gray-300 max-w-xl leading-relaxed">{{ $intro['body'] }}</p>
            @elseif($page->meta_description)
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">{{ $page->meta_description }}</p>
            @endif
            @if(!empty($visibleHeroStats))
                <div class="mt-8 flex items-center gap-6 text-sm" data-anim="fade-up" data-stagger>
                    @foreach($visibleHeroStats as $i => $s)
                        @if($i > 0)
                            <div class="w-px h-10 bg-white/10 {{ $i >= 2 ? 'hidden sm:block' : '' }}"></div>
                        @endif
                        <div class="{{ $i >= 2 ? 'hidden sm:block' : '' }}">
                            <div class="text-3xl font-bold">
                                @if(is_numeric($s['value']))
                                    <span data-count="{{ $s['value'] }}"@if($s['suffix'] !== '') data-count-suffix="{{ $s['suffix'] }}"@endif></span>
                                @else
                                    {{ $s['value'] }}{{ $s['suffix'] }}
                                @endif
                            </div>
                            @if($s['label'] !== '')
                                <div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">{{ $s['label'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div data-anim="fade-left" data-tilt="5" class="relative">
            <div class="img-frame img-tilt aspect-[16/10]">
                <img src="{{ $heroSideImage }}" alt="{{ $heroSideImageAlt }}">
            </div>
            @if($heroLocTitle !== '' || $heroLocSubtitle !== '')
                <div class="absolute -bottom-5 -left-5 bg-[#11101c] border border-white/10 rounded-2xl p-3 pr-4 flex items-center gap-3 shadow-2xl float-y">
                    @if($heroLocIcon !== '')<i class="fas {{ $heroLocIcon }} text-violet-400"></i>@endif
                    <div class="text-xs">
                        @if($heroLocTitle !== '')<div class="font-semibold text-white">{{ $heroLocTitle }}</div>@endif
                        @if($heroLocSubtitle !== '')<div class="text-gray-400">{{ $heroLocSubtitle }}</div>@endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

@include('public.partials.marketing-stats')

{{-- VALUES --}}
@if(!empty($valueCards))
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($valuesHeading !== '' || $valuesSubheading !== '')
            <div class="text-center mb-10" data-anim="fade-up">
                @if($valuesHeading !== '')<h2 class="text-3xl sm:text-4xl font-bold tracking-tight">{{ $valuesHeading }}</h2>@endif
                @if($valuesSubheading !== '')<p class="mt-3 text-gray-400 max-w-2xl mx-auto">{{ $valuesSubheading }}</p>@endif
            </div>
        @endif
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5" data-anim="fade-up" data-stagger>
            @foreach($valueCards as $v)
                <div class="bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 hover:border-violet-400/40 rounded-2xl p-6 transition-all duration-300 hover:-translate-y-1">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/15 border border-violet-400/30 flex items-center justify-center text-violet-200 mb-4">
                        <i class="fas {{ $v['icon'] ?? 'fa-circle-dot' }}"></i>
                    </div>
                    @if(!empty($v['title']))<h3 class="text-base font-bold text-white">{{ $v['title'] }}</h3>@endif
                    @if(!empty($v['desc']))<p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ $v['desc'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{--
    Lower /about sections rendered in admin-chosen order. Each @case
    block holds the original markup for one section so admins can
    re-order them from the editor without us moving HTML around.
--}}
@foreach($lowerOrder as $lowerSlug)
    @switch($lowerSlug)
        @case('story')
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
                            <img src="{{ $officeUrl }}" alt="{{ $officeAlt }}">
                        </div>
                        <div class="img-frame aspect-[4/3]" data-anim="fade-left" data-tilt="4">
                            <img src="{{ $valuesUrl }}" alt="{{ $valuesAlt }}">
                        </div>
                    </div>
                </div>
            </section>
            @endif
            @break

        @case('team_band')
            <section class="pb-20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="img-frame aspect-[16/7]" data-anim="fade-up" data-tilt="3">
                        <img src="{{ $teamBandUrl }}" alt="{{ $teamBandAlt }}">
                    </div>
                </div>
            </section>
            @break

        @case('founder')
            @if(!empty($founder['name']) || !empty($founder['bio']))
            <section class="pb-16">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8" data-anim="fade-up">
                    @if($founderTitle !== '')
                        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-8 tracking-tight">{{ $founderTitle }}</h2>
                    @endif
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
            @break

        @case('co_founders')
            @if(!empty($coFounders))
            <section class="pb-16">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    @if($coFoundersTitle !== '')
                        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-8 tracking-tight" data-anim="fade-up">{{ $coFoundersTitle }}</h2>
                    @endif
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
            @break

        @case('team')
            @if(!empty($team))
            <section class="pb-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    @if($teamTitle !== '')
                        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-2 tracking-tight" data-anim="fade-up">{{ $teamTitle }}</h2>
                    @endif
                    @if($teamSubtitle !== '')
                        <p class="text-center text-gray-400 mb-8" data-anim="fade-up">{{ $teamSubtitle }}</p>
                    @endif
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
            @break

        @case('milestones')
            @if(!empty($milestones))
            <section class="pb-24">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8" data-anim="fade-up">
                    @if($milestonesTitle !== '')
                        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-2 tracking-tight">{{ $milestonesTitle }}</h2>
                    @endif
                    @if($milestonesSubtitle !== '')
                        <p class="text-center text-gray-400 mb-10">{{ $milestonesSubtitle }}</p>
                    @endif
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
            @break

        @case('cta')
            <section class="pb-24">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
                        <div class="mesh-bg opacity-50"></div>
                        <div class="relative">
                            @if($ctaHeading !== '')
                                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">{{ $ctaHeading }}</h3>
                            @endif
                            @if($ctaBody !== '')
                                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">{{ $ctaBody }}</p>
                            @endif
                            @if($ctaPrimaryLbl !== '' || $ctaSecondaryLbl !== '')
                                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                                    @if($ctaPrimaryLbl !== '')
                                        <a href="{{ $ctaPrimaryUrl }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">{{ $ctaPrimaryLbl }}</a>
                                    @endif
                                    @if($ctaSecondaryLbl !== '')
                                        <a href="{{ $ctaSecondaryUrl }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">{{ $ctaSecondaryLbl }}</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
            @break
    @endswitch
@endforeach

@include('public.blogs.partials.latest-cta')
@endsection
