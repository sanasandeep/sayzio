@php
    $settingsTabs = [
        'appearance' => ['icon' => 'fa-palette', 'label' => 'Appearance', 'route' => 'user.links.settings.appearance'],
        'layout' => ['icon' => 'fa-ruler-combined', 'label' => 'Layout', 'route' => 'user.links.settings.layout'],
        'block-theme' => ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Block Theme', 'route' => 'user.links.settings.block-theme'],
        'advanced' => ['icon' => 'fa-sliders-h', 'label' => 'Advanced', 'route' => 'user.links.settings.advanced'],
    ];
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
        <a href="{{ route('user.links.blocks.editor', $link) }}" class="btn-primary px-4 py-2 text-xs inline-flex items-center gap-2">
            <i class="fas fa-th-large text-[10px]"></i> Blocks
        </a>
        <a href="{{ url('/' . $link->alias) }}" target="_blank" class="btn-ghost text-xs py-2" title="Preview">
            <i class="fas fa-external-link-alt text-[10px]"></i>
        </a>
    </div>
</div>

<div class="flex items-center gap-1.5 mt-5 mb-6 p-1 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
    @foreach($settingsTabs as $tabKey => $tab)
    <a href="{{ route($tab['route'], $link) }}"
       class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold py-2.5 rounded-lg transition-all no-underline {{ $activeSettingsTab === $tabKey ? 'text-white shadow-sm' : '' }}"
       style="{{ $activeSettingsTab === $tabKey ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);' }}">
        <i class="fas {{ $tab['icon'] }} text-[10px]"></i>
        <span class="hidden sm:inline">{{ $tab['label'] }}</span>
    </a>
    @endforeach
</div>

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
    <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
</div>
@endif
