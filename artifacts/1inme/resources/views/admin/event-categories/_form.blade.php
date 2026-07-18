@if (isset($errors) && $errors && $errors->any())
    <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">Name</label>
        <input type="text" name="name" value="{{ old('name', $category->name ?? '') }}"
               required maxlength="100"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white"
               placeholder="e.g. Music">
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $category->slug ?? '') }}"
               maxlength="60" pattern="[a-z0-9_]+"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono"
               placeholder="auto-generated from name if left blank">
        <p class="text-[11px] text-white/40 mt-1.5">
            Stored on events as <code class="text-white/60">settings.event_category</code>. Changing the slug on an
            existing category orphans events already saved under the old value (they'll fall back to a guessed icon).
        </p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">
            Icon <span class="text-white/30 normal-case">(Font Awesome class)</span>
        </label>
        <input type="text" name="icon" value="{{ old('icon', $category->icon ?? 'fa-calendar-days') }}"
               required maxlength="60" pattern="fa-[a-z0-9-]+"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono"
               placeholder="fa-music">
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">Gradient start</label>
        <input type="text" name="color_from" value="{{ old('color_from', $category->color_from ?? '#3d6bff') }}"
               required maxlength="20"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono">
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">Gradient end</label>
        <input type="text" name="color_to" value="{{ old('color_to', $category->color_to ?? '#2342c7') }}"
               required maxlength="20"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono">
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">Sort order</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white">
    </div>
</div>

<div class="flex items-center gap-3">
    <div class="rounded-xl overflow-hidden border border-white/10 w-16 h-16 flex items-center justify-center text-white text-xl"
         style="background: linear-gradient(135deg, {{ old('color_from', $category->color_from ?? '#3d6bff') }} 0%, {{ old('color_to', $category->color_to ?? '#2342c7') }} 100%);">
        <i class="fas {{ old('icon', $category->icon ?? 'fa-calendar-days') }}"></i>
    </div>
    <label class="flex items-center gap-2 cursor-pointer px-3 h-[42px] rounded-lg bg-black/30 border border-white/15">
        <input type="hidden" name="is_enabled" value="0">
        <input type="checkbox" name="is_enabled" value="1"
               @checked(old('is_enabled', $category->is_enabled ?? true))
               class="rounded">
        <span class="text-sm text-white/80">Enabled &amp; shown on /events</span>
    </label>
</div>
