@extends('public.layouts.site')

@section('title', 'API Documentation')

@php
    $base = url('/api/v1');
    $sections = [
        ['id' => 'intro',         'label' => 'Introduction',     'icon' => 'fa-rocket'],
        ['id' => 'auth-overview', 'label' => 'Authentication',   'icon' => 'fa-key'],
        ['id' => 'errors',        'label' => 'Errors & codes',   'icon' => 'fa-triangle-exclamation'],
        ['id' => 'pagination',    'label' => 'Pagination',       'icon' => 'fa-list-ol'],
        ['id' => 'rate-limits',   'label' => 'Rate limits',      'icon' => 'fa-gauge-high'],
        ['id' => 'visibility',    'label' => 'Visibility tiers', 'icon' => 'fa-eye'],
        ['id' => 'auth',          'label' => 'Auth endpoints',   'icon' => 'fa-user-shield'],
        ['id' => 'profile',       'label' => 'Profile',          'icon' => 'fa-id-card'],
        ['id' => 'links',         'label' => 'Links',            'icon' => 'fa-link'],
        ['id' => 'biolinks',      'label' => 'Biolinks',         'icon' => 'fa-square-share-nodes'],
        ['id' => 'feed',          'label' => 'Feed',             'icon' => 'fa-rss'],
        ['id' => 'follows',       'label' => 'Follows',          'icon' => 'fa-user-plus'],
        ['id' => 'subscribers',   'label' => 'Subscribers',      'icon' => 'fa-envelope-open-text'],
        ['id' => 'discovery',     'label' => 'Discovery',        'icon' => 'fa-compass'],
        ['id' => 'health',        'label' => 'Health',           'icon' => 'fa-heart-pulse'],
    ];
@endphp

