{{--
    Reusable "Custom Alias" field with the live availability indicator.

    Optional variables:
      $aliasLabel   — field label (default "Custom Alias")
      $prefillAlias — pre-filled alias value
      $aliasLimits  — ['min' => int, 'max' => int]
--}}
@include('user.links.partials.alias-checker')
<div x-data="aliasChecker('{{ route('user.links.check-alias') }}')" x-init="init()">
    <label class="block text-sm font-medium text-white/60 mb-1">{{ $aliasLabel ?? 'Custom Alias' }}</label>
    <div class="flex items-stretch rounded-xl bg-white/5 border overflow-hidden transition-colors"
         :class="state === 'available' ? 'border-emerald-500/40 focus-within:ring-2 focus-within:ring-emerald-500/40'
             : (isError ? 'border-red-500/40 focus-within:ring-2 focus-within:ring-red-500/40'
             : 'border-white/10 focus-within:ring-2 focus-within:ring-blue-500/40')">
        <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}"
               placeholder="auto-generated"
               minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}"
               maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}"
               pattern="[A-Za-z0-9_\-]+" autocomplete="off" spellcheck="false"
               @input.debounce.400ms="check($event.target.value)"
               class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-white/20 outline-none">
        <span class="flex items-center px-3" x-show="state && state !== 'empty'" x-cloak>
            <i x-show="state === 'checking'" class="fas fa-spinner fa-spin text-white/40 text-sm"></i>
            <i x-show="state === 'available'" class="fas fa-circle-check text-emerald-400 text-sm"></i>
            <i x-show="isError" class="fas fa-circle-xmark text-red-400 text-sm"></i>
        </span>
    </div>
    @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    <p aria-live="polite" x-show="message && state && state !== 'empty'" x-cloak
       class="text-sm mt-1.5"
       :class="state === 'available' ? 'text-emerald-400' : (isError ? 'text-red-400' : 'text-white/40')"
       x-text="message"></p>
</div>
