@once
<style>
  ._pw-wrap input::-ms-reveal,
  ._pw-wrap input::-ms-clear { display: none !important; }
</style>
@endonce
<div x-data="{ _pwShow: false }" class="_pw-wrap relative">
    <input
        :type="_pwShow ? 'text' : 'password'"
        name="{{ $name }}"
        @isset($id) id="{{ $id }}" @endisset
        placeholder="{{ $placeholder ?? '' }}"
        autocomplete="{{ $autocomplete ?? 'current-password' }}"
        @if(!empty($required)) required @endif
        @isset($value) value="{{ $value }}" @endisset
        class="{{ $inputClass ?? 'theme-input w-full' }}"
        style="padding-right: 2.5rem;"
    >
    <button
        type="button"
        @click="_pwShow = !_pwShow"
        :aria-label="_pwShow ? 'Hide password' : 'Show password'"
        :aria-pressed="_pwShow.toString()"
        tabindex="0"
        class="absolute inset-y-0 right-0 flex items-center justify-center w-10 opacity-40 hover:opacity-75 focus:opacity-75 transition-opacity focus:outline-none"
        style="color: inherit;"
    >
        <i :class="'fas ' + (_pwShow ? 'fa-eye-slash' : 'fa-eye')" class="text-[13px] pointer-events-none"></i>
    </button>
</div>
