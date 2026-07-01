@extends('user.layouts.app')

@section('title', $feature['label'].' — Coming soon')

@php
    $__user = auth()->user();
    $__adminAccount = ($__user && $__user->hasActiveAdminAccount()) ? $__user->adminAccount() : null;
    $__canManage = $__adminAccount && $__adminAccount->hasPermission('settings.manage');
    $__impersonating = session()->has('impersonate_user_id');

    $__tint = $feature['tint'] ?? '#6366f1';
    $__icon = $feature['icon'] ?? 'fa-sparkles';
    $__reason = $state['reason'] ?? null; // auto | forced
    $__hint = $feature['admin_hint'] ?? null;
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl"
             style="background-color: {{ $__tint }}1a; color: {{ $__tint }};">
            <i class="fas {{ $__icon }} text-2xl"></i>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white/70">
            <i class="fas fa-clock text-[10px]"></i>
            Coming soon
        </span>

        <h1 class="mt-4 text-lg font-semibold text-white">{{ $feature['label'] }} is available soon</h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-white/60">
            {{ $feature['blurb'] }}
        </p>

        @if(!empty($feature['capabilities']))
            <div class="mx-auto mt-5 max-w-md rounded-xl border border-white/10 bg-white/[0.02] p-4 text-left text-sm text-white/60">
                <p class="font-medium text-white/80">What you’ll be able to do</p>
                <ul class="mt-2 space-y-1.5">
                    @foreach($feature['capabilities'] as $cap)
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check mt-0.5 text-xs" style="color: {{ $__tint }};"></i>
                            <span>{{ $cap }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Notify me: deduped per user. --}}
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            @if($notified)
                <span class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/70">
                    <i class="fas fa-bell-slash text-xs" style="color: {{ $__tint }};"></i>
                    You’re on the list — we’ll email you
                </span>
            @else
                <form action="{{ route('user.coming-soon.notify', ['feature' => $featureKey]) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                            style="background-color: {{ $__tint }};">
                        <i class="fas fa-bell text-xs"></i>
                        Notify me when it’s ready
                    </button>
                </form>
            @endif

            <a href="{{ route('user.dashboard') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">
                <i class="fas fa-arrow-left text-xs"></i>
                Back to dashboard
            </a>
        </div>

        @if(session('status'))
            <p class="mx-auto mt-4 max-w-md text-xs text-white/50">
                <i class="fas fa-check-circle mr-1" style="color: {{ $__tint }};"></i>
                {{ session('status') }}
            </p>
        @endif

        {{-- Admin viewer branch: explain why it's coming soon + a shortcut. --}}
        @if($__canManage && !$__impersonating)
            <div class="mx-auto mt-6 max-w-md rounded-xl border border-white/10 bg-white/[0.02] p-4 text-left text-sm text-white/60">
                <p class="font-medium text-white/80">
                    <i class="fas fa-user-shield mr-1 text-xs text-white/50"></i>
                    Admin note
                </p>
                @if($__reason === 'forced')
                    <p class="mt-1">
                        This feature is manually marked <strong class="text-white/80">Coming soon</strong> for everyone.
                        Clear the override on the Feature States screen to make it available again.
                    </p>
                @else
                    <p class="mt-1">
                        This feature is enabled but its integration or configuration isn’t connected yet, so it’s
                        showing as coming soon. Connect it to make it available.
                    </p>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    <form action="{{ route('user.switch-to-admin') }}" method="POST">
                        @csrf
                        <input type="hidden" name="intent" value="feature-states">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white/80 hover:bg-white/10">
                            <i class="fas fa-sliders-h text-[10px]"></i>
                            Manage feature states
                        </button>
                    </form>
                    @if($__hint)
                        <form action="{{ route('user.switch-to-admin') }}" method="POST">
                            @csrf
                            <input type="hidden" name="intent" value="integrations">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white/80 hover:bg-white/10">
                                <i class="fas fa-plug text-[10px]"></i>
                                {{ $__hint['label'] }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
