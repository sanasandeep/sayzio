@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Category label</label>
            <input type="text" name="category" required maxlength="80"
                   value="{{ old('category', $slide->category ?? '') }}"
                   placeholder="e.g. For creators"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/30 focus:outline-none focus:border-blue-500">
            <p class="text-[11px] text-white/30 mt-1">Shown as a small chip above the headline in the mobile app.</p>
            @error('category')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Headline</label>
            <input type="text" name="title" required maxlength="255"
                   value="{{ old('title', $slide->title ?? '') }}"
                   placeholder="e.g. Every link, every channel — one tap away"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/30 focus:outline-none focus:border-blue-500">
            @error('title')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Supporting copy</label>
            <textarea name="body" rows="4" maxlength="600"
                      placeholder="One short paragraph explaining how Sayzio helps this audience."
                      class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/30 focus:outline-none focus:border-blue-500">{{ old('body', $slide->body ?? '') }}</textarea>
            @error('body')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:border-blue-500">
                    <option value="active"   @selected(old('status', $slide->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $slide->status ?? 'active') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">Sort order</label>
                <input type="number" name="sort_order" min="0" max="9999"
                       value="{{ old('sort_order', $slide->sort_order ?? 0) }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:border-blue-500">
            </div>
        </div>
    </div>

    <div class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Background image (single)</label>
            <input type="file" name="image" accept="image/*"
                   class="w-full text-sm text-white/70 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-700">
            <p class="text-[11px] text-white/30 mt-1">Optional fallback shown when the gallery is empty. Portrait 9:16 works best (e.g. 1080×1920). Max 5 MB.</p>
            @error('image')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror

            @if(!empty($slide->image_path))
                <div class="mt-3 relative aspect-[3/4] rounded-xl overflow-hidden border border-white/10">
                    <img src="{{ $slide->imageUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-black/20"></div>
                    <div class="absolute bottom-3 left-3 right-3 text-white text-xs font-medium drop-shadow line-clamp-2">{{ $slide->title }}</div>
                </div>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Gallery images (slider)</label>
            <input type="file" name="gallery_images[]" accept="image/*" multiple
                   class="w-full text-sm text-white/70 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-fuchsia-600 file:text-white hover:file:bg-fuchsia-700">
            <p class="text-[11px] text-white/30 mt-1">Pick 2–5 portrait photos. They auto-rotate inside this slide on the mobile splash. Max 10 images, 5 MB each.</p>
            @error('gallery_images')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            @error('gallery_images.*')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror

            @if(!empty($slide) && !empty($slide->gallery_images))
                <p class="text-[11px] text-white/40 mt-3 mb-2">Current gallery — tick to remove on save.</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach($slide->gallery_images as $p)
                        <label class="relative block aspect-[3/4] rounded-lg overflow-hidden border border-white/10 cursor-pointer group">
                            <img src="{{ rtrim(config('app.url'), '/') . \Illuminate\Support\Facades\Storage::disk('public')->url($p) }}"
                                 alt="" class="absolute inset-0 w-full h-full object-cover">
                            <span class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition"></span>
                            <span class="absolute top-1 right-1 inline-flex items-center justify-center w-6 h-6 rounded-md bg-black/60 backdrop-blur border border-white/20">
                                <input type="checkbox" name="remove_gallery[]" value="{{ $p }}" class="accent-red-500">
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<div class="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-white/5">
    <a href="{{ route('admin.onboarding-slides.index') }}" class="px-4 py-2 text-white/60 hover:text-white text-sm">Cancel</a>
    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">{{ $submitLabel ?? 'Save' }}</button>
</div>
