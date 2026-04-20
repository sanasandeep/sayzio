{{-- Two-panel login/register modal. Mounted only on the home page. Forms post to the existing user auth endpoints. --}}
<div x-show="authOpen" x-cloak
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     @keydown.escape.window="authOpen=false"
     role="dialog" aria-modal="true" aria-label="Sign in or create an account">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="authOpen=false"></div>

    <div class="relative w-full max-w-3xl bg-[#1e2330] rounded-2xl shadow-2xl overflow-hidden border border-white/10">
        <button type="button" @click="authOpen=false"
                class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center"
                aria-label="Close">
            <i class="fas fa-times text-sm"></i>
        </button>

        <div class="grid md:grid-cols-2">
            {{-- Left dark panel --}}
            <div class="hidden md:block p-8 bg-gradient-to-br from-[#161b26] to-[#0f1320] text-white">
                <div class="mb-8">@include('common.partials.brand-logo', ['height' => 'h-9'])</div>
                <h3 class="text-xl font-bold mb-6 leading-tight">One link.<br>A whole audience.</h3>
                <ul class="space-y-4 text-sm">
                    <li class="flex items-start gap-3">
                        <span class="w-8 h-8 rounded-lg bg-violet-500/20 flex items-center justify-center text-violet-300"><i class="fas fa-link text-xs"></i></span>
                        <div>
                            <div class="font-semibold">Drag-and-drop biolinks</div>
                            <div class="text-xs text-gray-400">Stack blocks, theme, ship.</div>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-8 h-8 rounded-lg bg-cyan-500/20 flex items-center justify-center text-cyan-300"><i class="fas fa-chart-line text-xs"></i></span>
                        <div>
                            <div class="font-semibold">Live analytics</div>
                            <div class="text-xs text-gray-400">See visitors as they arrive.</div>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-8 h-8 rounded-lg bg-pink-500/20 flex items-center justify-center text-pink-300"><i class="fas fa-bolt text-xs"></i></span>
                        <div>
                            <div class="font-semibold">Performance Coach</div>
                            <div class="text-xs text-gray-400">One-click fixes for what's slowing you down.</div>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-300"><i class="fas fa-qrcode text-xs"></i></span>
                        <div>
                            <div class="font-semibold">Short links &amp; QR codes</div>
                            <div class="text-xs text-gray-400">Branded, dynamic, repointable.</div>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- Right form panel --}}
            <div class="p-6 sm:p-8">
                <div class="flex bg-white/5 rounded-xl p-1 mb-6 text-sm">
                    <button type="button" @click="authTab='login'"
                            :class="authTab==='login' ? 'bg-violet-600 text-white' : 'text-gray-400'"
                            class="flex-1 py-2 rounded-lg font-semibold transition">Login</button>
                    <button type="button" @click="authTab='register'"
                            :class="authTab==='register' ? 'bg-violet-600 text-white' : 'text-gray-400'"
                            class="flex-1 py-2 rounded-lg font-semibold transition">Sign up</button>
                </div>

                {{-- Login form --}}
                <form x-show="authTab==='login'" method="POST" action="{{ route('user.otp.send') }}"
                      x-data="{ otpType:'email' }" class="space-y-3">
                    @csrf
                    <input type="hidden" name="type" :value="otpType">
                    <div class="flex gap-2">
                        <button type="button" @click="otpType='email'" :class="otpType==='email' ? 'border-violet-500 text-violet-300 bg-violet-500/10' : 'border-white/10 text-gray-400'" class="flex-1 py-2 text-xs font-medium rounded-lg border">
                            <i class="fas fa-envelope mr-1"></i> Email
                        </button>
                        <button type="button" @click="otpType='mobile'" :class="otpType==='mobile' ? 'border-violet-500 text-violet-300 bg-violet-500/10' : 'border-white/10 text-gray-400'" class="flex-1 py-2 text-xs font-medium rounded-lg border">
                            <i class="fas fa-mobile-alt mr-1"></i> Mobile
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400" x-text="otpType==='email' ? 'Email' : 'Mobile'"></label>
                        <input type="text" name="identifier" required :placeholder="otpType==='email' ? 'you@example.com' : '+1234567890'"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-violet-500 focus:outline-none">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-bold text-white">
                        <i class="fas fa-paper-plane mr-1 text-xs"></i> Send 6-digit code
                    </button>
                    <p class="text-center text-xs text-gray-500">No password — we'll text or email you a code.</p>
                </form>

                {{-- Register form --}}
                <form x-show="authTab==='register'" x-cloak method="POST" action="{{ route('user.register.submit') }}"
                      class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Full name</label>
                        <input type="text" name="name" required value="{{ old('name') }}" placeholder="Jane Doe"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-violet-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Email</label>
                        <input type="email" name="email" required value="{{ old('email') }}" placeholder="you@example.com"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-violet-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Mobile <span class="text-gray-600 normal-case">(optional)</span></label>
                        <input type="text" name="mobile" value="{{ old('mobile') }}" placeholder="+1234567890"
                               class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:border-violet-500 focus:outline-none">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-bold text-white">
                        Create account <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </button>
                    <p class="text-center text-xs text-gray-500">By signing up you agree to our
                        <a href="{{ route('site.terms') }}" class="text-violet-400 hover:underline">Terms</a> and
                        <a href="{{ route('site.privacy') }}" class="text-violet-400 hover:underline">Privacy Policy</a>.
                    </p>
                </form>

                @if($errors->any())
                    <div class="mt-3 rounded-lg px-3 py-2 text-xs bg-red-500/10 border border-red-500/30 text-red-300">
                        @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@if($errors->any() || old('name') || old('email'))
    <script>document.addEventListener('alpine:init',()=>{});window.addEventListener('DOMContentLoaded',()=>{const root=document.querySelector('[x-data*="authOpen"]');if(root&&root._x_dataStack){root._x_dataStack[0].authOpen=true;}});</script>
@endif
