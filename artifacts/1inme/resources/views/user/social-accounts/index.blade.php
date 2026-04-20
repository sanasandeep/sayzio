@extends('user.layouts.app')
@section('title', 'Connected Accounts')

@section('content')
@php
    $totalConnections = collect($connections)->flatten()->count();
@endphp

<div class="max-w-5xl mx-auto" x-data="{ tab: '{{ array_key_first($platforms) }}' }">
    @include('user.partials.page-hero', [
        'title'    => 'Connected Accounts',
        'subtitle' => 'Link your social profiles so biolink Follow buttons can show live follower counts. Counts refresh every few hours in the background.',
        'icon'     => 'fa-share-nodes',
        'chips'    => [
            ['icon' => 'fa-link', 'text' => $totalConnections . ' connected'],
        ],
    ])

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
        <div class="card-premium p-5 mb-5">
            <h2 class="text-base font-bold mb-4" style="color: var(--text-primary);">Your connections</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($connections as $platform => $rows)
                    @foreach($rows as $c)
                        @php $meta = $platforms[$platform] ?? ['label'=>ucfirst($platform), 'icon'=>'fas fa-link', 'color'=>'#7c3aed']; @endphp
                        <div class="flex items-center gap-3 p-3 rounded-lg" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                 style="background: {{ $meta['color'] }}20;">
                                <i class="{{ $meta['icon'] }} text-base" style="color: {{ $meta['color'] }};"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                                    {{ $meta['label'] }} · @{{ $c->handle }}
                                </div>
                                <div class="text-[11px] truncate" style="color: var(--text-muted);">
                                    @if($c->follower_count !== null)
                                        <span style="color: #10b981; font-weight:600;">{{ \App\Modules\User\Models\SocialAccountConnection::formatCount($c->follower_count) }}</span> followers
                                        @if($c->last_refreshed_at) · refreshed {{ $c->last_refreshed_at->diffForHumans() }} @endif
                                    @elseif($c->last_refresh_status === 'error')
                                        <span style="color:#ef4444;">{{ $c->last_refresh_error ?: 'refresh failed' }}</span>
                                    @elseif($c->last_refresh_status === 'unsupported')
                                        Refresh not yet supported · save the access token to enable
                                    @else
                                        Awaiting first refresh
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('user.social-accounts.refresh', $c) }}">
                                @csrf
                                <button type="submit" title="Refresh now"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-xs"
                                        style="background: var(--bg-glass-input); color: var(--text-muted);">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('user.social-accounts.destroy', $c) }}"
                                  onsubmit="return confirm('Disconnect this account? Follow buttons that reference it will fall back to icon style.');">
                                @csrf @method('DELETE')
                                <button type="submit" title="Disconnect"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-xs"
                                        style="background: rgba(239,68,68,0.1); color: #ef4444;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    {{-- Add new connection --}}
    <div class="card-premium p-5">
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
