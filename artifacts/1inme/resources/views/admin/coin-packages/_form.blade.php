@php
    $pUsd = $package->prices->first(fn($p)=>$p->currency==='USD' && $p->billing_cycle==='monthly');
    $pInr = $package->prices->first(fn($p)=>$p->currency==='INR' && $p->billing_cycle==='monthly');
    $oUsd = $package->prices->first(fn($p)=>$p->currency==='USD' && $p->billing_cycle===\App\Modules\Admin\Models\CoinPackage::COMPARE_CYCLE);
    $oInr = $package->prices->first(fn($p)=>$p->currency==='INR' && $p->billing_cycle===\App\Modules\Admin\Models\CoinPackage::COMPARE_CYCLE);
@endphp

<div class="space-y-5">
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1">Package Name</label>
        <input type="text" name="name" value="{{ old('name', $package->name) }}" required
               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">
        @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1">Description</label>
        <textarea name="description" rows="2"
                  class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">{{ old('description', $package->description) }}</textarea>
    </div>
    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-xs text-white/60 mb-1">Coin amount</label>
            <input type="number" name="coin_amount" min="1" required value="{{ old('coin_amount', $package->coin_amount) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1">Bonus coins (optional)</label>
            <input type="number" name="bonus_coins" min="0" value="{{ old('bonus_coins', $package->bonus_coins) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1">Sort order</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $package->sort_order ?? 0) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs text-white/60 mb-1">Price USD (cents)</label>
            <input type="number" name="price_usd" min="0" required
                   value="{{ old('price_usd', $pUsd->amount_minor_units ?? 0) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1">Price INR (paise)</label>
            <input type="number" name="price_inr" min="0" required
                   value="{{ old('price_inr', $pInr->amount_minor_units ?? 0) }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs text-white/60 mb-1">Original price USD (cents) <span class="text-white/30">— optional</span></label>
            <input type="number" name="original_price_usd" min="0"
                   value="{{ old('original_price_usd', $oUsd->amount_minor_units ?? '') }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
            @error('original_price_usd')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs text-white/60 mb-1">Original price INR (paise) <span class="text-white/30">— optional</span></label>
            <input type="number" name="original_price_inr" min="0"
                   value="{{ old('original_price_inr', $oInr->amount_minor_units ?? '') }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
            @error('original_price_inr')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>
    <p class="text-xs text-white/40 -mt-2">Set a higher original price to show it struck through next to the live price (the classic discount look). Leave blank or 0 to hide it. Display-only — checkout always charges the live price.</p>
    <div>
        <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
        <select name="status" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white">
            <option value="active" {{ old('status', $package->status) === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $package->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    <div class="flex items-center gap-3 pt-4">
        <button class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700">{{ $submitLabel ?? 'Save Package' }}</button>
        <a href="{{ route('admin.coin-packages.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06]">Cancel</a>
    </div>
</div>
