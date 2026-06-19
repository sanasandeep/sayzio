@php
    $features = $addon->features ?? [];
    $checkedPlanIds = $checkedPlanIds ?? [];
    $featureBooleans = [
        'custom_domains' => 'Grants custom domains',
        'remove_branding' => 'Removes 1INME branding',
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
        <label class="block text-sm font-medium text-white/80 mb-1">Addon Name</label>
        <input type="text" name="name" value="{{ old('name', $addon->name) }}" required
               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
        @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-white/80 mb-1">Description</label>
        <textarea name="description" rows="2"
                  class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">{{ old('description', $addon->description) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-white/80 mb-1">Type</label>
        <select name="type" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
            @foreach(\App\Modules\Admin\Models\Addon::TYPES as $t)
                <option value="{{ $t }}" {{ old('type', $addon->type) === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$t)) }}</option>
            @endforeach
        </select>
    </div>

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-1">Pricing per country</h3>
        <p class="text-[11px] text-white/40 mb-3">USD is shown to everyone by default. INR is shown to users whose billing country is India. The two amounts are independent — no FX conversion. <span class="text-white/30">Enter amounts as <strong>integer minor units</strong> — e.g. <code>999</code> = $9.99, <code>82900</code> = ₹829.</span></p>
        <div class="grid grid-cols-2 gap-6">
            <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                <div class="text-xs uppercase tracking-wider text-white/50 mb-3">USD <span class="text-white/30 normal-case">— everyone outside India</span></div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Monthly (USD, cents)</label>
                        <input type="number" name="monthly_price" step="1" min="0" required
                               value="{{ old('monthly_price', (int) round(((float) $addon->monthly_price) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Annual (USD, cents)</label>
                        <input type="number" name="annual_price" step="1" min="0" required
                               value="{{ old('annual_price', (int) round(((float) $addon->annual_price) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02]">
                <div class="text-xs uppercase tracking-wider text-white/50 mb-3">INR <span class="text-white/30 normal-case">— users in India</span></div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Monthly (INR, paise)</label>
                        <input type="number" name="monthly_price_secondary" step="1" min="0" required
                               value="{{ old('monthly_price_secondary', (int) round(((float) $addon->monthly_price_secondary) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Annual (INR, paise)</label>
                        <input type="number" name="annual_price_secondary" step="1" min="0" required
                               value="{{ old('annual_price_secondary', (int) round(((float) $addon->annual_price_secondary) * 100)) }}"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>
                <p class="text-[10px] text-white/30 mt-2">INR is required — every addon has explicit USD <em>and</em> INR pricing.</p>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-1">Coin price (optional)</h3>
        <p class="text-[11px] text-white/40 mb-3">If set, customers can pay for this add-on with coins from their wallet. Leave blank to keep this add-on currency-only.</p>
        <input type="number" name="coin_cost" min="0" step="1"
               value="{{ old('coin_cost', $addon->coin_cost) }}"
               placeholder="e.g. 500"
               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
            <select name="status" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                <option value="active"   {{ old('status', $addon->status) === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $addon->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-white/80 mb-1">Sort Order</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $addon->sort_order) }}"
                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
        </div>
    </div>

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-3">Eligible Plans</h3>
        <p class="text-[11px] text-white/40 mb-3">Plans that may purchase this addon. Leave empty to allow no plans (effectively disabled).</p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach($plans as $plan)
                <label class="flex items-center gap-2 text-sm text-white/70 p-2 rounded hover:bg-white/5">
                    <input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}"
                           {{ in_array($plan->id, old('plan_ids', $checkedPlanIds)) ? 'checked' : '' }}
                           class="rounded border-white/10 text-violet-400">
                    {{ $plan->name }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="border-t border-white/10 pt-5">
        <h3 class="text-sm font-medium text-white/80 mb-3">Granted Features</h3>
        <p class="text-[11px] text-white/40 mb-3">What this addon unlocks. Numeric <span class="font-mono">_extra</span> fields ADD to the plan's base limit; booleans only flip features on (never off).</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($featureNumeric as $key => $label)
            <div>
                <label class="block text-xs text-white/40 mb-1" title="{{ $key }}">{{ $label }}</label>
                <input type="number" name="features[{{ $key }}]" value="{{ $features[$key] ?? '' }}" min="0"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4">
            @foreach($featureBooleans as $key => $label)
            <label class="flex items-center gap-2 text-sm text-white/60 p-2 rounded hover:bg-white/5">
                <input type="checkbox" name="features[{{ $key }}]" value="1"
                       {{ !empty($features[$key]) ? 'checked' : '' }}
                       class="rounded border-white/10 text-violet-400">
                {{ $label }}
            </label>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-3 pt-4">
        <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">{{ $submitLabel ?? 'Save Addon' }}</button>
        <a href="{{ route('admin.addons.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition">Cancel</a>
    </div>
</div>
