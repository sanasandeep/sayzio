@extends('user.layouts.app')

@section('title', 'Devices & sessions')

@section('content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Devices & sessions',
        'subtitle' => 'Every browser and app currently signed into your account. Revoke anything you don\'t recognise.',
        'icon' => 'fa-shield-halved',
        'chips' => [
            ['icon' => 'fa-mobile-screen text-violet-400', 'text' => count($items) . ' active'],
        ],
    ])

    @if(session('success'))
        <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
            <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
            <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm" style="color: var(--text-muted);">
            If you spot a session you don't recognise, revoke it and change your password.
        </p>
        <form method="POST" action="{{ route('user.settings.sessions.destroy-others') }}"
              onsubmit="return confirm('Sign out of every other device? You\'ll stay signed in here.');">
            @csrf @method('DELETE')
            <button type="submit"
                    class="px-3 py-2 rounded-xl text-sm font-medium transition"
                    style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #f87171;">
                <i class="fas fa-right-from-bracket mr-1.5"></i> Sign out everywhere except this
            </button>
        </form>
    </div>

    <div class="space-y-3">
        @forelse($items as $item)
            <div class="card-premium p-4 flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center"
                     style="background: rgba(124,58,237,0.12); color: #a78bfa;">
                    @if(($item['platform'] ?? null) === 'ios')
                        <i class="fab fa-apple text-lg"></i>
                    @elseif(($item['platform'] ?? null) === 'android')
                        <i class="fab fa-android text-lg"></i>
                    @elseif(($item['platform'] ?? null) === 'macos')
                        <i class="fab fa-apple text-lg"></i>
                    @elseif(($item['platform'] ?? null) === 'windows')
                        <i class="fab fa-windows text-lg"></i>
                    @elseif($item['kind'] === 'web')
                        <i class="fas fa-globe text-lg"></i>
                    @else
                        <i class="fas fa-mobile-screen-button text-lg"></i>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $item['device_label'] }}</span>
                        @if($item['is_current'])
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                  style="background: rgba(16,185,129,0.18); color: #10b981;">
                                This device
                            </span>
                        @endif
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wider"
                              style="background: rgba(255,255,255,0.06); color: var(--text-muted);">
                            {{ $item['kind'] === 'token' ? 'App / API' : 'Web' }}
                        </span>
                    </div>

                    <div class="mt-1.5 text-xs space-y-0.5" style="color: var(--text-muted);">
                        @if(!empty($item['user_agent']))
                            <div class="truncate" title="{{ $item['user_agent'] }}">
                                <i class="fas fa-circle-info mr-1 opacity-60"></i>{{ \Illuminate\Support\Str::limit($item['user_agent'], 90) }}
                            </div>
                        @endif
                        <div>
                            <i class="fas fa-location-dot mr-1 opacity-60"></i>
                            {{ $item['country'] ?: 'Unknown country' }}
                            @if(!empty($item['ip']))
                                <span class="opacity-60">· {{ $item['ip'] }}</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-0.5">
                            @if(!empty($item['first_seen_at']))
                                <span><i class="fas fa-clock-rotate-left mr-1 opacity-60"></i>First seen {{ \Carbon\Carbon::parse($item['first_seen_at'])->diffForHumans() }}</span>
                            @endif
                            @if(!empty($item['last_active_at']))
                                <span><i class="fas fa-eye mr-1 opacity-60"></i>Last active {{ \Carbon\Carbon::parse($item['last_active_at'])->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex-shrink-0">
                    @if($item['is_current'])
                        <span class="text-xs" style="color: var(--text-muted);">Current</span>
                    @else
                        <form method="POST" action="{{ route('user.settings.sessions.destroy', ['id' => $item['id']]) }}"
                              onsubmit="return confirm('Revoke this session? The device will be signed out on its next request.');">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                                    style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #f87171;">
                                Revoke
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="card-premium p-8 text-center text-sm" style="color: var(--text-muted);">
                <i class="fas fa-circle-info mb-2 text-lg opacity-60"></i>
                <p>No active sessions found.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
