@php
    $__socialNetworks = \App\Modules\Common\Support\SitePagesContent::socialNetworks();
    $__socialLinks = [];
    foreach ($__socialNetworks as $__sKey => $__sMeta) {
        $__sUrl = trim((string) \App\Modules\Admin\Models\AppSetting::get($__sKey, ''));
        if ($__sUrl !== '') {
            $__socialLinks[] = ['url' => $__sUrl, 'label' => $__sMeta['label'], 'icon' => $__sMeta['icon']];
        }
    }
@endphp
@if(!empty($__socialLinks))
    <div class="flex flex-wrap items-center {{ $justify ?? 'justify-center' }} gap-2">
        @foreach($__socialLinks as $__sLink)
            <a href="{{ $__sLink['url'] }}"
               target="_blank"
               rel="noopener noreferrer nofollow"
               aria-label="{{ $__sLink['label'] }}"
               title="{{ $__sLink['label'] }}"
               class="w-8 h-8 rounded-full flex items-center justify-center transition"
               style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-muted);"
               onmouseover="this.style.color='var(--text-primary)'; this.style.background='var(--bg-glass-hover)';"
               onmouseout="this.style.color='var(--text-muted)'; this.style.background='var(--bg-glass)';">
                <i class="fa-brands {{ $__sLink['icon'] }} text-xs"></i>
            </a>
        @endforeach
    </div>
@endif
