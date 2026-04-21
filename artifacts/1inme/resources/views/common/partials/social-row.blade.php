{{--
    Reusable admin-managed social-media icon row. Hidden when no
    social links are configured. Reads from AppSetting using the
    same SitePagesContent::socialNetworks() source as the public footer.
--}}
@php
    $__sNets = \App\Modules\Common\Support\SitePagesContent::socialNetworks();
    $__sLinks = [];
    foreach ($__sNets as $__sKey => $__sMeta) {
        $__sUrl = trim((string) \App\Modules\Admin\Models\AppSetting::get($__sKey, ''));
        if ($__sUrl !== '') {
            $__sLinks[] = ['url' => $__sUrl, 'label' => $__sMeta['label'], 'icon' => $__sMeta['icon']];
        }
    }
@endphp

@if(!empty($__sLinks))
    <div class="flex flex-wrap items-center justify-center gap-3">
        @foreach($__sLinks as $__sLink)
            <a href="{{ $__sLink['url'] }}"
               target="_blank"
               rel="noopener noreferrer nofollow"
               aria-label="{{ $__sLink['label'] }}"
               title="{{ $__sLink['label'] }}"
               class="w-9 h-9 rounded-full flex items-center justify-center bg-white/5 border border-white/10 text-gray-300 hover:text-white hover:bg-white/10 transition">
                <i class="fa-brands {{ $__sLink['icon'] }} text-sm"></i>
            </a>
        @endforeach
    </div>
@endif
