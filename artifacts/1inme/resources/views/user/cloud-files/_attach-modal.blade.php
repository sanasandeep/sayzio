{{--
    Renders the picker modal markup tied to an existing Alpine instance.
    Caller is responsible for the surrounding x-data="cloudAttachPicker({...})".
    Optional slot $confirmLabel sets the confirm-button text (default depends
    on mode).
--}}
@php($_confirmLabel = $confirmLabel ?? null)
<div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
     @keydown.escape.window="open = false">
    <div class="w-full max-w-2xl rounded-xl border max-h-[80vh] flex flex-col"
         style="background: var(--bg-card); border-color: var(--border-soft);">
        <div class="px-5 py-3 border-b flex items-center justify-between" style="border-color: var(--border-soft);">
            <div class="font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-cloud mr-1"></i> Attach from Cloud Files
            </div>
            <button type="button" @click="open = false" class="text-sm" style="color: var(--text-faint);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="px-5 py-2 border-b" style="border-color: var(--border-soft);">
            <input type="text" x-model.debounce.300ms="search" @input="refresh()"
                   placeholder="Search the workspace library…"
                   class="w-full px-3 py-2 rounded-lg border text-sm"
                   style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
        </div>
        <div class="flex-1 overflow-y-auto p-3">
            <div x-show="loading" class="text-center py-8 text-sm" style="color: var(--text-faint);">
                <i class="fas fa-spinner fa-spin mr-1"></i> Loading…
            </div>
            <div x-show="error" x-text="error" class="px-3 py-2 rounded text-xs"
                 style="background: rgba(239,68,68,0.1); color: #f87171;"></div>
            <template x-if="!loading && files.length === 0">
                <div class="text-center py-8 text-sm" style="color: var(--text-faint);">
                    <p>No files in the workspace library yet.</p>
                    <a href="{{ route('user.cloud-files.index') }}" class="text-blue-400 underline text-xs">Open Cloud Files</a>
                </div>
            </template>
            <template x-for="f in files" :key="f.id">
                <label class="flex items-center gap-3 px-3 py-2 rounded cursor-pointer hover:bg-white/5">
                    <input type="checkbox" :checked="isPicked(f)" @change="toggle(f)" class="w-4 h-4">
                    <i :class="f.provider_icon" class="text-sm" style="color: var(--text-muted);"></i>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm truncate" style="color: var(--text-primary);" x-text="f.name"></div>
                        <div class="text-[11px]" style="color: var(--text-faint);">
                            <span x-text="f.provider_label"></span>
                            · <span x-text="f.human_size"></span>
                            <template x-if="f.added_by"><span> · added by <span x-text="f.added_by"></span></span></template>
                        </div>
                    </div>
                </label>
            </template>
        </div>
        <div class="px-5 py-3 border-t flex items-center justify-between" style="border-color: var(--border-soft);">
            <div class="text-xs" style="color: var(--text-faint);">
                <span x-text="picked.length"></span> selected
            </div>
            <div class="flex gap-2">
                <button type="button" @click="open = false" class="px-3 py-2 rounded text-sm"
                        style="color: var(--text-muted);">Cancel</button>
                <button type="button" @click="confirm()" :disabled="saving"
                        class="px-4 py-2 rounded text-sm font-semibold text-white disabled:opacity-50"
                        style="background: linear-gradient(135deg,#3d6bff,#90acff);">
                    <span x-show="!saving">{{ $_confirmLabel ?? 'Attach selected' }}</span>
                    <span x-show="saving"><i class="fas fa-spinner fa-spin"></i> Attaching…</span>
                </button>
            </div>
        </div>
    </div>
</div>
