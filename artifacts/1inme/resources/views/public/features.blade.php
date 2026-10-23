@extends('public.layouts.site')
@section('title', $page->title ?? 'Features')
@php
    $groups = \App\Modules\Common\Support\FeatureDirectory::groups($categories);
    $formatCount = count(collect($categories)->firstWhere('id', 'link-types')['features'] ?? []);
    $startUrl = auth()->check() ? route('user.dashboard') : route('register.page');
    $startLabel = auth()->check() ? 'Open your dashboard' : 'Get started free';
    $demoAliases = [];
    try {
        $demoData = \Illuminate\Support\Facades\Cache::remember(
            \App\Modules\Common\Controllers\SitePageController::DEMOS_CACHE_KEY,
            300,
            fn () => \App\Modules\Common\Controllers\SitePageController::buildDemosData()
        );
        $demoAliases = array_fill_keys(array_keys((array) ($demoData['links'] ?? [])), true);
    } catch (\Throwable $e) {
        $demoAliases = [];
    }
@endphp
@push('head')
<link rel="stylesheet" href="{{ asset('css/features-directory.css') }}?v=1">
@endpush
@section('content')
<div class="fx-directory" data-feature-directory>
    <section class="fx-hero">
        <div class="fx-wrap">
            <div class="fx-hero-top">
                <div>
                    <p class="fx-eyebrow"><span></span> THE SAYZIO TOOLKIT</p>
                    <h1>One place.<br><span>So many possibilities.</span></h1>
                    <p class="fx-lead">Create your online presence. Meet your next customer. Grow with AI. Explore the tools that bring it all together.</p>
                    <div class="fx-actions">
                        <a class="fx-button fx-primary" href="{{ $startUrl }}">{{ $startLabel }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                        <a class="fx-button fx-secondary" href="{{ url('/demos') }}">Explore live demos <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                    </div>
                    <p class="fx-small">Start with what you need. Add more as you grow.</p>
                </div>
                <div class="fx-product-map" aria-label="Sayzio connects your pages, AI tools, audience and business">
                    <div class="fx-map-label">YOUR CONNECTED WORKSPACE</div>
                    <div class="fx-map-center"><img src="{{ asset('images/marketing/features/biolink.png') }}" alt="" loading="eager"><strong>Your next big thing</strong><span>Built with Sayzio</span></div>
                    <a href="#group-create" class="fx-node fx-node-create"><i class="fas fa-layer-group" aria-hidden="true"></i><span>Create</span></a>
                    <a href="#group-ai" class="fx-node fx-node-ai"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i><span>Ask AI</span></a>
                    <a href="#group-audience" class="fx-node fx-node-audience"><i class="fas fa-comments" aria-hidden="true"></i><span>Connect</span></a>
                    <a href="#group-sell" class="fx-node fx-node-sell"><i class="fas fa-store" aria-hidden="true"></i><span>Grow</span></a>
                    <div class="fx-map-caption"><span></span> Your pages. Your people. All connected.</div>
                </div>
            </div>
            <div class="fx-at-a-glance">
                <span><strong>{{ $formatCount }}</strong> page & link formats</span>
                <span><strong>{{ count($groups) }}</strong> ways to explore</span>
                <span><strong>One</strong> connected workspace</span>
                <a href="#categories">Find your next tool <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
            </div>
        </div>
    </section>
    <section class="fx-explore fx-wrap" id="categories" aria-labelledby="fx-explore-heading">
        <div class="fx-directory-heading">
            <div><p class="fx-eyebrow">EXPLORE THE POSSIBILITIES</p><h2 id="fx-explore-heading">Find the right tool for your next step.</h2></div>
            <div class="fx-search" data-search-control hidden>
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <label class="fx-sr-only" for="fx-search">Search all features</label>
                <input type="search" id="fx-search" placeholder="Try bookings, AI, QR codes…" autocomplete="off" aria-controls="fx-results">
                <button type="button" data-clear-search aria-label="Clear feature search" hidden><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
        </div>
        <p class="fx-availability">Features vary by plan. AI tools use coins; connected services need setup. <a href="{{ url('/pricing') }}">Compare plans <i class="fas fa-arrow-right" aria-hidden="true"></i></a></p>
        <div class="fx-directory-layout">
            <aside class="fx-sidebar">
                <nav aria-label="Feature categories">
                    <p class="fx-nav-title">EXPLORE BY CATEGORY</p>
                    @foreach($groups as $group)
                        <a href="#group-{{ $group['id'] }}" data-group-link="{{ $group['id'] }}"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i><span>{{ $group['title'] }}</span><i class="fas fa-arrow-up-right-from-square fx-nav-arrow" aria-hidden="true"></i></a>
                    @endforeach
                    <div class="fx-nav-help"><strong>See it in action.</strong><p>Explore real page formats before you build your own.</p><a href="{{ url('/demos') }}">Visit the demo gallery <span aria-hidden="true">↗</span></a></div>
                </nav>
            </aside>
            <div class="fx-results" id="fx-results">
                <p class="fx-result-status" data-result-status role="status" aria-live="polite" hidden></p>
                <div class="fx-empty" data-empty hidden><i class="fas fa-magnifying-glass" aria-hidden="true"></i><h3>No matching features yet.</h3><p>Try a broader term such as “pages”, “contacts” or “AI”.</p><button class="fx-button fx-secondary" type="button" data-reset-search>Clear search</button></div>
                @foreach($groups as $groupIndex => $group)
                    <section class="fx-group" id="group-{{ $group['id'] }}" data-feature-group="{{ $group['id'] }}" aria-labelledby="fx-heading-{{ $group['id'] }}">
                        <header class="fx-group-heading">
                            <div class="fx-group-kicker"><span>{{ str_pad($groupIndex + 1, 2, '0', STR_PAD_LEFT) }}</span> {{ $group['title'] }}</div>
                            <h2 id="fx-heading-{{ $group['id'] }}">{{ $group['heading'] }}</h2>
                            <p>{{ $group['intro'] }}</p>
                        </header>
                        <div class="fx-card-grid">
                            @foreach($group['items'] as $cat)
                                <article id="cat-{{ $cat['id'] }}" class="fx-card {{ $cat['id'] === 'link-types' ? 'fx-card-formats' : '' }}" data-feature-card data-search="{{ mb_strtolower($group['title'] . ' ' . $cat['heading'] . ' ' . $cat['intro'] . ' ' . implode(' ', array_map(fn ($f) => ($f['name'] ?? $f[0] ?? '') . ' ' . ($f['description'] ?? $f[1] ?? ''), $cat['features']))) }}">
                                    <div class="fx-card-top"><span class="fx-card-icon"><i class="fas {{ $cat['icon'] }}" aria-hidden="true"></i></span><span class="fx-card-count">{{ count($cat['features']) }} {{ count($cat['features']) === 1 ? 'capability' : 'capabilities' }}</span></div>
                                    <h3>{{ $cat['heading'] }}</h3>
                                    <p class="fx-card-intro">{{ $cat['intro'] }}</p>
                                    @if($cat['id'] === 'link-types')
                                        <div class="fx-format-grid">
                                            @foreach($cat['features'] as $feat)
                                                @php
                                                    $name = $feat['name'] ?? $feat[0] ?? '';
                                                    $alias = 'demo-type-' . \Illuminate\Support\Str::slug($name);
                                                    $href = trim((string) ($feat['link'] ?? ''));
                                                    if ($href === '' && isset($demoAliases[$alias])) $href = url('/' . $alias);
                                                @endphp
                                                <details class="fx-format">
                                                    <summary><i class="fas {{ ($feat['icon'] ?? '') ?: 'fa-file-lines' }}" aria-hidden="true"></i><strong>{{ $name }}</strong><span aria-hidden="true">+</span></summary>
                                                    <p>{{ $feat['description'] ?? $feat[1] ?? '' }}</p>
                                                    @if($href !== '')<a href="{{ $href }}" aria-label="See the live {{ $name }} demo">Explore {{ $name }} <span aria-hidden="true">↗</span></a>@endif
                                                </details>
                                            @endforeach
                                        </div>
                                        <a class="fx-text-link" href="{{ url('/demos') }}">Browse the live demo gallery <span aria-hidden="true">↗</span></a>
                                    @else
                                        <div class="fx-feature-list">
                                            @foreach(array_slice($cat['features'], 0, 3) as $feat)
                                                @include('public.partials.feature-directory-row', ['feat' => $feat])
                                            @endforeach
                                        </div>
                                        @if(count($cat['features']) > 3)
                                            <details class="fx-more">
                                                <summary><span class="fx-more-closed">Explore {{ count($cat['features']) - 3 }} more</span><span class="fx-more-open">Show less</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                                                <div class="fx-feature-list">
                                                    @foreach(array_slice($cat['features'], 3) as $feat)
                                                        @include('public.partials.feature-directory-row', ['feat' => $feat])
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </section>
    <section class="fx-wrap fx-final">
        <p class="fx-eyebrow">YOUR NEXT STEP STARTS HERE</p><h2>Start small. Build something bigger.</h2>
        <p>A page today. An audience tomorrow. A workspace that grows with you.</p>
        <div class="fx-actions"><a href="{{ $startUrl }}" class="fx-button fx-primary">{{ $startLabel }} <span aria-hidden="true">↗</span></a><a href="{{ url('/pricing') }}" class="fx-button fx-secondary">Find your plan</a></div>
    </section>
</div>
@include('public.partials.subscribe-block', ['heading' => 'Keep up with what’s new.', 'subtext' => 'Product updates, useful ideas and new ways to build with Sayzio.', 'source' => 'features'])
@endsection
@push('scripts')
<script src="{{ asset('js/features-directory.js') }}?v=1" defer></script>
@endpush
