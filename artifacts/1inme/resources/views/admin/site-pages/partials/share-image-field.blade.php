{{--
    Shared "Share preview image" (og:image) control for the Site Pages
    editors. Persists to site_pages.extra['share_image'] via the top-level
    `share_image` input; blank falls back to the global default share image
    from Marketing Settings. Reuses the shared aboutPhotoUploader helper.
--}}
@php
    $__shareImageValue = old('share_image', is_array($page->extra ?? null) ? (string) ($page->extra['share_image'] ?? '') : '');
@endphp
<div x-data="{ url: @js($__shareImageValue) }">
    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5 ak-muted">Share preview image <span class="normal-case tracking-normal text-white/40 font-normal ak-note">(og:image, ideally 1200×630)</span></label>
    <div x-data="aboutPhotoUploader({ get: () => url, set: (v) => url = v, aspect: 1200/630, outputSize: 1200, isCircle: false })" class="space-y-2">
        <div class="flex items-start gap-3">
            <div class="shrink-0 text-center">
                <template x-if="url">
                    <img :src="url" alt="" class="w-40 object-cover rounded-md border border-white/10 bg-white/5" style="height:84px" x-on:error="$el.style.display='none'">
                </template>
                <template x-if="!url">
                    <div class="w-40 rounded-md border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2 ak-note" style="height:84px">Falls back to the global default share image</div>
                </template>
                <div class="text-[10px] text-white/40 mt-1 ak-note">Live preview</div>
            </div>
            <div class="flex-1 space-y-2">
                <input type="text" name="share_image" x-model="url"
                       placeholder="https://… or /storage/… (or upload)"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                @error('share_image')<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                        <i class="fas fa-upload"></i>
                        <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                    </button>
                    <button type="button" x-show="url" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white ak-muted"><i class="fas fa-times mr-1"></i>Remove</button>
                </div>
                <p x-show="error" x-text="error" class="text-xs text-red-400 ak-red"></p>
                <p class="text-[11px] text-white/40 ak-note">Shown when this page is shared on social platforms. Leave blank to use the global default share image from Marketing Settings.</p>
            </div>
        </div>
        <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
        @include('admin.site-pages.partials.about-crop-modal')
    </div>
</div>
@include('admin.site-pages.partials.photo-uploader')
