@php
    $activeMainTab = $activeMainTab ?? 'blocks';
@endphp
<div class="flex items-center justify-between mb-1">
    <div>
        <h1 class="text-2xl font-bold gradient-text">{{ $link->alias }}</h1>
        <div class="flex items-center gap-2 mt-1" x-data="{ copied: false }">
            <span class="inline-flex items-center gap-1.5 text-sm">
                <span class="w-2 h-2 rounded-full {{ $link->is_active ? 'bg-emerald-400' : 'bg-red-400' }}" style="{{ $link->is_active ? 'box-shadow: 0 0 8px rgba(16,185,129,0.5);' : '' }}"></span>
                <span style="color: var(--text-dimmed);">Your link is</span>
                <span class="text-purple-400">{{ $link->getShortUrl() }}</span>
            </span>
            <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="hover:text-purple-400 transition-colors" style="color: var(--text-faint);">
                <i x-show="!copied" class="fas fa-copy text-xs"></i>
                <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-xs"></i>
            </button>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('user.links.toggle-active', $link) }}" method="POST" class="inline">
            @csrf
            <button class="btn-ghost text-xs py-2" title="{{ $link->is_active ? 'Deactivate' : 'Activate' }}">
                <i class="fas {{ $link->is_active ? 'fa-toggle-on text-emerald-400' : 'fa-toggle-off' }}"></i>
            </button>
        </form>
        <a href="{{ url('/' . $link->alias) }}" target="_blank" class="btn-ghost text-xs py-2" title="Open in new tab">
            <i class="fas fa-external-link-alt text-[10px]"></i>
        </a>
        <a href="{{ route('user.links.qrcode', $link) }}" class="btn-ghost text-xs py-2" title="QR Code">
            <i class="fas fa-qrcode text-[10px]"></i>
        </a>
        <a href="{{ route('user.links.show', $link) }}" class="btn-ghost text-xs py-2" title="Analytics">
            <i class="fas fa-chart-bar text-[10px]"></i>
        </a>
    </div>
</div>

<div class="flex items-center gap-1.5 mt-5 mb-6 p-1 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
    <a href="{{ route('user.links.blocks.editor', $link) }}"
       class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold py-2.5 rounded-lg transition-all no-underline {{ $activeMainTab === 'blocks' ? 'text-white shadow-sm' : '' }}"
       style="{{ $activeMainTab === 'blocks' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);' }}">
        <i class="fas fa-th-large text-[10px]"></i>
        <span>Blocks</span>
    </a>
    <a href="{{ route('user.links.settings.appearance', $link) }}"
       class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold py-2.5 rounded-lg transition-all no-underline {{ $activeMainTab === 'settings' ? 'text-white shadow-sm' : '' }}"
       style="{{ $activeMainTab === 'settings' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);' }}">
        <i class="fas fa-cog text-[10px]"></i>
        <span>Settings</span>
    </a>
</div>
