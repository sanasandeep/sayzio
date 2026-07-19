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
                    class="store-badge" aria-label="{{ $__b['label'] }} (coming soon)"
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

    /* Notify-me email capture inside the modal */
    .store-cs-notify-input {
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.14);
        color: #fff; outline: none;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .store-cs-notify-input::placeholder { color: rgba(255,255,255,.35); }
    .store-cs-notify-input:focus {
        border-color: rgba(61,107,255,.65);
        box-shadow: 0 0 0 3px rgba(61,107,255,.2);
    }
    html.light-mode .store-cs-notify-input {
        background: rgba(15,23,42,.04); border-color: rgba(15,23,42,.16); color: #0f172a;
    }
    html.light-mode .store-cs-notify-input::placeholder { color: rgba(15,23,42,.4); }
    .store-cs-notify-btn {
        background: linear-gradient(135deg, #3d6bff, #1bd4d9); color: #fff; border: none;
        cursor: pointer; transition: transform .2s ease, box-shadow .2s ease, opacity .2s ease;
    }
    .store-cs-notify-btn:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px -10px rgba(61,107,255,.65);
    }
    .store-cs-notify-btn:disabled { opacity: .6; cursor: default; }
    .store-cs-notify-done {
        background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); color: #e7fbef;
    }
    html.light-mode .store-cs-notify-done { background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.35); color: #14532d; }

    /* Phone frame that wraps the real app screenshot.
       The frame itself keeps the on-brand purple glow + dark bezel.
       The screenshot fills the screen area (inset 8px) with border-radius
       to mimic the rounded display — no CSS-drawn content needed. */
    .store-cs-phone {
        position: relative; width: 190px; max-width: 60vw; margin: 0 auto;
        aspect-ratio: 300 / 600; border-radius: 30px; padding: 8px;
        background: linear-gradient(160deg, #1b1030, #0c0718);
        box-shadow:
            0 30px 70px -26px rgba(61,107,255,.55),
            0 10px 26px -10px rgba(0,0,0,.7),
            inset 0 0 0 1.5px rgba(255,255,255,.08);
        overflow: hidden;
    }
    .store-cs-notch {
        position: absolute; top: 6px; left: 50%; transform: translateX(-50%);
        width: 58px; height: 14px; border-radius: 999px; background: #05030a; z-index: 5;
    }
    /* Real screenshot fills the bezel area inside the phone frame. */
    .store-cs-screenshot {
        position: absolute; inset: 8px; border-radius: 23px;
        width: calc(100% - 16px); height: calc(100% - 16px);
        object-fit: cover; object-position: top center;
        display: block;
    }
    /* Graceful fallback shown only if the screenshot fails to load, so the
       phone frame never renders an empty dark rectangle. */
    .store-cs-screen-fallback {
        position: absolute; inset: 8px; border-radius: 23px;
        display: none;
        flex-direction: column; align-items: center; justify-content: center; gap: 8px;
        text-align: center; padding: 16px;
        background: linear-gradient(160deg, #3d6bff 0%, #1bd4d9 100%);
    }
    .store-cs-screen-fallback .store-cs-wordmark {
        font-family: 'Space Grotesk', system-ui, sans-serif;
        font-weight: 700; font-size: 22px; letter-spacing: -0.02em; color: #fff;
    }
    .store-cs-screen-fallback .store-cs-wordmark-sub {
        font-size: 11px; font-weight: 600; color: rgba(255,255,255,.85);
        text-transform: uppercase; letter-spacing: .08em;
    }
</style>
{{-- Coming-soon modal — teleported to <body> so it is never trapped inside a
     transformed / opacity-animated / overflow-hidden ancestor (e.g. the dialer
     section's .reveal wrapper on the homepage).  Opened from any store badge
     via the open-store-coming-soon window event. Self-contained Alpine scope.

     The empty x-data on the <template> is REQUIRED: Alpine.start() only
     initializes trees rooted at [x-data] elements, and this partial is included
     in plain (non-Alpine) markup — without its own x-data root the x-teleport
     directive is never processed and the modal silently never mounts.
     Guarded by tests/Browser/store-coming-soon-modal.spec.ts. --}}
<template x-data x-teleport="body">
<div x-data="{
        open: false, store: 'play',
        nEmail: '', nHp: '', nBusy: false, nDone: false, nMsg: '', nErr: '',
        async notifySubmit() {
            if (this.nBusy || this.nDone) return;
            this.nErr = '';
            if (!this.nEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.nEmail)) {
                this.nErr = 'Please enter a valid email address.';
                return;
            }
            this.nBusy = true;
            try {
                const res = await fetch(@js(route('site.app-launch.notify')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @js(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ email: this.nEmail, store: this.store, website: this.nHp }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    this.nDone = true;
                    this.nMsg = data.message || 'You\'re on the list!';
                } else {
                    this.nErr = (data && data.message) ? data.message : 'Something went wrong. Please try again.';
                }
            } catch (e) {
                this.nErr = 'Something went wrong. Please try again.';
            } finally {
                this.nBusy = false;
            }
        },
     }"
     @open-store-coming-soon.window="store = $event.detail && $event.detail.store ? $event.detail.store : 'play'; open = true"
     x-show="open" x-cloak
     class="fixed inset-0 z-[120] overflow-y-auto overscroll-contain bg-black/70 backdrop-blur-sm"
     @click.self="open = false"
     @keydown.escape.window="open = false"
     role="dialog" aria-modal="true" aria-label="Sayzio and Dialer apps (coming soon)">
    <div class="min-h-full flex items-center justify-center p-4" @click.self="open = false">
        <div class="store-cs-card relative w-full max-w-2xl my-8 rounded-2xl shadow-2xl overflow-hidden">
            <button type="button" @click="open = false"
                    class="store-cs-close absolute top-3 right-3 z-10 w-9 h-9 rounded-full flex items-center justify-center transition"
                    aria-label="Close">
                <i class="fas fa-times text-sm"></i>
            </button>

            <div class="grid sm:grid-cols-2">
                {{-- Left: real Sayzio app screenshot inside an on-brand phone frame. --}}
                <div class="hidden sm:flex items-center justify-center p-8"
                     style="background: radial-gradient(circle at 30% 20%, rgba(61,107,255,.25), transparent 60%), radial-gradient(circle at 75% 80%, rgba(27,212,217,.18), transparent 55%), #0b0616;">
                    <div class="store-cs-phone" role="img" aria-label="Screenshot of the Sayzio mobile app showing the links dashboard">
                        <div class="store-cs-notch" aria-hidden="true"></div>
                        <img src="{{ asset('img/app-screenshot-sayzio.png') }}"
                             alt="Sayzio app dashboard: links list with click counts and stat tiles"
                             class="store-cs-screenshot"
                             loading="lazy" decoding="async"
                             width="390" height="690"
                             onerror="this.style.display='none'; var fb=this.parentElement.querySelector('.store-cs-screen-fallback'); if (fb) fb.style.display='flex';">
                        <div class="store-cs-screen-fallback" aria-hidden="true">
                            <span class="store-cs-wordmark">Sayzio</span>
                            <span class="store-cs-wordmark-sub">Coming soon</span>
                        </div>
                    </div>
                </div>

                {{-- Right: copy — promotes both Sayzio app and the Dialer app. --}}
                <div class="p-6 sm:p-8 flex flex-col justify-center">
                    <span class="inline-flex items-center gap-1.5 self-start px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider"
                          style="background: rgba(61,107,255,.14); color: #6d92ff; border: 1px solid rgba(61,107,255,.35);">
                        <i class="fas fa-rocket text-[10px]"></i> Coming soon
                    </span>
                    <h3 class="text-xl sm:text-2xl font-bold mt-3 leading-tight">
                        Sayzio &amp; Dialer are
                        <span x-text="store === 'play' ? 'coming to Google Play' : 'coming to the App Store'"></span>
                    </h3>
                    <p class="store-cs-muted text-sm mt-2.5 leading-relaxed">
                        Two powerful apps in one ecosystem. <strong>Sayzio</strong> lets you manage
                        links, biolinks and QR codes and watch your stats live from your pocket.
                        <strong>Dialer</strong> brings a smart T9 dialer and caller&nbsp;ID that
                        resolves any number to a full Sayzio profile, so you always know who's calling.
                    </p>
                    <div class="grid gap-2 mt-4">
                        <div class="store-cs-feat"><i class="fas fa-link"></i> Create &amp; manage links, biolinks &amp; QR codes</div>
                        <div class="store-cs-feat"><i class="fas fa-chart-line"></i> Live analytics &amp; performance insights</div>
                        <div class="store-cs-feat"><i class="fas fa-phone-alt"></i> Smart dialer with T9 search</div>
                        <div class="store-cs-feat"><i class="fas fa-id-badge"></i> Caller ID resolved to Sayzio profiles</div>
                    </div>
                    {{-- Notify-me email capture — posts to the public app-launch
                         list (rate-limited + honeypot server-side) and shows the
                         success state inline without closing the modal. --}}
                    <form class="mt-4" @submit.prevent="notifySubmit()" x-show="!nDone" novalidate>
                        <label class="store-cs-muted text-xs font-semibold block mb-1.5" for="store-cs-notify-email">
                            <i class="fas fa-bell mr-1 text-[10px]"></i>
                            Get an email the moment the apps ship
                        </label>
                        <div class="flex items-stretch gap-2">
                            <input type="email" id="store-cs-notify-email" x-model="nEmail"
                                   placeholder="you@example.com" autocomplete="email" required
                                   class="store-cs-notify-input flex-1 min-w-0 px-3 py-2 rounded-lg text-sm"
                                   :disabled="nBusy">
                            <button type="submit" :disabled="nBusy"
                                    class="store-cs-notify-btn px-4 py-2 rounded-lg text-sm font-bold whitespace-nowrap">
                                <span x-show="!nBusy">Notify me</span>
                                <span x-show="nBusy"><i class="fas fa-circle-notch fa-spin"></i></span>
                            </button>
                        </div>
                        {{-- Honeypot — hidden from humans, tempting to bots. --}}
                        <input type="text" x-model="nHp" name="website" tabindex="-1" autocomplete="off"
                               aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
                        <p class="text-xs mt-2" style="color:#f87171;" x-show="nErr" x-text="nErr"></p>
                        <p class="store-cs-muted text-[11px] mt-2">
                            One email at launch, no spam, ever.
                        </p>
                    </form>
                    <div class="store-cs-notify-done mt-4 px-3.5 py-3 rounded-xl text-sm flex items-start gap-2.5" x-show="nDone" x-cloak>
                        <i class="fas fa-circle-check mt-0.5" style="color:#22c55e;"></i>
                        <span x-text="nMsg"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</template>
@endonce
