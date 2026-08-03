{{-- ============================ FORM BUILDER ============================ --}}
<style>
    .fb-wrap { position: relative; }
    .fb-mesh::before {
        content:""; position:absolute; inset:-20%;
        background: radial-gradient(ellipse 75% 75% at 50% 50%, rgba(61,107,255,.08), transparent 78%);
        pointer-events:none; z-index:0;
        animation: fbMesh 14s ease-in-out infinite alternate;
    }
    @keyframes fbMesh { 0% { transform: translate3d(0,0,0) scale(1); } 100% { transform: translate3d(2%,2%,0) scale(1.06); } }

    /* Form card mock */
    .fb-card {
        position: relative; max-width: 400px; margin: 0 auto;
        border-radius: 22px; overflow: hidden;
        background: linear-gradient(180deg, #ffffff 0%, #f5f7fb 100%);
        color: #0f172a;
        box-shadow: 0 30px 80px -30px rgba(61,107,255,.5), 0 12px 30px -10px rgba(0,0,0,.55), inset 0 0 0 1px rgba(255,255,255,.6);
    }
    .fb-card-head {
        padding: 20px 22px 16px; color:#fff; position:relative; overflow:hidden;
        background: linear-gradient(135deg, #3d6bff 0%, #6e61ff 60%, #22d3ee 130%);
    }
    .fb-card-head::before {
        content:""; position:absolute; inset:0;
        background: radial-gradient(60% 80% at 80% 20%, rgba(255,255,255,.25), transparent 60%);
    }
    .fb-field { padding: 0 22px; }
    .fb-label { font-size: 11px; font-weight: 700; color:#64748b; margin-bottom: 5px; display:block; }
    .fb-input {
        height: 38px; border-radius: 10px; border: 1.5px solid #e2e8f0; background:#fff;
        display:flex; align-items:center; padding: 0 12px; font-size: 12px; color:#94a3b8;
    }
    .fb-input.is-focus { border-color:#3d6bff; box-shadow: 0 0 0 3px rgba(61,107,255,.14); color:#0f172a; }
    .fb-caret { width:1.5px; height:16px; background:#3d6bff; margin-left:1px; animation: fbCaret 1.1s steps(1) infinite; }
    @keyframes fbCaret { 50% { opacity:0; } }
    .fb-opt { display:flex; align-items:center; gap:8px; font-size:12px; color:#475569; }
    .fb-radio { width:16px; height:16px; border-radius:50%; border:2px solid #cbd5e1; flex-shrink:0; }
    .fb-radio.on { border-color:#3d6bff; background: radial-gradient(circle, #3d6bff 0 45%, transparent 50%); }
    .fb-submit {
        margin: 4px 22px 20px; height: 40px; border-radius: 10px; color:#fff; font-weight:700; font-size:13px;
        display:flex; align-items:center; justify-content:center; gap:8px;
        background: linear-gradient(135deg,#3d6bff,#2342c7);
        box-shadow: 0 12px 26px -10px rgba(61,107,255,.7);
    }

    /* floating field-type chips */
    .fb-chip {
        position:absolute; padding:7px 11px; border-radius:12px; font-size:11px; font-weight:700;
        background: rgba(15,18,28,.9); backdrop-filter: blur(10px); color:#fff;
        border:1px solid rgba(255,255,255,.1); display:flex; align-items:center; gap:7px;
        box-shadow: 0 18px 40px -16px rgba(0,0,0,.7);
        animation: fbFloat 6.5s ease-in-out infinite;
    }
    .fb-chip-1 { top:-14px; right:-18px; animation-delay:.2s; }
    .fb-chip-2 { bottom:22%; left:-26px; animation-delay:1.4s; }
    .fb-chip-3 { bottom:-12px; right:8%; animation-delay:2.6s; }
    @keyframes fbFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-9px); } }

    @media (prefers-reduced-motion: reduce) {
        .fb-mesh::before, .fb-caret, .fb-chip { animation: none !important; }
    }
</style>
<section id="form-builder" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="fb-h">
    <div class="fb-mesh absolute inset-0 pointer-events-none" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c2,#6e61ff)">Form Builder</div>
            <h2 id="fb-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Collect anything.<br><span class="grad-text">Right from your page.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A drag-and-drop form builder with 21 field types, full design control, and instant email, SMS and webhook alerts on every submission, embeddable in any biolink in seconds.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- LEFT: animated form card --}}
            <div class="reveal rd-3 fb-wrap relative h-[480px] sm:h-[520px] flex items-center">
                <div class="fb-card" role="img" aria-label="Form builder preview">
                    <div class="fb-card-head">
                        <div class="relative text-base font-bold leading-tight">Book a discovery call</div>
                        <div class="relative text-[11px] opacity-90 mt-0.5">Takes under a minute</div>
                    </div>
                    <div class="py-4 space-y-3.5">
                        <div class="fb-field">
                            <span class="fb-label">Full name</span>
                            <div class="fb-input is-focus">Jordan Avery<span class="fb-caret"></span></div>
                        </div>
                        <div class="fb-field">
                            <span class="fb-label">Email address</span>
                            <div class="fb-input">you@studio.com</div>
                        </div>
                        <div class="fb-field">
                            <span class="fb-label">What do you need?</span>
                            <div class="space-y-2 mt-1">
                                <div class="fb-opt"><span class="fb-radio on"></span> Brand &amp; web design</div>
                                <div class="fb-opt"><span class="fb-radio"></span> Motion &amp; video</div>
                            </div>
                        </div>
                    </div>
                    <div class="fb-submit"><i class="fas fa-paper-plane text-[11px]"></i> Send request</div>
                </div>

                <div class="fb-chip fb-chip-1" aria-hidden="true"><i class="fas fa-calendar-day" style="color:#1bd4d9;"></i> Date picker</div>
                <div class="fb-chip fb-chip-2" aria-hidden="true"><i class="fas fa-star" style="color:#ff8a3c;"></i> Rating</div>
                <div class="fb-chip fb-chip-3" aria-hidden="true"><i class="fas fa-cloud-arrow-up" style="color:#e94e8c;"></i> File upload</div>
            </div>

            {{-- RIGHT: features --}}
            <div class="space-y-4">
                @foreach([
                    ['fa-shapes',          '#3d6bff', '21 field types',              'Text, email, phone, dropdowns, checkboxes, ratings, dates, file uploads, hidden fields and more &mdash; drag, drop, reorder.'],
                    ['fa-palette',         '#1bd4d9', 'Designed to match',           'Colors, fonts, spacing, buttons and backgrounds &mdash; style every form to fit your brand, no code required.'],
                    ['fa-bell-concierge',  '#e94e8c', 'Instant notifications',       'Get every submission by <span class="text-white font-semibold">email</span>, <span class="text-white font-semibold">SMS</span> or <span class="text-white font-semibold">webhook</span> &mdash; pipe leads straight into your tools.'],
                    ['fa-link',            '#ff8a3c', 'Embed in any biolink',        'Drop a form block onto your Link in Bio page and start collecting responses the moment you publish.'],
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
                    <a href="{{ route('site.forms') }}" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Build a form free <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ route('site.features') }}#cat-forms" class="inline-flex items-center gap-2 px-5 py-3 rounded-full glass text-white hover:bg-white/10 text-xs font-semibold transition-colors">
                        See all field types <i class="fas fa-list-check text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
