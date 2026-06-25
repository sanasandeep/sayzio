@php
    use App\Modules\Common\Support\PlanFormCatalogue;
    use App\Modules\Common\Support\PremiumFeatures;
    use App\Services\UploadPolicy;

    $isEdit   = isset($plan) && $plan && $plan->exists;
    // Prefer old('features') so validation errors don't wipe the user's edits;
    // fall back to the persisted features (edit) or an empty array (create).
    $features = (array) old('features', $isEdit ? ($plan->features ?? []) : []);

    // Helper: read a feature value with a fallback (used both for old() and existing plan data).
    $val = function (string $key, $default = null) use ($features) {
        return array_key_exists($key, $features) ? $features[$key] : $default;
    };

    // Helper: read a feature value that MUST be a scalar for a number/text input.
    // Some quantity keys (notably `max_aliases_per_link`) are persisted as a
    // structured map once per-link-type overrides are set
    // (`['default' => 5, 'short' => 10, ...]`). Echoing that array into a
    // `value="..."` attribute fatals htmlspecialchars(), so collapse any map
    // to its `default` element (the global value shown in the number box) and
    // fall back to the field default for any other unexpected array.
    $scalarVal = function (string $key, $default = null) use ($val) {
        $v = $val($key, $default);
        if (is_array($v)) {
            $v = array_key_exists('default', $v) ? $v['default'] : $default;
        }
        // Never let a non-scalar (e.g. a malformed nested map) reach an
        // attribute echo — fall back to the field default in that case.
        return (is_scalar($v) || $v === null) ? $v : $default;
    };

    // Modules default to ON for any plan that doesn't explicitly set them
    // (preserves prior behavior — every existing plan had every module on).
    $moduleOn = function (string $key) use ($features) {
        return !array_key_exists($key, $features) ? true : (bool) $features[$key];
    };

    $modules        = PlanFormCatalogue::modules();
    $quantities     = PlanFormCatalogue::quantityLimits();
    $featureFlags   = PlanFormCatalogue::featureFlags();
    $aiSuite        = PlanFormCatalogue::aiSuite();
    $blocksByCat    = PlanFormCatalogue::blockTypesByCategory();
    $integrations   = PlanFormCatalogue::integrationMatrix();
    $sectionNav     = PlanFormCatalogue::sectionNav();

    // Block allowlist: '*' means all, otherwise array of slugs.
    // Prefer old() top-level inputs (block_mode + block_types_allowed) so
    // a failed validation round-trip restores exactly what the admin had picked.
    $blockAllowedRaw = $val('block_types_allowed', '*');
    $blockMode       = old('block_mode', ($blockAllowedRaw === '*' || $blockAllowedRaw === null) ? 'all' : 'pick');
    $blockSelected   = (array) old('block_types_allowed', is_array($blockAllowedRaw) ? $blockAllowedRaw : []);

    // Integration provider allowlist: stored per-kind as '*' or array.
    // Same treatment — old('integration_providers_allowed') / old('provider_mode')
    // win over the persisted features so a failed POST repopulates correctly.
    $intAllowed = (array) old('integration_providers_allowed', (array) $val('integration_providers_allowed', []));
    $intCaps    = (array) $val('integration_accounts_max', []);
    $oldProviderMode = (array) old('provider_mode', []);

    // Build Alpine init state for module toggles.
    $moduleAlpine = [];
    foreach ($modules as $mk => $_) {
        $moduleAlpine[$mk] = $moduleOn($mk);
    }
    // Block-mode + per-kind provider modes ('all' | 'pick'). Old wins over derived state.
    $providerModeAlpine = [];
    foreach (array_keys($integrations) as $kind) {
        $derived = (($intAllowed[$kind] ?? '*') === '*') ? 'all' : 'pick';
        $providerModeAlpine[$kind] = $oldProviderMode[$kind] ?? $derived;
    }

    $alpineState = [
        'modules'      => (object) $moduleAlpine,
        'blockMode'    => $blockMode,
        'providerMode' => (object) $providerModeAlpine,
        // Lifted into the root scope so the Storage section's converter updates
        // live as admins edit the canonical inputs in Quantity limits.
        'storageMb'    => (int) $val('storage_limit_mb', 100),
        'fileMb'       => (int) $val('max_file_size_mb', 5),
    ];
@endphp

