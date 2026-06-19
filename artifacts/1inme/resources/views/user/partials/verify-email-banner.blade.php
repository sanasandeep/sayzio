@auth
    @php
        // Gently nudge signed-in users who skipped email verification at
        // sign-up (Task #1862) to verify it now. Only render when:
        //   - the user's email is still unverified,
        //   - they actually have an email on file, and
        //   - email verification is meaningful under the current login policy
        //     (i.e. email is a usable sign-in method, not a mobile-only setup).
        $__verifyUser = auth()->user();
        $__showVerifyBanner = $__verifyUser
            && empty($__verifyUser->email_verified_at)
            && filled($__verifyUser->email)
            && \App\Modules\Common\Support\AuthMethods::emailVerificationMeaningful();
        $__verifyCodeSent = session('verify_email_code_sent') || $errors->has('verify_email_code');
    @endphp
    @if($__showVerifyBanner)
        <div x-data="{
                dismissed: sessionStorage.getItem('verifyEmailNudgeDismissed') === '1',
                showCode: {{ $__verifyCodeSent ? 'true' : 'false' }},
                dismiss() { this.dismissed = true; sessionStorage.setItem('verifyEmailNudgeDismissed', '1'); }
             }"
             x-show="!dismissed"
             x-cloak
             class="mb-4 p-3.5 rounded-xl text-amber-300 text-xs font-medium"
             style="border: 1px solid rgba(245,158,11,0.25); background: rgba(245,158,11,0.08);">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-envelope-circle-check"></i>
                <span class="flex-1">
                    Verify your email <strong>{{ $__verifyUser->email }}</strong> to keep your account secure and your links deliverable.
                </span>

                <form action="{{ route('user.verification.code.send') }}" method="POST" class="inline-flex" x-show="!showCode">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap"
                            style="border: 1px solid rgba(245,158,11,0.4); background: rgba(245,158,11,0.10); color: #fcd34d;">
                        <i class="fas fa-paper-plane text-[9px]"></i> Send code
                    </button>
                </form>

                <button type="button"
                        @click="dismiss()"
                        class="inline-flex items-center justify-center w-6 h-6 rounded-full text-amber-300/70 hover:text-amber-200 hover:bg-amber-500/10 transition-colors"
                        title="Dismiss"
                        aria-label="Dismiss this reminder">
                    <i class="fas fa-times text-[10px]"></i>
                </button>
            </div>

            <div x-show="showCode" x-cloak class="mt-3 pt-3" style="border-top: 1px solid rgba(245,158,11,0.2);">
                <form action="{{ route('user.verification.code.confirm') }}" method="POST"
                      class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="6" pattern="[0-9]*" placeholder="Enter 6-digit code" required
                           class="px-3 py-1.5 rounded-lg text-xs tracking-[0.3em] text-center w-36 focus:outline-none"
                           style="border: 1px solid rgba(245,158,11,0.3); background: rgba(0,0,0,0.15); color: #fcd34d;">
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold"
                            style="border: 1px solid rgba(16,185,129,0.4); background: rgba(16,185,129,0.12); color: #6ee7b7;">
                        <i class="fas fa-check text-[9px]"></i> Verify
                    </button>
                    <span class="text-[11px] text-amber-300/70">or</span>
                    <button type="submit" form="verify-email-resend-form"
                            class="text-[11px] font-semibold text-amber-200 hover:text-amber-100 underline-offset-2 hover:underline">
                        Resend code
                    </button>
                </form>
                <form id="verify-email-resend-form" action="{{ route('user.verification.code.send') }}" method="POST" class="hidden">
                    @csrf
                </form>
                @error('verify_email_code')
                    <p class="mt-2 text-[11px] text-red-400"><i class="fas fa-exclamation-circle"></i> {{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif
@endauth
