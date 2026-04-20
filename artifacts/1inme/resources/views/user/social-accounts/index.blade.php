@extends('user.layouts.app')
@section('title', 'Connected Accounts')

@section('content')
@php
    $allConnections    = collect($connections)->flatten();
    $totalConnections  = $allConnections->count();
    $failingConnections = $allConnections->filter(fn ($c) => $c->isFailing());
    $staleConnections   = $allConnections->filter(fn ($c) => ! $c->isFailing() && $c->isStuck(24));
    $needsAttention     = $failingConnections->count() + $staleConnections->count();
    $hero_chips = [
        ['icon' => 'fa-link', 'text' => $totalConnections . ' connected'],
    ];
    if ($needsAttention > 0) {
        $hero_chips[] = ['icon' => 'fa-triangle-exclamation', 'text' => $needsAttention . ' needs attention'];
    }
@endphp

<div class="max-w-5xl mx-auto" x-data="{ tab: '{{ array_key_first($platforms) }}' }">
    @include('user.partials.page-hero', [
        'title'    => 'Connected Accounts',
        'subtitle' => 'Link your social profiles so biolink Follow buttons can show live follower counts. Counts refresh every few hours in the background.',
        'icon'     => 'fa-share-nodes',
        'chips'    => $hero_chips,
    ])

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
                            $meta       = $platforms[$platform] ?? ["label"=>ucfirst($platform), "icon"=>"fas fa-link", "color"=>"#7c3aed"];
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
                                    @if($health === "error")
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

                                    <form method="POST" action="{{ route("user.social-accounts.refresh", $c) }}">
                                        @csrf
                                        <button type="submit" title="Refresh now"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center text-xs"
                                                style="background: var(--bg-glass-input); color: var(--text-muted);">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route("user.social-accounts.destroy", $c) }}"
                                          onsubmit="return confirm("Disconnect this account? Follow buttons that reference it will fall back to icon style.");">
                                        @csrf @method("DELETE")
                                        <button type="submit" title="Disconnect"
                                                class="w-8 h-8 rounded-lg flex items-center justify-center text-xs"
                                                style="background: rgba(239,68,68,0.1); color: #ef4444;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    {{-- Add new connection --}}
    <div class="card-premium p-5" id="new-connection">
        <h2 class="text-base font-bold mb-1" style="color: var(--text-primary);">Connect a new account</h2>
        <p class="text-xs mb-4" style="color: var(--text-muted);">Pick a platform, enter your handle, and (for OAuth platforms) paste a long-lived access token.</p>

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
                @if($oauthReady)
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
                                    {{ $meta['label'] }} OAuth isn't configured on this server yet.
                                    For now, paste a long-lived access token here and we'll use it for refreshes.
                                    A server admin can enable one-click connect by setting
                                    <code>{{ \App\Modules\User\Services\SocialFollowers\SocialOAuthService::PROVIDERS[$key]['client_id_env'] ?? '' }}</code>
                                    and the matching secret.
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
</div>

<style>
    [x-cloak] { display: none !important; }
    .tab-active   { background: var(--accent); color: #fff; }
    .tab-inactive { background: var(--bg-glass-input); color: var(--text-muted); }
</style>
@endsection
