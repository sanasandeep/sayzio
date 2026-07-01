@extends('user.layouts.app')
@php
    $__aiLabelMap = [
        'Mind'       => 'AI Note Summarizer',
        'Minds'      => 'AI Knowledge Bases',
        'Persona'    => 'AI Persona Generator',
        'Personas'   => 'AI Agents',
        'Companion'  => 'AI Chat',
        'Companions' => 'Chat Widgets',
        'Coach'      => 'AI Growth Coach',
        'Ask Coach'  => 'AI Coach',
    ];
    $__aiDisplayTitle = $__aiLabelMap[$title ?? ''] ?? ($title ?? 'AI');
@endphp
@section('title', $__aiDisplayTitle)

@php
    $__user = auth()->user();
    $__adminAccount = ($__user && $__user->hasActiveAdminAccount()) ? $__user->adminAccount() : null;
    $__canManageAi = $__adminAccount && $__adminAccount->hasPermission('settings.manage');
    $__impersonating = session()->has('impersonate_user_id');
    $__supportEmail = config('billing.support_email') ?: config('mail.from.address');
    $__appName = config('app.name', 'Sayzio');

    // Admins who already have an OpenAI key configured can flip the master
    // switch on right here — no detour through the settings screen.
    $__hasOpenAiKey = $__canManageAi && !$__impersonating
        && \App\Services\AI\AiEngineSettings::openAiKey() !== null;
    // The feature the admin was trying to open, so we can land them back on
    // it after enabling the engine.
    $__returnTo = url()->current();

    // The engine master switch decides who can unlock this feature.
    //  - OFF: only an administrator can turn it on, so the page keeps the
    //    explainer + "Request access" email (behaviour unchanged).
    //  - ON: the gate is now the user's plan and/or coin balance, which
    //    they can self-serve, so we surface upgrade + coin top-up links
    //    and explain the coin cost model instead of just emailing support.
    // Controllers may force the engine-off branch even when the master AI
    // switch is on — e.g. the Voice Assistant feature toggle is off, where
    // no plan upgrade would help and only an admin can turn it on.
    $__aiEnabled = $aiEnabled ?? \App\Services\AI\AiEngineSettings::isEnabled();
    $__featureLabel = $__aiLabelMap[$title ?? ''] ?? ($title ?? 'AI features');
    $__planName = $__user && $__user->plan ? $__user->plan->name : 'your current plan';
    $__coinBalance = $__user && $__user->wallet ? (int) $__user->wallet->balance : 0;
    // Optional concrete upgrade target passed by the controller (e.g. the
    // cheapest plan that unlocks the feature). Falls back to the generic
    // upgrade page when absent.
    $__upgradePlan = $upgradePlan ?? null;

    // Per-feature one-liner so a user who clicks in knows exactly what this
    // particular surface does — keyed off the `title` each controller passes.
    $__featureBlurbs = [
        'Mind'       => 'AI Note Summarizer turns raw notes into a tight summary with clear next steps.',
        'Minds'      => 'AI Knowledge Bases let you build and manage several AI knowledge bases, each trained on its own set of sources.',
        'Persona'    => 'AI Persona Generator creates a brand persona that shapes the tone and personality your AI uses when it writes or replies on your behalf.',
        'Personas'   => 'AI Agents let you create and switch between configurable agents — each with its own prompt, tone, and knowledge — for different audiences.',
        'Companion'  => 'AI Chat is a chat assistant that helps you draft content and answer questions about your account.',
        'Companions' => 'Chat Widgets are embeddable AI chatbots you can drop into your pages, widgets and inbox.',
        'Coach'      => 'AI Growth Coach gives you AI-powered suggestions to grow and fine-tune your links and pages.',
        'Ask Coach'  => 'AI Coach lets you chat with an AI advisor for tips on improving your account.',
    ];
    $__featureBlurb = $__featureBlurbs[$title ?? ''] ?? null;
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-300">
            <i class="fas fa-robot text-2xl"></i>
        </div>

        @if($__canManageAi && !$__impersonating)
            <h1 class="text-lg font-semibold text-white">{{ $heading ?? 'AI features are currently turned off' }}</h1>
            <p class="mx-auto mt-2 max-w-md text-sm text-white/60">
                {{ $message ?? 'The AI engine isn’t enabled on this account right now. Once an administrator switches it on, this feature will be ready to use here.' }}
            </p>
            @if($__featureBlurb)
                <p class="mx-auto mt-3 max-w-md text-sm text-blue-200/80">
                    <i class="fas fa-info-circle mr-1 text-xs text-blue-300/80"></i>
                    {{ $__featureBlurb }}
                </p>
            @endif
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
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
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
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
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
        @elseif(!$__aiEnabled)
            {{-- Engine OFF globally: only an admin can enable it. Keep the
                 explainer + request-access email exactly as before. --}}
            <h1 class="text-lg font-semibold text-white">{{ $heading ?? 'AI features are currently turned off' }}</h1>
            <p class="mx-auto mt-2 max-w-md text-sm text-white/60">
                {{ $message ?? 'The AI engine isn’t enabled on this account right now. AI is controlled by a master switch that only an administrator can turn on. Once it’s switched on, this feature will be ready to use here.' }}
            </p>
            @if($__featureBlurb)
                <p class="mx-auto mt-3 max-w-md text-sm text-blue-200/80">
                    <i class="fas fa-info-circle mr-1 text-xs text-blue-300/80"></i>
                    {{ $__featureBlurb }}
                </p>
            @endif
            <div class="mx-auto mt-5 max-w-md rounded-xl border border-white/10 bg-white/[0.02] p-4 text-left text-sm text-white/60">
                <p class="font-medium text-white/80">What you’re missing</p>
                <p class="mt-1">
                    AI features on {{ $__appName }} — like AI Knowledge Bases, AI Agents, AI Chat and AI Growth Coach — help you
                    draft content, answer questions about your account and build pages faster. They run on
                    your coin balance once an administrator enables the engine.
                </p>
                <p class="mt-2 text-white/50">
                    You can’t switch this on yourself — it’s controlled by an administrator. Use the button
                    below to ask them to turn AI on for your account.
                </p>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @if($__supportEmail)
                    <a href="mailto:{{ $__supportEmail }}?subject={{ rawurlencode('Please enable AI features on my '.$__appName.' account') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
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
        @else
            {{-- Engine ON but this user is gated by their plan: tell them
                 which plan/coins unlock it so they can self-serve. --}}
            <h1 class="text-lg font-semibold text-white">{{ $heading ?? $__featureLabel.' isn’t included on your plan yet' }}</h1>
            <p class="mx-auto mt-2 max-w-md text-sm text-white/60">
                {{ $message ?? $__featureLabel.' is available on '.$__appName.', but '.$__planName.' doesn’t unlock it yet. Upgrade to switch it on for your account.' }}
            </p>
            @if($__featureBlurb)
                <p class="mx-auto mt-3 max-w-md text-sm text-blue-200/80">
                    <i class="fas fa-info-circle mr-1 text-xs text-blue-300/80"></i>
                    {{ $__featureBlurb }}
                </p>
            @endif

            <div class="mx-auto mt-5 max-w-md rounded-xl border border-white/10 bg-white/[0.02] p-4 text-left text-sm text-white/60">
                <p class="font-medium text-white/80">How AI is billed</p>
                <p class="mt-1">
                    Once your plan includes it, AI runs straight from your coin wallet — you’re only charged
                    coins for what you actually use, with no separate AI subscription. You currently have
                    <span class="font-semibold text-white/80">{{ number_format($__coinBalance) }}</span>
                    {{ \Illuminate\Support\Str::plural('coin', $__coinBalance) }} to spend, and you can top up any time.
                </p>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('user.upgrade') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="fas fa-arrow-up text-xs"></i>
                    @if($__upgradePlan)
                        Upgrade to {{ $__upgradePlan->name }}
                    @else
                        See upgrade options
                    @endif
                </a>
                <a href="{{ route('user.wallet.buy') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">
                    <i class="fas fa-coins text-xs"></i>
                    Top up coins
                </a>
                <a href="{{ route('user.dashboard') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/10">
                    <i class="fas fa-arrow-left text-xs"></i>
                    Back to dashboard
                </a>
            </div>

            @if($__supportEmail)
                <p class="mx-auto mt-4 max-w-md text-xs text-white/40">
                    Not sure which plan you need?
                    <a href="mailto:{{ $__supportEmail }}?subject={{ rawurlencode('Question about AI features on my '.$__appName.' account') }}"
                       class="text-blue-300 hover:text-blue-200 underline underline-offset-2">Ask support</a>.
                </p>
            @endif
        @endif
    </div>
</div>
@endsection
