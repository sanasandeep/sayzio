@extends('user.layouts.app')
@section('title', 'Settings · ' . $form->title)

@section('content')
<div class="max-w-3xl mx-auto">

    @include('user.partials.page-hero', [
        'title' => 'Settings: ' . $form->title,
        'subtitle' => 'Configure captcha, spam protection, and other per-form settings.',
        'icon' => 'fa-shield-halved',
        'back' => route('user.forms.show', $form),
    ])

    @include('user.forms._tabs')

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #f87171;">
        <i class="fas fa-exclamation-triangle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.forms.settings.update', $form) }}">
        @csrf @method('PUT')

        {{-- Captcha / Spam Protection --}}
        <div class="card-premium p-6 mb-6" x-data="{ provider: @js($captcha['provider']) }">
            <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">
                <i class="fas fa-robot mr-2 text-blue-400"></i> Captcha / Bot Protection
            </h3>
            <p class="text-xs mb-5" style="color: var(--text-muted);">
                Pick a method to protect this form from spam bots. Honeypot is invisible and enabled by default. Other providers require a free third-party account.
            </p>

            <div class="space-y-2 mb-5">
                @foreach([
                    'honeypot'     => ['label' => 'Honeypot (invisible, no keys required)',  'icon' => 'fa-eye-slash', 'needsKey' => false],
                    'math'         => ['label' => 'Math Challenge (server-side, no keys)',   'icon' => 'fa-calculator', 'needsKey' => false],
                    'recaptcha_v2' => ['label' => 'Google reCAPTCHA v2 (checkbox)',          'icon' => 'fa-google', 'needsKey' => true],
                    'recaptcha_v3' => ['label' => 'Google reCAPTCHA v3 (invisible score)',   'icon' => 'fa-google', 'needsKey' => true],
                    'hcaptcha'     => ['label' => 'hCaptcha (privacy-friendly checkbox)',    'icon' => 'fa-h-square', 'needsKey' => true],
                    'turnstile'    => ['label' => 'Cloudflare Turnstile (smart challenge)',  'icon' => 'fa-shield', 'needsKey' => true],
                ] as $p => $meta)
                    <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all"
                           style="border: 1px solid var(--border-glass); {{ $captcha['provider'] === $p ? 'background: rgba(92,131,255,0.08); border-color: rgba(92,131,255,0.35);' : 'background: var(--bg-glass-input);' }}">
                        <input type="radio" name="captcha_provider" value="{{ $p }}" x-model="provider"
                               @change="provider = '{{ $p }}'" {{ $captcha['provider'] === $p ? 'checked' : '' }}
                               class="text-blue-500" style="accent-color:#5c83ff;">
                        <i class="fas {{ $meta['icon'] }} text-blue-400 w-4 text-center text-xs"></i>
                        <span class="text-sm" style="color: var(--text-secondary);">{{ $meta['label'] }}</span>
                    </label>
                @endforeach
            </div>

            {{-- reCAPTCHA v3 score threshold --}}
            <div x-show="provider === 'recaptcha_v3'" class="mb-4 p-3 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-muted);">Score threshold <span style="color: var(--text-faint);">(0.1 – 1.0; 0.5 is recommended)</span></label>
                <input type="number" name="score_threshold" min="0.1" max="1.0" step="0.05"
                       class="theme-input w-32 text-xs" value="{{ $captcha['score_threshold'] ?? 0.5 }}">
                <p class="text-[10px] mt-1" style="color: var(--text-faint);">Submissions with a score below this threshold are rejected as bots. Higher = stricter.</p>
            </div>

            {{-- Site key + secret key inputs (shown for providers that need keys) --}}
            <div x-show="['recaptcha_v2','recaptcha_v3','hcaptcha','turnstile'].includes(provider)" class="space-y-3">
                <div class="p-3 rounded-xl" style="background: rgba(251,191,36,0.07); border: 1px solid rgba(251,191,36,0.2);">
                    <p class="text-[11px]" style="color: #fbbf24;">
                        <i class="fas fa-triangle-exclamation mr-1"></i>
                        Keys are saved per-form. Your secret key is encrypted at rest. Get your keys from the provider's dashboard.
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-muted);">Site key <span style="color: var(--text-faint);">(public — shown in the browser)</span></label>
                    <input type="text" name="site_key" class="theme-input w-full text-xs font-mono"
                           placeholder="Paste your site key here"
                           value="{{ $captcha['site_key'] ?? '' }}">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-muted);">Secret key <span style="color: var(--text-faint);">(server-only — never sent to the browser)</span></label>
                    @include('common.partials.password-field', [
                        'name' => 'secret_key',
                        'placeholder' => !empty($captcha['secret_key']) ? '••••••••••••••••••••' : 'Paste your secret key here',
                        'autocomplete' => 'new-password',
                        'inputClass' => 'theme-input w-full text-xs font-mono',
                    ])
                    @if(!empty($captcha['secret_key']))
                        <p class="text-[10px] mt-1" style="color: var(--text-faint);">A secret key is already saved. Leave blank to keep the existing one.</p>
                        <label class="flex items-center gap-1.5 mt-1 text-[11px] cursor-pointer" style="color: #f87171;">
                            <input type="checkbox" name="clear_secret" value="1"> Remove saved secret key
                        </label>
                    @endif
                </div>
            </div>

            {{-- Math captcha note --}}
            <div x-show="provider === 'math'" class="mt-3 p-3 rounded-xl text-xs" style="background: rgba(92,131,255,0.07); border: 1px solid rgba(92,131,255,0.2); color: var(--text-muted);">
                <i class="fas fa-info-circle mr-1 text-blue-400"></i>
                A simple arithmetic question is generated server-side on each form load (e.g. "What is 4 + 7?"). No third-party scripts or keys are needed.
            </div>

            {{-- Honeypot note --}}
            <div x-show="provider === 'honeypot'" class="mt-3 p-3 rounded-xl text-xs" style="background: rgba(16,185,129,0.07); border: 1px solid rgba(16,185,129,0.2); color: var(--text-muted);">
                <i class="fas fa-check-circle mr-1 text-emerald-400"></i>
                An invisible field is already present on every form. Most simple bots fill it in and are automatically caught. No visible challenge shown to visitors.
            </div>
        </div>

        @canInWorkspace('inbox.edit')
        <button type="submit" class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg">
            <i class="fas fa-save text-xs"></i> Save Settings
        </button>
        @else
        <button type="button" disabled class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg opacity-60 cursor-not-allowed">
            <i class="fas fa-lock text-xs"></i> Save Settings
        </button>
        @endcanInWorkspace
    </form>
</div>
@endsection
