<style>
    .device-switcher-btn {
        width: 32px; height: 32px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px;
        color: var(--text-faint);
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .device-switcher-btn:hover {
        background: var(--bg-glass-hover);
        color: var(--text-muted);
        border-color: rgba(139,92,246,0.15);
    }
    .device-switcher-btn.active {
        background: rgba(139,92,246,0.15);
        color: #a78bfa;
        border-color: rgba(139,92,246,0.3);
        box-shadow: 0 0 12px rgba(139,92,246,0.1);
    }

    .sp-device-frame-phone {
        width: 240px;
        margin: 0 auto;
    }
    .sp-device-frame-phone .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 2rem;
        position: relative;
        aspect-ratio: 375 / 812;
    }
    .sp-device-frame-phone .device-screen iframe {
        width: 375px;
        height: 812px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .sp-device-frame-tablet {
        width: 100%;
        max-width: 360px;
        margin: 0 auto;
    }
    .sp-device-frame-tablet .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 0.75rem;
        position: relative;
        aspect-ratio: 3 / 4;
    }
    .sp-device-frame-tablet .device-screen iframe {
        width: 768px;
        height: 1024px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .sp-device-frame-desktop {
        width: 100%;
        margin: 0 auto;
    }
    .sp-device-frame-desktop .device-screen {
        width: 100%;
        overflow: hidden;
        position: relative;
        aspect-ratio: 16 / 10;
    }
    .sp-device-frame-desktop .device-screen iframe {
        width: 1440px;
        height: 900px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .sp-resolution-label {
        text-align: center;
        font-size: 10px;
        color: var(--text-faint);
        margin-top: 6px;
        font-family: 'SF Mono', 'Fira Code', monospace;
        letter-spacing: 0.5px;
        opacity: 0.6;
    }
</style>

<div x-data="{ previewMode: 'phone' }">
    <div class="flex items-center justify-center gap-1 mb-3">
        <button type="button" @click="previewMode = 'phone'; spSwitchPreview('phone')" class="device-switcher-btn" :class="previewMode === 'phone' ? 'active' : ''" title="Phone">
            <i class="fas fa-mobile-alt"></i>
        </button>
        <button type="button" @click="previewMode = 'tablet'; spSwitchPreview('tablet')" class="device-switcher-btn" :class="previewMode === 'tablet' ? 'active' : ''" title="Tablet">
            <i class="fas fa-tablet-alt"></i>
        </button>
        <button type="button" @click="previewMode = 'desktop'; spSwitchPreview('desktop')" class="device-switcher-btn" :class="previewMode === 'desktop' ? 'active' : ''" title="Desktop">
            <i class="fas fa-desktop"></i>
        </button>
    </div>
    <div class="flex justify-center transition-all duration-500 ease-in-out">
        <div x-show="previewMode === 'phone'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="sp-device-frame-phone relative mx-auto">
            <div class="absolute -inset-3 rounded-[3.5rem] animate-pulse-glow" style="background: linear-gradient(135deg, rgba(139,92,246,0.12), rgba(168,85,247,0.06)); filter: blur(24px);"></div>
            <div class="relative bg-black rounded-[2.8rem] p-[8px] shadow-2xl" style="border: 2px solid rgba(60,60,70,0.8); box-shadow: 0 24px 80px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.06), 0 0 0 1px rgba(0,0,0,0.3);">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 z-10 flex items-center justify-center" style="width: 80px; height: 22px; background: #000; border-radius: 0 0 14px 14px;">
                    <div class="rounded-full" style="width: 44px; height: 12px; background: rgba(25,25,30,0.95); border: 1px solid rgba(40,40,50,0.6);"></div>
                </div>
                <div class="device-screen relative" style="background: #000;">
                    <iframe class="sp-preview-iframe" data-preview="phone"></iframe>
                </div>
                <div class="absolute bottom-[4px] left-1/2 -translate-x-1/2 rounded-full" style="width: 90px; height: 3px; background: rgba(255,255,255,0.12);"></div>
            </div>
            <div class="sp-resolution-label">iPhone 15 &middot; 375 &times; 812</div>
        </div>
        <div x-show="previewMode === 'tablet'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="sp-device-frame-tablet relative mx-auto">
            <div class="relative rounded-[1.8rem] shadow-2xl" style="background: linear-gradient(180deg, #2a2a35, #1a1a25); border: 2px solid rgba(60,60,70,0.7); box-shadow: 0 20px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);">
                <div class="flex justify-center pt-2 pb-1">
                    <div class="rounded-full" style="width: 6px; height: 6px; background: rgba(30,30,40,0.9); border: 1px solid rgba(60,60,70,0.5);"></div>
                </div>
                <div class="px-2.5 pb-2.5">
                    <div class="device-screen rounded-lg" style="background: #000;">
                        <iframe class="sp-preview-iframe" data-preview="tablet"></iframe>
                    </div>
                </div>
            </div>
            <div class="sp-resolution-label">iPad &middot; 768 &times; 1024</div>
        </div>
        <div x-show="previewMode === 'desktop'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="sp-device-frame-desktop relative mx-auto">
            <div class="relative rounded-t-xl shadow-2xl" style="background: linear-gradient(180deg, #2c2c38, #1e1e28); border: 2px solid rgba(60,60,70,0.7); border-bottom: none;">
                <div class="flex items-center gap-1.5 px-3 py-1.5" style="border-bottom: 1px solid rgba(60,60,70,0.5);">
                    <div class="w-[8px] h-[8px] rounded-full" style="background: #ff5f57;"></div>
                    <div class="w-[8px] h-[8px] rounded-full" style="background: #febc2e;"></div>
                    <div class="w-[8px] h-[8px] rounded-full" style="background: #28c840;"></div>
                    <div class="flex-1 ml-3 mr-6 rounded-md py-0.5 px-2 text-[9px] truncate flex items-center gap-1" style="background: rgba(0,0,0,0.25); color: rgba(255,255,255,0.4); border: 1px solid rgba(60,60,70,0.4);">
                        <i class="fas fa-lock text-[6px]" style="color: rgba(255,255,255,0.25);"></i>
                        {{ url('/' . $link->alias) }}
                    </div>
                </div>
                <div class="device-screen" style="background: #000;">
                    <iframe class="sp-preview-iframe" data-preview="desktop"></iframe>
                </div>
            </div>
            <div class="mx-auto relative" style="width: 50%; height: 14px; background: linear-gradient(180deg, #2a2a35, #1e1e28); border-radius: 0 0 2px 2px; border: 2px solid rgba(60,60,70,0.5); border-top: 1px solid rgba(60,60,70,0.3);"></div>
            <div class="mx-auto" style="width: 70%; height: 3px; background: linear-gradient(180deg, #25252f, #1a1a22); border-radius: 0 0 8px 8px; border: 1.5px solid rgba(60,60,70,0.4); border-top: none;"></div>
            <div class="sp-resolution-label">MacBook &middot; 1440 &times; 900</div>
        </div>
    </div>
</div>

<script>
var _spPreviewUrl = '{{ url("/" . $link->alias) }}';
var _spActiveMode = 'phone';
var _spViewports = {
    phone:   { w: 375, h: 812 },
    tablet:  { w: 768, h: 1024 },
    desktop: { w: 1440, h: 900 }
};

function _spScaleIframes() {
    document.querySelectorAll('.sp-preview-iframe').forEach(function(iframe) {
        var mode = iframe.dataset.preview;
        var vp = _spViewports[mode];
        if (!vp) return;
        var screen = iframe.closest('.device-screen');
        if (!screen) return;
        var containerW = screen.offsetWidth;
        if (containerW <= 0) return;
        iframe.style.transform = 'scale(' + (containerW / vp.w) + ')';
    });
}

function spSwitchPreview(mode) {
    _spActiveMode = mode;
    document.querySelectorAll('.sp-preview-iframe').forEach(function(f) {
        if (f.dataset.preview === mode) {
            if (!f.src || f.src === 'about:blank' || f.src === '') {
                f.src = _spPreviewUrl;
            }
        } else {
            f.src = 'about:blank';
        }
    });
    setTimeout(_spScaleIframes, 50);
}

document.addEventListener('DOMContentLoaded', function() {
    var phoneFrame = document.querySelector('.sp-preview-iframe[data-preview="phone"]');
    if (phoneFrame) phoneFrame.src = _spPreviewUrl;
    setTimeout(_spScaleIframes, 100);
});

window.addEventListener('resize', function() {
    clearTimeout(window._spResizeTimer);
    window._spResizeTimer = setTimeout(_spScaleIframes, 150);
});
</script>
