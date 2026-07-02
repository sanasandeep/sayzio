{{-- Reusable Google Play / App Store badge buttons.
     Reads the admin-configured store URLs (marketing settings). When a URL is
     configured the badge links straight to the store in a new tab; when it is
     missing the badge opens the shared "coming soon" modal instead (rendered
     once per page via @once below, driven by a window event so it works from
     any include point — footer, homepage dialer section, etc.). --}}
@php
    $__storePlayUrl = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_play_store_url', ''));
    $__storeAppUrl  = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_app_store_url', ''));
    $__storeBadges = [
        ['key' => 'play', 'url' => $__storePlayUrl, 'icon' => 'fab fa-google-play', 'kicker' => 'GET IT ON',        'label' => 'Google Play'],
        ['key' => 'app',  'url' => $__storeAppUrl,  'icon' => 'fab fa-apple',       'kicker' => 'Download on the',  'label' => 'App Store'],
    ];
@endphp
<div class="store-badges flex flex-wrap items-center gap-3">
    @foreach($__storeBadges as $__b)
        @if($__b['url'] !== '')
            <a href="{{ $__b['url'] }}" target="_blank" rel="noopener noreferrer"
               class="store-badge" aria-label="{{ $__b['label'] }}">
                <i class="{{ $__b['icon'] }} store-badge-icon" aria-hidden="true"></i>
                <span class="store-badge-text">
                    <span class="store-badge-kicker">{{ $__b['kicker'] }}</span>
                    <span class="store-badge-label">{{ $__b['label'] }}</span>
                </span>
            </a>
        @else
            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-store-coming-soon',{detail:{store:'{{ $__b['key'] }}'}}))"
                    class="store-badge" aria-label="{{ $__b['label'] }} — coming soon"
                    aria-haspopup="dialog">
                <i class="{{ $__b['icon'] }} store-badge-icon" aria-hidden="true"></i>
                <span class="store-badge-text">
                    <span class="store-badge-kicker">{{ $__b['kicker'] }}</span>
                    <span class="store-badge-label">{{ $__b['label'] }}</span>
                </span>
            </button>
        @endif
    @endforeach
</div>

