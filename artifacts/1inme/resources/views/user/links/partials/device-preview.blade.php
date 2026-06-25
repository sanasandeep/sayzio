<style>
    .device-switcher-btn {
        width: 36px; height: 36px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px;
        color: var(--text-faint);
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .device-switcher-btn:hover {
        background: var(--bg-glass-hover);
        color: var(--text-muted);
        border-color: rgba(124,58,237,0.15);
    }
    .device-switcher-btn.active {
        background: rgba(124,58,237,0.15);
        color: #a78bfa;
        border-color: rgba(124,58,237,0.3);
        box-shadow: 0 0 12px rgba(124,58,237,0.1);
    }

    /* Manual zoom control — lets creators trade "see the whole device" for
       "read the text" on top of the automatic fit (100% == auto-fit baseline,
       lower values shrink the whole device frame so a tall device fits a short
       editor viewport). */
    .preview-zoom-slider {
        -webkit-appearance: none;
        appearance: none;
        width: 108px;
        height: 4px;
        border-radius: 999px;
        background: var(--border-glass);
        outline: none;
        cursor: pointer;
    }
    .preview-zoom-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 13px; height: 13px;
        border-radius: 50%;
        background: #a78bfa;
        border: 1px solid rgba(167,139,250,0.5);
        box-shadow: 0 0 6px rgba(124,58,237,0.4);
        cursor: pointer;
    }
    .preview-zoom-slider::-moz-range-thumb {
        width: 13px; height: 13px;
        border-radius: 50%;
        background: #a78bfa;
        border: 1px solid rgba(167,139,250,0.5);
        box-shadow: 0 0 6px rgba(124,58,237,0.4);
        cursor: pointer;
    }
    .preview-zoom-reset {
        padding: 1px 7px;
        border-radius: 6px;
        font-size: 9px;
        color: #a78bfa;
        background: rgba(124,58,237,0.12);
        border: 1px solid rgba(124,58,237,0.25);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .preview-zoom-reset:hover { background: rgba(124,58,237,0.2); }

    /* Cohesive preview "shell": groups the device switcher, the simulate
       controls and the phone frame into one panel so the controls read as
       attached to the device instead of floating detached above it. */
    .device-preview-root {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 22px;
        padding: 14px 14px 18px;
    }

    .device-frame-phone {
        width: 320px;
        margin: 0 auto;
    }
    @media (min-width: 1024px) {
        .device-preview-root {
            position: sticky;
            top: 12px;
        }
        /* Render the phone as large as the available height allows (down to a
           readable floor) so block text never collapses to one word per line.
           The iframe is scaled to this width, so a bigger frame == bigger,
           legible text. The scroll container is <main>, whose visible viewport
           excludes the in-app header, so subtract its height (via the shared
           --app-header-h var, defaulting to 4rem) plus the 96px top offset +
           control/padding budget to keep the frame in view. The var keeps this
           in lockstep if the header height ever changes. */
        .device-preview-root .device-frame-phone {
            width: min(360px, max(300px, calc((100vh - var(--app-header-h, 4rem) - 96px) * 375 / 812)));
        }
    }
    /* Activate the side-by-side editor layout (form | device preview) a bit
       earlier than Tailwind's lg breakpoint so the phone stays visible on
       ~960-1023px viewports (common iframe widths). */
    @media (min-width: 900px) and (max-width: 1023.98px) {
        .grid.lg\:grid-cols-12 {
            display: grid !important;
            grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
        }
        .lg\:col-span-7 { grid-column: span 7 / span 7 !important; }
        .lg\:col-span-5 { grid-column: span 5 / span 5 !important; }
        .hidden.lg\:block { display: block !important; }
        .device-preview-root {
            position: sticky;
            top: 12px;
        }
        .device-preview-root .device-frame-phone {
            width: min(340px, max(280px, calc((100vh - var(--app-header-h, 4rem) - 96px) * 375 / 812)));
        }
    }
    .device-frame-phone .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 2rem;
        position: relative;
        aspect-ratio: 375 / 812;
    }
    .device-frame-phone .device-screen iframe {
        width: 375px;
        height: 812px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-frame-tablet {
        width: 100%;
        max-width: 480px;
        margin: 0 auto;
    }
    .device-frame-tablet .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 0.75rem;
        position: relative;
        aspect-ratio: 3 / 4;
    }
    .device-frame-tablet .device-screen iframe {
        width: 768px;
        height: 1024px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-frame-tablet-land {
        width: 100%;
        margin: 0 auto;
    }
    .device-frame-tablet-land .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 0.75rem;
        position: relative;
        aspect-ratio: 4 / 3;
    }
    .device-frame-tablet-land .device-screen iframe {
        width: 1024px;
        height: 768px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-frame-desktop {
        width: 100%;
        margin: 0 auto;
    }
    .device-frame-desktop .device-screen {
        width: 100%;
        overflow: hidden;
        position: relative;
        aspect-ratio: 16 / 10;
    }
    .device-frame-desktop .device-screen iframe {
        width: 1440px;
        height: 900px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-resolution-label {
        text-align: center;
        font-size: 10px;
        color: var(--text-faint);
        margin-top: 8px;
        font-family: 'SF Mono', 'Fira Code', monospace;
        letter-spacing: 0.5px;
        opacity: 0.6;
    }

    /* Inline "preview session expired" banner overlaid on the device screen
       when our background re-mint of the signed URL fails (e.g. dashboard
       session was logged out in another tab). The user can click to retry. */
    .preview-expired-banner {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px;
        text-align: center;
        background: rgba(10, 6, 18, 0.88);
        backdrop-filter: blur(6px);
        color: rgba(255, 255, 255, 0.92);
        font-size: 12px;
        line-height: 1.4;
    }
    .device-screen.preview-expired .preview-expired-banner { display: flex; }

    /* ── Full-screen pop-out preview ─────────────────────────────────────── */
    .preview-popout-overlay {
        position: fixed;
        inset: 0;
        z-index: 2147483600; /* above the dashboard chrome & most widgets */
        display: flex;
        flex-direction: column;
        background: rgba(8, 5, 16, 0.92);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .preview-popout-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 16px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border-glass);
        background: var(--bg-glass);
    }
    .preview-popout-bar-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .preview-popout-bar-group[data-grow] { flex: 1; justify-content: center; }
    .preview-popout-res {
        font-size: 11px;
        color: var(--text-faint);
        font-family: 'SF Mono', 'Fira Code', monospace;
        letter-spacing: 0.5px;
        margin-left: 6px;
        white-space: nowrap;
    }
    .preview-popout-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 9px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 600;
        background: rgba(245,158,11,0.12);
        color: #fbbf24;
        border: 1px solid rgba(245,158,11,0.3);
    }
    .preview-popout-stage {
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow: hidden;
    }
    .preview-popout-frame {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 24px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(124,58,237,0.18);
        background: #000;
    }
    .preview-popout-frame .device-screen {
        width: 100%;
        height: 100%;
    }
    #previewPopoutIframe {
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }
    .preview-expired-banner .preview-expired-title {
        font-size: 13px;
        font-weight: 600;
        color: #fff;
    }
    .preview-expired-banner .preview-expired-msg {
        color: rgba(255, 255, 255, 0.65);
        max-width: 220px;
    }
    .preview-expired-banner button {
        margin-top: 4px;
        padding: 6px 14px;
        border-radius: 8px;
        background: rgba(124, 58, 237, 0.85);
        color: #fff;
        font-size: 12px;
        font-weight: 500;
        border: 1px solid rgba(167, 139, 250, 0.4);
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .preview-expired-banner button:hover { background: rgba(124, 58, 237, 1); }
</style>

<div class="device-preview-root" x-data="{ previewMode: 'phone' }">
    <div id="draftPreviewBadge" class="hidden flex items-center justify-center mb-2">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold" style="background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3);" title="Preview reflects your unsaved edits — click Save Settings to persist them.">
            <i class="fas fa-circle text-[6px]"></i>
            Unsaved preview
        </span>
    </div>
    <div class="flex items-center justify-center gap-1 mb-2">
        <button type="button" @click="previewMode = 'phone'; switchPreviewMode('phone')" class="device-switcher-btn" :class="previewMode === 'phone' ? 'active' : ''" title="Phone">
            <i class="fas fa-mobile-alt"></i>
        </button>
        <button type="button" @click="previewMode = 'tablet'; switchPreviewMode('tablet')" class="device-switcher-btn" :class="previewMode === 'tablet' ? 'active' : ''" title="Tablet Portrait">
            <i class="fas fa-tablet-alt"></i>
        </button>
        <button type="button" @click="previewMode = 'tablet-land'; switchPreviewMode('tablet-land')" class="device-switcher-btn" :class="previewMode === 'tablet-land' ? 'active' : ''" title="Tablet Landscape">
            <i class="fas fa-tablet-alt" style="transform: rotate(-90deg);"></i>
        </button>
        <button type="button" @click="previewMode = 'desktop'; switchPreviewMode('desktop')" class="device-switcher-btn" :class="previewMode === 'desktop' ? 'active' : ''" title="Desktop">
            <i class="fas fa-desktop"></i>
        </button>
        <span class="mx-0.5" style="width:1px; height:20px; background: var(--border-glass);"></span>
        {{-- Pop out the current preview into a full-screen overlay so desktop /
             tablet-landscape pages are readable at a comfortable scale. Opens
             on whatever device mode is currently selected and reuses the same
             signed/draft/simulate-aware URL via _currentPreviewSrc(). --}}
        <button type="button" onclick="openPreviewPopout()" class="device-switcher-btn" title="Open full-screen preview">
            <i class="fas fa-up-right-and-down-left-from-center"></i>
        </button>
    </div>

    {{-- Manual zoom — rescales the visible device frame on top of the
         automatic ResizeObserver fit. 100% is the auto-fit baseline; dragging
         below 100% shrinks the whole device so a tall frame fits a short
         editor viewport. Persisted per-tab in sessionStorage like the
         "Simulate as" controls. --}}
    <div class="flex items-center justify-center gap-2 mb-3 text-[10px]"
         x-data="previewZoom()" x-init="restore()">
        <i class="fas fa-magnifying-glass-minus text-[10px]" style="color: var(--text-faint);" title="Zoom out"></i>
        <input type="range" min="50" max="100" step="5"
               x-model.number="zoom" @input="apply()"
               class="preview-zoom-slider"
               aria-label="Preview zoom" title="Preview zoom">
        <i class="fas fa-magnifying-glass-plus text-[10px]" style="color: var(--text-faint);" title="Zoom in"></i>
        <span class="tabular-nums" style="color: var(--text-muted); min-width: 30px; text-align: right;" x-text="zoom + '%'"></span>
        <button type="button" @click="set(100)" x-show="zoom !== 100" x-cloak class="preview-zoom-reset" title="Reset to auto-fit">Fit</button>
    </div>

    {{-- "Simulate as" controls — let creators preview their per-block geo
         and device targeting rules without spoofing headers or VPN-hopping.
         The chosen sim values get appended as `_sim_country` / `_sim_device`
         query params on the iframe URL; the RedirectController honors them
         only when the owner-signed `?_preview=1` signature still validates. --}}
    <div class="flex flex-wrap items-center justify-center gap-1.5 mb-3 text-[10px]"
         x-data="previewSimulator()"
         x-init="restore()">
        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md" style="color: var(--text-faint); background: rgba(255,255,255,0.03); border: 1px solid var(--border-glass);">
            <i class="fas fa-user-secret text-[9px]"></i> Simulate as
        </span>
        <select x-model="simDevice" @change="apply()"
                class="text-[10px] py-0.5 px-1.5 rounded-md"
                style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
            <option value="">Real device</option>
            <option value="mobile">Mobile</option>
            <option value="tablet">Tablet</option>
            <option value="desktop">Desktop</option>
        </select>
        <input type="text" x-model="simCountry" @change="apply()" @keyup.enter="apply()"
               maxlength="2" placeholder="--"
               class="text-[10px] py-0.5 px-1.5 rounded-md w-12 text-center uppercase tracking-wider"
               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);"
               title="ISO country code (e.g. US, IN, GB) — leave blank for real geo">
        <button type="button" x-show="simDevice || simCountry" @click="clear()"
                class="text-[10px] px-1.5 py-0.5 rounded-md transition"
                style="background: rgba(244,114,182,0.10); color: rgba(251,207,232,0.85); border: 1px solid rgba(244,114,182,0.25);"
                title="Clear simulation">
            <i class="fas fa-times text-[9px]"></i>
        </button>
        <span x-show="simDevice || simCountry" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md"
              style="background: rgba(245,158,11,0.10); color: #fbbf24; border: 1px solid rgba(245,158,11,0.25);">
            <i class="fas fa-eye text-[8px]"></i> simulating
        </span>
    </div>
    <div class="flex justify-center transition-all duration-500 ease-in-out">
        {{-- Phone --}}
        <div x-show="previewMode === 'phone'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="device-frame-phone relative mx-auto">
            <div class="relative bg-black rounded-[2.8rem] p-[10px]" style="border: 2px solid rgba(60,60,70,0.7); box-shadow: 0 10px 28px -10px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.05);">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 z-10 flex items-center justify-center" style="width: 100px; height: 28px; background: #000; border-radius: 0 0 18px 18px;">
                    <div class="rounded-full" style="width: 56px; height: 16px; background: rgba(25,25,30,0.95); border: 1px solid rgba(40,40,50,0.6);"></div>
                </div>
                <div class="absolute top-[6px] right-[22px] z-10 flex items-center gap-1">
                    <div style="width: 14px; height: 8px; border-radius: 2px; border: 1px solid rgba(255,255,255,0.15);"><div style="width: 60%; height: 100%; background: rgba(255,255,255,0.15); border-radius: 1px;"></div></div>
                </div>
                <div class="device-screen relative" style="background: #000;">
                    <iframe class="preview-iframe" data-preview="phone"></iframe>
                    <div class="preview-expired-banner">
                        <div class="preview-expired-title">Preview session expired</div>
                        <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                        <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                    </div>
                </div>
                <div class="absolute bottom-[6px] left-1/2 -translate-x-1/2 rounded-full" style="width: 110px; height: 4px; background: rgba(255,255,255,0.12);"></div>
            </div>
            <div class="device-resolution-label">iPhone 15 &middot; 375 &times; 812</div>
        </div>
        {{-- Tablet Portrait --}}
        <div x-show="previewMode === 'tablet'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="device-frame-tablet relative mx-auto">
            <div class="relative rounded-[1.8rem] shadow-2xl" style="background: linear-gradient(180deg, #2a2a35, #1a1a25); border: 2.5px solid rgba(60,60,70,0.7); box-shadow: 0 20px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);">
                <div class="flex justify-center pt-2 pb-1">
                    <div class="rounded-full" style="width: 8px; height: 8px; background: rgba(30,30,40,0.9); border: 1px solid rgba(60,60,70,0.5); box-shadow: inset 0 1px 2px rgba(0,0,0,0.4);"></div>
                </div>
                <div class="px-3 pb-3">
                    <div class="device-screen rounded-lg" style="background: #000;">
                        <iframe class="preview-iframe" data-preview="tablet"></iframe>
                        <div class="preview-expired-banner">
                            <div class="preview-expired-title">Preview session expired</div>
                            <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                            <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="device-resolution-label">iPad &middot; 768 &times; 1024</div>
        </div>
        {{-- Tablet Landscape --}}
        <div x-show="previewMode === 'tablet-land'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="device-frame-tablet-land relative mx-auto">
            <div class="relative rounded-[1.8rem] shadow-2xl" style="background: linear-gradient(90deg, #2a2a35, #1a1a25); border: 2.5px solid rgba(60,60,70,0.7); box-shadow: 0 20px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);">
                <div class="flex items-center" style="min-height: 100%;">
                    <div class="flex flex-col justify-center items-center px-1.5" style="flex-shrink: 0;">
                        <div class="rounded-full" style="width: 8px; height: 8px; background: rgba(30,30,40,0.9); border: 1px solid rgba(60,60,70,0.5); box-shadow: inset 0 1px 2px rgba(0,0,0,0.4);"></div>
                    </div>
                    <div class="py-3 pr-3 flex-1">
                        <div class="device-screen rounded-lg" style="background: #000;">
                            <iframe class="preview-iframe" data-preview="tablet-land"></iframe>
                            <div class="preview-expired-banner">
                                <div class="preview-expired-title">Preview session expired</div>
                                <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                                <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="device-resolution-label">iPad Landscape &middot; 1024 &times; 768</div>
        </div>
        {{-- Desktop --}}
        <div x-show="previewMode === 'desktop'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="device-frame-desktop relative mx-auto">
            <div class="relative rounded-t-xl shadow-2xl" style="background: linear-gradient(180deg, #2c2c38, #1e1e28); border: 2.5px solid rgba(60,60,70,0.7); border-bottom: none; box-shadow: 0 -2px 40px rgba(0,0,0,0.3);">
                <div class="flex items-center gap-1.5 px-4 py-2" style="border-bottom: 1px solid rgba(60,60,70,0.5);">
                    <div class="w-[10px] h-[10px] rounded-full" style="background: #ff5f57; box-shadow: inset 0 -1px 1px rgba(0,0,0,0.2);"></div>
                    <div class="w-[10px] h-[10px] rounded-full" style="background: #febc2e; box-shadow: inset 0 -1px 1px rgba(0,0,0,0.2);"></div>
                    <div class="w-[10px] h-[10px] rounded-full" style="background: #28c840; box-shadow: inset 0 -1px 1px rgba(0,0,0,0.2);"></div>
                    <div class="flex-1 ml-4 mr-8 rounded-md py-1 px-3 text-[10px] truncate flex items-center gap-1.5" style="background: rgba(0,0,0,0.25); color: rgba(255,255,255,0.4); border: 1px solid rgba(60,60,70,0.4);">
                        <i class="fas fa-lock text-[7px]" style="color: rgba(255,255,255,0.25);"></i>
                        {{ url('/' . $link->alias) }}
                    </div>
                </div>
                <div class="device-screen" style="background: #000;">
                    <iframe class="preview-iframe" data-preview="desktop"></iframe>
                    <div class="preview-expired-banner">
                        <div class="preview-expired-title">Preview session expired</div>
                        <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                        <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                    </div>
                </div>
            </div>
            <div class="mx-auto relative" style="width: 50%; height: 18px; background: linear-gradient(180deg, #2a2a35, #1e1e28); border-radius: 0 0 2px 2px; border: 2px solid rgba(60,60,70,0.5); border-top: 1px solid rgba(60,60,70,0.3);">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-12 h-[3px] rounded-b" style="background: rgba(255,255,255,0.04);"></div>
            </div>
            <div class="mx-auto" style="width: 70%; height: 4px; background: linear-gradient(180deg, #25252f, #1a1a22); border-radius: 0 0 8px 8px; border: 1.5px solid rgba(60,60,70,0.4); border-top: none;"></div>
            <div class="device-resolution-label">MacBook &middot; 1440 &times; 900</div>
        </div>
    </div>
