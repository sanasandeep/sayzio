{{-- Two-panel login/register modal. Mounted only on the home page. Forms post to the existing user auth endpoints. --}}
{{-- Light-mode pairing: the card is dark glass by default; when the site is in
     light mode the card flips to light glass and the hardcoded dark-theme text
     utilities inside it are remapped (scoped to .auth-modal-card only). --}}
<style>
    html.light-mode .auth-modal-card { background: rgba(255,255,255,0.85) !important; border-color: rgba(15,23,42,0.10) !important; }
    html.light-mode .auth-modal-card .text-white { color: #071437; }
    html.light-mode .auth-modal-card .text-gray-400 { color: #4b5675; }
    html.light-mode .auth-modal-card .text-gray-500, html.light-mode .auth-modal-card .text-gray-600 { color: #6b7280; }
    html.light-mode .auth-modal-card .text-blue-200, html.light-mode .auth-modal-card .text-blue-300 { color: #1d4ed8; }
    html.light-mode .auth-modal-card input, html.light-mode .auth-modal-card select {
        background: #ffffff !important; border-color: #dbdfe9 !important; color: #071437 !important;
    }
    html.light-mode .auth-modal-card input::placeholder { color: #9aa3b5 !important; }
    html.light-mode .auth-modal-card .bg-white\/5 { background: rgba(7,20,55,0.05); }
    html.light-mode .auth-modal-card .bg-white\/10 { background: rgba(7,20,55,0.08); }
    html.light-mode .auth-modal-card .border-white\/10 { border-color: rgba(7,20,55,0.12); }
    html.light-mode .auth-modal-card .auth-modal-close { background: rgba(7,20,55,0.08); color: #071437; }
    html.light-mode .auth-modal-card .auth-modal-close:hover { background: rgba(7,20,55,0.15); }
    {{-- Keep the tab pills readable: active pill stays solid blue with white text --}}
    html.light-mode .auth-modal-card .bg-blue-600, html.light-mode .auth-modal-card .bg-blue-600 * { color: #ffffff !important; }
</style>
<div x-show="authOpen" x-cloak
     class="fixed inset-0 z-[100] overflow-y-auto overscroll-contain bg-black/70 backdrop-blur-sm"
     @click.self="authOpen=false"
     @keydown.escape.window="authOpen=false"
     role="dialog" aria-modal="true" aria-label="Sign in or create an account">
    <div class="min-h-full flex items-center justify-center p-4" @click.self="authOpen=false">
    <div class="auth-modal-card relative w-full max-w-3xl my-8 rounded-2xl shadow-2xl overflow-hidden border border-white/10"
         style="background: rgba(22,26,40,0.72); backdrop-filter: blur(30px) saturate(1.5); -webkit-backdrop-filter: blur(30px) saturate(1.5);">
        <button type="button" @click="authOpen=false"
                class="auth-modal-close absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center"
                aria-label="Close">
            <i class="fas fa-times text-sm"></i>
        </button>

        <div class="grid md:grid-cols-2">
            {{-- Left full-bleed slider panel — real photos with category bullets --}}
            <div class="hidden md:block relative">
                @include('common.partials.auth-slider', ['variant' => 'modal'])
            </div>

            {{-- Right form panel --}}
            <div class="px-6 pb-6 pt-16 sm:px-8 sm:pb-8 md:pt-8">
                <div class="flex bg-white/5 rounded-xl p-1 mb-6 text-sm">
                    <button type="button" @click="authTab='login'"
                            :class="authTab==='login' ? 'bg-blue-600 text-white' : 'text-gray-400'"
                            class="flex-1 py-2 rounded-lg font-semibold transition">Login</button>
                    <button type="button" @click="authTab='register'"
                            :class="authTab==='register' ? 'bg-blue-600 text-white' : 'text-gray-400'"
                            class="flex-1 py-2 rounded-lg font-semibold transition">Sign up</button>
                </div>

                {{-- Login form --}}
                @php
                    $emailPasswordEnabled = $emailPasswordEnabled ?? false;
                    $emailOtpEnabled      = $emailOtpEnabled ?? true;
                    $mobileLoginEnabled   = $mobileLoginEnabled ?? false;
                    $defaultLoginMethod   = old('login_method')
                        ?: ($emailPasswordEnabled ? 'password' : ($emailOtpEnabled ? 'email_otp' : 'mobile'));
                    $loginMethodCount     = ($emailPasswordEnabled ? 1 : 0) + ($emailOtpEnabled ? 1 : 0) + ($mobileLoginEnabled ? 1 : 0);
                @endphp

                <div x-show="authTab==='login'" x-data="{ method: '{{ $defaultLoginMethod }}' }">

                    {{-- Method toggle — only shown when more than one method is enabled --}}
                    @if($loginMethodCount > 1)
                    <div class="flex gap-2 mb-4">
                        @if($emailPasswordEnabled)
                        <button type="button" @click="method='password'"
                                :class="method==='password' ? 'border-blue-500 text-blue-300 bg-blue-500/10' : 'border-white/10 text-gray-400'"
                                class="flex-1 py-2 text-xs font-medium rounded-lg border">
                            <i class="fas fa-key mr-1"></i> Password
                        </button>
                        @endif
                        @if($emailOtpEnabled)
                        <button type="button" @click="method='email_otp'"
                                :class="method==='email_otp' ? 'border-blue-500 text-blue-300 bg-blue-500/10' : 'border-white/10 text-gray-400'"
                                class="flex-1 py-2 text-xs font-medium rounded-lg border">
                            <i class="fas fa-envelope mr-1"></i> Email code
                        </button>
                        @endif
                        @if($mobileLoginEnabled)
                        <button type="button" @click="method='mobile'"
                                :class="method==='mobile' ? 'border-blue-500 text-blue-300 bg-blue-500/10' : 'border-white/10 text-gray-400'"
                                class="flex-1 py-2 text-xs font-medium rounded-lg border">
                            <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                        </button>
                        @endif
                    </div>
                    @endif

                    @if($emailPasswordEnabled)
                    {{-- Email + password sign-in --}}
                    <form method="POST" action="{{ route('user.login.submit') }}"
                          x-show="method==='password'" @if($loginMethodCount > 1)x-cloak @endif
                          class="space-y-3">
                        @csrf
                        <input type="hidden" name="login_method" value="password">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com"
                                   class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Password</label>
                            @include('common.partials.password-field', [
                                'name' => 'password',
                                'placeholder' => 'Your password',
                                'autocomplete' => 'current-password',
                                'required' => true,
                                'inputClass' => 'w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none',
                            ])
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-bold text-white">
                            <i class="fas fa-arrow-right-to-bracket mr-1 text-xs"></i> Sign In
                        </button>
                    </form>
                    @endif

                    @if($emailOtpEnabled)
                    {{-- Email one-time-code sign-in --}}
                    <form method="POST" action="{{ route('user.otp.send') }}"
                          x-show="method==='email_otp'" @if($loginMethodCount > 1)x-cloak @endif
                          class="space-y-3">
                        @csrf
                        {{-- Login tab = sign IN. An unknown identifier stays
                             enumeration-safe (no account, no code). Sign-up lives
                             on the "Sign up" tab. --}}
                        <input type="hidden" name="intent" value="login">
                        <input type="hidden" name="type" value="email">
                        <input type="hidden" name="login_method" value="email_otp">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Email</label>
                            <input type="email" name="identifier" value="{{ old('identifier') }}" required placeholder="you@example.com"
                                   class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-bold text-white">
                            <i class="fas fa-paper-plane mr-1 text-xs"></i> Send 6-digit code
                        </button>
                        @if(!$emailPasswordEnabled)
                        <p class="text-center text-xs text-gray-500">No password needed; we'll email you a code.</p>
                        @endif
                    </form>
                    @endif

                    @if($mobileLoginEnabled)
                    {{-- WhatsApp one-time-code sign-in --}}
                    <form method="POST" action="{{ route('user.otp.send') }}"
                          x-show="method==='mobile'" @if($loginMethodCount > 1)x-cloak @endif
                          class="space-y-3">
                        @csrf
                        <input type="hidden" name="intent" value="login">
                        <input type="hidden" name="type" value="mobile">
                        <input type="hidden" name="login_method" value="mobile">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">WhatsApp Number</label>
                            <input type="text" name="identifier" value="{{ old('identifier') }}" required placeholder="+1234567890"
                                   class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none">
                            <p class="mt-1.5 text-[10px] text-gray-500">
                                <i class="fab fa-whatsapp mr-0.5"></i> We'll send your code over WhatsApp. Supported country codes: {{ implode(', ', $allowedCountryCodes ?? []) }}.
                            </p>
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-bold text-white">
                            <i class="fas fa-paper-plane mr-1 text-xs"></i> Send 6-digit code
                        </button>
                    </form>
                    @endif

                    {{-- Fallback: if somehow all methods are disabled, show nothing useful --}}
                    @if(!$emailPasswordEnabled && !$emailOtpEnabled && !$mobileLoginEnabled)
                    <p class="text-center text-xs text-gray-500 py-4">Login is currently unavailable.</p>
                    @endif

                    {{-- Social sign-in, same providers as the full-page login --}}
                    <div class="flex items-center gap-3 my-4">
                        <div class="flex-1 h-px bg-white/10"></div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-gray-500">or sign in with</span>
                        <div class="flex-1 h-px bg-white/10"></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <a href="{{ route('user.social-oauth.login', 'google') }}"
                           class="w-full py-2.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-sm font-semibold text-white flex items-center justify-center gap-2">
                            <i class="fab fa-google text-[13px]"></i> Log in with Google
                        </a>
                        <a href="{{ route('user.social-oauth.login', 'linkedin') }}"
                           class="w-full py-2.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-sm font-semibold text-white flex items-center justify-center gap-2">
                            <i class="fab fa-linkedin text-[13px]"></i> Log in with LinkedIn
                        </a>
                    </div>
                </div>

                {{-- Register: two simple tabs mirroring the login tab — Email
                     and WhatsApp — instead of both forms stacked. --}}
                <div x-show="authTab==='register'" x-cloak x-data="{ reg: '{{ old('intent') === 'signup' ? 'mobile' : 'email' }}' }">

                @if($mobileLoginEnabled)
                <div class="flex gap-2 mb-4">
                    <button type="button" @click="reg='email'"
                            :class="reg==='email' ? 'border-blue-500 text-blue-300 bg-blue-500/10' : 'border-white/10 text-gray-400'"
                            class="flex-1 py-2 text-xs font-medium rounded-lg border">
                        <i class="fas fa-envelope mr-1"></i> Email
                    </button>
                    <button type="button" @click="reg='mobile'"
                            :class="reg==='mobile' ? 'border-blue-500 text-blue-300 bg-blue-500/10' : 'border-white/10 text-gray-400'"
                            class="flex-1 py-2 text-xs font-medium rounded-lg border">
                        <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                    </button>
                </div>
                @endif

                <form x-show="reg==='email'" method="POST" action="{{ route('user.register.submit') }}"
                      class="space-y-3">
                    @csrf
                    {{-- Carries the handle typed into the homepage hero "claim your
                         link" control so it's applied as the new account's @handle
                         right after sign-up. Bound to the Alpine authHandle set by
                         the open-auth event; empty when the modal is opened any
                         other way (registration still works normally). --}}
                    <input type="hidden" name="desired_handle" :value="authHandle">
                    <div x-show="authHandle" x-cloak class="rounded-lg px-3 py-2 text-xs bg-blue-500/10 border border-blue-500/30 text-blue-200 flex items-center gap-2">
                        <i class="fas fa-link text-[11px]"></i>
                        <span>Claiming <strong x-text="'@' + authHandle"></strong> for your page.</span>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Full name</label>
                        <input type="text" name="name" required value="{{ old('name') }}" placeholder="Jane Doe"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Email</label>
                        <input type="email" name="email" required value="{{ old('email') }}" placeholder="you@example.com"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none">
                    </div>
                    @if($emailPasswordEnabled)
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Password</label>
                        @include('common.partials.password-field', [
                            'name' => 'password',
                            'placeholder' => 'At least 8 characters',
                            'autocomplete' => 'new-password',
                            'required' => true,
                            'inputClass' => 'w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none',
                        ])
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Confirm password</label>
                        @include('common.partials.password-field', [
                            'name' => 'password_confirmation',
                            'placeholder' => 'Re-enter your password',
                            'autocomplete' => 'new-password',
                            'required' => true,
                            'inputClass' => 'w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none',
                        ])
                    </div>
                    @endif
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Referral code <span class="text-gray-600 normal-case">(optional)</span></label>
                        <input type="text" name="referral_code" value="{{ old('referral_code', $prefilledRef ?? request()->cookie('ref_code')) }}" maxlength="32" placeholder="friend's code" autocomplete="off"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none">
                        <p class="mt-1 text-[10px] text-gray-500">If a friend referred you, paste their code to give them credit.</p>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-bold text-white">
                        Create account <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </button>
                    <p class="text-center text-xs text-gray-500">By signing up you agree to our
                        <a href="{{ route('site.terms') }}" class="text-blue-400 hover:underline">Terms</a> and
                        <a href="{{ route('site.privacy') }}" class="text-blue-400 hover:underline">Privacy Policy</a>.
                    </p>
                </form>

                {{-- Passwordless WhatsApp sign-up. Distinct from the Login tab's
                     one-time-code form (which is sign IN only): this CREATES an
                     account for an unknown number (intent=signup) and carries the
                     handle claimed on the homepage hero through to it. Only shown
                     when an admin has enabled WhatsApp (mobile) login. --}}
                @if($mobileLoginEnabled)
                <div x-show="reg==='mobile'" x-cloak>
                    <form method="POST" action="{{ route('user.otp.send') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="type" value="mobile">
                        <input type="hidden" name="intent" value="signup">
                        {{-- Carries the homepage-hero claimed handle so it's
                             reserved on the new account, same as the form above. --}}
                        <input type="hidden" name="desired_handle" :value="authHandle">
                        <div x-show="authHandle" x-cloak class="rounded-lg px-3 py-2 text-xs bg-blue-500/10 border border-blue-500/30 text-blue-200 flex items-center gap-2">
                            <i class="fas fa-link text-[11px]"></i>
                            <span>Claiming <strong x-text="'@' + authHandle"></strong> for your page.</span>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">WhatsApp Number</label>
                            <input type="text" name="identifier" required placeholder="+1234567890"
                                   class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-green-500 focus:outline-none">
                            <p class="mt-1.5 text-[10px] text-gray-500">
                                <i class="fab fa-whatsapp mr-0.5"></i> We'll create your account and send a 6-digit code over WhatsApp. Supported country codes: {{ implode(', ', $allowedCountryCodes ?? []) }}.
                            </p>
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-green-600 hover:bg-green-700 rounded-lg text-sm font-bold text-white">
                            <i class="fab fa-whatsapp mr-1"></i> Sign up with WhatsApp
                        </button>
                    </form>
                </div>
                @endif

                {{-- Social sign-up, same providers as the full-page register --}}
                <div class="flex items-center gap-3 my-4">
                    <div class="flex-1 h-px bg-white/10"></div>
                    <span class="text-[10px] uppercase tracking-wider font-bold text-gray-500">or sign up with</span>
                    <div class="flex-1 h-px bg-white/10"></div>
                </div>
                <div class="flex flex-col gap-2">
                    <a href="{{ route('user.social-oauth.login', 'google') }}"
                       class="w-full py-2.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-sm font-semibold text-white flex items-center justify-center gap-2">
                        <i class="fab fa-google text-[13px]"></i> Sign up with Google
                    </a>
                    <a href="{{ route('user.social-oauth.login', 'linkedin') }}"
                       class="w-full py-2.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-sm font-semibold text-white flex items-center justify-center gap-2">
                        <i class="fab fa-linkedin text-[13px]"></i> Sign up with LinkedIn
                    </a>
                </div>
                </div>{{-- /register tab wrapper --}}

                @if($errors->any())
                    <div class="mt-3 rounded-lg px-3 py-2 text-xs bg-red-500/10 border border-red-500/30 text-red-300">
                        @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                    </div>
                @endif

            </div>
        </div>
    </div>
    </div>
</div>
@if($errors->any() || old('name') || old('email') || old('intent') === 'signup')
    {{-- Reopen the modal after a validation round-trip, on the tab the user
         was actually on: a register attempt (name field or WhatsApp signup
         intent) reopens the Sign up tab; anything else reopens Login. --}}
    <script>document.addEventListener('alpine:init',()=>{});window.addEventListener('DOMContentLoaded',()=>{const root=document.querySelector('[x-data*="authOpen"]');if(root&&root._x_dataStack){root._x_dataStack[0].authOpen=true;@if(old('name') !== null || old('intent') === 'signup')root._x_dataStack[0].authTab='register';@endif}});</script>
@endif
