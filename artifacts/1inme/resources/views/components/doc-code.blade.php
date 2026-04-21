@props(['lang' => 'bash'])

<div class="relative group">
    <button type="button"
            data-copy-target="#code-{{ $id = uniqid('c') }}"
            class="copy-btn absolute top-2.5 right-2.5 px-2 py-1 rounded-md bg-white/5 border border-white/10 text-[11px] text-gray-400 inline-flex items-center gap-1.5 opacity-70 hover:opacity-100"
            title="Copy">
        <i class="fas fa-copy text-[10px]"></i><span>Copy</span>
    </button>
    <div class="absolute top-2.5 left-3 text-[10px] uppercase tracking-wider text-gray-500 font-mono">{{ $lang }}</div>
    <pre id="code-{{ $id }}" class="doc-code pt-7">{{ trim($slot) }}</pre>
</div>
