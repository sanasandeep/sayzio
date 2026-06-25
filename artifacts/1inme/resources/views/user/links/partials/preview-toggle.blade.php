{{--
    Shared preview/interstitial toggle (settings.show_preview_page). Used by the
    short-link and vCard forms so the "show a page before the action" control is
    consistent across types. Optional vars:
      - $previewChecked : bool|null  pre-checked state (defaults to old() value)
      - $previewTitle   : string     heading text
      - $previewDesc    : string     helper text
--}}
@php
    $previewChecked = $previewChecked ?? old('show_preview_page');
@endphp
<div class="glass rounded-2xl p-4 my-4 flex items-start gap-3">
    <input type="hidden" name="show_preview_page" value="0">
    <label class="relative inline-flex items-center cursor-pointer mt-0.5">
        <input type="checkbox" name="show_preview_page" value="1" {{ $previewChecked ? 'checked' : '' }} class="sr-only peer">
        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
    </label>
    <div class="text-sm">
        <div class="text-white/80 font-medium">{{ $previewTitle ?? 'Show preview page before redirect' }}</div>
        <p class="text-xs text-white/40 mt-0.5">{{ $previewDesc ?? 'Renders a branded interstitial that fires marketing pixels and tracks visitor dwell time before forwarding to the destination.' }}</p>
    </div>
</div>
