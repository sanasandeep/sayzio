{{-- Task #3617 — subtle inline hint shown when the active layout hides one
     or more catalog tiles that would otherwise appear on this tab. Only
     included by the parent tab panel when it detects a trim, so no extra
     visibility check is needed here. --}}
<div class="flex items-center justify-between gap-3 mb-4 px-4 py-2.5 rounded-xl" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
    <span class="text-[11px] inline-flex items-center gap-2" style="color: var(--text-faint);">
        <i class="fas fa-circle-info text-[11px]" style="color: var(--accent-light, #90acff);"></i>
        Some tiles are hidden by your current layout.
    </span>
    <button type="button" @click="$dispatch('open-dashboard-customize', { step: 'quick' })"
            class="text-[11px] font-semibold whitespace-nowrap flex-shrink-0 transition-all hover:gap-2 inline-flex items-center gap-1"
            style="color: var(--accent-light, #90acff);">
        Switch layout <i class="fas fa-arrow-right text-[9px]"></i>
    </button>
</div>