</div>

{{-- ───────────────────────────────────────────────────────────────────────
     Full-screen pop-out preview overlay.

     The in-editor sidebar preview scales a wide (1440px desktop / 1024px
     tablet-landscape) page into a ~400px column, so its text is tiny. This
     overlay re-hosts the SAME signed/draft/simulate-aware preview URL in a
     near-fullscreen stage, fit-scaled to both width and height, so desktop
     and tablet-landscape pages are readable at a comfortable size. It has its
     own device switcher and reuses _currentPreviewSrc(), so draft pushes,
     simulate-as overrides and signed-URL refreshes keep flowing into it.
     ─────────────────────────────────────────────────────────────────────── --}}
<div id="previewPopoutOverlay" class="preview-popout-overlay" style="display:none;" aria-hidden="true">
    <div class="preview-popout-bar">
        <div class="preview-popout-bar-group">
            <i class="fas fa-eye" style="color: var(--text-faint); font-size: 12px;"></i>
            <span style="color: var(--text-muted); font-size: 13px; font-weight: 600;">Full-screen preview</span>
            <span id="previewPopoutDraftPill" class="preview-popout-pill hidden">
                <i class="fas fa-circle text-[6px]"></i> Unsaved
            </span>
        </div>
        <div class="preview-popout-bar-group">
            <button type="button" data-popout-mode="phone" onclick="setPopoutMode('phone')" class="device-switcher-btn" title="Phone">
                <i class="fas fa-mobile-alt"></i>
            </button>
            <button type="button" data-popout-mode="tablet" onclick="setPopoutMode('tablet')" class="device-switcher-btn" title="Tablet Portrait">
                <i class="fas fa-tablet-alt"></i>
            </button>
            <button type="button" data-popout-mode="tablet-land" onclick="setPopoutMode('tablet-land')" class="device-switcher-btn" title="Tablet Landscape">
                <i class="fas fa-tablet-alt" style="transform: rotate(-90deg);"></i>
            </button>
            <button type="button" data-popout-mode="desktop" onclick="setPopoutMode('desktop')" class="device-switcher-btn" title="Desktop">
                <i class="fas fa-desktop"></i>
            </button>
            <span id="previewPopoutLabel" class="preview-popout-res"></span>
        </div>
        <div class="preview-popout-bar-group">
            <a id="previewPopoutNewTab" href="#" target="_blank" rel="noopener" class="device-switcher-btn" title="Open in new tab (native size)">
                <i class="fas fa-up-right-from-square"></i>
            </a>
            <button type="button" onclick="closePreviewPopout()" class="device-switcher-btn" title="Close (Esc)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div id="previewPopoutStage" class="preview-popout-stage">
        <div id="previewPopoutFrame" class="preview-popout-frame">
            <div id="previewPopoutScreen" class="device-screen" style="background:#000; overflow:hidden; position:relative;">
                <iframe id="previewPopoutIframe" title="Full-screen Link in Bio preview"></iframe>
                <div class="preview-expired-banner">
                    <div class="preview-expired-title">Preview session expired</div>
                    <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                    <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    // Owner-scoped, signed preview URL. The RedirectController honours
    // `?_preview=1` + a valid Laravel signature as proof of ownership so the
    // iframe is never gated and never blocked by SameSite/3rd-party-cookie
    // behaviour on a custom domain. 24h expiry is plenty for an editing session.
    // The `_draft` and `_t` query params are appended client-side for the
    // unsaved-edits "draft preview" and are explicitly ignored when
    // validating the signature (see RedirectController).
    $__previewExpiresAt = now()->addHours(24);
    // Relative signature: stays valid no matter which platform host the
    // iframe is loaded on (Replit dev domain, deployed Replit URL, etc.).
    $__previewUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'redirect.handle',
        $__previewExpiresAt,
        ['alias' => $link->alias, '_preview' => 1],
        false
    );
    $__previewRefreshUrl = route('user.links.preview-url', ['link' => $link->id]);
    $__draftPreviewEndpoint = route('user.links.preview-draft', $link);
