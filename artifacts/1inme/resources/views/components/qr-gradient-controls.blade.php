@props(['field', 'label' => 'Gradient'])
<details>
    <summary class="text-[11px] font-semibold" style="color: var(--text-muted);">
        <i class="fas fa-caret-right transition-transform"></i> {{ $label }}
    </summary>
    <div class="mt-2 space-y-2 pl-2 border-l" style="border-color: var(--border-glass);">
        <label class="inline-flex items-center gap-1.5 text-[11px] cursor-pointer" style="color: var(--text-muted);">
            <input type="checkbox" x-model="design.{{ $field }}.enabled" @change="render()"> Enable
        </label>
        <div x-show="design.{{ $field }}.enabled" class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <label class="text-[11px]" style="color: var(--text-secondary);">From
                    <input type="color" x-model="design.{{ $field }}.from" @input="render()" class="w-full h-7 rounded cursor-pointer">
                </label>
                <label class="text-[11px]" style="color: var(--text-secondary);">To
                    <input type="color" x-model="design.{{ $field }}.to" @input="render()" class="w-full h-7 rounded cursor-pointer">
                </label>
            </div>
            <div class="flex items-center gap-2">
                <select x-model="design.{{ $field }}.type" @change="render()" class="px-2 py-1 text-[11px] rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <option value="linear">Linear</option>
                    <option value="radial">Radial</option>
                </select>
                <label class="flex-1 text-[11px]" style="color: var(--text-secondary);">Angle
                    <input type="range" min="0" max="360" step="5" x-model.number="design.{{ $field }}.angle" @input="render()" class="w-full">
                </label>
                <span class="text-[11px] font-mono w-10 text-right" x-text="design.{{ $field }}.angle + '°'" style="color: var(--text-muted);"></span>
            </div>
        </div>
    </div>
</details>
