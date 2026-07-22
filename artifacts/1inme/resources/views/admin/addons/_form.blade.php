@php
    $features = $addon->features ?? [];
    $checkedPlanIds = $checkedPlanIds ?? [];
    $featureBooleans = [
        'custom_domains' => 'Grants custom domains',
        'remove_branding' => 'Removes Sayzio branding',
        'custom_branding' => 'Custom branding',
        'priority_support' => 'Priority support',
        'api_access' => 'API access',
        'scheduled_posts' => 'Scheduled posts',
        'social_proof_popup' => 'Social-proof popup',
        'templates_premium' => 'Premium templates',
        'custom_forms' => 'Custom forms',
        'teams' => 'Team workspace',
    ];
    $featureNumeric = [
        'max_biolinks_extra' => 'Extra biolink pages',
        'max_links_extra' => 'Extra short links',
        'max_projects_extra' => 'Extra projects',
        'contacts_max_extra' => 'Extra contacts',
        'team_seats_extra' => 'Extra team seats',
        'custom_domains_extra' => 'Extra custom domains',
        'api_rate_per_min' => 'API rate (req/min)',
    ];
@endphp

<div class="space-y-5">
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Addon Name</label>
        <input type="text" name="name" value="{{ old('name', $addon->name) }}" required
               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
        @error('name')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Description</label>
        <textarea name="description" rows="2"
                  class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">{{ old('description', $addon->description) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Type</label>
        <select name="type" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
            @foreach(\App\Modules\Admin\Models\Addon::TYPES as $t)
                <option value="{{ $t }}" {{ old('type', $addon->type) === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$t)) }}</option>
            @endforeach
        </select>
    </div>

    @if($addon->exists)
        @php
            $currentPrices = [];
            foreach ($addon->prices as $priceRow) {
                $currentPrices[$priceRow->currency . '|' . $priceRow->billing_cycle] = (int) $priceRow->amount_minor_units;
            }
            $formatCurrentPrice = function (string $currency, string $cycle) use ($currentPrices) {
                $key = $currency . '|' . $cycle;
                if (!array_key_exists($key, $currentPrices)) {
                    return '-';
                }
                $symbol = $currency === 'INR' ? '₹' : '$';
                return $symbol . number_format($currentPrices[$key] / 100, 2);
            };
        @endphp
        <div class="border-t border-white/10 pt-5">
            <h3 class="text-sm font-medium text-white/80 mb-1 ak-strong">Current prices</h3>
            <p class="text-[11px] text-white/40 mb-3 ak-note">Read-only, what's currently saved in the price table. Edit the fields below and save to change these.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach([['USD','monthly','USD Monthly'],['USD','annual','USD Annual'],['INR','monthly','INR Monthly'],['INR','annual','INR Annual']] as [$curCurrency, $curCycle, $curLabel])
                    <div class="rounded-xl border border-white/10 bg-white/[0.02] px-3 py-2.5">
                        <div class="text-[10px] uppercase tracking-wider text-white/40 ak-note">{{ $curLabel }}</div>
                        <div class="text-sm text-white/80 font-medium mt-0.5 ak-strong">{{ $formatCurrentPrice($curCurrency, $curCycle) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-1 ak-strong">Pricing per country</h3>
        <p class="text-[11px] text-white/40 mb-3 ak-note">USD is shown to everyone by default. INR is shown to users whose billing country is India. The two amounts are independent, no FX conversion. <span class="text-white/30 ak-note">Enter amounts as <strong>integer minor units</strong>, e.g. <code>999</code> = $9.99, <code>82900</code> = ₹829.</span></p>
        <div class="grid grid-cols-2 gap-6">
            <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                <div class="text-xs uppercase tracking-wider text-white/50 mb-3 ak-muted">USD <span class="text-white/30 normal-case ak-note"> - everyone outside India</span></div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-white/40 mb-1 ak-note">Monthly (USD, cents)</label>
                        <input type="number" name="monthly_price" step="1" min="0" required
                               value="{{ old('monthly_price', (int) round(((float) $addon->monthly_price) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1 ak-note">Annual (USD, cents)</label>
                        <input type="number" name="annual_price" step="1" min="0" required
                               value="{{ old('annual_price', (int) round(((float) $addon->annual_price) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                <div class="text-xs uppercase tracking-wider text-white/50 mb-3 ak-muted">INR <span class="text-white/30 normal-case ak-note"> - users in India</span></div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-white/40 mb-1 ak-note">Monthly (INR, paise)</label>
                        <input type="number" name="monthly_price_secondary" step="1" min="0" required
                               value="{{ old('monthly_price_secondary', (int) round(((float) $addon->monthly_price_secondary) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1 ak-note">Annual (INR, paise)</label>
                        <input type="number" name="annual_price_secondary" step="1" min="0" required
                               value="{{ old('annual_price_secondary', (int) round(((float) $addon->annual_price_secondary) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                    </div>
                </div>
                <p class="text-[10px] text-white/30 mt-2 ak-note">INR is required, every addon has explicit USD <em>and</em> INR pricing.</p>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-1 ak-strong">Coin price (optional)</h3>
        <p class="text-[11px] text-white/40 mb-3 ak-note">If set, customers can pay for this add-on with coins from their wallet. Leave blank to keep this add-on currency-only.</p>
        <input type="number" name="coin_cost" min="0" step="1"
               value="{{ old('coin_cost', $addon->coin_cost) }}"
               placeholder="e.g. 500"
               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Status</label>
            <select name="status" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                <option value="active"   {{ old('status', $addon->status) === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $addon->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Sort Order</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $addon->sort_order) }}"
                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
        </div>
    </div>

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-3 ak-strong">Eligible Plans</h3>
        <p class="text-[11px] text-white/40 mb-3 ak-note">Plans that may purchase this addon. Leave empty to allow no plans (effectively disabled).</p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach($plans as $plan)
                <label class="flex items-center gap-2 text-sm text-white/70 p-2 rounded hover:bg-white/5 ak-strong">
                    <input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}"
                           {{ in_array($plan->id, old('plan_ids', $checkedPlanIds)) ? 'checked' : '' }}
                           class="rounded border-white/10 text-blue-400 ak-blue">
                    {{ $plan->name }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-3 ak-strong">Granted Features</h3>
        <p class="text-[11px] text-white/40 mb-3 ak-note">What this addon unlocks. Numeric <span class="font-mono">_extra</span> fields ADD to the plan's base limit; booleans only flip features on (never off).</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($featureNumeric as $key => $label)
            <div>
                <label class="block text-xs text-white/40 mb-1 ak-note" title="{{ $key }}">{{ $label }}</label>
                <input type="number" name="features[{{ $key }}]" value="{{ $features[$key] ?? '' }}" min="0"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4">
            @foreach($featureBooleans as $key => $label)
            <label class="flex items-center gap-2 text-sm text-white/60 p-2 rounded hover:bg-white/5 ak-muted">
                <input type="checkbox" name="features[{{ $key }}]" value="1"
                       {{ !empty($features[$key]) ? 'checked' : '' }}
                       class="rounded border-white/10 text-blue-400 ak-blue">
                {{ $label }}
            </label>
            @endforeach
        </div>
    </div>

    {{-- All-zero pricing save guard (USD/INR × monthly/annual). --}}
    @include('admin.partials.zero-price-guard', ['entityLabel' => 'add-on'])

    <div class="flex items-center gap-3 pt-4">
        <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">{{ $submitLabel ?? 'Save Addon' }}</button>
        <a href="{{ route('admin.addons.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition ak-strong">Cancel</a>
    </div>
</div>