@endphp
<script>
var _previewUrl = @json($__previewUrl);
var _previewExpiresAt = @json($__previewExpiresAt->getTimestamp()); // epoch seconds
var _previewRefreshEndpoint = @json($__previewRefreshUrl);
var _previewRefreshInFlight = null;
var _previewRefreshTimer = null;
var _draftPreviewUrl = @json($__draftPreviewEndpoint);
var _draftActive = false;
var _activePreviewMode = 'phone';
// Owner-preview simulation overrides — set by the previewSimulator Alpine
// component below when the creator picks a simulated device/country, so
// _currentPreviewSrc() can append `_sim_device=` / `_sim_country=` query
// params to every iframe URL. Persisted in sessionStorage so the choice
// survives accidental page reloads but doesn't leak across tabs.
var _simDevice = '';
var _simCountry = '';
// Manual zoom factor applied on top of the automatic fit (1 == 100% auto-fit
// baseline; 0.5 == half-size so a tall device fits a short editor viewport).
// Persisted per-tab in sessionStorage like the simulate-as controls.
var _previewZoom = 1;
var _deviceViewports = {
    phone:        { w: 375, h: 812 },
    tablet:       { w: 768, h: 1024 },
    'tablet-land': { w: 1024, h: 768 },
    desktop:      { w: 1440, h: 900 }
};

