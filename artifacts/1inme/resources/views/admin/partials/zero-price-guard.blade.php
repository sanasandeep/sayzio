{{--
    Zero-price save guard.

    Intercepts the enclosing <form>'s submit when ALL FOUR price fields
    (USD/INR × monthly/annual) are 0 or blank, and surfaces a confirmation
    modal so an accidental all-zero save can't silently turn a paid plan/addon
    into a free one. Intentional free tiers can still be saved by confirming.

    Pure client-side guard: the four price inputs are read by name from the
    closest form, so this works for both the Plan and Addon editors with no
    server-side changes. blank input → parseInt NaN → treated as zero.

    Optional vars:
      - $entityLabel : noun used in the modal copy (default "plan").
--}}
@php
    $entityLabel = $entityLabel ?? 'plan';
@endphp
<div
    x-data="{
        open: false,
        form: null,
        fields: ['monthly_price', 'annual_price', 'monthly_price_secondary', 'annual_price_secondary'],
        label: @js($entityLabel),
        init() {
            this.form = this.$el.closest('form');
            if (!this.form) return;
            this.form.addEventListener('submit', (e) => {
                if (this.allZero()) {
                    e.preventDefault();
                    this.open = true;
                }
            });
        },
        allZero() {
            return this.fields.every((name) => {
                const el = this.form.querySelector(`[name='${name}']`);
                if (!el) return true;
                const v = parseInt(el.value, 10);
                return isNaN(v) || v === 0;
            });
        },
        proceed() {
            this.open = false;
            /* Native submit() bypasses the submit-event listener above, so this
               saves without re-triggering the guard. */
            this.form.submit();
        },
    }"
    x-cloak
>
    <template x-teleport="body">
        <div x-show="open" x-cloak
             class="fixed inset-0 z-[60] flex items-center justify-center p-4"
             style="display:none">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="relative glass rounded-2xl border border-white/10 max-w-md w-full p-6 shadow-2xl">
                <div class="flex items-start gap-3 mb-4">
                    <div class="shrink-0 w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center">
                        <i class="fas fa-triangle-exclamation text-amber-300 ak-amber"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white ak-strong">All prices are $0 / ₹0</h3>
                        <p class="text-xs text-white/50 mt-0.5 ak-muted">Every currency and billing cycle is set to zero.</p>
                    </div>
                </div>
                <p class="text-sm text-white/70 leading-relaxed mb-5 ak-strong">
                    Saving will make this {{ '' }}<span x-text="label"></span> completely free for
                    <strong class="text-white/90 ak-strong">all users in every currency</strong> (USD &amp; INR, monthly &amp; annual).
                    If you meant to charge for it, go back and enter the prices first.
                </p>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="open = false"
                            class="px-5 py-2 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition text-sm ak-strong">
                        Go back &amp; set prices
                    </button>
                    <button type="button" @click="proceed()"
                            class="px-5 py-2 bg-amber-600 text-white rounded-xl font-medium hover:bg-amber-700 transition text-sm">
                        Save as free
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
