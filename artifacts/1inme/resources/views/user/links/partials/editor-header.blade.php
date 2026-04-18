@php
    $activeMainTab = $activeMainTab ?? 'blocks';
    $favSrc = $link->favicon
        ?? ($link->settings['biolink']['favicons']['icon_512'] ?? null)
        ?? ($link->settings['biolink']['favicons']['apple_touch_icon'] ?? null);
    if (!$favSrc && !empty($link->long_url)) {
        $host = parse_url($link->long_url, PHP_URL_HOST);
        if ($host) $favSrc = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
    }
    if (!$favSrc && $link->type === 'biolink') {
        $favSrc = url('favicon.ico');
    }
@endphp
@include('user.partials.page-hero', [
    'title'    => $link->title ?: $link->alias,
    'icon'     => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link',
    'favicon'  => $favSrc,
    'url'      => $link->getShortUrl(),
    'chips'    => [
        ['icon' => 'fa-circle ' . ($link->is_active ? 'text-emerald-400' : 'text-red-400'), 'text' => $link->is_active ? 'Active' : 'Inactive'],
        ['icon' => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link', 'text' => \App\Modules\User\Models\Link::typeLabel($link->type)],
    ],
    'back'     => route('user.links.index'),
    'actions'  => [
        ['label' => '', 'url' => url('/' . $link->alias), 'icon' => 'fa-external-link-alt', 'class' => 'btn-ghost', 'target' => '_blank', 'title' => 'Open in new tab'],
        ['label' => '', 'url' => route('user.links.qrcode', $link), 'icon' => 'fa-qrcode', 'class' => 'btn-ghost', 'title' => 'QR Code'],
        ['label' => '', 'url' => route('user.links.show', $link), 'icon' => 'fa-chart-bar', 'class' => 'btn-ghost', 'title' => 'Analytics'],
    ],
])

<div class="editor-tabs inline-flex items-center gap-1 mb-6 p-1 rounded-full">
    <a href="{{ route('user.links.blocks.editor', $link) }}"
       class="editor-tab no-underline {{ $activeMainTab === 'blocks' ? 'is-active' : '' }}">
        <i class="fas fa-th-large text-[10px]"></i>
        <span>Blocks</span>
    </a>
    <a href="{{ route('user.links.settings.appearance', $link) }}"
       class="editor-tab no-underline {{ $activeMainTab === 'settings' ? 'is-active' : '' }}">
        <i class="fas fa-cog text-[10px]"></i>
        <span>Settings</span>
    </a>
</div>
<style>
    .editor-tabs {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(16px) saturate(140%);
        -webkit-backdrop-filter: blur(16px) saturate(140%);
    }
    .editor-tab {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        color: var(--text-faint);
        border-radius: 9999px;
        transition: color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .editor-tab:hover { color: var(--text-primary); }
    .editor-tab.is-active {
        color: #fff;
        background: linear-gradient(135deg, rgba(167,139,250,0.95), rgba(103,232,249,0.85));
        box-shadow: 0 6px 18px -6px rgba(167,139,250,0.55), 0 2px 8px -2px rgba(103,232,249,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    html.light-mode .editor-tab.is-active {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        box-shadow: 0 4px 14px -4px rgba(124,58,237,0.45);
    }
</style>