function _scaleSingleIframe(iframe) {
    var mode = iframe.dataset.preview;
    var vp = _deviceViewports[mode];
    if (!vp) return;
    var screen = iframe.closest('.device-screen');
    if (!screen) return;
    var containerW = screen.offsetWidth;
    if (containerW <= 0) return;
    var scale = containerW / vp.w;
    iframe.style.transform = 'scale(' + scale + ')';
}

function _scaleDeviceIframes() {
    document.querySelectorAll('.preview-iframe').forEach(_scaleSingleIframe);
}

// Apply the manual zoom factor to every device frame wrapper. This scales the
// whole device (chrome + screen + already-fitted iframe) uniformly, so it
// composes cleanly with the per-iframe ResizeObserver fit. At 100% the inline
// transform is cleared entirely so the x-transition enter animation is left
// untouched.
function _applyZoom() {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.device-frame-phone, .device-frame-tablet, .device-frame-tablet-land, .device-frame-desktop').forEach(function(el) {
        if (_previewZoom === 1) {
            el.style.transform = '';
            el.style.transition = '';
            el.style.transformOrigin = '';
        } else {
            el.style.transformOrigin = 'top center';
            el.style.transition = reduce ? '' : 'transform 0.2s ease';
            el.style.transform = 'scale(' + _previewZoom + ')';
        }
    });
}

