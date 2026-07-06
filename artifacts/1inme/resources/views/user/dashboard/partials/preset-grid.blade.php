{{-- Shared preset-card grid for the "Customize dashboard" modal.
     Included by BOTH the "quick" and "picker" steps in customize-modal.blade.php
     so the preset-card markup (icon, label, description, selected-state styling)
     lives in ONE place — change it here and both entry points stay in sync.
     Relies on the parent scope: $dashboardPresets (server data) plus the Alpine
     `dashboardCustomizer()` bindings applyPreset / currentPreset / isCustom / busy. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
    @foreach($dashboardPresets as $preset)
    <button type="button"
            @click="applyPreset('{{ $preset['key'] }}')"
            :disabled="busy"
            class="text-left p-4 rounded-xl transition-all group disabled:opacity-50"
            style="background: var(--bg-glass-input); border: 1px solid var(--border-subtle);"
            :style="currentPreset === '{{ $preset['key'] }}' && !isCustom ? 'border-color: rgba(61,107,255,0.5); box-shadow: 0 0 0 1px rgba(61,107,255,0.3);' : ''">
        <div class="flex items-center gap-2.5 mb-2">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                <i class="fas {{ $preset['icon'] }} text-blue-400 text-xs"></i>
            </div>
            <span class="text-xs font-bold" style="color: var(--text-primary);">{{ $preset['label'] }}</span>
            <i x-show="currentPreset === '{{ $preset['key'] }}' && !isCustom" class="fas fa-circle-check text-blue-400 text-xs ml-auto"></i>
        </div>
        <p class="text-[11px] leading-relaxed" style="color: var(--text-faint);">{{ $preset['description'] }}</p>
    </button>
    @endforeach
</div>