<div x-data='@json($alpineState)' class="grid grid-cols-12 gap-6">
    {{-- Sticky jump nav --}}
    <aside class="hidden lg:block lg:col-span-3 xl:col-span-2">
        <nav class="sticky top-20 glass rounded-xl border border-white/10 p-3 text-sm">
            <p class="text-[10px] uppercase tracking-wider text-white/40 px-2 mb-2">On this page</p>
            <ul class="space-y-0.5">
                @foreach($sectionNav as $id => $label)
                    <li>
                        <a href="#{{ $id }}" class="block px-2 py-1.5 rounded text-white/60 hover:text-white hover:bg-white/5 transition">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </aside>

    <div class="col-span-12 lg:col-span-9 xl:col-span-10 space-y-6 pb-24">

        {{-- ============================== BASICS ============================== --}}
        <section id="sec-basics" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Basics</h2>
                <p class="text-xs text-white/40">Plan name, description, status, sort order and the homepage "Most Popular" flag.</p>
            </header>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Plan Name</label>
                    <input type="text" name="name" value="{{ old('name', $isEdit ? $plan->name : '') }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">{{ old('description', $isEdit ? $plan->description : '') }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                            @php $st = old('status', $isEdit ? $plan->status : 'active'); @endphp
                            <option value="active"   {{ $st === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $st === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $isEdit ? $plan->sort_order : 0) }}" min="0"
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm text-white/80 p-2 rounded hover:bg-white/5">
                        <input type="hidden" name="is_popular" value="0">
                        <input type="checkbox" name="is_popular" value="1"
                               {{ old('is_popular', $isEdit ? $plan->is_popular : false) ? 'checked' : '' }}
                               class="rounded border-white/10 text-violet-400">
                        Show as Most Popular on homepage
                    </label>
                    <p class="text-[11px] text-white/40 mt-1 ml-2">Highlights this plan as the second card in the landing-page pricing block. Saving will clear the flag on any other plan.</p>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm text-white/80 p-2 rounded hover:bg-white/5">
                        <input type="hidden" name="is_internal" value="0">
                        <input type="checkbox" name="is_internal" value="1"
                               {{ old('is_internal', $isEdit ? $plan->is_internal : false) ? 'checked' : '' }}
                               class="rounded border-white/10 text-violet-400">
                        Internal plan (admin/staff only)
                    </label>
                    <p class="text-[11px] text-white/40 mt-1 ml-2">Hides this plan from the public pricing page, the in-app upgrade page and the smart-upgrade recommender. It stays assignable to users by admins/staff — use it for private/comp plans that should never appear in self-serve checkout.</p>
                </div>
            </div>
        </section>

        {{-- ============================== PRICING ============================== --}}
        <section id="sec-pricing" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Pricing per country</h2>
                <p class="text-xs text-white/40">USD is shown to everyone by default. INR is shown to users whose billing country is India. Enter amounts as integer minor units (e.g. <code>999</code> = $9.99).</p>
            </header>
            <div class="grid grid-cols-2 gap-6">
                @php
                    $mp = old('monthly_price',           $isEdit ? (int) round(((float) $plan->monthly_price) * 100) : 0);
                    $ap = old('annual_price',            $isEdit ? (int) round(((float) $plan->annual_price) * 100) : 0);
                    $ms = old('monthly_price_secondary', $isEdit ? (int) round(((float) $plan->monthly_price_secondary) * 100) : 0);
                    $as = old('annual_price_secondary',  $isEdit ? (int) round(((float) $plan->annual_price_secondary) * 100) : 0);
                @endphp
                <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                    <div class="text-xs uppercase tracking-wider text-white/50 mb-3">USD <span class="text-white/30 normal-case">— shown to everyone outside India</span></div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Monthly (USD, cents)</label>
                            <input type="number" name="monthly_price" value="{{ $mp }}" step="1" min="0" required
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Annual (USD, cents)</label>
                            <input type="number" name="annual_price" value="{{ $ap }}" step="1" min="0" required
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                    <div class="text-xs uppercase tracking-wider text-white/50 mb-3">INR <span class="text-white/30 normal-case">— shown to users in India</span></div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Monthly (INR, paise)</label>
                            <input type="number" name="monthly_price_secondary" value="{{ $ms }}" step="1" min="0" required
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Annual (INR, paise)</label>
                            <input type="number" name="annual_price_secondary" value="{{ $as }}" step="1" min="0" required
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============================== INTRO DISCOUNT ============================== --}}
        @php
            $introCfg = old('intro_discount', $isEdit ? ($plan->introDiscount() ?? []) : []);
            $introEnabled = (bool) ($introCfg['enabled'] ?? false);
            $introType = ($introCfg['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
            $introPercent = (int) ($introCfg['percent'] ?? 0);
            $introFixedUsd = (int) ($introCfg['fixed']['USD'] ?? 0);
            $introFixedInr = (int) ($introCfg['fixed']['INR'] ?? 0);
            $introCycles = (array) ($introCfg['cycles'] ?? ['monthly', 'annual']);
            $introLabel = (string) ($introCfg['label'] ?? '');
        @endphp
        <section id="sec-intro" class="glass rounded-2xl border border-white/10 p-6"
                 x-data="{
                     enabled: {{ $introEnabled ? 'true' : 'false' }},
                     type: '{{ $introType }}',
                     percent: {{ $introPercent }},
                     fixedUsd: {{ $introFixedUsd }},
                     fixedInr: {{ $introFixedInr }},
                     cycles: [{{ implode(',', array_map(fn ($c) => "'" . $c . "'", array_values($introCycles))) }}],
                     base: { USD: { monthly: {{ (int) $mp }}, annual: {{ (int) $ap }} }, INR: { monthly: {{ (int) $ms }}, annual: {{ (int) $as }} } },
                     sym(c){ return c === 'USD' ? '$' : '₹'; },
                     fmt(c, minor){ return this.sym(c) + (Math.max(0, Math.round(minor)) / 100).toFixed(2); },
                     off(c){ return c === 'USD' ? (parseInt(this.fixedUsd) || 0) : (parseInt(this.fixedInr) || 0); },
                     discounted(c, cyc){
                         const b = this.base[c][cyc] || 0;
                         if (this.type === 'percent') { const p = Math.min(100, Math.max(0, parseInt(this.percent) || 0)); return Math.round(b * (1 - p / 100)); }
                         return Math.max(0, b - this.off(c));
                     },
                     pctOff(c, cyc){ const b = this.base[c][cyc] || 0; if (b <= 0) return 0; return Math.round((b - this.discounted(c, cyc)) / b * 100); },
                     appliesTo(cyc){ return !this.cycles || this.cycles.length === 0 || this.cycles.includes(cyc); },
                     hasAny(c){ return (this.appliesTo('monthly') && this.base[c].monthly > 0) || (this.appliesTo('annual') && this.base[c].annual > 0); },
                     syncBase(){
                         const f = this.$el.closest('form'); if (!f) return;
                         const v = (n) => { const el = f.querySelector('[name=\'' + n + '\']'); return el ? (parseInt(el.value || '0', 10) || 0) : 0; };
                         this.base = { USD: { monthly: v('monthly_price'), annual: v('annual_price') }, INR: { monthly: v('monthly_price_secondary'), annual: v('annual_price_secondary') } };
                     }
                 }"
                 x-init="syncBase(); $el.closest('form')?.addEventListener('input', () => syncBase())">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">First-term intro discount</h2>
                <p class="text-xs text-white/40">A one-time discount applied to the <strong>first billing term only</strong> of a brand-new subscription. Renewals and upgrades always charge the normal price. This is the only automatic discount — it never stacks with promo codes. The <strong>normal price shows struck through</strong> and the discounted first-term price becomes the headline buyers see.</p>
            </header>

            {{-- Off switch (always submits a value so unchecking persists) --}}
            <input type="hidden" name="intro_discount[enabled]" value="0">
            <label class="flex items-center gap-3 mb-4 cursor-pointer">
                <input type="checkbox" name="intro_discount[enabled]" value="1" x-model="enabled"
                       class="w-4 h-4 rounded bg-white/5 border-white/20 text-violet-500 focus:ring-violet-500/40">
                <span class="text-sm text-white/80">Enable an introductory discount for this plan</span>
            </label>

            <div x-show="enabled" x-cloak class="space-y-4">
                {{-- Discount type --}}
                <div>
                    <label class="block text-xs text-white/40 mb-1.5">Discount type</label>
                    <div class="flex gap-2">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="intro_discount[type]" value="percent" x-model="type" class="peer sr-only">
                            <div class="px-3 py-2 rounded-xl border border-white/10 bg-white/5 text-sm text-white/60 text-center peer-checked:border-violet-500/60 peer-checked:text-white peer-checked:bg-violet-500/10">Percentage off</div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="intro_discount[type]" value="fixed" x-model="type" class="peer sr-only">
                            <div class="px-3 py-2 rounded-xl border border-white/10 bg-white/5 text-sm text-white/60 text-center peer-checked:border-violet-500/60 peer-checked:text-white peer-checked:bg-violet-500/10">Fixed amount off</div>
                        </label>
                    </div>
                </div>

                {{-- Percentage --}}
                <div x-show="type === 'percent'" x-cloak>
                    <label class="block text-xs text-white/40 mb-1">Percent off (1–100)</label>
                    <input type="number" name="intro_discount[percent]" value="{{ $introPercent }}" min="0" max="100" step="1" x-model="percent"
                           class="w-40 px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>

                {{-- Fixed amounts (minor units, mirroring the price inputs) --}}
                <div x-show="type === 'fixed'" x-cloak class="grid grid-cols-2 gap-6">
                    <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                        <div class="text-xs uppercase tracking-wider text-white/50 mb-3">USD off</div>
                        <label class="block text-xs text-white/40 mb-1">Amount (USD, cents)</label>
                        <input type="number" name="intro_discount[fixed][USD]" value="{{ $introFixedUsd }}" min="0" step="1" x-model="fixedUsd"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                        <div class="text-xs uppercase tracking-wider text-white/50 mb-3">INR off</div>
                        <label class="block text-xs text-white/40 mb-1">Amount (INR, paise)</label>
                        <input type="number" name="intro_discount[fixed][INR]" value="{{ $introFixedInr }}" min="0" step="1" x-model="fixedInr"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>

                {{-- Applicable billing cycles --}}
                <div>
                    <label class="block text-xs text-white/40 mb-1.5">Applies to billing cycles</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="intro_discount[cycles][]" value="monthly" x-model="cycles" {{ in_array('monthly', $introCycles, true) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded bg-white/5 border-white/20 text-violet-500 focus:ring-violet-500/40">
                            <span class="text-sm text-white/70">Monthly</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="intro_discount[cycles][]" value="annual" x-model="cycles" {{ in_array('annual', $introCycles, true) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded bg-white/5 border-white/20 text-violet-500 focus:ring-violet-500/40">
                            <span class="text-sm text-white/70">Annual</span>
                        </label>
                    </div>
                    <p class="text-[11px] text-white/30 mt-1">Leave both unchecked to apply to every cycle.</p>
                </div>

                {{-- Optional marketing label --}}
                <div>
                    <label class="block text-xs text-white/40 mb-1">Badge label <span class="text-white/30">(optional, max 120 chars)</span></label>
                    <input type="text" name="intro_discount[label]" value="{{ $introLabel }}" maxlength="120" placeholder="e.g. Launch offer — 50% off your first month"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>

                {{-- Live preview: resulting normal vs. discounted first-term price.
                     Reads the base price inputs above (kept in sync on every form
                     edit) and the discount settings, so admins see exactly what the
                     pricing page will show without saving + reloading. --}}
                <div class="rounded-xl border border-violet-400/20 bg-violet-500/[0.06] p-4">
                    <div class="text-xs font-semibold text-white/80 mb-1 flex items-center gap-1.5">
                        <i class="fas fa-eye text-violet-300"></i> Live preview — normal vs. discounted first term
                    </div>
                    <p class="text-[11px] text-white/40 mb-3">Buyers see the struck-through normal price with the discounted first-term price as the headline. Renewals always charge the normal price.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="c in ['USD','INR']" :key="c">
                            <div class="rounded-lg border border-white/10 bg-white/[0.02] p-3">
                                <div class="text-[10px] uppercase tracking-wider text-white/40 mb-2" x-text="c"></div>
                                <template x-for="cyc in ['monthly','annual']" :key="cyc">
                                    <div x-show="appliesTo(cyc) && base[c][cyc] > 0" class="flex items-center justify-between gap-2 py-1">
                                        <span class="text-white/50 capitalize text-xs" x-text="cyc"></span>
                                        <span class="flex items-baseline gap-2 text-sm">
                                            <span class="text-white/40 line-through" x-text="fmt(c, base[c][cyc])"></span>
                                            <span class="font-semibold text-emerald-300" x-text="fmt(c, discounted(c, cyc))"></span>
                                            <span class="text-[10px] font-bold text-emerald-400/80" x-text="'-' + pctOff(c, cyc) + '%'"></span>
                                        </span>
                                    </div>
                                </template>
                                <div x-show="!hasAny(c)" class="text-[11px] text-white/30">Enter a base price to preview.</div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============================== TRIAL & RETENTION ============================== --}}
        <section id="sec-trial" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Trial &amp; retention</h2>
                <p class="text-xs text-white/40">Free trial length, the grace window after a failed payment, and the self-serve refund window.</p>
            </header>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Trial Days</label>
                    <input type="number" name="trial_days" value="{{ old('trial_days', $isEdit ? $plan->trial_days : 0) }}" min="0" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    <p class="text-[11px] text-white/40 mt-1">Free trial length for new subscribers.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Grace Days</label>
                    <input type="number" name="grace_days" value="{{ old('grace_days', $isEdit ? ($plan->grace_days ?? 7) : 7) }}" min="0" max="365" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    <p class="text-[11px] text-white/40 mt-1">Days features remain active after a failed renewal before auto-downgrade.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Refund Window (days)</label>
                    <input type="number" name="refund_window_days" value="{{ old('refund_window_days', $isEdit ? ($plan->refund_window_days ?? 7) : 7) }}" min="0" max="365" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    <p class="text-[11px] text-white/40 mt-1">Self-serve refund eligibility window after payment.</p>
                </div>
            </div>
        </section>

        {{-- ============================== REFERRAL PROGRAM ============================== --}}
        <section id="sec-referral" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Referral program</h2>
                <p class="text-xs text-white/40">Bonus days awarded by ReferralService when this plan is the sign-up target or activates a referral.</p>
            </header>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach(\App\Modules\Common\Support\PlanFormCatalogue::referralFields() as $r)
                    <div>
                        <label class="block text-xs text-white/60 mb-1">{{ $r['label'] }}</label>
                        <input type="number" name="features[{{ $r['key'] }}]" value="{{ (int) $val($r['key'], 0) }}" min="0"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <p class="text-[10px] text-white/40 mt-1">{{ $r['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============================== MODULES ============================== --}}
        <section id="sec-modules" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Modules</h2>
                <p class="text-xs text-white/40">High-level on/off switches for whole product areas. When a module is off, every limit and sub-toggle below it is dimmed (the values are still saved for round-tripping, but runtime checks treat the module as disabled).</p>
            </header>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($modules as $mk => $m)
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-white/10 bg-white/[0.02] hover:bg-white/[0.04] cursor-pointer">
                        <input type="hidden" name="features[{{ $mk }}]" value="0">
                        <input type="checkbox" name="features[{{ $mk }}]" value="1"
                               x-model="modules['{{ $mk }}']"
                               {{ $moduleOn($mk) ? 'checked' : '' }}
                               class="mt-1 rounded border-white/10 text-violet-400">
                        <span class="flex-1">
                            <span class="block text-sm font-medium text-white/90">{{ $m['label'] }}</span>
                            <span class="block text-[11px] text-white/40 leading-snug">{{ $m['desc'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- ============================== QUANTITY LIMITS ============================== --}}
        <section id="sec-quantities" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Quantity limits</h2>
                <p class="text-xs text-white/40">Every numeric limit in one place. Use the <span class="font-mono">∞</span> button (or type <code>-1</code>) to set "unlimited".</p>
            </header>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($quantities as $q)
                    @php
                        $module = $q['module'];
                        $current = $scalarVal($q['key'], $q['default']);
                        $minAttr = $q['min'] ?? -1;
                        // These two share state with the Storage section's live converter
                        // via the root x-data scope (storageMb / fileMb).
                        $rootModel = match ($q['key']) {
                            'storage_limit_mb' => 'storageMb',
                            'max_file_size_mb' => 'fileMb',
                            default            => null,
                        };
                    @endphp
                    <div @if($module) x-bind:class="modules['{{ $module }}'] ? '' : 'opacity-40'" @endif>
                        <label class="block text-xs text-white/60 mb-1">{{ $q['label'] }}</label>
                        <div class="flex items-stretch gap-1">
                            <input type="number" name="features[{{ $q['key'] }}]" value="{{ $current }}" min="{{ $minAttr }}" @isset($q['max']) max="{{ $q['max'] }}" @endisset
                                   x-ref="qty_{{ $q['key'] }}"
                                   @if($rootModel) x-model.number="{{ $rootModel }}" @endif
                                   class="flex-1 min-w-0 px-3 py-2 bg-white/5 border border-white/10 rounded-l-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <button type="button"
                                    @click="$refs.qty_{{ $q['key'] }}.value = '-1'@if($rootModel); {{ $rootModel }} = -1 @endif"
                                    title="Set to unlimited (-1)"
                                    class="px-3 bg-white/10 border border-white/10 border-l-0 rounded-r-xl text-white/70 hover:text-white hover:bg-white/15 text-sm font-bold">∞</button>
                        </div>
                        <p class="text-[10px] text-white/40 mt-1">
                            {{ $q['hint'] }} <span class="text-white/30">·</span> <code class="text-white/50">-1</code> = unlimited
                            @if($q['key'] === 'storage_limit_mb')
                                <br>
                                <span class="text-violet-300/80">
                                    <span x-show="storageMb === -1" x-cloak>≈ Unlimited GB</span>
                                    <span x-show="storageMb > 0"   x-cloak>≈ <span x-text="(storageMb / 1024).toFixed(2)"></span> GB</span>
                                    <span x-show="storageMb === 0" x-cloak>0 GB</span>
                                </span>
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Per-link-type alias overrides. The "Extra aliases per link"
                 field above is the global fallback; any type left blank here
                 inherits it. A type set here overrides the global for links of
                 that type only. -1 = unlimited, 0 = none. --}}
            @php
                $aliasTypes = PlanFormCatalogue::aliasLinkTypes();
                $aliasRaw   = $val('max_aliases_per_link', 0);
                $aliasByType = is_array($aliasRaw) ? $aliasRaw : [];
            @endphp
            <div class="mt-6 pt-5 border-t border-white/10" x-bind:class="modules['module_short_links'] ? '' : 'opacity-40'"
                 x-data="{ open: {{ count(array_diff(array_keys($aliasByType), ['default'])) ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open"
                        class="flex items-center gap-2 text-sm font-medium text-white/80 hover:text-white">
                    <i class="fas" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                    Per-link-type alias overrides
                    <span class="text-[10px] text-white/40 font-normal">— optional; blank inherits the global "Extra aliases per link"</span>
                </button>
                <div x-show="open" x-cloak class="mt-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($aliasTypes as $slug => $label)
                        @php $tv = $aliasByType[$slug] ?? null; @endphp
                        <div>
                            <label class="block text-[11px] text-white/60 mb-1">{{ $label }}</label>
                            <div class="flex items-stretch gap-1">
                                <input type="number" name="features[max_aliases_per_link_by_type][{{ $slug }}]"
                                       value="{{ is_numeric($tv) ? $tv : '' }}" min="-1" placeholder="inherit"
                                       x-ref="alias_{{ $slug }}"
                                       class="flex-1 min-w-0 px-2.5 py-1.5 bg-white/5 border border-white/10 rounded-l-lg text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                                <button type="button" @click="$refs.alias_{{ $slug }}.value = '-1'" title="Set to unlimited (-1)"
                                        class="px-2.5 bg-white/10 border border-white/10 border-l-0 rounded-r-lg text-white/70 hover:text-white hover:bg-white/15 text-sm font-bold">∞</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ============================== TEAM MANAGEMENT ============================== --}}
        <section id="sec-team" class="glass rounded-2xl border border-white/10 p-6"
                 x-bind:class="modules['module_teams'] ? '' : 'opacity-50'">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Team management</h2>
                <p class="text-xs text-white/40">Multi-seat workspaces and per-workspace seat caps. Owner is always counted as a seat.</p>
            </header>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="flex items-center gap-2 text-sm text-white/80 p-2 rounded hover:bg-white/5">
                        <input type="hidden" name="features[teams]" value="0">
                        <input type="checkbox" name="features[teams]" value="1"
                               {{ $val('teams', false) ? 'checked' : '' }}
                               class="rounded border-white/10 text-violet-400">
                        Teams enabled
                    </label>
                    <p class="text-[11px] text-white/40 mt-1 ml-2">Lets the user invite teammates into a workspace.</p>
                </div>
                @foreach([['key' => 'max_workspaces', 'label' => 'Max workspaces', 'default' => 1],
                         ['key' => 'max_seats_per_workspace', 'label' => 'Max seats per workspace', 'default' => 1]] as $row)
                    <div>
                        <label class="block text-xs text-white/60 mb-1">{{ $row['label'] }}</label>
                        <div class="flex items-stretch gap-1">
                            <input type="number" name="features[{{ $row['key'] }}]" value="{{ $scalarVal($row['key'], $row['default']) }}" min="-1"
                                   x-ref="qty_{{ $row['key'] }}"
                                   class="flex-1 min-w-0 px-3 py-2 bg-white/5 border border-white/10 rounded-l-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <button type="button" @click="$refs.qty_{{ $row['key'] }}.value = '-1'" title="Set to unlimited (-1)"
                                    class="px-3 bg-white/10 border border-white/10 border-l-0 rounded-r-xl text-white/70 hover:text-white hover:bg-white/15 text-sm font-bold">∞</button>
                        </div>
                        <p class="text-[10px] text-white/40 mt-1"><code class="text-white/50">-1</code> = unlimited</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============================== STORAGE ============================== --}}
        <section id="sec-storage" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Total user storage</h2>
                <p class="text-xs text-white/40">Live converter for the storage cap defined in <a href="#sec-quantities" class="text-violet-300 hover:underline">Quantity limits</a>. The per-upload size cap also lives there.</p>
            </header>
            {{-- Read-only mirror that reads `storageMb` / `fileMb` from the root
                 x-data scope — they are bound to the canonical inputs in the
                 Quantity limits section, so this converter updates live as the
                 admin edits the value (no separate state, no drift). --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-white/60 mb-1">Total storage (MB → GB)</label>
                    <input type="number" :value="storageMb" disabled
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white/70 text-sm cursor-not-allowed">
                    <p class="text-[10px] text-white/40 mt-1">
                        <span x-show="storageMb === -1" x-cloak>Unlimited storage.</span>
                        <span x-show="storageMb > 0"   x-cloak>≈ <span x-text="(storageMb / 1024).toFixed(2)"></span> GB</span>
                        <span x-show="storageMb === 0" x-cloak>No storage allowed.</span>
                        <span class="text-white/30">·</span> Edit in <a href="#sec-quantities" class="text-violet-300 hover:underline">Quantity limits</a>.
                    </p>
                </div>
                <div>
                    <label class="block text-xs text-white/60 mb-1">Per-upload size cap (MB)</label>
                    <input type="number" :value="fileMb" disabled
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white/70 text-sm cursor-not-allowed">
                    <p class="text-[10px] text-white/40 mt-1">
                        <span x-show="fileMb === -1" x-cloak>Unlimited per-file size.</span>
                        <span x-show="fileMb > 0"   x-cloak>Largest single upload.</span>
                        <span class="text-white/30">·</span> Edit in <a href="#sec-quantities" class="text-violet-300 hover:underline">Quantity limits</a>.
                    </p>
                </div>
            </div>
            @php
                // Render on both Create and Edit so the two forms mirror each other.
                // For a brand-new plan UploadPolicy::contextsForPlan() returns each
                // context with its system default, which the admin can override here.
                $uploadRows = UploadPolicy::contextsForPlan($features);
                $uploadGroups = collect($uploadRows)->groupBy('group');
            @endphp
            @if($uploadGroups->isNotEmpty())
                    <div class="mt-6 pt-5 border-t border-white/10">
                        <div class="flex items-center gap-2 mb-2">
                            <h3 class="text-sm font-medium text-white/80">Upload limits per location</h3>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300 border border-violet-500/20">PER PLAN</span>
                        </div>
                        <p class="text-[11px] text-white/40 mb-4">Override the maximum file size and allowed file types for each upload location. Leave blank to use the system default.</p>
                        <div x-data="{ open: {} }" class="space-y-3">
                            @foreach($uploadGroups as $groupName => $rows)
                                <div class="rounded-xl border border-white/10 bg-white/[0.02]">
                                    <button type="button" @click="open['{{ $loop->index }}'] = !open['{{ $loop->index }}']"
                                            class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/[0.04] transition rounded-xl">
                                        <span class="text-xs font-semibold text-white/80">{{ $groupName }} <span class="text-white/30 font-normal ml-1">({{ $rows->count() }})</span></span>
                                        <i class="fas fa-chevron-down text-[10px] text-white/40 transition-transform" :class="open['{{ $loop->index }}'] ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open['{{ $loop->index }}']" x-cloak class="px-4 pb-4 space-y-3">
                                        @foreach($rows as $key => $row)
                                            <div class="grid grid-cols-12 gap-3 items-end pt-3 border-t border-white/5 first:border-0 first:pt-0">
                                                <div class="col-span-12 md:col-span-4">
                                                    <label class="block text-[11px] text-white/60">{{ $row['label'] }}</label>
                                                    <p class="text-[10px] text-white/30 font-mono mt-0.5">{{ $key }}</p>
                                                </div>
                                                <div class="col-span-4 md:col-span-2">
                                                    <label class="block text-[10px] text-white/40 mb-1">Max MB</label>
                                                    <input type="number" min="0" step="1"
                                                           name="features[upload_limits][{{ $key }}][max_mb]"
                                                           value="{{ $row['max_mb'] }}"
                                                           placeholder="{{ $row['default_max_mb'] }}"
                                                           class="w-full px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-white text-xs focus:ring-2 focus:ring-violet-500/40 outline-none">
                                                </div>
                                                <div class="col-span-8 md:col-span-6">
                                                    <label class="block text-[10px] text-white/40 mb-1">Allowed extensions <span class="text-white/30">(default: {{ implode(', ', $row['default_extensions']) ?: 'any' }})</span></label>
                                                    <input type="text"
                                                           name="features[upload_limits][{{ $key }}][extensions]"
                                                           value="{{ implode(',', $row['extensions']) }}"
                                                           placeholder="{{ implode(',', $row['default_extensions']) }}"
                                                           class="w-full px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-white text-xs font-mono focus:ring-2 focus:ring-violet-500/40 outline-none">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
            @endif
        </section>

        {{-- ============================== BIOLINK BLOCKS ============================== --}}
        <section id="sec-blocks" class="glass rounded-2xl border border-white/10 p-6"
                 x-bind:class="modules['module_biolinks'] ? '' : 'opacity-50'">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Link in Bio block allowlist</h2>
                <p class="text-xs text-white/40">Which block types users on this plan can drop into their Link in Bio pages.</p>
            </header>
            <div class="flex items-center gap-6 mb-4">
                <label class="flex items-center gap-2 text-sm text-white/80 cursor-pointer">
                    <input type="radio" name="block_mode" value="all" x-model="blockMode" class="text-violet-400 border-white/10">
                    All blocks (<code class="text-white/50">*</code>)
                </label>
                <label class="flex items-center gap-2 text-sm text-white/80 cursor-pointer">
                    <input type="radio" name="block_mode" value="pick" x-model="blockMode" class="text-violet-400 border-white/10">
                    Only the ones I pick below
                </label>
            </div>

            <div x-bind:class="blockMode === 'all' ? 'opacity-40 pointer-events-none' : ''" class="space-y-4">
                @foreach($blocksByCat as $catKey => $cat)
                    @php
                        $catSlugs = array_keys($cat['types']);
                        $allChecked = count(array_intersect($catSlugs, $blockSelected)) === count($catSlugs);
                    @endphp
                    <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4"
                         x-data="{ all: {{ $allChecked ? 'true' : 'false' }} }">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-white/80">{{ $cat['label'] }} <span class="text-white/30 font-normal">({{ count($catSlugs) }})</span></h3>
                            <label class="flex items-center gap-2 text-[11px] text-white/60 cursor-pointer">
                                <input type="checkbox" x-model="all"
                                       @change="document.querySelectorAll('input[data-block-cat=\'{{ $catKey }}\']').forEach(el => el.checked = all)"
                                       class="rounded border-white/10 text-violet-400">
                                Select all
                            </label>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                            @foreach($cat['types'] as $slug => $meta)
                                <label class="flex items-center gap-2 text-xs text-white/70 p-2 rounded hover:bg-white/5 cursor-pointer">
                                    <input type="checkbox" name="block_types_allowed[]" value="{{ $slug }}"
                                           data-block-cat="{{ $catKey }}"
                                           {{ in_array($slug, $blockSelected, true) ? 'checked' : '' }}
                                           class="rounded border-white/10 text-violet-400">
                                    <i class="fas {{ $meta['icon'] ?? 'fa-cube' }} text-white/40 text-[10px]"></i>
                                    <span>{{ $meta['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============================== FEATURES & ANALYTICS ============================== --}}
        <section id="sec-features" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Features &amp; analytics depth</h2>
                <p class="text-xs text-white/40">Individual capability flags. Helper text for each control comes from the public Premium Features catalogue so the wording stays consistent.</p>
            </header>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($featureFlags as $flag)
                    @php
                        $module = $flag['module'];
                        $copy   = PlanFormCatalogue::copyFor($flag['key']);
                        $label  = $copy['name'] ?? ucwords(str_replace('_', ' ', $flag['key']));
                        $desc   = $copy['description'] ?? '';
                    @endphp
                    <div class="p-3 rounded-xl border border-white/10 bg-white/[0.02]"
                         @if($module) x-bind:class="modules['{{ $module }}'] ? '' : 'opacity-40'" @endif>
                        @if($flag['type'] === 'bool')
                            <label class="flex items-start gap-2 text-sm text-white/80 cursor-pointer">
                                <input type="hidden" name="features[{{ $flag['key'] }}]" value="0">
                                <input type="checkbox" name="features[{{ $flag['key'] }}]" value="1"
                                       {{ $val($flag['key'], false) ? 'checked' : '' }}
                                       class="mt-1 rounded border-white/10 text-violet-400">
                                <span class="flex-1">
                                    <span class="block font-medium">{{ $label }}</span>
                                    @if($desc)<span class="block text-[11px] text-white/40 leading-snug mt-0.5">{{ $desc }}</span>@endif
                                </span>
                            </label>
                        @elseif($flag['type'] === 'select')
                            <label class="block">
                                <span class="block text-sm font-medium text-white/80 mb-1">{{ $label }}</span>
                                <select name="features[{{ $flag['key'] }}]" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                                    @php $cur = $val($flag['key'], $flag['default'] ?? null); @endphp
                                    @foreach($flag['options'] as $opt => $optLabel)
                                        <option value="{{ $opt }}" {{ (string) $cur === (string) $opt ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                @if($desc)<p class="text-[11px] text-white/40 leading-snug mt-1">{{ $desc }}</p>@endif
                            </label>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============================== AI SUITE ============================== --}}
        <section id="sec-ai" class="glass rounded-2xl border border-white/10 p-6"
                 x-bind:class="modules['module_ai_suite'] ? '' : 'opacity-50'">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">AI suite</h2>
                <p class="text-xs text-white/40">Per-capability AI feature gates. Toggling the AI Suite module off above dims the whole section.</p>
            </header>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($aiSuite as $row)
                    @php
                        $copy  = PlanFormCatalogue::copyFor($row['key']);
                        $label = $copy['name'] ?? ucwords(str_replace('_', ' ', $row['key']));
                        $desc  = $copy['description'] ?? '';
                    @endphp
                    <label class="flex items-start gap-2 p-3 rounded-xl border border-white/10 bg-white/[0.02] cursor-pointer hover:bg-white/[0.04]">
                        <input type="hidden" name="features[{{ $row['key'] }}]" value="0">
                        <input type="checkbox" name="features[{{ $row['key'] }}]" value="1"
                               {{ $val($row['key'], false) ? 'checked' : '' }}
                               class="mt-1 rounded border-white/10 text-violet-400">
                        <span class="flex-1">
                            <span class="block text-sm font-medium text-white/90">{{ $label }}</span>
                            @if($desc)<span class="block text-[11px] text-white/40 leading-snug mt-0.5">{{ $desc }}</span>@endif
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- ============================== INTEGRATION ACCOUNTS ============================== --}}
        <section id="sec-integrations" class="glass rounded-2xl border border-white/10 p-6"
                 x-bind:class="modules['module_integrations'] ? '' : 'opacity-50'">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Integration accounts</h2>
                <p class="text-xs text-white/40">Per-kind cap and provider allowlist for connected third-party accounts. Limits the number of connected accounts a user on this plan can keep configured at once.</p>
            </header>
            <div class="space-y-5">
                @foreach($integrations as $kind => $info)
                    @php
                        $cap         = $intCaps[$kind] ?? 1;
                        $allowedRaw  = $intAllowed[$kind] ?? '*';
                        $allowedList = is_array($allowedRaw) ? $allowedRaw : [];
                    @endphp
                    <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-white/90">{{ $info['label'] }}</h3>
                                <p class="text-[11px] text-white/40">{{ $info['subtitle'] }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-white/60 mb-1">Max connected accounts</label>
                                <div class="flex items-stretch gap-1">
                                    <input type="number" name="features[integration_accounts_max][{{ $kind }}]"
                                           value="{{ $cap }}" min="-1"
                                           x-ref="int_cap_{{ $kind }}"
                                           class="flex-1 min-w-0 px-3 py-2 bg-white/5 border border-white/10 rounded-l-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                                    <button type="button" @click="$refs.int_cap_{{ $kind }}.value = '-1'" title="Set to unlimited"
                                            class="px-3 bg-white/10 border border-white/10 border-l-0 rounded-r-xl text-white/70 hover:text-white hover:bg-white/15 text-sm font-bold">∞</button>
                                </div>
                                <p class="text-[10px] text-white/40 mt-1"><code class="text-white/50">-1</code> = unlimited</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-white/60 mb-1">Allowed providers</label>
                                <div class="flex items-center gap-4 mb-2">
                                    <label class="flex items-center gap-2 text-xs text-white/80 cursor-pointer">
                                        <input type="radio" name="provider_mode[{{ $kind }}]" value="all" x-model="providerMode['{{ $kind }}']" class="text-violet-400">
                                        All providers (<code class="text-white/50">*</code>)
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-white/80 cursor-pointer">
                                        <input type="radio" name="provider_mode[{{ $kind }}]" value="pick" x-model="providerMode['{{ $kind }}']" class="text-violet-400">
                                        Only the ones I pick
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2"
                                     x-bind:class="providerMode['{{ $kind }}'] === 'all' ? 'opacity-40 pointer-events-none' : ''">
                                    @foreach($info['providers'] as $p)
                                        <label class="flex items-center gap-2 text-xs text-white/70 p-2 rounded hover:bg-white/5 cursor-pointer">
                                            <input type="checkbox" name="integration_providers_allowed[{{ $kind }}][]" value="{{ $p['slug'] }}"
                                                   {{ in_array($p['slug'], $allowedList, true) ? 'checked' : '' }}
                                                   class="rounded border-white/10 text-violet-400">
                                            {{ $p['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============================== ADDONS ============================== --}}
        <section id="sec-addons" class="glass rounded-2xl border border-white/10 p-6">
            <header class="mb-4">
                <h2 class="text-base font-semibold text-white">Eligible addons</h2>
                <p class="text-xs text-white/40">Pick which addons customers on this plan may purchase. Manage the catalog from <a href="{{ route('admin.addons.index') }}" class="text-violet-400 hover:underline">Addons</a>.</p>
            </header>
            @if(($addons ?? collect())->isEmpty())
                <p class="text-sm text-white/40">No addons in the catalog yet.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($addons as $addon)
                        <label class="flex items-start gap-2 text-sm text-white/70 p-2 rounded hover:bg-white/5 {{ $addon->is_archived ? 'opacity-60' : '' }}">
                            <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}"
                                   {{ in_array($addon->id, old('addon_ids', $attachedAddonIds ?? [])) ? 'checked' : '' }}
                                   class="mt-1 rounded border-white/10 text-violet-400">
                            <span>
                                <span class="block">{{ $addon->name }} @if($addon->is_archived)<span class="text-[10px] text-white/40">(archived)</span>@endif</span>
                                <span class="block text-[11px] text-white/40">${{ number_format($addon->monthly_price, 2) }}/mo · ${{ number_format($addon->annual_price, 2) }}/yr · {{ str_replace('_',' ',$addon->type) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- All-zero pricing save guard (USD/INR × monthly/annual). --}}
        @include('admin.partials.zero-price-guard', ['entityLabel' => 'plan'])

        {{-- ============================== STICKY SAVE FOOTER ============================== --}}
        <div class="sticky bottom-4 z-10">
            <div class="glass rounded-2xl border border-white/10 px-6 py-3 flex items-center justify-between">
                <p class="text-xs text-white/40">{{ $isEdit ? 'Editing existing plan — make sure to keep INR pricing in sync.' : 'New plan — values you leave at defaults are still saved explicitly.' }}</p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.plans.index') }}" class="px-5 py-2 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition text-sm">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition text-sm">{{ $isEdit ? 'Update Plan' : 'Create Plan' }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