// Per-screen ResizeObserver: re-scale the moment the device-screen actually
// has a non-zero width (after the x-show / x-transition animation finishes).
// Avoids the previous brittle "setTimeout(..., 100)" race where the iframe
// stayed permanently blank because it was measured at width 0.
function _ensureIframeObserved(iframe) {
    var screen = iframe.closest('.device-screen');
    if (!screen || screen.__previewObserved) return;
    screen.__previewObserved = true;
    if (typeof ResizeObserver === 'function') {
        var ro = new ResizeObserver(function() { _scaleSingleIframe(iframe); });
        ro.observe(screen);
    }
}

function _setExpiredBanner(show) {
    document.querySelectorAll('.preview-iframe').forEach(function(f) {
        var screen = f.closest('.device-screen');
        if (!screen) return;
        screen.classList.toggle('preview-expired', !!show);
    });
    var ps = document.getElementById('previewPopoutScreen');
    if (ps) ps.classList.toggle('preview-expired', !!show);
}

// Mint a fresh signed URL via the editor endpoint. When `forceReloadAll` is
// true we also reload every already-loaded iframe so the banner disappears
// the moment the new URL is in hand. Returns a Promise<boolean>.
function refreshPreviewSignedUrl(forceReloadAll) {
    if (_previewRefreshInFlight) return _previewRefreshInFlight;
    _previewRefreshInFlight = fetch(_previewRefreshEndpoint, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function(res) {
        if (!res.ok) throw new Error('preview-url HTTP ' + res.status);
        return res.json();
    }).then(function(json) {
        if (!json || !json.url) throw new Error('preview-url malformed response');
        _previewUrl = json.url;
        if (json.expires_at) _previewExpiresAt = json.expires_at;
        _setExpiredBanner(false);
        _schedulePreviewRefresh();
        if (forceReloadAll) {
            // Reload every iframe that already had a src so the new signed
            // URL takes effect immediately on every device mode.
            document.querySelectorAll('.preview-iframe').forEach(function(f) {
                if (f.src && f.src !== 'about:blank' && f.src !== window.location.href) {
                    _reloadIframe(f);
                }
            });
            if (_isPopoutOpen()) _reloadPopoutIframe();
        }
        return true;
    }).catch(function(err) {
        // Most common failure: dashboard session was lost (auth redirect to
        // login -> JSON parse fail) or network is offline. Show the inline
        // banner so the user knows to reload, instead of letting the iframe
        // silently drift into Laravel's "Invalid signature" page.
        _setExpiredBanner(true);
        return false;
    }).finally(function() {
        _previewRefreshInFlight = null;
    });
    return _previewRefreshInFlight;
}

