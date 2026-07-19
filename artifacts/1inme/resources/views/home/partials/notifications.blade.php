{{-- ============================ NOTIFICATIONS MANAGEMENT ============================ --}}
<style>
    .nf-wrap { position: relative; }
    .nf-mesh::before {
        content:""; position:absolute; inset:-20%;
        background: rgba(27,212,217,.06);
        filter: blur(40px); pointer-events:none; z-index:0;
        animation: nfMesh 14s ease-in-out infinite alternate;
    }
    @keyframes nfMesh { 0% { transform: translate3d(0,0,0) scale(1); } 100% { transform: translate3d(-2%,2%,0) scale(1.06); } }

    /* Notification panel mock */
    .nf-panel {
        position: relative; max-width: 420px; margin: 0 auto;
        border-radius: 22px; overflow: hidden;
        background: linear-gradient(180deg, rgba(20,24,36,.96) 0%, rgba(14,17,26,.98) 100%);
        border: 1px solid rgba(255,255,255,.08);
        box-shadow: 0 30px 80px -30px rgba(27,212,217,.45), 0 12px 30px -10px rgba(0,0,0,.6);
    }
    .nf-panel-head {
        padding: 16px 18px; display:flex; align-items:center; gap:10px;
        border-bottom: 1px solid rgba(255,255,255,.06);
        background: linear-gradient(135deg, rgba(27,212,217,.12), rgba(61,107,255,.10));
    }
    .nf-bell {
        width: 38px; height: 38px; border-radius: 12px; flex-shrink:0;
        display:flex; align-items:center; justify-content:center; color:#fff;
        background: linear-gradient(135deg,#1bd4d9,#3d6bff);
        box-shadow: 0 8px 22px -8px rgba(27,212,217,.7);
    }
    .nf-bell i { animation: nfRing 4s ease-in-out infinite; transform-origin: top center; }
    @keyframes nfRing {
        0%, 70%, 100% { transform: rotate(0); }
        75% { transform: rotate(14deg); } 80% { transform: rotate(-12deg); }
        85% { transform: rotate(8deg); } 90% { transform: rotate(-6deg); }
    }
    .nf-row {
        display:flex; align-items:flex-start; gap:12px; padding: 13px 18px;
        border-bottom: 1px solid rgba(255,255,255,.04);
        transition: background .3s ease;
        opacity: 0; transform: translateY(8px);
        animation: nfIn .55s ease forwards;
    }
    .nf-row:hover { background: rgba(255,255,255,.03); }
    .nf-row:nth-child(1){ animation-delay:.15s; } .nf-row:nth-child(2){ animation-delay:.35s; }
    .nf-row:nth-child(3){ animation-delay:.55s; } .nf-row:nth-child(4){ animation-delay:.75s; }
    @keyframes nfIn { to { opacity:1; transform: translateY(0); } }
    .nf-ico {
        width: 34px; height: 34px; border-radius: 10px; flex-shrink:0;
        display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px;
    }
    .nf-unread { width: 8px; height: 8px; border-radius:50%; background:#1bd4d9; margin-left:auto; margin-top:6px; flex-shrink:0; box-shadow: 0 0 10px #1bd4d9; }

    .nf-toast {
        position: absolute; right: -14px; padding: 10px 13px; border-radius: 14px;
        background: rgba(15,18,28,.92); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,.08); color:#fff;
        font-size: 11px; font-weight: 700; display:flex; align-items:center; gap:8px;
        box-shadow: 0 18px 40px -16px rgba(0,0,0,.7);
        animation: nfBubble 6s ease-in-out infinite;
    }
    .nf-toast-1 { top: -16px; animation-delay: .3s; }
    .nf-toast-2 { bottom: 8%; left: -18px; right:auto; animation-delay: 1.8s; }
    @keyframes nfBubble { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

    /* channel matrix chip */
    .nf-ch { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:5px 10px; border-radius:999px; border:1px solid rgba(255,255,255,.1); }

    @media (prefers-reduced-motion: reduce) {
        .nf-mesh::before, .nf-bell i, .nf-toast { animation: none !important; }
        .nf-row { opacity:1 !important; transform:none !important; animation: none !important; }
    }
</style>
<section id="notifications" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="nf-h">
    <div class="nf-mesh absolute inset-0 pointer-events-none" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Notifications</div>
            <h2 id="nf-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Never miss what matters.<br><span class="grad-text">You choose how you hear it.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A unified notification feed plus in-app, email and mobile push alerts, with a per-event preferences matrix so every follower, order, mention or security alert reaches you exactly the way you want.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- LEFT: features --}}
            <div class="space-y-4 order-2 lg:order-1">
                @foreach([
                    ['fa-bell',            '#1bd4d9', 'One unified feed',            'Every alert in one place &mdash; new followers, DMs, orders, mentions and system events. Mark all read, or dismiss and restore from a 30-day history.'],
                    ['fa-sliders',         '#3d6bff', 'Per-event preferences',       'A full matrix of 20+ event types. Toggle <span class="text-white font-semibold">in-app</span>, <span class="text-white font-semibold">email</span> and <span class="text-white font-semibold">push</span> independently for each one.'],
                    ['fa-paper-plane',     '#e94e8c', 'Email &amp; mobile push',     'Transactional emails, weekly digests you can schedule by day and hour, and mobile push that deep-links you straight to the action.'],
                    ['fa-shield-halved',   '#ff8a3c', 'Stay ahead of problems',      'Proactive alerts for suspicious logins, broken social connections, custom-domain DNS drift and API usage thresholds.'],
                ] as $i => $f)
                    <div class="reveal rd-{{ ($i % 4) + 1 }} rb-feat glass rounded-2xl p-4 flex items-start gap-4">
                        <div class="rb-feat-icon" style="--rb-c: {{ $f[1] }};"><i class="fas {{ $f[0] }}"></i></div>
                        <div class="min-w-0">
                            <div class="text-base font-bold mb-1">{!! $f[2] !!}</div>
                            <div class="text-sm text-gray-400 leading-relaxed">{!! $f[3] !!}</div>
                        </div>
                    </div>
                @endforeach

                <div class="reveal rd-4 pt-3 flex flex-wrap items-center gap-3">
                    <a href="{{ route('site.notifications') }}" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Explore notifications <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>

            {{-- RIGHT: animated notification panel --}}
            <div class="reveal rd-3 nf-wrap relative order-1 lg:order-2">
                <div class="nf-panel" role="img" aria-label="Notification feed preview">
                    <div class="nf-panel-head">
                        <div class="nf-bell"><i class="fas fa-bell"></i></div>
                        <div>
                            <div class="text-sm font-bold text-white leading-tight">Notifications</div>
                            <div class="text-[11px] text-gray-400">4 new · all channels</div>
                        </div>
                        <span class="ml-auto text-[11px] font-semibold text-cyan-300">Mark all read</span>
                    </div>
                    <div>
                        <div class="nf-row">
                            <span class="nf-ico" style="background:linear-gradient(135deg,#1bd4d9,#22d3ee);"><i class="fas fa-user-plus"></i></span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-white leading-tight">Priya started following you</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">In-app · just now</div>
                            </div>
                            <span class="nf-unread"></span>
                        </div>
                        <div class="nf-row">
                            <span class="nf-ico" style="background:linear-gradient(135deg,#3d6bff,#6e61ff);"><i class="fas fa-utensils"></i></span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-white leading-tight">New order · Table 6</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">Push · 2m ago</div>
                            </div>
                            <span class="nf-unread"></span>
                        </div>
                        <div class="nf-row">
                            <span class="nf-ico" style="background:linear-gradient(135deg,#e94e8c,#ff8a3c);"><i class="fas fa-at"></i></span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-white leading-tight">You were mentioned in a comment</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">In-app · 18m ago</div>
                            </div>
                        </div>
                        <div class="nf-row">
                            <span class="nf-ico" style="background:linear-gradient(135deg,#ff8a3c,#f59e0b);"><i class="fas fa-shield-halved"></i></span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-white leading-tight">Suspicious login blocked</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">Email + in-app · 1h ago</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nf-toast nf-toast-1" aria-hidden="true">
                    <i class="fas fa-mobile-screen" style="color:#1bd4d9;"></i> Push delivered
                </div>
                <div class="nf-toast nf-toast-2" aria-hidden="true">
                    <i class="fas fa-check" style="color:#22c55e;"></i> Digest scheduled · Mon 9am
                </div>
            </div>
        </div>
    </div>
</section>
