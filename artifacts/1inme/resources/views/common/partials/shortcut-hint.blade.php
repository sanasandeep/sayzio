{{--
    Tiny inline hint for the global ⌘K / ⌘I shortcuts.
    Drop into any footer to remind users the shortcuts exist.
--}}
<div class="flex items-center justify-center gap-3 text-[11px] text-gray-500 flex-wrap">
    <button
        type="button"
        onclick="window.dispatchEvent(new KeyboardEvent('keydown',{key:'k',ctrlKey:true,bubbles:true}))"
        class="inline-flex items-center gap-1.5 hover:text-gray-300 transition"
        aria-label="Open search">
        <kbd class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-gray-300 text-[10px] font-bold">⌘ K</kbd>
        <span>Search everything</span>
    </button>
    <span class="opacity-30">·</span>
    <button
        type="button"
        onclick="window.dispatchEvent(new KeyboardEvent('keydown',{key:'i',ctrlKey:true,bubbles:true}))"
        class="inline-flex items-center gap-1.5 hover:text-gray-300 transition"
        aria-label="Toggle theme">
        <kbd class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-gray-300 text-[10px] font-bold">⌘ I</kbd>
        <span>Toggle theme</span>
    </button>
</div>
