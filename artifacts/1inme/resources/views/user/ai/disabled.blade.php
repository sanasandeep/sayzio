@extends('user.layouts.app')
@section('title', $title ?? 'AI')

@php
    $__user = auth()->user();
    $__adminAccount = ($__user && $__user->hasActiveAdminAccount()) ? $__user->adminAccount() : null;
    $__canManageAi = $__adminAccount && $__adminAccount->hasPermission('settings.manage');
    $__impersonating = session()->has('impersonate_user_id');
    $__supportEmail = config('billing.support_email') ?: config('mail.from.address');
    $__appName = config('app.name', '1INME');
    // Admins who already have an OpenAI key configured can flip the master
    // switch on right here — no detour through the settings screen.
    $__hasOpenAiKey = $__canManageAi && !$__impersonating
        && \App\Services\AI\AiEngineSettings::openAiKey() !== null;
    // The feature the admin was trying to open, so we can land them back on
    // it after enabling the engine.
    $__returnTo = url()->current();
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-300">
            <i class="fas fa-robot text-2xl"></i>
        </div>
        <h1 class="text-lg font-semibold text-white">{{ $heading ?? 'AI features are currently turned off' }}</h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-white/60">
            {{ $message ?? 'The AI engine isn’t enabled on this account right now. Once an administrator switches it on, this feature will be ready to use here.' }}
        </p>

        @if($__canManageAi && !$__impersonating)
            @if($__hasOpenAiKey)
                {{-- Admin with a key already configured: one-click enable, then
                     land back on the feature they were trying to open. --}}
                <p class="mx-auto mt-4 max-w-md text-sm text-white/50">
                    You have admin access and an OpenAI key is already configured. Switch the AI engine on and continue right here.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <form action="{{ route('user.ai.enable') }}" method="POST">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ $__returnTo }}">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                            <i class="fas fa-bolt text-xs"></i>
                            Enable AI now
                        </button>
                    </form>
                    <form action="{{ route('user.switch-to-admin') }}" method="POST">
                        @csrf
                        <input type="hidden" name="intent" value="ai-engine">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">
                            <i class="fas fa-sliders-h text-xs"></i>
                            Open AI settings
                        </button>
                    </form>
                    <a href="{{ route('user.dashboard') }}"
                       class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">
                        <i class="fas fa-arrow-left text-xs"></i>
                        Back to dashboard
                    </a>
                </div>
            @else
                {{-- Admin / super-admin with no key yet: jump straight to the AI
                     engine settings to add one and turn it on. --}}
                <p class="mx-auto mt-4 max-w-md text-sm text-white/50">
                    You have admin access. Open the AI engine settings to add an OpenAI key and flip the master switch on.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <form action="{{ route('user.switch-to-admin') }}" method="POST">
                        @csrf
                        <input type="hidden" name="intent" value="ai-engine">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                            <i class="fas fa-sliders-h text-xs"></i>
                            Turn on AI in settings
                        </button>
                    </form>
                    <a href="{{ route('user.dashboard') }}"
                       class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">
                        <i class="fas fa-arrow-left text-xs"></i>
                        Back to dashboard
                    </a>
                </div>
            @endif
        @else
            {{-- Regular user: explain the feature and offer a way to ask for it. --}}
            <div class="mx-auto mt-5 max-w-md rounded-xl border border-white/10 bg-white/[0.02] p-4 text-left text-sm text-white/60">
                <p class="font-medium text-white/80">What you’re missing</p>
                <p class="mt-1">
                    AI features on {{ $__appName }} — like Minds, Personas, the Companion and Coach — help you
                    draft content, answer questions about your account and build pages faster. They run on
                    your coin balance once an administrator enables the engine.
                </p>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @if($__supportEmail)
                    <a href="mailto:{{ $__supportEmail }}?subject={{ rawurlencode('Please enable AI features on my '.$__appName.' account') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                        <i class="fas fa-envelope text-xs"></i>
                        Request access
                    </a>
                @endif
                <a href="{{ route('user.dashboard') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">
                    <i class="fas fa-arrow-left text-xs"></i>
                    Back to dashboard
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
