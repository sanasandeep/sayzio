{{--
    Custom Plan Request — multi-step "build your plan" form.
    Included on the pricing page; referenced by home + contact page buttons
    via anchor #custom-plan-request.
--}}
<section id="custom-plan-request" class="py-20 sm:py-28" style="background:linear-gradient(180deg,transparent 0%,rgba(110,97,255,0.05) 40%,rgba(61,107,255,0.04) 100%)">
    <div class="max-w-3xl mx-auto px-4 sm:px-6" data-anim="fade-up">
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border text-xs font-bold uppercase tracking-wider mb-4"
                 style="border-color:rgba(61,107,255,0.35);color:#93c5fd;background:rgba(61,107,255,0.08);">
                <i class="fas fa-gem text-[10px]"></i> Custom plans
            </div>
            <h2 class="text-3xl sm:text-4xl font-bold mb-3 hero-title" style="color:var(--text-main)">
                Need something <span class="grad-text">tailored?</span>
            </h2>
            <p class="text-base leading-relaxed" style="color:var(--text-muted)">
                Tell us about your requirements and we'll design a plan with the exact features, limits, and price that fits your business, with no compromise.
            </p>
        </div>

        <div x-data="{
            step: 1,
            maxStep: 3,
            submitted: false,
            submitting: false,
            error: '',
            form: {
                name: '{{ auth()->user()?->name ?? '' }}',
                email: '{{ auth()->user()?->email ?? '' }}',
                company: '',
                expected_volume: '',
                budget: '',
                preferred_cycle: '',
                requirements: '',
                message: ''
            },
            next() { if (this.step < this.maxStep) this.step++; },
            prev() { if (this.step > 1) this.step--; },
            async submit() {
                this.submitting = true;
                this.error = '';
                try {
                    const data = new FormData();
                    Object.entries(this.form).forEach(([k,v]) => data.append(k, v));
                    data.append('_token', document.querySelector('meta[name=csrf-token]').content);
                    data.append('_redirect_back', window.location.pathname);
                    const r = await fetch('{{ route('custom-plan-request.store') }}', { method: 'POST', body: data, credentials: 'same-origin' });
                    const j = await r.json().catch(() => ({}));
                    if (r.ok && j.ok) {
                        this.submitted = true;
                    } else {
                        this.error = j.message || 'Something went wrong. Please try again.';
                    }
                } catch(e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.submitting = false;
                }
            }
        }" class="relative">

            {{-- Success --}}
            <div x-show="submitted" x-cloak
                 class="rounded-2xl p-10 text-center"
                 style="background:rgba(255,255,255,0.03);border:1px solid rgba(61,107,255,0.25);">
                <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background:rgba(16,185,129,0.15);">
                    <i class="fas fa-check-circle text-2xl text-green-400"></i>
                </div>
                <h3 class="text-xl font-bold mb-2" style="color:var(--text-main)">Request received!</h3>
                <p class="text-sm leading-relaxed" style="color:var(--text-muted)">
                    Thanks, we've logged your requirements. Our team will review them and get back to you within 1–2 business days with a tailored offer.
                </p>
            </div>

            {{-- Step form --}}
            <div x-show="!submitted">

                {{-- Step indicator --}}
                <div class="flex items-center justify-center gap-2 mb-8">
                    @foreach([1 => 'About you', 2 => 'Your needs', 3 => 'Final details'] as $n => $label)
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                                 :style="{{ $n }} <= step ? 'background:rgba(61,107,255,0.25);border:1px solid rgba(61,107,255,0.5);color:#93c5fd;' : 'background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text-faint);'">
                                {{ $n }}
                            </div>
                            <span class="text-xs hidden sm:block transition-all"
                                  :style="{{ $n }} === step ? 'color:var(--text-main);font-weight:600;' : 'color:var(--text-faint);'">{{ $label }}</span>
                        </div>
                        @if($n < 3)
                        <div class="w-8 sm:w-12 h-px" style="background:rgba(255,255,255,0.08)"></div>
                        @endif
                    </div>
                    @endforeach
                </div>

                <div class="rounded-2xl p-6 sm:p-8"
                     style="background:rgba(255,255,255,0.025);border:1px solid rgba(255,255,255,0.08);">

                    {{-- Step 1: About you --}}
                    <div x-show="step === 1" class="space-y-4">
                        <h3 class="text-base font-semibold mb-4" style="color:var(--text-main)">Tell us about yourself</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Full name <span class="text-blue-400">*</span></label>
                                <input type="text" x-model="form.name" required
                                       class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none"
                                       style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);"
                                       placeholder="Jane Smith">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Work email <span class="text-blue-400">*</span></label>
                                <input type="email" x-model="form.email" required
                                       class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none"
                                       style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);"
                                       placeholder="jane@company.com"
                                       @if(auth()->check()) readonly @endif>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Company / organization <span class="text-xs opacity-60">(optional)</span></label>
                            <input type="text" x-model="form.company"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none"
                                   style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);"
                                   placeholder="Acme Inc.">
                        </div>
                    </div>

                    {{-- Step 2: Your needs --}}
                    <div x-show="step === 2" x-cloak class="space-y-4">
                        <h3 class="text-base font-semibold mb-4" style="color:var(--text-main)">What do you need?</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Expected usage volume</label>
                                <select x-model="form.expected_volume"
                                        class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none"
                                        style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);">
                                    <option value="">Select volume…</option>
                                    <option value="Under 1,000 links/month">Under 1,000 links/month</option>
                                    <option value="1,000–10,000 links/month">1,000–10,000 links/month</option>
                                    <option value="10,000–100,000 links/month">10,000–100,000 links/month</option>
                                    <option value="100,000+ links/month">100,000+ links/month</option>
                                    <option value="Enterprise / unlimited">Enterprise / unlimited</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Preferred billing cycle</label>
                                <select x-model="form.preferred_cycle"
                                        class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none"
                                        style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);">
                                    <option value="">No preference</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="annual">Annual (best value)</option>
                                    <option value="either">Either works</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Monthly budget range</label>
                            <select x-model="form.budget"
                                    class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none"
                                    style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);">
                                <option value="">I'd rather discuss</option>
                                <option value="$50–$100/month">$50–$100/month</option>
                                <option value="$100–$250/month">$100–$250/month</option>
                                <option value="$250–$500/month">$250–$500/month</option>
                                <option value="$500–$1,000/month">$500–$1,000/month</option>
                                <option value="$1,000+/month">$1,000+/month</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Specific features or requirements needed</label>
                            <textarea x-model="form.requirements" rows="3"
                                      class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none resize-none"
                                      style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);"
                                      placeholder="e.g. Need 500+ custom domains, advanced API access, white-label branding…"></textarea>
                        </div>
                    </div>

                    {{-- Step 3: Final message --}}
                    <div x-show="step === 3" x-cloak class="space-y-4">
                        <h3 class="text-base font-semibold mb-4" style="color:var(--text-main)">Anything else to add?</h3>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color:var(--text-muted)">Additional message <span class="text-xs opacity-60">(optional)</span></label>
                            <textarea x-model="form.message" rows="5"
                                      class="w-full px-4 py-2.5 rounded-xl text-sm transition focus:outline-none resize-none"
                                      style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-main);"
                                      placeholder="Tell us anything else about your use case, timeline, or special requirements…"></textarea>
                        </div>

                        {{-- Error --}}
                        <p x-show="error" x-cloak x-text="error" class="text-xs text-red-400"></p>

                        {{-- Review summary --}}
                        <div class="rounded-xl p-4 text-xs space-y-1.5" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);">
                            <div class="font-semibold text-[11px] uppercase tracking-wider mb-2" style="color:var(--text-faint)">Your request summary</div>
                            <div style="color:var(--text-muted)"><span class="font-medium" style="color:var(--text-main)" x-text="form.name || '—'"></span> · <span x-text="form.email || '—'"></span></div>
                            <div x-show="form.company" x-text="form.company" style="color:var(--text-muted)"></div>
                            <div x-show="form.budget" style="color:var(--text-muted)">Budget: <span x-text="form.budget"></span></div>
                            <div x-show="form.preferred_cycle" style="color:var(--text-muted)">Cycle: <span x-text="form.preferred_cycle" class="capitalize"></span></div>
                        </div>
                    </div>

                    {{-- Navigation --}}
                    <div class="flex items-center justify-between mt-6 pt-4" style="border-top:1px solid rgba(255,255,255,0.06)">
                        <button type="button" x-show="step > 1" @click="prev()"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition"
                                style="border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:var(--text-muted);">
                            <i class="fas fa-arrow-left text-[10px]"></i> Back
                        </button>
                        <div x-show="step === 1"></div>

                        <div class="flex items-center gap-3 ml-auto">
                            <button type="button" x-show="step < maxStep" @click="next()"
                                    :disabled="(step === 1 && (!form.name.trim() || !form.email.trim()))"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition disabled:opacity-40"
                                    style="background:rgba(61,107,255,0.25);border:1px solid rgba(61,107,255,0.45);color:#93c5fd;">
                                Continue <i class="fas fa-arrow-right text-[10px]"></i>
                            </button>
                            <button type="button" x-show="step === maxStep" @click="submit()"
                                    :disabled="submitting"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition disabled:opacity-60"
                                    style="background:linear-gradient(135deg,#6e61ff 0%,#3d6bff 100%);color:#fff;box-shadow:0 8px 24px -8px rgba(110,97,255,0.5);">
                                <span x-show="!submitting"><i class="fas fa-paper-plane mr-1 text-[10px]"></i>Send Request</span>
                                <span x-show="submitting" x-cloak><i class="fas fa-circle-notch fa-spin mr-1 text-[10px]"></i>Sending…</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Honeypot --}}
                <input type="text" name="website" value="" aria-hidden="true" tabindex="-1" style="position:absolute;opacity:0;pointer-events:none;">
            </div>
        </div>

        <p class="mt-6 text-center text-xs" style="color:var(--text-faint)">
            <i class="fas fa-lock text-[10px] mr-1"></i>
            No commitment. We'll email your personalized offer within 1–2 business days.
        </p>
    </div>
</section>
