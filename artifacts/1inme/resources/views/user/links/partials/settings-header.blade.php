@php
    $settingsTabs = [
        'appearance' => ['icon' => 'fa-palette', 'label' => 'Appearance', 'route' => 'user.links.settings.appearance'],
        'layout' => ['icon' => 'fa-ruler-combined', 'label' => 'Layout', 'route' => 'user.links.settings.layout'],
        'block-theme' => ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Block Theme', 'route' => 'user.links.settings.block-theme'],
        'advanced' => ['icon' => 'fa-sliders-h', 'label' => 'Advanced', 'route' => 'user.links.settings.advanced'],
        'splash'   => ['icon' => 'fa-rocket', 'label' => 'Intro', 'route' => 'user.links.splash'],
    ];
@endphp

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
    <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
</div>
@endif

<div class="settings-tabs inline-flex items-center gap-1 mb-6 p-1 rounded-full">
    @foreach($settingsTabs as $tabKey => $tab)
    <a href="{{ route($tab['route'], $link) }}"
       class="settings-tab no-underline {{ $activeSettingsTab === $tabKey ? 'is-active' : '' }}">
        <i class="fas {{ $tab['icon'] }} text-[10px]"></i>
        <span>{{ $tab['label'] }}</span>
    </a>
    @endforeach
</div>
<style>
    .settings-tabs {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(16px) saturate(140%);
        -webkit-backdrop-filter: blur(16px) saturate(140%);
    }
    .settings-tab {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        color: var(--text-faint);
        border-radius: 9999px;
        transition: color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .settings-tab:hover { color: var(--text-primary); }
    .settings-tab.is-active {
        color: #fff;
        background: linear-gradient(135deg, rgba(167,139,250,0.95), rgba(103,232,249,0.85));
        box-shadow: 0 6px 18px -6px rgba(167,139,250,0.55), 0 2px 8px -2px rgba(103,232,249,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    html.light-mode .settings-tab.is-active {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        box-shadow: 0 4px 14px -4px rgba(124,58,237,0.45);
    }
</style>
