@php
    $settingsTabs = [
        'appearance' => ['icon' => 'fa-palette', 'label' => 'Appearance', 'route' => 'user.links.settings.appearance'],
        'layout' => ['icon' => 'fa-ruler-combined', 'label' => 'Layout', 'route' => 'user.links.settings.layout'],
        'block-theme' => ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Block Theme', 'route' => 'user.links.settings.block-theme'],
        'advanced' => ['icon' => 'fa-sliders-h', 'label' => 'Advanced', 'route' => 'user.links.settings.advanced'],
    ];
@endphp

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
    <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
</div>
@endif

<div class="flex items-center gap-1 mb-6 p-1 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
    @foreach($settingsTabs as $tabKey => $tab)
    <a href="{{ route($tab['route'], $link) }}"
       class="flex-1 flex items-center justify-center gap-1.5 text-[11px] font-semibold py-2 rounded-lg transition-all no-underline {{ $activeSettingsTab === $tabKey ? 'text-white shadow-sm' : '' }}"
       style="{{ $activeSettingsTab === $tabKey ? 'background: rgba(124,58,237,0.2); border: 1px solid rgba(124,58,237,0.3);' : 'color: var(--text-faint);' }}">
        <i class="fas {{ $tab['icon'] }} text-[9px]"></i>
        <span class="hidden sm:inline">{{ $tab['label'] }}</span>
    </a>
    @endforeach
</div>
