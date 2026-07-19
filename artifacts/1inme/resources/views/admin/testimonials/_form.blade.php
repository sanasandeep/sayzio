@php
    $t = $testimonial ?? null;
@endphp

<div class="grid md:grid-cols-2 gap-5">
    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-white/70 mb-1.5">Quote <span class="text-rose-400">*</span></label>
        <textarea name="quote" rows="3" required maxlength="600"
                  class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none"
                  placeholder="The line you want shown in quotes on the homepage…">{{ old('quote', $t->quote ?? '') }}</textarea>
        <p class="text-[11px] text-white/40 mt-1">Up to 600 characters. Don't add quote marks, they're added automatically.</p>
    </div>

    <div>
        <label class="block text-xs font-semibold text-white/70 mb-1.5">Author name <span class="text-rose-400">*</span></label>
        <input type="text" name="author_name" required maxlength="120"
               value="{{ old('author_name', $t->author_name ?? '') }}"
               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none"
               placeholder="e.g. Jane Doe">
    </div>

    <div>
        <label class="block text-xs font-semibold text-white/70 mb-1.5">Author role / company</label>
        <input type="text" name="author_role" maxlength="160"
               value="{{ old('author_role', $t->author_role ?? '') }}"
               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none"
               placeholder="e.g. Café owner">
    </div>

    <div>
        <label class="block text-xs font-semibold text-white/70 mb-1.5">Marquee row</label>
        <select name="row"
                class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none">
            @foreach (['top' => 'Top row (left → right)', 'bottom' => 'Bottom row (right → left)'] as $val => $label)
                <option value="{{ $val }}" @selected(old('row', $t->row ?? 'top') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold text-white/70 mb-1.5">Avatar accent colour</label>
        <div class="flex items-center gap-2">
            <input type="color" name="accent_color"
                   value="{{ old('accent_color', $t->accent_color ?? '#3d6bff') }}"
                   class="w-12 h-10 rounded-lg bg-white/5 border border-white/10 cursor-pointer">
            <span class="text-[11px] text-white/50">Used as the gradient start colour for the round avatar.</span>
        </div>
    </div>

    <div>
        <label class="block text-xs font-semibold text-white/70 mb-1.5">Star rating</label>
        <select name="rating"
                class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none">
            @for ($r = 5; $r >= 1; $r--)
                <option value="{{ $r }}" @selected((int) old('rating', $t->rating ?? 5) === $r)>{{ $r }} {{ $r === 1 ? 'star' : 'stars' }}</option>
            @endfor
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold text-white/70 mb-1.5">Sort order</label>
        <input type="number" name="sort_order" min="0" max="99999"
               value="{{ old('sort_order', $t->sort_order ?? 0) }}"
               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white/90 focus:border-blue-400 focus:outline-none">
        <p class="text-[11px] text-white/40 mt-1">Lower numbers appear first within their row.</p>
    </div>

    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm text-white/80 cursor-pointer">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $t->is_active ?? true))
                   class="rounded bg-white/5 border-white/20 text-blue-500 focus:ring-blue-500">
            <span>Show on the homepage</span>
        </label>
    </div>
</div>

<div class="flex items-center gap-2 pt-4 mt-4 border-t border-white/10">
    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
        {{ isset($t) ? 'Save changes' : 'Add testimonial' }}
    </button>
    <a href="{{ route('admin.testimonials.index') }}"
       class="px-4 py-2 rounded-xl text-sm font-medium bg-white/5 hover:bg-white/10 text-white/80 border border-white/10">
        Cancel
    </a>
</div>