// Schedule a proactive refresh ~5 minutes before the current signed URL
// expires. Falls back to "right now" if the URL has already expired (e.g.
// the laptop was asleep past the 24h window).
function _schedulePreviewRefresh() {
    if (_previewRefreshTimer) {
        clearTimeout(_previewRefreshTimer);
        _previewRefreshTimer = null;
    }
    if (!_previewExpiresAt) return;
    var nowSec = Math.floor(Date.now() / 1000);
    var leadSec = 300; // refresh 5 min before expiry
    var delayMs = Math.max(0, (_previewExpiresAt - leadSec - nowSec) * 1000);
    // Cap at ~24 days — setTimeout silently fires immediately past INT32_MAX.
    if (delayMs > 2147483000) delayMs = 2147483000;
    _previewRefreshTimer = setTimeout(function() { refreshPreviewSignedUrl(true); }, delayMs);
}

function _currentPreviewSrc() {
    // Draft mode appends `&_draft=1&_t=<ts>` so the iframe re-fetches the
    // server-rendered page with the cached unsaved overrides applied. The
    // signature ignores both params (see RedirectController).
    var url = _draftActive ? (_previewUrl + '&_draft=1&_t=' + Date.now()) : _previewUrl;
    if (_simDevice)  url += '&_sim_device='  + encodeURIComponent(_simDevice);
    if (_simCountry) url += '&_sim_country=' + encodeURIComponent(_simCountry);
    return url;
}

// Alpine component backing the "Simulate as" toolbar. Picks are persisted
// in sessionStorage so a tab-local refresh keeps the simulation, but new
// tabs (and other creators on shared devices) start clean.
function previewSimulator() {
    return {
        simDevice: '',
        simCountry: '',
        restore() {
            try {
                this.simDevice  = sessionStorage.getItem('_simDevice')  || '';
                this.simCountry = (sessionStorage.getItem('_simCountry') || '').toUpperCase();
            } catch (e) { /* sessionStorage disabled (private mode) — no-op */ }
            _simDevice  = this.simDevice;
            _simCountry = this.simCountry;
        },
        apply() {
            // Country must be a 2-letter ISO code (or blank). Anything else
            // is silently dropped so we don't ship junk into the URL.
            var cc = (this.simCountry || '').toUpperCase();
            if (cc && !/^[A-Z]{2}$/.test(cc)) cc = '';
            this.simCountry = cc;
            _simDevice  = this.simDevice  || '';
            _simCountry = cc;
            try {
                if (_simDevice)  sessionStorage.setItem('_simDevice', _simDevice);  else sessionStorage.removeItem('_simDevice');
                if (_simCountry) sessionStorage.setItem('_simCountry', _simCountry); else sessionStorage.removeItem('_simCountry');
            } catch (e) { /* ignore */ }
            // Re-render every loaded iframe so the new simulation takes
            // effect everywhere immediately. Hidden iframes get marked
            // stale and reload on next activation (cheap; matches draft
            // preview behaviour).
            document.querySelectorAll('.preview-iframe').forEach(function(f) {
                if (!f.src || f.src === 'about:blank' || f.src === window.location.href) return;
                if (f.dataset.preview === _activePreviewMode) {
                    _reloadIframe(f);
                } else {
                    f.dataset.previewStale = '1';
                }
            });
            if (_isPopoutOpen()) _reloadPopoutIframe();
        },
        clear() {
            this.simDevice = '';
            this.simCountry = '';
            this.apply();
        },
    };
}

function _reloadIframe(f) {
    // If the URL has already expired by the time we try to reload (long sleep
    // / browser was backgrounded past the 24h window), block this reload and
    // kick off a fresh-URL fetch instead — otherwise we'd just paint Laravel's
    // "Invalid signature" page into the iframe.
    var nowSec = Math.floor(Date.now() / 1000);
    if (_previewExpiresAt && nowSec >= _previewExpiresAt) {
        refreshPreviewSignedUrl(true);
        return;
    }
    // Always assign to .src (instead of contentWindow.location.replace) so
    // the iframe element's `src` attribute reflects the live URL — observable
    // by tests, debug tools, and any parent-window JS that inspects it. The
    // iframe is same-origin (proxy-routed) so this triggers a normal
    // navigation; we don't lean on history.replaceState semantics here since
    // each draft push already cache-busts via the &_t=… param.
    f.src = _currentPreviewSrc();
    f.dataset.previewStale = '';
}

function _ensureIframeLoaded(mode) {
    var f = document.querySelector('.preview-iframe[data-preview="' + mode + '"]');
    if (!f) return null;
    var nowSec = Math.floor(Date.now() / 1000);
    var expired = _previewExpiresAt && nowSec >= _previewExpiresAt;
    if (expired) {
        // URL has already expired (long-asleep tab) — fetch a fresh one before
        // the iframe ever sees the bad signature.
        refreshPreviewSignedUrl(true);
        _ensureIframeObserved(f);
        _scaleSingleIframe(f);
        return f;
    }
    if (!f.src || f.src === 'about:blank' || f.src === window.location.href) {
        f.src = _currentPreviewSrc();
        f.dataset.previewStale = '';
    } else if (f.dataset.previewStale === '1') {
        // The page was edited while this device mode was hidden — reload
        // now so the user doesn't see stale content on mode switch.
        _reloadIframe(f);
    }
    _ensureIframeObserved(f);
    _scaleSingleIframe(f);
    return f;
}

