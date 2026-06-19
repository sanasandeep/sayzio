<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>[x-cloak]{display:none !important}</style>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen relative overflow-hidden" style="background: var(--bg-body);">
    <div class="bg-mesh"></div>

    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full animate-float-slow" style="background: radial-gradient(circle, rgba(124,58,237,0.12) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-24 -right-24 w-[400px] h-[400px] rounded-full animate-float-slow-delay" style="background: radial-gradient(circle, rgba(139,92,246,0.08) 0%, transparent 70%);"></div>
    </div>

    <div class="absolute top-5 right-5 z-20">
        @include('common.partials.theme-toggle')
    </div>

    <div class="min-h-screen flex relative z-10">
        <div class="hidden lg:block flex-1 relative">
            <a href="{{ route('home') }}" class="force-dark-logo absolute top-7 left-7 xl:top-9 xl:left-9 z-30 inline-flex items-center group">
                @include('common.partials.brand-logo', ['height' => 'h-10'])
            </a>
            @include('common.partials.auth-slider', ['variant' => 'page'])
        </div>

        <div class="flex-1 lg:flex-none lg:w-[480px] flex items-center justify-center p-6 lg:p-12 relative">
            <div class="hidden lg:block absolute inset-y-0 left-0 w-px" style="background: linear-gradient(180deg, transparent, var(--border-glass), transparent);"></div>

            <div class="w-full max-w-sm">
                <div class="text-center mb-7 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center">@include('common.partials.brand-logo', ['height' => 'h-12'])</a>
                </div>

                <div class="hidden lg:block mb-7">
                    <h2 class="text-2xl font-bold" style="color: var(--text-primary);">Create your account</h2>
                    <p class="text-sm mt-1" style="color: var(--text-dimmed);">{{ ($emailPasswordEnabled ?? false) ? 'Choose a password to sign in with your email.' : "No password needed — we'll email you a code each time." }}</p>
                </div>

                <div class="lg:hidden text-center mb-6">
                    <p class="text-sm" style="color: var(--text-dimmed);">Create your free account</p>
                </div>

                <form method="POST" action="{{ route('user.register.submit') }}" autocomplete="off">
                    @csrf
                    {{-- Honeypot: visually hidden, off the tab order, off
                         autocomplete. Real users never type into it; spam
                         bots fill every input on the page. The controller
                         silently accepts and discards any POST with this
                         field populated. --}}
                    <div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
                        <label for="website">Leave this field empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Full Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="John Doe" class="theme-input w-full">
                            @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" class="theme-input w-full">
                            @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        @if($emailPasswordEnabled ?? false)
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Password</label>
                            <input type="password" name="password" required placeholder="At least 8 characters" autocomplete="new-password" class="theme-input w-full">
                            @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Confirm Password</label>
                            <input type="password" name="password_confirmation" required placeholder="Re-enter your password" autocomplete="new-password" class="theme-input w-full">
                        </div>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Mobile <span style="color: var(--text-faint);">(optional)</span></label>
                            <input type="text" name="mobile" value="{{ old('mobile') }}" placeholder="+1234567890" class="theme-input w-full">
                            @error('mobile')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Country</label>
                            <select name="country" class="theme-input w-full">
                                <option value="">— Select your country —</option>
                                <option value="IN" {{ old('country') === 'IN' ? 'selected' : '' }}>India (billed in ₹ INR)</option>
                                <option value="US" {{ old('country') === 'US' ? 'selected' : '' }}>United States</option>
                                <option value="GB" {{ old('country') === 'GB' ? 'selected' : '' }}>United Kingdom</option>
                                <option value="CA" {{ old('country') === 'CA' ? 'selected' : '' }}>Canada</option>
                                <option value="AU" {{ old('country') === 'AU' ? 'selected' : '' }}>Australia</option>
                                <option value="DE" {{ old('country') === 'DE' ? 'selected' : '' }}>Germany</option>
                                <option value="FR" {{ old('country') === 'FR' ? 'selected' : '' }}>France</option>
                                <option value="NL" {{ old('country') === 'NL' ? 'selected' : '' }}>Netherlands</option>
                                <option value="SG" {{ old('country') === 'SG' ? 'selected' : '' }}>Singapore</option>
                                <option value="AE" {{ old('country') === 'AE' ? 'selected' : '' }}>United Arab Emirates</option>
                                <option value="BR" {{ old('country') === 'BR' ? 'selected' : '' }}>Brazil</option>
                                <option value="MX" {{ old('country') === 'MX' ? 'selected' : '' }}>Mexico</option>
                                <option value="JP" {{ old('country') === 'JP' ? 'selected' : '' }}>Japan</option>
                            </select>
                            <p class="mt-1 text-[11px]" style="color: var(--text-faint);">Determines your billing currency. India = ₹ INR, everywhere else = $ USD. You can change this later.</p>
                            @error('country')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Referral code <span style="color: var(--text-faint);">(optional)</span></label>
                            <input type="text" name="referral_code" id="referral_code_input" value="{{ old('referral_code', $prefilledRef ?? '') }}" maxlength="32" placeholder="friend's code" class="theme-input w-full" autocomplete="off">
                            <p class="mt-1 text-[11px]" id="referral_code_feedback" style="color: var(--text-faint);">If a friend referred you, paste their code to give them credit.</p>
                            @error('referral_code')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="rounded-xl px-3 py-2.5 text-[11px] flex items-start gap-2" style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.18); color: var(--text-dimmed);">
                            <i class="fas fa-shield-alt text-violet-400 mt-0.5"></i>
                            <span>{{ ($emailPasswordEnabled ?? false) ? "Use your email and password to sign in. Keep your password somewhere safe — it can't be reset yet." : "No password needed. We'll email you a 6-digit code to sign in every time." }}</span>
                        </div>

                        <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm">
                            Create Account <i class="fas fa-arrow-right text-[10px] ml-1"></i>
                        </button>
                    </div>
                </form>

                <script>
                (function(){
                    const input = document.getElementById('referral_code_input');
                    const fb = document.getElementById('referral_code_feedback');
                    if (!input) return;
                    let timer;
                    const check = async () => {
                        const v = input.value.trim();
                        if (!v) { fb.textContent = "If a friend referred you, paste their code to give them credit."; fb.style.color=''; return; }
                        try {
                            const r = await fetch('{{ route('user.referrals.check') }}?code=' + encodeURIComponent(v));
                            const j = await r.json();
                            fb.textContent = j.ok ? 'Looks good — your friend will get credit.' : j.message;
                            fb.style.color = j.ok ? '#34d399' : '#f87171';
                        } catch (_) {}
                    };
                    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(check, 300); });
                    if (input.value.trim()) check();
                })();
                </script>

                <p class="mt-6 text-center text-xs" style="color: var(--text-dimmed);">
                    Already have an account?
                    <a href="{{ route('user.login') }}" class="text-violet-400 hover:text-violet-300 font-semibold transition-colors">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
