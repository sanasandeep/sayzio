@extends('user.layouts.settings')
@section('title', 'Connected Accounts')

@section('settings-content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__canEdit = $__can('settings.edit');
    $allConnections    = collect($connections)->flatten();
    $totalConnections  = $allConnections->count();
    $failingConnections = $allConnections->filter(fn ($c) => $c->isFailing());
    $staleConnections   = $allConnections->filter(fn ($c) => ! $c->isFailing() && $c->isStuck(24));
    $needsAttention     = $failingConnections->count() + $staleConnections->count();
    // Treat NULL as opted-in so accounts predating the toggle keep getting alerts.
    $brokenEmailsOn     = auth()->user()->social_connection_broken_emails !== false;
    $hero_chips = [
        ['icon' => 'fa-link', 'text' => $totalConnections . ' connected'],
    ];
    if ($needsAttention > 0) {
        $hero_chips[] = ['icon' => 'fa-triangle-exclamation', 'text' => $needsAttention . ' needs attention'];
    }
@endphp

<div x-data="{ tab: '{{ array_key_first($platforms) }}' }">
    @include('user.partials.page-hero', [
        'title'    => 'Connected Accounts',
        'subtitle' => 'Link your social profiles so Link in Bio Follow buttons can show live follower counts. Counts refresh every few hours in the background.',
        'icon'     => 'fa-share-nodes',
        'chips'    => $hero_chips,
    ])

    {{-- Inline "merge accounts?" offer — raised when a Connect flow found
         the provider identity already bound to a different Sayzio account.
         The OAuth round-trip already proved ownership, so "Merge" jumps
         straight to the merge preview. --}}
    @if(session('social_merge_offer'))
        @php $__mergeOffer = session('social_merge_offer'); @endphp
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-start gap-3"
             style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.35); color: var(--text-primary);">
            <i class="fas fa-code-merge mt-0.5" style="color:#3d6bff;"></i>
            <div class="flex-1 min-w-0">
                <div class="font-semibold" style="color:#3d6bff;">
                    That {{ \App\Modules\User\Models\SocialAccountConnection::platformLabel($__mergeOffer['provider'] ?? '') }} account already belongs to another Sayzio account
                </div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    It's linked to <span class="font-semibold">{{ $__mergeOffer['label'] ?? 'another account' }}</span>.
                    Do you want to merge that account into this one? Everything from it will move here — this can't be undone.
                </div>
                @if($__canEdit)
                    <div class="flex items-center gap-2 mt-3">
                        <form method="POST" action="{{ route('user.social-oauth.merge-offer.accept') }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                    style="background: rgba(61,107,255,0.15); color:#3d6bff; border: 1px solid rgba(61,107,255,0.4);">
                                <i class="fas fa-code-merge"></i> Merge accounts
                            </button>
                        </form>
                        <form method="POST" action="{{ route('user.social-oauth.merge-offer.decline') }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                    style="background: var(--bg-glass-input); color: var(--text-muted); border: 1px solid var(--border-glass);">
                                Not now
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Top-level health banner: only renders when at least one connection is broken or stale. --}}
    @if($failingConnections->count() > 0)
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-start gap-3"
             style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); color: var(--text-primary);">
            <i class="fas fa-triangle-exclamation mt-0.5" style="color:#ef4444;"></i>
            <div class="flex-1">
                <div class="font-semibold" style="color:#ef4444;">
                    {{ $failingConnections->count() }} connection{{ $failingConnections->count() === 1 ? '' : 's' }} stopped refreshing
                </div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Follow buttons will hide the count until you reconnect or update the access token.
                </div>
            </div>
        </div>
    @elseif($staleConnections->count() > 0)
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-start gap-3"
             style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); color: var(--text-primary);">
            <i class="fas fa-clock-rotate-left mt-0.5" style="color:#f59e0b;"></i>
            <div class="flex-1">
                <div class="font-semibold" style="color:#f59e0b;">
                    {{ $staleConnections->count() }} connection{{ $staleConnections->count() === 1 ? '' : 's' }} haven't refreshed recently
                </div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Use "Refresh now" below to retry, or check that the access token is still valid.
                </div>
            </div>
        </div>
    @endif

    {{-- Per-user opt-out for broken-connection emails. Sits next to the
         health banners so it's right where creators look when the nudges
         start feeling spammy. The in-app alerts above are unaffected. --}}
    <div class="card-premium p-4 mb-4 flex items-start gap-3">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
             style="background: rgba(37,99,235,0.1); color:#2563eb;">
            <i class="fas fa-envelope-circle-check"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                Email me when a connection breaks
            </div>
            <div class="text-[11px] mt-0.5" style="color: var(--text-muted);">
                One email per failure streak (max once per week per connection). The warning badges and in-app alerts above stay either way.
            </div>
        </div>
        <form method="POST" action="{{ route('user.social-accounts.broken-emails.preference') }}" class="flex-shrink-0">
            @csrf
            <input type="hidden" name="enabled" value="{{ $brokenEmailsOn ? '0' : '1' }}">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold"
                    style="background: {{ $brokenEmailsOn ? 'rgba(16,185,129,0.12)' : 'var(--bg-glass-input)' }};
                           color: {{ $brokenEmailsOn ? '#10b981' : 'var(--text-muted)' }};
                           border: 1px solid {{ $brokenEmailsOn ? 'rgba(16,185,129,0.3)' : 'var(--border-glass)' }};">
                <i class="fas {{ $brokenEmailsOn ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                {{ $brokenEmailsOn ? 'On — turn off' : 'Off — turn on' }}
            </button>
        </form>
    </div>

    {{-- Browser extension install card. The KB ("Settings → Connected
         Accounts & Apps") points users here for the extension, so this card
         must exist or the docs dead-end. "Signed in" is detected server-side
         via the Sanctum token the handshake page mints under the
         'browser-extension' name (revocable from Devices & sessions). --}}
    @php
        $__extSignedIn = false;
        try {
            $__extSignedIn = $__user && method_exists($__user, 'tokens')
                ? $__user->tokens()->where('name', 'browser-extension')->exists()
                : false;
        } catch (\Throwable $e) {
            $__extSignedIn = false;
        }
        // Store URLs resolve through ExtensionStoreLinks: admin-configured
        // direct listing URLs (Admin → Marketing settings) with a pre-publish
        // fallback to store search pages. Mobile reads the same source via
        // GET /api/v1/extension/stores.
        $__extIcons = [
            'chrome'  => 'fab fa-chrome',
            'edge'    => 'fab fa-edge',
            'firefox' => 'fab fa-firefox-browser',
        ];
        $__extStores = array_map(
            fn ($s) => $s + ['icon' => $__extIcons[$s['key']] ?? 'fas fa-puzzle-piece'],
            \App\Modules\Common\Support\ExtensionStoreLinks::stores()
        );
        $__extAnyListing = collect($__extStores)->contains(fn ($s) => $s['is_listing']);
    @endphp
    <div class="card-premium p-4 mb-4" id="browser-extension">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background: rgba(61,107,255,0.1); color:#3d6bff;">
                <i class="fas fa-puzzle-piece"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="text-sm font-semibold" style="color: var(--text-primary);">
                        Browser Extension
                    </div>
                    @if($__extSignedIn)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                              style="background: rgba(16,185,129,0.12); color:#10b981;">
                            <i class="fas fa-circle-check"></i> Signed in
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                              style="background: var(--bg-glass-input); color: var(--text-muted);">
                            <i class="fas fa-circle-info"></i> Not installed
                        </span>
                    @endif
                </div>
                <div class="text-[11px] mt-0.5" style="color: var(--text-muted);">
                    Shorten links, capture reviews, save events, and get notification badges right from your browser.
                    @if($__extAnyListing)
                        Install it from your browser's store below, then click the
                        extension icon and choose <span class="font-semibold">Sign in with Sayzio</span>.
                    @else
                        Search for <span class="font-semibold">"Sayzio"</span> in your browser's store, then click the
                        extension icon and choose <span class="font-semibold">Sign in with Sayzio</span>.
                    @endif
                    @if($__extSignedIn)
                        You can revoke the extension's access any time from
                        <a href="{{ route('user.settings.sessions.index') }}" class="underline" style="color:#3d6bff;">Devices &amp; sessions</a>.
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach($__extStores as $store)
                        <a href="{{ $store['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                           style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">
                            <i class="{{ $store['icon'] }}" style="color:#3d6bff;"></i>
                            {{ $store['label'] }}
                            <i class="fas fa-arrow-up-right-from-square text-[9px]" style="color: var(--text-faint);"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #10b981;">
            <i class="fas fa-check-circle"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
            <i class="fas fa-exclamation-circle"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Existing connections grid --}}
    @if($totalConnections > 0)
        @php
            $oauthSvc = app(\App\Modules\User\Services\SocialFollowers\SocialOAuthService::class);
        @endphp
        <div class="card-premium p-5 mb-5">
            <h2 class="text-base font-bold mb-4" style="color: var(--text-primary);">Your connections</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($connections as $platform => $rows)
                    @foreach($rows as $c)
                        @php
                            $meta       = $platforms[$platform] ?? ["label"=>ucfirst($platform), "icon"=>"fas fa-link", "color"=>"#3d6bff"];
                            $health     = $c->healthState();
                            $isOauth    = $c->isOauthPlatform();
                            $oauthReady = $isOauth && $oauthSvc->isConfigured($platform);
                            $cardBorder = $health === "error" ? "rgba(239,68,68,0.45)"
                                        : ($health === "stale" ? "rgba(245,158,11,0.4)" : "var(--border-glass)");
                        @endphp
                        <div class="p-3 rounded-lg"
                             style="background: var(--bg-glass); border: 1px solid {{ $cardBorder }};">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background: {{ $meta["color"] }}20;">
                                    <i class="{{ $meta["icon"] }} text-base" style="color: {{ $meta["color"] }};"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                                            {{ $meta["label"] }} · @{{ $c->handle }}
                                        </div>
                                        {{-- Health pill: at-a-glance status of the most recent fetch. --}}
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                              style="background: {{ $c->healthColor() }}1a; color: {{ $c->healthColor() }};">
                                            <i class="fas {{ $health === "ok" ? "fa-circle-check" : ($health === "error" ? "fa-circle-exclamation" : ($health === "stale" ? "fa-clock" : "fa-circle-info")) }}"></i>
                                            {{ $c->healthLabel() }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] mt-1" style="color: var(--text-muted);">
                                        @if($c->follower_count !== null)
                                            <span style="color: #10b981; font-weight:600;">{{ \App\Modules\User\Models\SocialAccountConnection::formatCount($c->follower_count) }}</span> followers
                                        @endif
                                        @if($c->last_refreshed_at)
                                            @if($c->follower_count !== null) · @endif
                                            Last refreshed
                                            <span title="{{ $c->last_refreshed_at->toDayDateTimeString() }}">{{ $c->last_refreshed_at->diffForHumans() }}</span>
                                        @else
                                            Awaiting first refresh
                                        @endif
                                    </div>
                                    @if($health === "error" && $c->last_refresh_error)
                                        <div class="mt-2 text-[11px] px-2 py-1.5 rounded"
                                             style="background: rgba(239,68,68,0.08); color:#ef4444; border:1px solid rgba(239,68,68,0.2);">
                                            <i class="fas fa-circle-exclamation mr-1"></i>
                                            <span class="font-semibold">Last error</span>
                                            @if($c->consecutive_failures > 1)
                                                <span class="opacity-75">({{ $c->consecutive_failures }} attempts)</span>
                                            @endif
                                            : {{ $c->last_refresh_error }}
                                        </div>
                                    @elseif($health === "unsupported")
                                        <div class="mt-1 text-[11px]" style="color: var(--text-faint);">
                                            Auto-refresh isn"t wired up for this platform yet — paste an access token to enable.
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0">
                                    {{-- Reconnect: shown for failing connections. Sends OAuth users
                                         straight back through the provider"s authorize flow; for handle/manual
                                         platforms, deep-links to the matching tab in the form below. --}}
                                    @if($health === "error" && $__canEdit)
                                        @if($oauthReady)
                                            <a href="{{ route("user.social-oauth.connect", ["provider" => $platform]) }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                               style="background: {{ $meta["color"] }}; color: #fff;"
                                               title="Re-authorize {{ $meta["label"] }}">
                                                <i class="fas fa-rotate-right"></i> Reconnect
                                            </a>
                                        @else
                                            @php
                                                // Handle/API-key platforms can"t OAuth-reconnect — send the user
                                                // back to the matching tab so they can update the handle or token.
                                                $isHandleOnly = ! $isOauth;
                                                $fixLabel = $isHandleOnly ? "Update handle" : "Re-enter token";
                                                $fixIcon  = $isHandleOnly ? "fa-pen-to-square" : "fa-key";
                                            @endphp
                                            <a href="#new-connection"
                                               @click.prevent="tab="{{ $platform }}"; document.getElementById("new-connection")?.scrollIntoView({behavior:"smooth"});"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                               style="background: {{ $meta["color"] }}; color: #fff;"
                                               title="Reconnect {{ $meta["label"] }}">
                                                <i class="fas {{ $fixIcon }}"></i> {{ $fixLabel }}
                                            </a>
                                        @endif
                                    @endif

                                    @if($__canEdit)
                                    <form method="POST" action="{{ route("user.social-accounts.refresh", $c) }}">
                                        @csrf
                                        <button type="submit" title="Refresh now"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center text-xs"
                                                style="background: var(--bg-glass-input); color: var(--text-muted);">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route("user.social-accounts.destroy", $c) }}"
                                          onsubmit="return window.themedConfirmSubmit(this, {title: 'Disconnect this account?', message: 'Follow buttons that reference it will fall back to icon style.', confirmText: 'Disconnect', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                                        @csrf @method("DELETE")
                                        <button type="submit" title="Disconnect"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center text-xs"
                                                style="background: rgba(239,68,68,0.1); color: #ef4444;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    @else
                                    <span title="Your role doesn't allow refreshing connections — ask a workspace admin"
                                          class="w-8 h-8 rounded-lg flex items-center justify-center text-xs cursor-not-allowed opacity-60"
                                          style="background: var(--bg-glass-input); color: var(--text-faint);">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <span title="Your role doesn't allow disconnecting accounts — ask a workspace admin"
                                          class="w-8 h-8 rounded-lg flex items-center justify-center text-xs cursor-not-allowed opacity-60"
                                          style="background: rgba(239,68,68,0.05); color: var(--text-faint);">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Task #3588: public searchability toggle + "where it's synced" summary. --}}
                            @php $__sync = $c->syncSummary(); @endphp
                            <div class="mt-3 pt-3 flex items-start gap-3" style="border-top: 1px solid var(--border-glass);">
                                <div class="flex-1 min-w-0">
                                    <div class="text-[11px] font-semibold" style="color: var(--text-primary);">
                                        <i class="fas fa-magnifying-glass mr-1" style="color: var(--text-muted);"></i>Searchable in public
                                    </div>
                                    <div class="text-[10px] mt-0.5" style="color: var(--text-muted);">
                                        {{ $__sync['label'] }}
                                    </div>
                                </div>
                                @if($__canEdit)
                                    <form method="POST" action="{{ route('user.social-accounts.searchable', $c) }}" class="flex-shrink-0">
                                        @csrf
                                        <input type="hidden" name="searchable" value="{{ $c->is_searchable ? '0' : '1' }}">
                                        <button type="submit" title="{{ $c->is_searchable ? 'Turn off' : 'Turn on' }} public searchability"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold"
                                                style="background: {{ $c->is_searchable ? 'rgba(16,185,129,0.12)' : 'var(--bg-glass-input)' }};
                                                       color: {{ $c->is_searchable ? '#10b981' : 'var(--text-muted)' }};
                                                       border: 1px solid {{ $c->is_searchable ? 'rgba(16,185,129,0.3)' : 'var(--border-glass)' }};">
                                            <i class="fas {{ $c->is_searchable ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                            {{ $c->is_searchable ? 'On' : 'Off' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="flex-shrink-0 text-[11px] font-semibold" style="color: {{ $c->is_searchable ? '#10b981' : 'var(--text-muted)' }};">
                                        <i class="fas {{ $c->is_searchable ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                        {{ $c->is_searchable ? 'On' : 'Off' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    {{-- Add new connection --}}
    @if(!$__canEdit)
    <div class="card-premium p-5" id="new-connection">
        <div class="flex items-start gap-3" style="color:#b45309;">
            <i class="fas fa-lock mt-0.5"></i>
            <div class="text-xs">
                <div class="font-semibold mb-0.5" style="color:#b45309;">View-only access</div>
                <span style="color: var(--text-muted);">Your role doesn't allow connecting new social accounts. Ask a workspace admin to connect them for you.</span>
            </div>
        </div>
    </div>
    @else
    <div class="card-premium p-5" id="new-connection">
        <h2 class="text-base font-bold mb-1" style="color: var(--text-primary);">Connect a new account</h2>
        <p class="text-xs mb-4" style="color: var(--text-muted);">Pick a platform and enter your handle. For OAuth platforms, use one-click connect when available, or paste a long-lived access token if your admin hasn't enabled it yet.</p>

        <div class="flex flex-wrap gap-2 mb-4">
            @foreach($platforms as $key => $meta)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'tab-active' : 'tab-inactive'"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition">
                    <i class="{{ $meta['icon'] }}" style="color: {{ $meta['color'] }};"></i>
                    {{ $meta['label'] }}
                </button>
            @endforeach
        </div>

        @php
            $oauthSvc = app(\App\Modules\User\Services\SocialFollowers\SocialOAuthService::class);
        @endphp
        @foreach($platforms as $key => $meta)
            @php $oauthReady = $oauthSvc->isConfigured($key); @endphp
            <div x-show="tab === '{{ $key }}'" x-cloak>
                {{-- One-click OAuth connect, when this provider has its
                     CLIENT_ID + CLIENT_SECRET configured server-side. --}}
                @if($oauthReady && (($meta['kind'] ?? 'handle') === 'oauth'))
                    <div class="mb-4 px-3 py-2.5 rounded-lg text-[11px] flex items-start gap-2"
                         style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.25); color: var(--text-primary);">
                        <i class="fas fa-circle-check mt-0.5" style="color:#10b981;"></i>
                        <div>
                            <span class="font-semibold" style="color:#10b981;">One-click connect available.</span>
                            Authorize with {{ $meta['label'] }} below — no token to copy or paste.
                        </div>
                    </div>
                    <a href="{{ route('user.social-oauth.connect', ['provider' => $key]) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold mb-4"
                       style="background: {{ $meta['color'] }}; color: #fff;">
                        <i class="{{ $meta['icon'] }}"></i>
                        Connect with {{ $meta['label'] }}
                    </a>
                    <p class="text-[11px] mb-4" style="color: var(--text-faint);">
                        You'll be redirected to {{ $meta['label'] }} to authorize access. We only request the
                        scopes needed to read your public follower count.
                    </p>
                @elseif(($meta['kind'] ?? 'handle') === 'oauth')
                    {{-- OAuth not configured on this server — surface a clear notice
                         instead of silently dropping the user into the manual token form.
                         Intentionally avoids exposing env var names to end users. --}}
                    <div class="mb-4 px-3 py-2.5 rounded-lg text-[11px] flex items-start gap-2"
                         style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); color: var(--text-primary);">
                        <i class="fas fa-circle-info mt-0.5" style="color:#f59e0b;"></i>
                        <div>
                            <span class="font-semibold" style="color:#f59e0b;">One-click connect isn't enabled for {{ $meta['label'] }} on this server yet.</span>
                            You can paste a long-lived access token below to connect manually,
                            or ask a server admin to enable {{ $meta['label'] }} sign-in.
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('user.social-accounts.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="platform" value="{{ $key }}">

                    <div>
                        <label class="block text-xs mb-1" style="color: var(--text-muted);">Handle / username</label>
                        <input type="text" name="handle" required placeholder="yourhandle"
                               class="theme-input w-full">
                        <p class="text-[11px] mt-1" style="color: var(--text-faint);">
                            Your public {{ $meta['label'] }} handle (without the @).
                        </p>
                    </div>

                    @if(($meta['kind'] ?? 'handle') === 'oauth')
                        <div>
                            <label class="block text-xs mb-1" style="color: var(--text-muted);">
                                Access token
                                @if($oauthReady)
                                    <span class="text-white/30">(advanced — prefer "Connect with {{ $meta['label'] }}" above)</span>
                                @endif
                            </label>
                            <input type="password" name="access_token" autocomplete="off" spellcheck="false" placeholder="paste long-lived token"
                                   class="theme-input w-full">
                            <p class="text-[11px] mt-1" style="color: var(--text-faint);">
                                @if($oauthReady)
                                    Manual fallback for cases where you already have a long-lived token from another tool.
                                @else
                                    Paste a long-lived {{ $meta['label'] }} access token and we'll use it for refreshes.
                                    Once a server admin enables {{ $meta['label'] }} sign-in, you'll be able to connect
                                    in one click without managing tokens yourself.
                                @endif
                            </p>
                        </div>
                    @else
                        <p class="text-[11px]" style="color: var(--text-faint);">
                            @if($key === 'github')
                                GitHub follower counts are public — no token needed.
                            @elseif($key === 'youtube')
                                YouTube uses the public Data API. Counts will appear once the server has YOUTUBE_API_KEY configured.
                            @elseif($key === 'twitch')
                                Twitch uses the public Helix API. Counts will appear once the server has TWITCH_CLIENT_ID + TWITCH_CLIENT_SECRET configured.
                            @endif
                        </p>
                    @endif

                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background: {{ $meta['color'] }}; color: #fff;">
                        <i class="{{ $meta['icon'] }}"></i> Connect {{ $meta['label'] }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>
    @endif
</div>

<style>
    [x-cloak] { display: none !important; }
    .tab-active   { background: var(--accent); color: #fff; }
    .tab-inactive { background: var(--bg-glass-input); color: var(--text-muted); }
</style>
@endsection
