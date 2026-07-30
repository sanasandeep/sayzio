@php
    $pUsd = $package->prices->first(fn($p)=>$p->currency==='USD' && $p->billing_cycle==='monthly');
    $pInr = $package->prices->first(fn($p)=>$p->currency==='INR' && $p->billing_cycle==='monthly');
    $oUsd = $package->prices->first(fn($p)=>$p->currency==='USD' && $p->billing_cycle===\App\Modules\Admin\Models\CoinPackage::COMPARE_CYCLE);
    $oInr = $package->prices->first(fn($p)=>$p->currency==='INR' && $p->billing_cycle===\App\Modules\Admin\Models\CoinPackage::COMPARE_CYCLE);
@endphp

<div class="space-y-5">
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Package Name</label>
        <input type="text" name="name" value="{{ old('name', $package->name) }}" required
               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white ak-strong ak-input">
        @error('name')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Description</label>
        <textarea name="description" rows="2"
                  class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white ak-strong ak-input">{{ old('description', $package->description) }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Best for <span class="text-white/30 ak-note">- short audience label, e.g. "Trying AI"</span></label>
        <input type="text" name="best_for" maxlength="100" value="{{ old('best_for', $package->best_for) }}"
               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white ak-strong ak-input">
        @error('best_for')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-xs text-white/60 mb-1 ak-muted">Coin amount</label>
            <input type="number" name="coin_amount" min="1" required value="{{ old('coin_amount', $package->coin_amount) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1 ak-muted">Bonus coins (optional)</label>
            <input type="number" name="bonus_coins" min="0" value="{{ old('bonus_coins', $package->bonus_coins) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1 ak-muted">Sort order</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $package->sort_order ?? 0) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs text-white/60 mb-1 ak-muted">Price USD (cents)</label>
            <input type="number" name="price_usd" min="0" required
                   value="{{ old('price_usd', $pUsd->amount_minor_units ?? 0) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1 ak-muted">Price INR (paise)</label>
            <input type="number" name="price_inr" min="0" required
                   value="{{ old('price_inr', $pInr->amount_minor_units ?? 0) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
            @php $fxRateHint = $fxRate ?? \App\Modules\Admin\Support\BillingFxRate::get(); @endphp
            <p class="mt-1 text-[11px] text-white/40 ak-note" data-fx-hint data-fx-rate="{{ $fxRateHint }}">
                At ₹{{ rtrim(rtrim(number_format($fxRateHint, 4, '.', ''), '0'), '.') }}/$1, the USD price converts to
                <span data-fx-computed>₹{{ number_format(\App\Modules\Admin\Support\BillingFxRate::usdMinorToInrMinor((int) old('price_usd', $pUsd->amount_minor_units ?? 0), $fxRateHint) / 100, 2) }}</span>
                (<span data-fx-paise>{{ \App\Modules\Admin\Support\BillingFxRate::usdMinorToInrMinor((int) old('price_usd', $pUsd->amount_minor_units ?? 0), $fxRateHint) }}</span> paise).
            </p>
        </div>
    </div>
    <script>
        (function () {
            var usd = document.querySelector('input[name="price_usd"]');
            var hint = document.querySelector('[data-fx-hint]');
            if (!usd || !hint) return;
            var rate = parseFloat(hint.getAttribute('data-fx-rate')) || 0;
            usd.addEventListener('input', function () {
                var cents = parseInt(usd.value, 10);
                if (isNaN(cents) || cents < 0 || !rate) return;
                var paise = Math.round(cents * rate);
                hint.querySelector('[data-fx-computed]').textContent = '₹' + (paise / 100).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                hint.querySelector('[data-fx-paise]').textContent = String(paise);
            });
        })();
    </script>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs text-white/60 mb-1 ak-muted">Original price USD (cents) <span class="text-white/30 ak-note"> - optional</span></label>
            <input type="number" name="original_price_usd" min="0"
                   value="{{ old('original_price_usd', $oUsd->amount_minor_units ?? '') }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
            @error('original_price_usd')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1 ak-muted">Original price INR (paise) <span class="text-white/30 ak-note"> - optional</span></label>
            <input type="number" name="original_price_inr" min="0"
                   value="{{ old('original_price_inr', $oInr->amount_minor_units ?? '') }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
            @error('original_price_inr')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
        </div>
    </div>
    <p class="text-xs text-white/40 -mt-2 ak-note">Set a higher original price to show it struck through next to the live price (the classic discount look). Leave blank or 0 to hide it. Display-only, checkout always charges the live price.</p>
    <div class="rounded-xl border border-amber-400/20 bg-amber-400/[0.04] p-4">
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong"><i class="fas fa-lock mr-1 text-amber-300"></i>Internal allocation: API budget %</label>
        <div class="flex items-center gap-3">
            <input type="number" name="api_budget_pct" min="0" max="100" step="0.01"
                   value="{{ old('api_budget_pct', $package->api_budget_pct) }}"
                   class="w-36 px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input"
                   data-alloc-pct>
            <span class="text-sm text-white/60 ak-muted">Platform margin: <span class="font-semibold text-white ak-strong" data-alloc-margin>{{ $package->api_budget_pct !== null ? rtrim(rtrim(number_format(100 - $package->api_budget_pct, 2, '.', ''), '0'), '.') : '—' }}</span>%</span>
        </div>
        @error('api_budget_pct')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
        <p class="mt-2 text-[11px] text-white/40 ak-note">Hidden internal split of each sale: this % of the price is budgeted for API costs; the platform margin is always the remaining 100&nbsp;−&nbsp;X. Snapshotted on every completed purchase for the <a href="{{ route('admin.coin-packages.allocations') }}" class="text-blue-400 hover:underline ak-blue">allocation report</a>. Never shown to customers. Blank = default {{ \App\Modules\Admin\Models\CoinPackage::DEFAULT_API_BUDGET_PCT }}%.</p>
    </div>
    <script>
        (function () {
            var pct = document.querySelector('[data-alloc-pct]');
            var out = document.querySelector('[data-alloc-margin]');
            if (!pct || !out) return;
            pct.addEventListener('input', function () {
                var v = parseFloat(pct.value);
                out.textContent = (isNaN(v) || v < 0 || v > 100) ? '—' : String(Math.round((100 - v) * 100) / 100);
            });
        })();
    </script>
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong">Status</label>
        <select name="status" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white ak-strong ak-input">
            <option value="active" {{ old('status', $package->status) === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $package->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    <div class="flex items-center gap-3 pt-4">
        <button class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">{{ $submitLabel ?? 'Save Package' }}</button>
        <a href="{{ route('admin.coin-packages.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] ak-strong">Cancel</a>
    </div>
</div>
