@php
    $activeMainTab = $activeMainTab ?? 'blocks';
    $favSrc = $link->favicon
        ?? ($link->settings['biolink']['favicons']['icon_512'] ?? null)
        ?? ($link->settings['biolink']['favicons']['apple_touch_icon'] ?? null);
    if (!$favSrc && !empty($link->long_url)) {
        $host = parse_url($link->long_url, PHP_URL_HOST);
        if ($host) $favSrc = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
    }
    $shortUrl = $link->getShortUrl();
@endphp

<div class="page-hero mb-4" x-data="{ copied: false }">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
            <a href="{{ route('user.links.index') }}" class="hero-chip" title="Back to links" aria-label="Back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="hero-emblem {{ $favSrc ? 'has-favicon' : '' }}" x-data="{ failed: false }" title="Page favicon">
                @if($favSrc)
                    <img src="{{ $favSrc }}" alt="favicon" class="favicon-img"
                         x-show="!failed" @@error="failed = true">
                    <i x-show="failed" x-cloak class="fas {{ $link->type === 'biolink' ? 'fa-th-large' : 'fa-link' }}" style="color:#7c3aed;"></i>
                @else
                    <i class="fas {{ $link->type === 'biolink' ? 'fa-th-large' : 'fa-link' }}"></i>
                @endif
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <span class="hero-chip">
                        <i class="fas fa-circle {{ $link->is_active ? 'text-emerald-400' : 'text-red-400' }}"></i>
                        {{ $link->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span class="hero-chip"><i class="fas {{ $link->type === 'biolink' ? 'fa-th-large' : 'fa-link' }}"></i> {{ ucfirst($link->type ?? 'link') }}</span>
                </div>
                <h1 class="hero-title gradient-text truncate">{{ $link->title ?: $link->alias }}</h1>
                <div class="flex items-center gap-2 mt-1.5 text-sm">
                    <span style="color: var(--text-dimmed);">Your link is</span>
                    <a href="{{ url('/' . $link->alias) }}" target="_blank" class="text-purple-400 hover:text-purple-300 truncate" style="text-decoration: none;">{{ $shortUrl }}</a>
                    <button type="button" @click="navigator.clipboard.writeText('{{ $shortUrl }}'); copied = true; setTimeout(() => copied = false, 2000)" class="hover:text-purple-400 transition-colors" style="color: var(--text-faint);" title="Copy short URL">
                        <i x-show="!copied" class="fas fa-copy text-xs"></i>
                        <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-xs"></i>
                    </button>
                </div>
                @if(!$favSrc)
                    <a href="{{ route('user.links.edit', $link) }}" class="inline-flex items-center gap-1 text-[10px] mt-1 text-purple-400 hover:text-purple-300 transition-colors">
                        <i class="fas fa-info-circle text-[9px]"></i> No favicon set — upload one to brand your link
                    </a>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
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
</div>

<div class="flex items-center gap-1.5 mb-6 p-1 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
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
