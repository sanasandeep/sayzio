{{-- ============================ RESUME / PORTFOLIO BUILDER ============================ --}}
<style>
    /* Resume / Portfolio section animations */
    .rb-wrap { position: relative; }
    .rb-mesh::before {
        content:""; position:absolute; inset:-20%;
        background: rgba(61,107,255,.06);
        filter: blur(40px); pointer-events:none; z-index:0;
        animation: rbMesh 14s ease-in-out infinite alternate;
    }
    @keyframes rbMesh { 0% { transform: translate3d(0,0,0) scale(1); } 100% { transform: translate3d(2%,-2%,0) scale(1.06); } }

    /* Resume preview card — looks like an actual A4 résumé */
    .rb-paper {
        position: relative; aspect-ratio: 1 / 1.32; max-width: 380px; margin: 0 auto;
        border-radius: 22px; overflow: hidden;
        background: linear-gradient(180deg, #ffffff 0%, #f5f7fb 100%);
        color: #0f172a;
        box-shadow:
            0 30px 80px -30px rgba(61,107,255,.55),
            0 12px 30px -10px rgba(0,0,0,.55),
            inset 0 0 0 1px rgba(255,255,255,.6);
        transform: rotate(-3.5deg);
        animation: rbFloat 6s ease-in-out infinite;
    }
    @keyframes rbFloat {
        0%,100% { transform: rotate(-3.5deg) translateY(0); }
        50%     { transform: rotate(-3.5deg) translateY(-10px); }
    }
    .rb-paper::after {
        content:""; position:absolute; inset:0; pointer-events:none; border-radius:inherit;
        background: linear-gradient(115deg, transparent 35%, rgba(255,255,255,.55) 50%, transparent 65%);
        background-size: 250% 250%;
        animation: rbShine 4.5s ease-in-out infinite;
    }
    @keyframes rbShine {
        0%   { background-position: 200% 50%; }
        100% { background-position: -100% 50%; }
    }
    .rb-paper-head {
        padding: 22px 22px 16px; color: #fff;
        background: linear-gradient(135deg, #3d6bff 0%, #6e61ff 100%);
        position: relative; overflow: hidden;
    }
    .rb-paper-head::before {
        content:""; position:absolute; inset:0;
        background: radial-gradient(60% 80% at 80% 30%, rgba(255,255,255,.25), transparent 60%);
    }
    .rb-avatar {
        width: 52px; height: 52px; border-radius: 50%;
        background: linear-gradient(135deg, #6e61ff, #3d6bff);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:800; font-size: 20px;
        box-shadow: 0 6px 18px -4px rgba(0,0,0,.45);
    }
    .rb-bar { height: 8px; border-radius: 999px; background: #e5e7eb; overflow: hidden; position: relative; }
    .rb-bar > span {
        position:absolute; left:0; top:0; bottom:0; border-radius: inherit;
        background: linear-gradient(90deg, #3d6bff, #6e61ff);
        animation: rbFill 2.6s ease-out forwards;
        transform-origin: left center;
        width: var(--rb-w, 70%);
        transform: scaleX(0);
    }
    @keyframes rbFill { to { transform: scaleX(1); } }
    .rb-chip {
        display:inline-flex; align-items:center; gap:4px;
        font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 999px;
        background: rgba(61,107,255,.10); color: #2342c7; border: 1px solid rgba(61,107,255,.25);
    }
    .rb-tap {
        position: absolute; width: 22px; height: 22px; border-radius: 50%;
        background: rgba(255,255,255,.92); box-shadow: 0 0 0 0 rgba(61,107,255,.6);
        animation: rbTap 3.6s ease-in-out infinite;
        pointer-events: none;
    }
    @keyframes rbTap {
        0%, 80%, 100% { transform: scale(.8); opacity: 0; box-shadow: 0 0 0 0 rgba(61,107,255,.55); }
        15%           { transform: scale(1);  opacity: 1; }
        25%           { transform: scale(1);  opacity: 1; box-shadow: 0 0 0 14px rgba(61,107,255,0); }
    }

    /* Floating template thumbs that spin around the paper */
    .rb-thumb {
        position: absolute; width: 92px; height: 124px; border-radius: 14px;
        background: linear-gradient(180deg, #ffffff, #eef0f7);
        box-shadow: 0 18px 36px -16px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.06);
        overflow:hidden;
        animation: rbFloat2 7s ease-in-out infinite;
    }
    .rb-thumb::before {
        content:""; position:absolute; left:0; right:0; top:0; height: 30px;
    }
    .rb-thumb i { position:absolute; bottom:8px; right:10px; font-size: 12px; color: rgba(15,23,42,.35); }
    .rb-thumb .rb-thumb-lines { position:absolute; left:10px; right:10px; top:42px; display:flex; flex-direction:column; gap:5px; }
    .rb-thumb .rb-thumb-lines span { height:4px; border-radius:3px; background: rgba(15,23,42,.10); }
    .rb-thumb .rb-thumb-lines span:nth-child(1){ width: 70%; background: rgba(15,23,42,.20); }
    .rb-thumb .rb-thumb-lines span:nth-child(2){ width: 90%; }
    .rb-thumb .rb-thumb-lines span:nth-child(3){ width: 60%; }
    .rb-thumb .rb-thumb-lines span:nth-child(4){ width: 80%; }
    .rb-thumb-1 { top: -28px; left: -36px; transform: rotate(-12deg); animation-delay: .0s; }
    .rb-thumb-1::before { background: linear-gradient(135deg,#1bd4d9,#3d6bff); }
    .rb-thumb-2 { bottom: -22px; right: -28px; transform: rotate(8deg); animation-delay: 1.2s; }
    .rb-thumb-2::before { background: linear-gradient(135deg,#e94e8c,#ff8a3c); }
    .rb-thumb-3 { top: 40%; right: -52px; transform: rotate(14deg); animation-delay: 2.4s; }
    .rb-thumb-3::before { background: linear-gradient(135deg,#22d3ee,#16a34a); }
    @keyframes rbFloat2 {
        0%,100% { transform: translateY(0) rotate(var(--rot,0deg)); }
        50%     { transform: translateY(-12px) rotate(var(--rot,0deg)); }
    }

    /* Feature pills column */
    .rb-feat { transition: transform .35s ease, background .35s ease, border-color .35s ease; }
    .rb-feat:hover { transform: translateX(6px); border-color: rgba(61,107,255,.45); background: rgba(61,107,255,.08); }
    .rb-feat-icon {
        width: 44px; height: 44px; border-radius: 14px;
        display:flex; align-items:center; justify-content:center; flex-shrink:0;
        color: #fff; box-shadow: 0 12px 28px -10px var(--rb-c, #3d6bff);
        background: var(--rb-c, #3d6bff);
        position: relative;
    }
    .rb-feat-icon::after {
        content:""; position:absolute; inset:-5px; border-radius:18px;
        border: 2px solid color-mix(in srgb, var(--rb-c, #3d6bff) 50%, transparent);
        opacity:.35; animation: rbPulse 2.4s ease-in-out infinite;
    }
    @keyframes rbPulse { 0%,100% { transform: scale(1); opacity:.25; } 50% { transform: scale(1.08); opacity:.65; } }

    .rb-stat-bubble {
        position: absolute; padding: 8px 12px; border-radius: 14px;
        background: rgba(15,18,28,.85); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,.08); color:#fff;
        font-size: 11px; font-weight: 700; display:flex; align-items:center; gap:8px;
        box-shadow: 0 18px 40px -16px rgba(0,0,0,.7);
        animation: rbBubble 6s ease-in-out infinite;
    }
    .rb-stat-bubble i { color: #1bd4d9; }
    .rb-stat-1 { top: 8%; right: -18px; animation-delay: .3s; }
    .rb-stat-2 { bottom: 18%; left: -22px; animation-delay: 1.6s; }
    @keyframes rbBubble {
        0%,100% { transform: translateY(0); }
        50%     { transform: translateY(-8px); }
    }
</style>
<section id="resume-portfolio" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="rb-h">
    <div class="rb-mesh absolute inset-0 pointer-events-none" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Resume &amp; Portfolio</div>
            <h2 id="rb-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Build a résumé and portfolio<br><span class="grad-text">that lands the interview.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Drag-and-drop sections, AI-polished copy, and a public portfolio link that lives at <span class="font-semibold text-white">1inme.com/you/cv</span>. Export to PDF in one click — no Word, no fiddling, no recruiter rejection.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            {{-- LEFT: animated résumé preview --}}
            <div class="reveal rd-3 rb-wrap relative h-[520px] sm:h-[560px]">
                <div class="rb-thumb rb-thumb-1" style="--rot:-12deg;" aria-hidden="true">
                    <div class="rb-thumb-lines"><span></span><span></span><span></span><span></span></div>
                    <i class="fas fa-palette"></i>
                </div>
                <div class="rb-thumb rb-thumb-2" style="--rot:8deg;" aria-hidden="true">
                    <div class="rb-thumb-lines"><span></span><span></span><span></span><span></span></div>
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="rb-thumb rb-thumb-3" style="--rot:14deg;" aria-hidden="true">
                    <div class="rb-thumb-lines"><span></span><span></span><span></span><span></span></div>
                    <i class="fas fa-graduation-cap"></i>
                </div>

                <div class="rb-paper" role="img" aria-label="Résumé preview">
                    <div class="rb-paper-head">
                        <div class="relative flex items-center gap-3">
                            <div class="rb-avatar">MA</div>
                            <div>
                                <div class="text-base font-bold leading-tight">Maya Anders</div>
                                <div class="text-[11px] opacity-90">Senior Product Designer · Berlin</div>
                            </div>
                            <span class="ml-auto text-[10px] font-bold uppercase tracking-wider bg-white/20 backdrop-blur px-2 py-1 rounded-full">CV</span>
                        </div>
                        <div class="relative mt-3 flex flex-wrap gap-1.5">
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">Figma</span>
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">Design Systems</span>
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">Prototyping</span>
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">UX Research</span>
                        </div>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Experience</div>
                                <span class="rb-chip"><i class="fas fa-sparkles text-[8px]"></i> AI polished</span>
                            </div>
                            <div class="text-[12px] font-bold text-slate-900 leading-tight">Senior Product Designer</div>
                            <div class="text-[10px] text-slate-500">Linear · 2023 — Now</div>
                            <div class="text-[10px] text-slate-600 mt-1 leading-snug">Shipped onboarding redesign, +28% activation. Led design system across 4 squads.</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700 mb-2">Skills</div>
                            <div class="space-y-2">
                                <div>
                                    <div class="flex items-center justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">Product design</span><span class="text-slate-500">95%</span></div>
                                    <div class="rb-bar"><span style="--rb-w:95%"></span></div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">Design systems</span><span class="text-slate-500">88%</span></div>
                                    <div class="rb-bar"><span style="--rb-w:88%; animation-delay:.25s;"></span></div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">Front-end (React)</span><span class="text-slate-500">72%</span></div>
                                    <div class="rb-bar"><span style="--rb-w:72%; animation-delay:.5s;"></span></div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700 mb-1.5">Portfolio</div>
                            <div class="grid grid-cols-3 gap-1.5">
                                <div class="aspect-square rounded-md" style="background:linear-gradient(135deg,#3d6bff,#1bd4d9);"></div>
                                <div class="aspect-square rounded-md" style="background:linear-gradient(135deg,#e94e8c,#ff8a3c);"></div>
                                <div class="aspect-square rounded-md" style="background:linear-gradient(135deg,#22d3ee,#16a34a);"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Animated tap pointer --}}
                    <span class="rb-tap" style="top: 28%; right: 24%;" aria-hidden="true"></span>
                </div>

                <div class="rb-stat-bubble rb-stat-1" aria-hidden="true">
                    <i class="fas fa-eye"></i> 412 portfolio views
                </div>
                <div class="rb-stat-bubble rb-stat-2" aria-hidden="true">
                    <i class="fas fa-file-pdf" style="color:#ff8a3c;"></i> Exported to PDF · 2.1s
                </div>
            </div>

            {{-- RIGHT: features --}}
            <div class="space-y-4">
                @foreach([
                    ['fa-wand-magic-sparkles', '#3d6bff', 'AI writes the boring parts',  'Paste your past role &mdash; we generate impact-first bullet points with metrics, action verbs and ATS keywords.'],
                    ['fa-grip-vertical',       '#1bd4d9', 'Drag-and-drop sections',       'Reorder Experience, Education, Projects, Skills and custom blocks. Live preview, no save button.'],
                    ['fa-palette',             '#e94e8c', '20+ recruiter-tested templates','Minimalist, design-led, classic ATS &mdash; all responsive, all printable, all yours to recolor.'],
                    ['fa-link',                '#ff8a3c', 'Public portfolio link',        'Share <span class="text-white font-semibold">1inme.com/you/cv</span> instantly. Embed projects, GitHub repos, Behance shots and case studies.'],
                    ['fa-file-pdf',            '#22c55e', 'One-click PDF export',         'Pixel-perfect A4 / Letter export with selectable text and embedded fonts. ATS systems read it cleanly.'],
                    ['fa-shield-halved',       '#22d3ee', 'Privacy-first',                'Toggle between public, unlisted (link-only) and private. Hide email/phone from public view in one tap.'],
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
                    <a href="{{ route('site.resume-builder') }}" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Build my résumé free <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ route('site.resume-builder') }}#templates" class="inline-flex items-center gap-2 px-5 py-3 rounded-full glass text-white hover:bg-white/10 text-xs font-semibold transition-colors">
                        See templates <i class="fas fa-images text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

