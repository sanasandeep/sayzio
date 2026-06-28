@php $item = $item ?? null; @endphp
<div class="grid grid-cols-1 gap-3">
    <label class="text-xs" style="color: var(--text-muted);">Name *<input name="name" value="{{ old('name', $item->name ?? '') }}" required class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
    <div class="grid grid-cols-2 gap-3">
        <label class="text-xs" style="color: var(--text-muted);">Unit price (minor) *<input type="number" min="0" name="unit_price_minor" value="{{ old('unit_price_minor', $item->unit_price_minor ?? 0) }}" required class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
        <label class="text-xs" style="color: var(--text-muted);">Currency<input name="currency" maxlength="3" value="{{ old('currency', $item->currency ?? 'USD') }}" class="block w-full mt-1 p-2 rounded-lg border uppercase" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <label class="text-xs" style="color: var(--text-muted);">SKU<input name="sku" value="{{ old('sku', $item->sku ?? '') }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
        <label class="text-xs" style="color: var(--text-muted);">Unit label<input name="unit_label" value="{{ old('unit_label', $item->unit_label ?? '') }}" placeholder="hour, item…" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <label class="text-xs" style="color: var(--text-muted);">Category
            <select name="category_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                <option value="">— None —</option>
                @foreach($categories as $cat)<option value="{{ $cat->id }}" @selected(($item->category_id ?? null) == $cat->id)>{{ $cat->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs" style="color: var(--text-muted);">Tax rule
            <select name="tax_rule_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                <option value="">— None —</option>
                @foreach($taxRules as $rule)<option value="{{ $rule->id }}" @selected(($item->tax_rule_id ?? null) == $rule->id)>{{ $rule->name }} ({{ number_format($rule->rate_bps / 100, 2) }}%)</option>@endforeach
            </select>
        </label>
    </div>
    <label class="text-xs" style="color: var(--text-muted);">Description<textarea name="description" rows="2" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">{{ old('description', $item->description ?? '') }}</textarea></label>
    <label class="flex items-center gap-2 text-sm" style="color: var(--text-primary);"><input type="checkbox" name="is_active" value="1" @checked($item->is_active ?? true)> Active</label>
</div>
