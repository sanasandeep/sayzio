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
        ['icon' => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link', 'text' => ucfirst($link->type ?? 'link')],
    ],
    'back'     => route('user.links.index'),
    'actions'  => [
        ['label' => '', 'url' => url('/' . $link->alias), 'icon' => 'fa-external-link-alt', 'class' => 'btn-ghost', 'target' => '_blank', 'title' => 'Open in new tab'],
        ['label' => '', 'url' => route('user.links.qrcode', $link), 'icon' => 'fa-qrcode', 'class' => 'btn-ghost', 'title' => 'QR Code'],
        ['label' => '', 'url' => route('user.links.show', $link), 'icon' => 'fa-chart-bar', 'class' => 'btn-ghost', 'title' => 'Analytics'],
    ],
])

<div class="flex items-center gap-1.5 mb-6 p-1 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
    <a href="{{ route('user.links.blocks.editor', $link) }}"
       class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold py-2.5 rounded-lg transition-all no-underline {{ $activeMainTab === 'blocks' ? 'text-white shadow-sm' : '' }}"
       style="{{ $activeMainTab === 'blocks' ? 'background: linear-gradient(135deg, #3e97ff, #1b84ff);' : 'color: var(--text-faint);' }}">
        <i class="fas fa-th-large text-[10px]"></i>
        <span>Blocks</span>
    </a>
    <a href="{{ route('user.links.settings.appearance', $link) }}"
       class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold py-2.5 rounded-lg transition-all no-underline {{ $activeMainTab === 'settings' ? 'text-white shadow-sm' : '' }}"
       style="{{ $activeMainTab === 'settings' ? 'background: linear-gradient(135deg, #3e97ff, #1b84ff);' : 'color: var(--text-faint);' }}">
        <i class="fas fa-cog text-[10px]"></i>
        <span>Settings</span>
    </a>
</div>