@once
<style>
    /* Store badges — dark pill buttons that hold up on both the near-black
       footer and light-mode marketing pages. */
    .store-badge {
        display: inline-flex; align-items: center; gap: 10px;
        padding: 9px 16px 9px 13px; border-radius: 12px;
        background: #0d0f17; border: 1px solid rgba(255,255,255,.18);
        color: #fff; text-align: left; cursor: pointer;
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }
    .store-badge:hover {
        transform: translateY(-2px); border-color: rgba(61,107,255,.55);
        box-shadow: 0 12px 28px -12px rgba(61,107,255,.55);
    }
    .store-badge-icon { font-size: 20px; flex-shrink: 0; }
    .store-badge-text { display: flex; flex-direction: column; line-height: 1.15; }
    .store-badge-kicker { font-size: 9px; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.6); font-weight: 600; }
    .store-badge-label { font-size: 14px; font-weight: 700; color: #fff; }
    html.light-mode .store-badge { background: #111827; border-color: rgba(17,24,39,.9); }

    /* Coming-soon modal */
    .store-cs-card {
        background: #171b28; border: 1px solid rgba(255,255,255,.1);
        color: #fff;
    }
    html.light-mode .store-cs-card { background: #ffffff; border-color: rgba(15,23,42,.12); color: #0f172a; }
    .store-cs-muted { color: rgba(255,255,255,.6); }
    html.light-mode .store-cs-muted { color: rgba(15,23,42,.6); }
    .store-cs-close { background: rgba(255,255,255,.1); color: #fff; }
    .store-cs-close:hover { background: rgba(255,255,255,.2); }
    html.light-mode .store-cs-close { background: rgba(15,23,42,.08); color: #0f172a; }
    html.light-mode .store-cs-close:hover { background: rgba(15,23,42,.16); }
    .store-cs-feat {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 12px; border-radius: 12px;
        background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
        font-size: 12.5px; font-weight: 600;
    }
    html.light-mode .store-cs-feat { background: rgba(15,23,42,.04); border-color: rgba(15,23,42,.08); }
    .store-cs-feat i { color: #1bd4d9; width: 16px; text-align: center; }

    /* Mini phone mockup inside the modal — adapted from the homepage dialer
       phone-frame style, kept lightweight (pure CSS, no images). */
    .store-cs-phone {
        position: relative; width: 190px; max-width: 60vw; margin: 0 auto;
        aspect-ratio: 300 / 600; border-radius: 30px; padding: 8px;
        background: linear-gradient(160deg, #1b1030, #0c0718);
        box-shadow:
            0 30px 70px -26px rgba(61,107,255,.55),
            0 10px 26px -10px rgba(0,0,0,.7),
            inset 0 0 0 1.5px rgba(255,255,255,.08);
    }
    .store-cs-screen {
        position: absolute; inset: 8px; border-radius: 23px; overflow: hidden;
        background: linear-gradient(180deg, #14091f 0%, #0a0a14 100%);
        display: flex; flex-direction: column; padding: 26px 12px 12px;
    }
    .store-cs-notch {
        position: absolute; top: 6px; left: 50%; transform: translateX(-50%);
        width: 58px; height: 14px; border-radius: 999px; background: #05030a; z-index: 5;
    }
    .store-cs-row {
        display: flex; align-items: center; gap: 8px;
        border-radius: 12px; padding: 8px;
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
        margin-bottom: 8px;
    }
    .store-cs-ava {
        width: 26px; height: 26px; border-radius: 9px; flex-shrink: 0;
        background: linear-gradient(135deg, #3d6bff, #1bd4d9);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 800; font-size: 10px;
    }
    .store-cs-line { height: 6px; border-radius: 999px; background: rgba(255,255,255,.22); }
    .store-cs-line.thin { height: 5px; background: rgba(255,255,255,.12); margin-top: 4px; }
    .store-cs-cta {
        margin-top: auto; height: 30px; border-radius: 999px;
        background: linear-gradient(135deg, #3d6bff, #1bd4d9);
        display: flex; align-items: center; justify-content: center; gap: 6px;
        color: #fff; font-weight: 700; font-size: 10px;
    }
</style>
{{-- Coming-soon modal — teleported to <body> so it is never trapped inside a
     transformed / opacity-animated / overflow-hidden ancestor (e.g. the dialer
     section's .reveal wrapper on the homepage).  Opened from any store badge
     via the open-store-coming-soon window event. Self-contained Alpine scope. --}}
<template x-teleport="body">
<div x-data="{ open: false, store: 'play' }"
     @open-store-coming-soon.window="store = $event.detail && $event.detail.store ? $event.detail.store : 'play'; open = true"
     x-show="open" x-cloak
     class="fixed inset-0 z-[120] overflow-y-auto overscroll-contain bg-black/70 backdrop-blur-sm"
     @click.self="open = false"
     @keydown.escape.window="open = false"
     role="dialog" aria-modal="true" aria-label="Sayzio mobile app — coming soon">
    <div class="min-h-full flex items-center justify-center p-4" @click.self="open = false">
        <div class="store-cs-card relative w-full max-w-2xl my-8 rounded-2xl shadow-2xl overflow-hidden">
            <button type="button" @click="open = false"
                    class="store-cs-close absolute top-3 right-3 z-10 w-9 h-9 rounded-full flex items-center justify-center transition"
                    aria-label="Close">
                <i class="fas fa-times text-sm"></i>
            </button>

            <div class="grid sm:grid-cols-2">
                {{-- Left: mini phone mockup --}}
                <div class="hidden sm:flex items-center justify-center p-8"
                     style="background: radial-gradient(circle at 30% 20%, rgba(61,107,255,.25), transparent 60%), radial-gradient(circle at 75% 80%, rgba(27,212,217,.18), transparent 55%), #0b0616;">
                    <div class="store-cs-phone" role="img" aria-label="Preview of the Sayzio mobile app">
                        <div class="store-cs-screen">
                            <div class="store-cs-notch" aria-hidden="true"></div>
                            <div class="store-cs-row">
                                <div class="store-cs-ava">AR</div>
                                <div class="flex-1 min-w-0">
                                    <div class="store-cs-line" style="width: 70%;"></div>
                                    <div class="store-cs-line thin" style="width: 50%;"></div>
                                </div>
                                <i class="fas fa-circle-check text-[10px]" style="color:#3d6bff;"></i>
                            </div>
                            <div class="store-cs-row">
                                <div class="store-cs-ava" style="background: linear-gradient(135deg, #e94e8c, #ff8a3c);">QR</div>
                                <div class="flex-1 min-w-0">
                                    <div class="store-cs-line" style="width: 62%;"></div>
                                    <div class="store-cs-line thin" style="width: 44%;"></div>
                                </div>
                                <i class="fas fa-qrcode text-[10px] text-white/50"></i>
                            </div>
                            <div class="store-cs-row">
                                <div class="store-cs-ava" style="background: linear-gradient(135deg, #22c55e, #1bd4d9);">
                                    <i class="fas fa-chart-line text-[9px]"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="store-cs-line" style="width: 76%;"></div>
                                    <div class="store-cs-line thin" style="width: 58%;"></div>
                                </div>
                            </div>
                            <div class="store-cs-cta"><i class="fas fa-link text-[9px]"></i> Share your link</div>
                        </div>
                    </div>
                </div>

                {{-- Right: copy --}}
                <div class="p-6 sm:p-8 flex flex-col justify-center">
                    <span class="inline-flex items-center gap-1.5 self-start px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider"
                          style="background: rgba(61,107,255,.14); color: #6d92ff; border: 1px solid rgba(61,107,255,.35);">
                        <i class="fas fa-rocket text-[10px]"></i> Coming soon
                    </span>
                    <h3 class="text-xl sm:text-2xl font-bold mt-3 leading-tight">
                        The Sayzio app is
                        <span x-text="store === 'play' ? 'coming to Google Play' : 'coming to the App Store'"></span>
                    </h3>
                    <p class="store-cs-muted text-sm mt-2.5 leading-relaxed">
                        Manage your links, biolinks and QR codes, chat with your audience and watch
                        your stats live — all from your pocket. We're putting the finishing touches
                        on the mobile app right now.
                    </p>
                    <div class="grid gap-2 mt-4">
                        <div class="store-cs-feat"><i class="fas fa-link"></i> Create &amp; edit links on the go</div>
                        <div class="store-cs-feat"><i class="fas fa-chart-line"></i> Live analytics in your pocket</div>
                        <div class="store-cs-feat"><i class="fas fa-phone-volume"></i> Smart dialer &amp; caller ID</div>
                    </div>
                    <p class="store-cs-muted text-xs mt-4">
                        <i class="fas fa-bell mr-1 text-[10px]"></i>
                        Check back soon — the download buttons will go live the moment the app ships.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</template>
@endonce
