{{-- Confirm-before-apply OG-metadata preview card. Shown after the picker
     auto-runs the OG fetch (linkBlockEditor.ogPreview); nothing is written
     into the form until the creator clicks Apply. --}}
<div x-show="ogPreview" x-cloak class="rounded-xl p-3" style="background:var(--bg-glass);border:1px solid rgba(61,107,255,.25);" data-og-preview-card>
    <div class="flex items-start gap-2.5">
        <template x-if="ogPreview && (ogPreview.image_url || ogPreview.favicon_url)">
            <img :src="ogPreview.image_url || ogPreview.favicon_url" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0" style="border:1px solid var(--border-glass);background:var(--bg-glass-input);">
        </template>
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold uppercase tracking-wide mb-0.5" style="color:#90acff;"><i class="fas fa-wand-magic-sparkles mr-1"></i>Page details found</div>
            <div class="text-xs font-medium truncate" style="color:var(--text-primary);" x-text="ogPreview ? (ogPreview.title || 'Untitled page') : ''"></div>
            <div class="text-[11px] truncate" style="color:var(--text-faint);" x-show="ogPreview && ogPreview.description" x-text="ogPreview ? (ogPreview.description || '') : ''"></div>
        </div>
    </div>
    <div class="flex gap-2 mt-2.5">
        <button type="button" @click="applyOgPreview()" class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors" style="background:rgba(61,107,255,.18);color:#90acff;border:1px solid rgba(61,107,255,.30);">
            <i class="fas fa-check mr-1"></i>Apply
        </button>
        <button type="button" @click="ogPreview = null" class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors hover:bg-white/5" style="color:var(--text-secondary);border:1px solid var(--border-glass);">
            Dismiss
        </button>
    </div>
</div>