function switchPreviewMode(mode) {
    _activePreviewMode = mode;
    // Ensure the requested mode has a src; leave OTHER iframes alone instead
    // of resetting them to about:blank — switching back and forth was causing
    // a permanently-blank frame because the just-shown iframe was measured at
    // width 0 before its container animated in. Stale iframes are reloaded
    // on activation rather than on every edit (cheaper, no flash).
    _ensureIframeLoaded(mode);
    // Re-assert the manual zoom on the freshly shown frame (x-transition may
    // have settled the inline transform back to its enter value).
    _applyZoom();
}

// Alpine component backing the manual zoom slider. Mirrors previewSimulator's
// sessionStorage persistence so the zoom survives a tab-local refresh but
// doesn't leak across tabs.
function previewZoom() {
    return {
        zoom: 100,
        restore() {
            try {
                var z = parseInt(sessionStorage.getItem('_previewZoom') || '100', 10);
                if (!isNaN(z) && z >= 50 && z <= 100) this.zoom = z;
            } catch (e) { /* sessionStorage disabled (private mode) — no-op */ }
            _previewZoom = this.zoom / 100;
            _applyZoom();
        },
        apply() {
            var z = parseInt(this.zoom, 10);
            if (isNaN(z)) z = 100;
            z = Math.max(50, Math.min(100, Math.round(z / 5) * 5));
            this.zoom = z;
            _previewZoom = z / 100;
            try {
                if (z === 100) sessionStorage.removeItem('_previewZoom');
                else sessionStorage.setItem('_previewZoom', String(z));
            } catch (e) { /* ignore */ }
            _applyZoom();
        },
        set(z) {
            this.zoom = z;
            this.apply();
        },
    };
}

function refreshPreview() {
    // Reload the currently visible iframe immediately. Mark all other
    // already-loaded iframes as stale so switchPreviewMode() reloads them
    // when they next become active — keeps every device mode in sync with
    // the latest edit without re-fetching backgrounds the user can't see.
    document.querySelectorAll('.preview-iframe').forEach(function(f) {
        if (!f.src || f.src === 'about:blank' || f.src === window.location.href) return;
        if (f.dataset.preview === _activePreviewMode) {
            _reloadIframe(f);
        } else {
            f.dataset.previewStale = '1';
        }
    });
}

// ----------------------------------------------------------------------------
// Live "draft preview" — push unsaved page-settings form changes to the cache
// endpoint and reload the visible iframe with `_draft=1` so the owner can see
// colour/font/theme/layout tweaks without clicking "Save Settings" first.
// Wired up generically to any form whose action targets `/page-settings`, so
// it covers Appearance, Layout, Block Theme and Advanced settings pages.
// ----------------------------------------------------------------------------
var _draftPushTimer = null;
var _draftLastSent = 0;

function _csrfTokenForDraft() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

function pushDraftPreview(form, opts) {
    if (!form || !_draftPreviewUrl) return;
    var fd = new FormData();
    form.querySelectorAll('input, select, textarea').forEach(function(el) {
        if (!el.name) return;
        if (el.type === 'file') return;             // files are skipped — only saved files preview
        if (el.disabled) return;
        if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
        fd.append(el.name, el.value);
    });
    fd.append('_token', _csrfTokenForDraft());

    fetch(_draftPreviewUrl, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
        credentials: 'same-origin'
    }).then(function(r) {
        if (!r.ok) return null;
        return r.json();
    }).then(function(data) {
        if (data && data.success) {
            _draftActive = true;
            _showDraftBadge();
            // Reload the currently visible iframe (and mark hidden ones stale).
            document.querySelectorAll('.preview-iframe').forEach(function(f) {
                if (f.dataset.preview === _activePreviewMode) {
                    _reloadIframe(f);
                } else {
                    f.dataset.previewStale = '1';
                }
            });
            // Pop-out is always visible while open, so keep it live.
            if (_isPopoutOpen()) _reloadPopoutIframe();
            var pill = document.getElementById('previewPopoutDraftPill');
            if (pill) pill.classList.remove('hidden');
        }
    }).catch(function() { /* silent — keep editing */ });
}

function _scheduleDraftPush(form) {
    if (_draftPushTimer) clearTimeout(_draftPushTimer);
    _draftPushTimer = setTimeout(function() { pushDraftPreview(form); }, 500);
}

function _bindDraftPreviewToForm(form) {
    if (form.__draftPreviewBound) return;
    form.__draftPreviewBound = true;

    function onAnyChange() { _scheduleDraftPush(form); }

    form.addEventListener('input', onAnyChange);
    form.addEventListener('change', onAnyChange);

    // After the form's normal submit (Save Settings) succeeds, the saved
    // values become the new baseline — drop draft mode so the next reload
    // shows the persisted page (no longer needs the cached overrides).
    form.addEventListener('submit', function() {
        if (_draftPushTimer) { clearTimeout(_draftPushTimer); _draftPushTimer = null; }
        _draftActive = false;
        _hideDraftBadge();
    });
}

