@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="block text-xs font-semibold text-gray-300 mb-1">Value <span class="text-rose-400">*</span></label>
        <input name="value" value="{{ old('value', $stat->value ?? '') }}" required maxlength="32" placeholder="3,25,000" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-cyan-400 focus:outline-none">
        <p class="text-[11px] text-gray-500 mt-1">The big number. e.g. <code>3,25,000</code> or <code>99</code>.</p>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-300 mb-1">Suffix</label>
        <input name="suffix" value="{{ old('suffix', $stat->suffix ?? '') }}" maxlength="16" placeholder="+ or cr+" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-cyan-400 focus:outline-none">
        <p class="text-[11px] text-gray-500 mt-1">Renders next to the value (e.g. <code>+</code>, <code>cr+</code>, <code>K+</code>).</p>
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-300 mb-1">Label <span class="text-rose-400">*</span></label>
        <input name="label" value="{{ old('label', $stat->label ?? '') }}" required maxlength="160" placeholder="Users onboarded" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-cyan-400 focus:outline-none">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-300 mb-1">Icon</label>
        <input name="icon" value="{{ old('icon', $stat->icon ?? 'fa-chart-line') }}" maxlength="64" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-cyan-400 focus:outline-none">
        <p class="text-[11px] text-gray-500 mt-1">Font Awesome class. e.g. <code>fa-users</code>, <code>fa-globe</code>, <code>fa-bolt</code>, <code>fa-qrcode</code>.</p>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-300 mb-1">Accent color</label>
        <input type="color" name="color" value="{{ old('color', $stat->color ?? '#3d6bff') }}" class="w-full h-10 rounded-lg bg-white/5 border border-white/10">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-300 mb-1">Sort order</label>
        <input type="number" name="sort_order" min="0" max="99999" value="{{ old('sort_order', $stat->sort_order ?? 0) }}" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:border-cyan-400 focus:outline-none">
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2 text-sm text-gray-200 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $stat->is_active ?? true) ? 'checked' : '' }} class="rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-cyan-500">
            Show on marketing pages
        </label>
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button class="px-5 py-2 rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white text-sm font-semibold hover:opacity-90">{{ ($stat ?? null)?->exists ? 'Save changes' : 'Add stat' }}</button>
    <a href="{{ route('admin.site-stats.index') }}" class="text-sm text-gray-400 hover:text-white">Cancel</a>
</div>
