@php
    $activeMainTab = $activeMainTab ?? 'blocks';
    // Icon comes from the shared link-type catalog so it stays in step with
    // the rest of the app (links list, create picker, etc.).
    $__typeIcon = \App\Modules\User\Support\LinkTypeCategories::types()[$link->type]['icon'] ?? 'fa-link';
    $favSrc = $link->favicon
        ?? ($link->settings['biolink']['favicons']['icon_512'] ?? null)
        ?? ($link->settings['biolink']['favicons']['apple_touch_icon'] ?? null);
    if (!$favSrc && !empty($link->long_url)) {
        $host = parse_url($link->long_url, PHP_URL_HOST);
        if ($host) $favSrc = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
    }
    if (!$favSrc && $link->isBiolinkFamily()) {
        $favSrc = url('favicon.ico');
    }
@endphp
@include('user.partials.page-hero', [
    'title'    => $link->title ?: $link->alias,
    'icon'     => $__typeIcon,
    'favicon'  => $favSrc,
    'url'      => $link->getShortUrl(),
    'chips'    => [
        ['icon' => 'fa-circle ' . ($link->is_active ? 'text-emerald-400' : 'text-red-400'), 'text' => $link->is_active ? 'Active' : 'Inactive'],
        ['icon' => $__typeIcon, 'text' => \App\Modules\User\Models\Link::typeLabel($link->type)],
    ],
    'back'     => route('user.links.index'),
    'actions'  => [
        ['label' => '', 'url' => url('/' . $link->alias), 'icon' => 'fa-external-link-alt', 'class' => 'btn-ghost', 'target' => '_blank', 'title' => 'Open in new tab'],
        ['label' => '', 'url' => route('user.links.qrcode', $link), 'icon' => 'fa-qrcode', 'class' => 'btn-ghost', 'title' => 'QR Code'],
        ['label' => '', 'url' => route('user.links.show', $link), 'icon' => 'fa-chart-bar', 'class' => 'btn-ghost', 'title' => 'Analytics'],
    ],
])

@if(!($hideEditorTabs ?? false))
<div class="editor-tabs-row mb-6">
    <div class="editor-tabs inline-flex items-center gap-1 p-1 rounded-full">
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
        @if($link->type === 'conversational')
        <a href="{{ route('user.links.conversational.editor', $link) }}"
           class="editor-tab no-underline {{ $activeMainTab === 'conversational' ? 'is-active' : '' }}">
            <i class="fas fa-comments text-[10px]"></i>
            <span>Conversational</span>
        </a>
        @elseif($link->type === 'slides')
        <a href="{{ route('user.links.slides.editor', $link) }}"
           class="editor-tab no-underline {{ $activeMainTab === 'slides' ? 'is-active' : '' }}">
            <i class="fas fa-images text-[10px]"></i>
            <span>Slides</span>
        </a>
        @elseif($link->type === 'ai_chat')
        <a href="{{ route('user.links.ai-chat.editor', $link) }}"
           class="editor-tab no-underline {{ $activeMainTab === 'ai_chat' ? 'is-active' : '' }}">
            <i class="fas fa-robot text-[10px]"></i>
            <span>AI Chat</span>
        </a>
        @endif
    </div>
</div>
@endif
<style>
    .editor-tabs-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .editor-tabs {
        max-width: 100%;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
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
    .editor-tabs::-webkit-scrollbar { display: none; }
    .editor-tab { flex: 0 0 auto; }
    .editor-tab:hover { color: var(--text-primary); }
    @media (max-width: 640px) {
        .editor-tabs-row { gap: 8px; }
        .editor-tab { padding: 7px 13px; }
    }
    .editor-tab.is-active {
        color: #fff;
        background: linear-gradient(135deg, rgba(144,172,255,0.95), rgba(103,232,249,0.85));
        box-shadow: 0 6px 18px -6px rgba(144,172,255,0.55), 0 2px 8px -2px rgba(103,232,249,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    html.light-mode .editor-tab.is-active {
        background: linear-gradient(135deg, #5c83ff, #3d6bff);
        box-shadow: 0 4px 14px -4px rgba(61,107,255,0.45);
    }
</style>