function _findDraftPreviewForms() {
    var forms = document.querySelectorAll('form[action*="/preview-draft"], form[action*="/page-settings"]');
    forms.forEach(function(form) {
        // Don't bind to the preview-draft endpoint itself (defensive).
        if (form.action.indexOf('/preview-draft') !== -1) return;
        _bindDraftPreviewToForm(form);
    });
}

// Tiny "Draft preview" pill so creators know the iframe reflects unsaved
// changes. Lives next to the device switcher, hidden until the first push.
function _showDraftBadge() {
    var badge = document.getElementById('draftPreviewBadge');
    if (!badge) return;
    badge.classList.remove('hidden');
}
function _hideDraftBadge() {
    var badge = document.getElementById('draftPreviewBadge');
    if (!badge) return;
    badge.classList.add('hidden');
}

// ----------------------------------------------------------------------------
// Full-screen pop-out preview. Re-hosts _currentPreviewSrc() in a large stage
// so wide (desktop / tablet-landscape) pages are legible. It has its own device
// mode (_popoutMode) independent of the sidebar, fit-scales the iframe to both
// the available width AND height, and is kept in sync with draft/sim/signed-URL
// refreshes via the hooks added below.
// ----------------------------------------------------------------------------
var _popoutMode = 'phone';
var _popoutResLabels = {
    phone:        'iPhone 15 · 375 × 812',
    tablet:       'iPad · 768 × 1024',
    'tablet-land': 'iPad Landscape · 1024 × 768',
    desktop:      'MacBook · 1440 × 900'
};

function _isPopoutOpen() {
    var o = document.getElementById('previewPopoutOverlay');
    return !!o && o.style.display !== 'none';
}

function _scalePopoutIframe() {
    if (!_isPopoutOpen()) return;
    var vp = _deviceViewports[_popoutMode];
    if (!vp) return;
    var stage = document.getElementById('previewPopoutStage');
    var frame = document.getElementById('previewPopoutFrame');
    var iframe = document.getElementById('previewPopoutIframe');
    if (!stage || !frame || !iframe) return;
    var pad = 8; // breathing room inside the stage padding
    var availW = Math.max(1, stage.clientWidth - pad);
    var availH = Math.max(1, stage.clientHeight - pad);
    // Fit to both axes; never upscale past native (keeps text crisp). Even at
    // 1:1 a 1440px desktop page is far larger than the ~400px sidebar column.
    var scale = Math.min(availW / vp.w, availH / vp.h, 1);
    frame.style.width  = Math.round(vp.w * scale) + 'px';
    frame.style.height = Math.round(vp.h * scale) + 'px';
    iframe.style.width  = vp.w + 'px';
    iframe.style.height = vp.h + 'px';
    iframe.style.transform = 'scale(' + scale + ')';
}

function _syncPopoutModeButtons() {
    document.querySelectorAll('[data-popout-mode]').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.popoutMode === _popoutMode);
    });
    var label = document.getElementById('previewPopoutLabel');
    if (label) label.textContent = _popoutResLabels[_popoutMode] || '';
}

function _reloadPopoutIframe() {
    var iframe = document.getElementById('previewPopoutIframe');
    if (!iframe) return;
    iframe.src = _currentPreviewSrc();
    var link = document.getElementById('previewPopoutNewTab');
    if (link) link.href = _currentPreviewSrc();
}

function setPopoutMode(mode) {
    if (!_deviceViewports[mode]) return;
    _popoutMode = mode;
    _syncPopoutModeButtons();
    // Same URL across modes — only the iframe's logical size changes, which
    // re-triggers the page's responsive layout. No reload needed.
    _scalePopoutIframe();
}

function openPreviewPopout() {
    var overlay = document.getElementById('previewPopoutOverlay');
    if (!overlay) return;
    // Open on whatever device mode the sidebar is currently showing.
    _popoutMode = _activePreviewMode || 'phone';
    // Mirror the sidebar's draft state into the pop-out pill.
    var pill = document.getElementById('previewPopoutDraftPill');
    if (pill) pill.classList.toggle('hidden', !_draftActive);
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    _syncPopoutModeButtons();
    _reloadPopoutIframe();
    // Scale once layout has settled (stage now has real dimensions).
    requestAnimationFrame(function() { requestAnimationFrame(_scalePopoutIframe); });
}

function closePreviewPopout() {
    var overlay = document.getElementById('previewPopoutOverlay');
    if (!overlay) return;
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    // Drop the iframe so it stops being reloaded by draft/refresh loops while
    // hidden, and to free the embedded page.
    var iframe = document.getElementById('previewPopoutIframe');
    if (iframe) iframe.src = 'about:blank';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && _isPopoutOpen()) closePreviewPopout();
});

document.addEventListener('DOMContentLoaded', function() {
    _schedulePreviewRefresh();
    _ensureIframeLoaded('phone');
    _findDraftPreviewForms();
    // Forms can be injected after load (e.g. tab swaps); rescan on a light
    // mutation observer so we don't miss them.
    var mo = new MutationObserver(function() { _findDraftPreviewForms(); });
    mo.observe(document.body, { childList: true, subtree: true });
});

window.addEventListener('resize', function() {
    clearTimeout(window._resizeScaleTimer);
    window._resizeScaleTimer = setTimeout(function() {
        _scaleDeviceIframes();
        _scalePopoutIframe();
    }, 150);
});

// Tab-return safety net: if the laptop was asleep / tab was hidden long
// enough for the URL to expire, mint a new one the moment the user comes
// back so they don't first see a broken preview frame.
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState !== 'visible') return;
    var nowSec = Math.floor(Date.now() / 1000);
    if (_previewExpiresAt && nowSec >= (_previewExpiresAt - 60)) {
        refreshPreviewSignedUrl(true);
    }
});
</script>