@push('head')
<style>
    .doc-method { font-family: 'JetBrains Mono', ui-monospace, monospace; font-weight: 700; letter-spacing: .04em; }
    .m-get    { background: rgba( 59,130,246,.15); color:#60a5fa; }
    .m-post   { background: rgba( 16,185,129,.15); color:#34d399; }
    .m-patch  { background: rgba(245,158, 11,.15); color:#fbbf24; }
    .m-put    { background: rgba(245,158, 11,.15); color:#fbbf24; }
    .m-delete { background: rgba(239, 68, 68,.15); color:#f87171; }
    pre.doc-code {
        background: #0b0f1a; border: 1px solid rgba(255,255,255,.07); border-radius: .75rem;
        padding: 1rem 1.1rem; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12.5px;
        line-height: 1.55; color: #e5e7eb; overflow-x: auto;
    }
    .doc-code .tk-key { color:#90acff; }
    .doc-code .tk-str { color:#86efac; }
    .doc-code .tk-num { color:#fbbf24; }
    .doc-code .tk-cmt { color:#64748b; }
    .copy-btn { transition: all .15s ease; }
    .copy-btn:hover { color:#90acff; }
    .copy-btn.copied { color:#34d399; }
    html { scroll-behavior: smooth; scroll-padding-top: 5rem; }
    .endpoint-card { transition: border-color .15s ease, transform .15s ease; }
    .endpoint-card:hover { border-color: rgba(144,172,255,.35); }
    .anchor-link { opacity:0; transition: opacity .15s ease; }
    .endpoint-card:hover .anchor-link { opacity:1; }
    .sidebar-link.active { color:#90acff; background: rgba(144,172,255,.08); border-left-color:#90acff; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-20">

    {{-- Hero --}}
    <header class="mb-12 relative overflow-hidden rounded-3xl border border-white/10 grad-border">
        <div class="mesh-bg opacity-60"></div>
        <div class="relative grid lg:grid-cols-[1.1fr_1fr] gap-8 items-center p-6 sm:p-10">
            <div data-anim="fade-right">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-400/20 text-xs text-blue-300 uppercase tracking-wider font-semibold mb-3">
                    <i class="fas fa-bolt text-[10px]"></i> REST API · v1
                </div>
                <h1 class="text-4xl sm:text-5xl font-bold tracking-tight leading-[1.05]">Build on <span class="grad-text">Sayzio</span>.</h1>
                <p class="mt-4 text-gray-300 max-w-2xl leading-relaxed">
                    Bearer-token authenticated, JSON in / JSON out. Power mobile apps, integrations and automations on top of every link, biolink and creator post.
                </p>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <code class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm text-blue-300 font-mono">{{ $base }}</code>
                    <button type="button" data-copy="{{ $base }}" class="copy-btn px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm text-gray-300 inline-flex items-center gap-2">
                        <i class="fas fa-copy text-xs"></i> <span>Copy base URL</span>
                    </button>
                    <a href="#auth" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-sm font-semibold text-white inline-flex items-center gap-2">
                        <i class="fas fa-arrow-down text-xs"></i> Get a token
                    </a>
                </div>
                <div class="mt-7 flex items-center gap-6 text-sm" data-anim="fade-up" data-stagger>
                    <div><div class="text-2xl font-bold"><span data-count="60" data-count-suffix="+"></span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-0.5">Endpoints</div></div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div><div class="text-2xl font-bold"><span data-count="120"></span><span class="text-blue-300">ms</span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-0.5">p50 latency</div></div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div><div class="text-2xl font-bold"><span data-count="99.99" data-count-suffix="%"></span></div><div class="text-[11px] uppercase tracking-wider text-gray-500 mt-0.5">Uptime</div></div>
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="5">
                <div class="img-frame img-tilt aspect-[16/10]">
                    <img src="{{ asset('images/marketing/api-docs/hero.png') }}" alt="Code editor showing the Sayzio REST API">
                </div>
            </div>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-10">

        {{-- Sidebar nav --}}
        <aside class="lg:sticky lg:top-20 lg:self-start lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto pr-2">
            <nav class="space-y-0.5 text-sm" id="docs-nav">
                @foreach($sections as $s)
                    <a href="#{{ $s['id'] }}" data-target="{{ $s['id'] }}"
                       class="sidebar-link flex items-center gap-2.5 px-3 py-2 rounded-r-md border-l-2 border-transparent text-gray-400 hover:text-white hover:bg-white/5">
                        <i class="fas {{ $s['icon'] }} w-4 text-[11px] text-gray-500"></i>
                        <span>{{ $s['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- Main --}}
        <main class="space-y-12 min-w-0">

            {{-- ── Intro ──────────────────────────────────────────── --}}
            <section id="intro" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-4">Introduction</h2>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 space-y-4 text-gray-300 text-sm leading-relaxed">
                    <p>The Sayzio REST API gives you programmatic access to everything your account can do on the web: managing links and biolinks, browsing the creators feed, following creators, and managing subscribers.</p>
                    <ul class="list-disc list-inside space-y-1 text-gray-400">
                        <li>All endpoints live under <code class="text-blue-300">{{ $base }}</code>.</li>
                        <li>All requests and responses use <code class="text-blue-300">application/json</code>.</li>
                        <li>Authentication is via a bearer token in the <code class="text-blue-300">Authorization</code> header.</li>
                        <li>Successful responses are wrapped in <code class="text-blue-300">{ "data": … }</code>; errors in <code class="text-blue-300">{ "error": { … } }</code>.</li>
                    </ul>
                </div>
            </section>

            {{-- ── Authentication overview ────────────────────────── --}}
            <section id="auth-overview" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-4">Authentication</h2>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 space-y-4 text-gray-300 text-sm leading-relaxed">
                    <p>Sign in or register to receive a personal access token, then send it on every protected request:</p>
                    <x-doc-code lang="http">Authorization: Bearer YOUR_TOKEN
Accept: application/json</x-doc-code>
                    <p>Tokens never expire on their own — log out (<code class="text-blue-300">POST /auth/logout</code>) to revoke the current one. Some public endpoints (biolinks, feed) accept an optional bearer token to reveal additional content based on follow/subscribe relationships.</p>
                </div>
            </section>

            {{-- ── Errors ─────────────────────────────────────────── --}}
            <section id="errors" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-4">Errors & error codes</h2>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 space-y-4">
                    <p class="text-gray-300 text-sm">All errors share the same envelope:</p>
                    <x-doc-code lang="json">{
  "error": {
    "message": "Human-readable explanation",
    "code": "machine_readable_code",
    "details": { ... }
  }
}</x-doc-code>
                    <div class="overflow-x-auto -mx-2">
                        <table class="w-full text-sm text-left text-gray-300 mt-2">
                            <thead class="text-xs uppercase text-gray-500 border-b border-white/10">
                                <tr><th class="py-2 px-2">Status</th><th class="py-2 px-2">Code</th><th class="py-2 px-2">When</th></tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach([
                                    ['401','unauthenticated','Missing or invalid bearer token on a protected route.'],
                                    ['401','auth_required','Biolink visibility requires sign-in.'],
                                    ['401','invalid_credentials','Login failed.'],
                                    ['403','follow_required','Biolink visibility = followers, viewer is not following.'],
                                    ['403','subscribe_required','Biolink visibility = subscribers, viewer is not subscribed.'],
                                    ['403','forbidden','General authorization failure.'],
                                    ['404','not_found','Unknown route or resource.'],
                                    ['405','method_not_allowed','Wrong HTTP method.'],
                                    ['422','validation_failed','Body validation failed; details = {field: [messages]}.'],
                                    ['429','rate_limited','Too many requests.'],
                                ] as $row)
                                    <tr>
                                        <td class="py-2 px-2 font-mono text-blue-300">{{ $row[0] }}</td>
                                        <td class="py-2 px-2 font-mono text-amber-300">{{ $row[1] }}</td>
                                        <td class="py-2 px-2 text-gray-400">{{ $row[2] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- ── Pagination ─────────────────────────────────────── --}}
            <section id="pagination" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-4">Pagination</h2>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 space-y-4 text-gray-300 text-sm leading-relaxed">
                    <p>List endpoints accept <code class="text-blue-300">page</code> and <code class="text-blue-300">per_page</code> (max 100) query parameters and return:</p>
                    <x-doc-code lang="json">{
  "data": {
    "items": [ /* … */ ],
    "meta":  { "current_page": 1, "per_page": 20, "total": 53, "last_page": 3 }
  }
}</x-doc-code>
                </div>
            </section>

            {{-- ── Rate limits ────────────────────────────────────── --}}
            <section id="rate-limits" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-4">Rate limits</h2>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 text-sm text-gray-300 space-y-2">
                    <p>The following endpoints are rate-limited per IP / token. Excess requests return <code class="text-amber-300">429 rate_limited</code>.</p>
                    <ul class="list-disc list-inside text-gray-400">
                        <li><code class="text-blue-300">POST /auth/register</code> — 10 / minute</li>
                        <li><code class="text-blue-300">POST /auth/login</code> — 10 / minute</li>
                        <li><code class="text-blue-300">POST /biolinks/{alias}/subscribe</code> — 10 / minute</li>
                    </ul>
                </div>
            </section>

            {{-- ── Visibility ─────────────────────────────────────── --}}
            <section id="visibility" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-4">Visibility tiers</h2>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 space-y-3 text-sm">
                    <p class="text-gray-300">Biolinks and feed events carry one of four visibility levels:</p>
                    <div class="grid sm:grid-cols-2 gap-3 mt-2">
                        @foreach([
                            ['public',      'sky',      'Open to everyone.'],
                            ['registered',  'violet',   'Any signed-in user can view.'],
                            ['followers',   'fuchsia',  'Only viewers following the creator.'],
                            ['subscribers', 'amber',    'Only viewers actively subscribed to the creator (by email).'],
                        ] as [$tier,$color,$desc])
                            <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4">
                                <span class="inline-block text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-{{ $color }}-500/10 text-{{ $color }}-300 border border-{{ $color }}-400/20">{{ $tier }}</span>
                                <p class="mt-2 text-gray-400">{{ $desc }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-gray-400 mt-3">For non-public tiers the API responds with <code class="text-amber-300">401 auth_required</code>, <code class="text-amber-300">403 follow_required</code>, or <code class="text-amber-300">403 subscribe_required</code> as appropriate. Owners always bypass.</p>
                </div>
            </section>

            {{-- ── Auth endpoints ─────────────────────────────────── --}}
            <section id="auth" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-user-shield text-blue-400 text-base mr-2"></i>Authentication</h2>
                <div class="space-y-5">

                    <x-endpoint method="POST" path="/auth/register" auth="false" id="register"
                        summary="Create an account and receive an access token.">
                        <x-slot:params>
                            <x-param name="name" type="string" req="true">Full display name. Max 120 chars.</x-param>
                            <x-param name="email" type="string" req="true">Unique email address.</x-param>
                            <x-param name="password" type="string" req="true">Min 8 characters.</x-param>
                            <x-param name="handle" type="string">Optional unique username (a–z, 0–9, _).</x-param>
                        </x-slot:params>
                        <x-slot:request>curl -X POST {{ $base }}/auth/register \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "name": "Jane Creator",
    "email": "jane@example.com",
    "password": "supersecret"
  }'</x-slot:request>
                        <x-slot:response lang="json">{
  "data": {
    "user":  { "id": 42, "name": "Jane Creator", "email": "jane@example.com", "handle": null, ... },
    "token": "1|abcdef0123456789..."
  }
}</x-slot:response>
                    </x-endpoint>

                    <x-endpoint method="POST" path="/auth/login" auth="false" id="login"
                        summary="Exchange credentials for a bearer token.">
                        <x-slot:params>
                            <x-param name="email" type="string" req="true" />
                            <x-param name="password" type="string" req="true" />
                            <x-param name="device" type="string">Optional label for the token (e.g. "iphone-pro").</x-param>
                        </x-slot:params>
                        <x-slot:request>curl -X POST {{ $base }}/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"jane@example.com","password":"supersecret"}'</x-slot:request>
                        <x-slot:response lang="json">{
  "data": {
    "user":  { "id": 42, "name": "Jane Creator", ... },
    "token": "1|..."
  }
}</x-slot:response>
                    </x-endpoint>

                    <x-endpoint method="GET" path="/auth/me" id="me"
                        summary="Return the currently authenticated user." />

                    <x-endpoint method="POST" path="/auth/logout" id="logout"
                        summary="Revoke the bearer token used for this request."
                        responseStatus="204 No Content" />
                </div>
            </section>

            {{-- ── Profile ────────────────────────────────────────── --}}
            <section id="profile" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-id-card text-blue-400 text-base mr-2"></i>Profile</h2>
                <div class="space-y-5">
                    <x-endpoint method="GET" path="/profile" id="profile-show"
                        summary="Same payload as GET /auth/me." />
                    <x-endpoint method="PATCH" path="/profile" id="profile-update"
                        summary="Update profile fields. Send only what you want to change.">
                        <x-slot:params>
                            <x-param name="name" type="string" />
                            <x-param name="bio" type="string">Up to 500 chars.</x-param>
                            <x-param name="handle" type="string">Unique. a–z, 0–9, _.</x-param>
                            <x-param name="avatar" type="string">Public URL.</x-param>
                            <x-param name="phone" type="string" />
                            <x-param name="timezone" type="string">e.g. <code>America/New_York</code>.</x-param>
                            <x-param name="language" type="string">2-letter code.</x-param>
                            <x-param name="discoverable" type="boolean" />
                            <x-param name="allow_followers" type="boolean" />
                        </x-slot:params>
                        <x-slot:request>curl -X PATCH {{ $base }}/profile \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"bio":"Building cool stuff","handle":"jane"}'</x-slot:request>
                    </x-endpoint>
                </div>
            </section>

            {{-- ── Links ──────────────────────────────────────────── --}}
            <section id="links" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-link text-blue-400 text-base mr-2"></i>Links</h2>
                <div class="space-y-5">

                    <x-endpoint method="GET" path="/links" id="links-list" summary="Paginated list of YOUR links.">
                        <x-slot:params>
                            <x-param name="type" type="string">Filter by link type (short/biolink/file/qr/event/...).</x-param>
                            <x-param name="q" type="string">Search title, alias, or long URL.</x-param>
                            <x-param name="page" type="integer" />
                            <x-param name="per_page" type="integer">Default 20, max 100.</x-param>
                        </x-slot:params>
                    </x-endpoint>

                    <x-endpoint method="POST" path="/links" id="links-create" summary="Create a new link or biolink.">
                        <x-slot:params>
                            <x-param name="type" type="string" req="true">One of: <code>short, biolink, file, qr, event, vcard, social, sms, wifi, pdf</code>.</x-param>
                            <x-param name="alias" type="string">Custom slug. Auto-generated if omitted.</x-param>
                            <x-param name="title" type="string" />
                            <x-param name="long_url" type="string">Required for short links.</x-param>
                            <x-param name="visibility" type="string">One of <code>public, registered, followers, subscribers</code>. Default <code>public</code>.</x-param>
                            <x-param name="is_active" type="boolean">Default true.</x-param>
                            <x-param name="seo_title" type="string" />
                            <x-param name="seo_description" type="string" />
                            <x-param name="expires_at" type="datetime" />
                        </x-slot:params>
                        <x-slot:request>curl -X POST {{ $base }}/links \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"type":"biolink","title":"My links","visibility":"followers"}'</x-slot:request>
                    </x-endpoint>

                    <x-endpoint method="GET" path="/links/{id}" id="links-show" summary="Show a single link you own." />
                    <x-endpoint method="PATCH" path="/links/{id}" id="links-update" summary="Partial update (any of the fields above)." />
                    <x-endpoint method="DELETE" path="/links/{id}" id="links-delete" summary="Permanently delete a link." responseStatus="204 No Content" />
                </div>
            </section>

            {{-- ── Biolinks ───────────────────────────────────────── --}}
            <section id="biolinks" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-square-share-nodes text-blue-400 text-base mr-2"></i>Biolinks <span class="text-xs text-gray-500 font-normal ml-1">(public, visibility-aware)</span></h2>
                <div class="space-y-5">
                    <x-endpoint method="GET" path="/biolinks/{alias}" auth="optional" id="biolinks-show"
                        summary="Public biolink. Visibility tiers are enforced — supply a token to view gated content as a follower/subscriber.">
                        <x-slot:request>curl {{ $base }}/biolinks/jane \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'</x-slot:request>
                        <x-slot:response lang="json">{
  "data": {
    "biolink": { "id": 47, "alias": "jane", "title": "...", "visibility": "followers", ... },
    "owner":   { "id": 42, "name": "Jane Creator", "handle": "jane", "avatar": null, ... },
    "blocks":  [ { "id": 1, "type": "link", "settings": { ... } }, ... ]
  }
}</x-slot:response>
                    </x-endpoint>

                    <x-endpoint method="POST" path="/biolinks/{alias}/subscribe" auth="false" id="biolinks-subscribe"
                        summary="Public email subscribe to a creator. Rate-limited 10/minute.">
                        <x-slot:params>
                            <x-param name="email" type="string" req="true" />
                            <x-param name="name" type="string" />
                        </x-slot:params>
                    </x-endpoint>
                </div>
            </section>

            {{-- ── Feed ───────────────────────────────────────────── --}}
            <section id="feed" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-rss text-blue-400 text-base mr-2"></i>Feed</h2>
                <div class="space-y-5">
                    <x-endpoint method="GET" path="/feed" auth="optional" id="feed-global"
                        summary="Global creators feed. Anonymous viewers see only public events; signed-in viewers also see registered events plus followers/subscribers events from creators they follow or subscribe to.">
                        <x-slot:params>
                            <x-param name="page" type="integer" />
                            <x-param name="per_page" type="integer">Default 20, max 100.</x-param>
                        </x-slot:params>
                    </x-endpoint>

                    <x-endpoint method="GET" path="/creators/{handle}/feed" auth="optional" id="feed-creator"
                        summary="Same as /feed but scoped to a single creator." />
                </div>
            </section>

            {{-- ── Follows ────────────────────────────────────────── --}}
            <section id="follows" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-user-plus text-blue-400 text-base mr-2"></i>Follows</h2>
                <div class="space-y-5">
                    <x-endpoint method="POST"   path="/follows/{userId}" id="follow"
                        summary="Follow a creator. Returns 422 self_follow if you target yourself."  responseStatus="201 Created" />
                    <x-endpoint method="DELETE" path="/follows/{userId}" id="unfollow"
                        summary="Unfollow a creator." />
                    <x-endpoint method="GET"    path="/follows/following" id="following"
                        summary="Paginated creators you follow." />
                    <x-endpoint method="GET"    path="/follows/followers" id="followers"
                        summary="Paginated users following you." />
                </div>
            </section>

            {{-- ── Subscribers ────────────────────────────────────── --}}
            <section id="subscribers" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-envelope-open-text text-blue-400 text-base mr-2"></i>Subscribers <span class="text-xs text-gray-500 font-normal ml-1">(creator-side)</span></h2>
                <div class="space-y-5">
                    <x-endpoint method="GET" path="/subscribers" id="subs-list"
                        summary="List of YOUR subscribers.">
                        <x-slot:params>
                            <x-param name="status" type="string">Filter (e.g. <code>active</code>, <code>unsubscribed</code>).</x-param>
                            <x-param name="q" type="string">Search email or name.</x-param>
                            <x-param name="page" type="integer" />
                            <x-param name="per_page" type="integer" />
                        </x-slot:params>
                    </x-endpoint>
                    <x-endpoint method="DELETE" path="/subscribers/{id}" id="subs-remove"
                        summary="Soft-unsubscribe a subscriber (marks status=unsubscribed)." />
                </div>
            </section>

            {{-- ── Discovery ──────────────────────────────────────── --}}
            <section id="discovery" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-compass text-blue-400 text-base mr-2"></i>Discovery <span class="text-xs text-gray-500 font-normal ml-1">(public)</span></h2>
                <div class="space-y-5">
                    <x-endpoint method="GET" path="/discovery/creators" auth="false" id="disc-list"
                        summary="Paginated discoverable creators sorted by followers count.">
                        <x-slot:params>
                            <x-param name="q" type="string">Search name, handle, or bio.</x-param>
                            <x-param name="per_page" type="integer">Default 20, max 50.</x-param>
                        </x-slot:params>
                    </x-endpoint>
                    <x-endpoint method="GET" path="/discovery/creators/{handle}" auth="false" id="disc-show"
                        summary="Public profile by handle." />
                </div>
            </section>

            {{-- ── Health ─────────────────────────────────────────── --}}
            <section id="health" class="scroll-mt-20">
                <h2 class="text-2xl font-bold mb-5"><i class="fas fa-heart-pulse text-blue-400 text-base mr-2"></i>Health</h2>
                <x-endpoint method="GET" path="/health" auth="false" id="health-check"
                    summary="Liveness probe. Always returns 200 when the API is up.">
                    <x-slot:response lang="json">{ "data": { "status": "ok", "time": "2026-04-21T06:33:08+00:00" } }</x-slot:response>
                </x-endpoint>
            </section>

            <div class="mt-16 text-center text-xs text-gray-500">
                Found a problem with this page? <a class="text-blue-400 hover:underline" href="{{ route('site.contact') }}">Let us know</a>.
            </div>

        </main>
    </div>
</div>

<script>
(function () {
    // Copy-to-clipboard for any [data-copy] or [data-copy-text-of]
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-copy], [data-copy-target]');
        if (!btn) return;
        let text = btn.getAttribute('data-copy');
        if (!text && btn.dataset.copyTarget) {
            const el = document.querySelector(btn.dataset.copyTarget);
            if (el) text = el.innerText;
        }
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            btn.classList.add('copied');
            const lbl = btn.querySelector('span');
            const prev = lbl ? lbl.textContent : null;
            if (lbl) lbl.textContent = 'Copied!';
            setTimeout(() => {
                btn.classList.remove('copied');
                if (lbl && prev) lbl.textContent = prev;
            }, 1400);
        } catch (err) { console.warn('Clipboard failed', err); }
    });

    // Sidebar active-section highlighting via IntersectionObserver
    const links = document.querySelectorAll('#docs-nav .sidebar-link');
    const sections = Array.from(links).map(a => document.getElementById(a.dataset.target)).filter(Boolean);
    const setActive = (id) => links.forEach(a => a.classList.toggle('active', a.dataset.target === id));
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) setActive(entry.target.id); });
        }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });
        sections.forEach(s => io.observe(s));
    }
})();
</script>
@endsection
