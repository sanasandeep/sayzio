{{-- Shared folder form fields (create + edit). Expects $project (nullable) and $colors. --}}
@php
    $current = old('color', $project->color ?? $colors[0]);
    $isPreset = in_array(strtolower($current), array_map('strtolower', $colors), true);
@endphp
<div class="glass rounded-2xl p-6 space-y-5">
    <div>
        <label class="block text-sm font-medium text-white/60 mb-1">Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $project->name ?? '') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40" required autofocus>
        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div x-data="{ color: @js($current) }">
        <label class="block text-sm font-medium text-white/60 mb-2">Folder color</label>
        <input type="hidden" name="color" :value="color">
        <div class="flex items-center gap-3 flex-wrap">
            @foreach($colors as $swatch)
                <button type="button" @click="color = @js($swatch)"
                        class="w-9 h-9 rounded-xl transition-transform"
                        :class="color === @js($swatch) ? 'ring-2 ring-white ring-offset-2 ring-offset-black scale-110' : 'hover:scale-105'"
                        style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
            @endforeach
            <label class="w-9 h-9 rounded-xl border border-dashed border-white/25 flex items-center justify-center cursor-pointer hover:border-white/50" title="Custom color">
                <input type="color" class="sr-only" :value="color" @input="color = $event.target.value">
                <i class="fas fa-eye-dropper text-xs text-white/40"></i>
            </label>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <svg viewBox="0 0 96 72" class="w-14 h-11" aria-hidden="true">
                <path d="M6 14 a6 6 0 0 1 6-6 h22 l8 8 h42 a6 6 0 0 1 6 6 v4 H6 Z" :fill="color" opacity="0.75"/>
                <rect x="6" y="22" width="84" height="44" rx="6" :fill="color"/>
                <rect x="6" y="22" width="84" height="10" rx="5" fill="#ffffff" opacity="0.18"/>
            </svg>
            <span class="text-xs text-white/40">Preview</span>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-white/60 mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40" placeholder="Optional description...">{{ old('description', $project->description ?? '') }}</textarea>
    </div>
</div>
