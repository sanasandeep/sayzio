{{-- Keyword-focused designed intro band used by the short homepage designs.
     Include with:
       eyebrow (string), heading (HTML from checked-in fragments ONLY, never
       user/admin input, may contain grad-text span), lead (string),
       chips (array of [label, href] or [label, href, icon]) rendered as
       mini feature cards, anchorId (optional id so the shared hero's
       #ai-zone CTA has a target on designs without an AI zone),
       floats (optional array of Font Awesome classes for decorative
       floating icons, desktop only).
     Styling mirrors the classic page shell (glass / grad-text / reveal);
     light-mode rules keep contrast on white. --}}
<style>
    .seo-intro { position: relative; }
    .seo-intro-glow {
        position: absolute; left: 50%; top: -40px; transform: translateX(-50%);
        width: 640px; height: 340px; border-radius: 9999px; pointer-events: none;
        background: radial-gradient(closest-side, rgba(61,107,255,.22), rgba(139,92,246,.10), transparent 70%);
        filter: blur(10px);
    }
    html.light-mode .seo-intro-glow { opacity: .5; }
    .seo-intro-lead { color: #9ca3af; }
    html.light-mode .seo-intro-lead { color: #475569; }
    html.light-mode .seo-intro-eyebrow-pill { color: #1d4ed8; border-color: rgba(29,78,216,.25); background: rgba(29,78,216,.06); }
    .seo-intro-eyebrow-pill {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 14px; border-radius: 9999px;
        font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase;
        color: var(--c4); border: 1px solid rgba(61,107,255,.35); background: rgba(61,107,255,.10);
    }
    .seo-intro-card {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 18px; border-radius: 16px;
        font-size: 13px; font-weight: 700;
        transition: transform .3s ease, box-shadow .3s ease;
    }
    .seo-intro-card:hover { transform: translateY(-4px); box-shadow: 0 22px 44px -22px rgba(61,107,255,.55); }
    .seo-intro-card i {
        width: 30px; height: 30px; border-radius: 10px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px; color: #fff;
        background: linear-gradient(135deg, #3d6bff, #8b5cf6);
        box-shadow: 0 8px 18px -8px rgba(61,107,255,.8);
    }
    html.light-mode .seo-intro-card { color: #0f172a; border-color: rgba(15,23,42,.12); }
    .seo-intro-float {
        position: absolute; display: none; pointer-events: none;
        width: 46px; height: 46px; border-radius: 15px;
        align-items: center; justify-content: center; font-size: 17px; color: #fff;
        background: linear-gradient(135deg, rgba(61,107,255,.9), rgba(139,92,246,.9));
        box-shadow: 0 16px 34px -14px rgba(61,107,255,.7);
        animation: seoIntroFloat 5.2s ease-in-out infinite;
        opacity: .9;
    }
    @media (min-width: 1024px) { .seo-intro-float { display: inline-flex; } }
    .seo-intro-float:nth-child(2) { animation-delay: 1.3s; }
    .seo-intro-float:nth-child(3) { animation-delay: 2.6s; }
    .seo-intro-float:nth-child(4) { animation-delay: 3.9s; }
    @keyframes seoIntroFloat { 0%,100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-14px) rotate(3deg); } }
    @media (prefers-reduced-motion: reduce) { .seo-intro-float { animation: none; } }
</style>
<section @if(!empty($anchorId)) id="{{ $anchorId }}" @endif class="seo-intro pt-16 pb-6 lg:pt-20 overflow-hidden">
    <div class="seo-intro-glow"></div>
    @php
        $__floats = $floats ?? [];
        $__floatPos = [
            'left:8%;top:52px;', 'right:9%;top:76px;',
            'left:16%;bottom:8px;animation-duration:6.1s;', 'right:17%;bottom:24px;animation-duration:5.6s;',
        ];
    @endphp
    @foreach(array_slice($__floats, 0, 4) as $__fi => $__fa)
        <span class="seo-intro-float" style="{{ $__floatPos[$__fi] }}" aria-hidden="true"><i class="{{ $__fa }}"></i></span>
    @endforeach
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="reveal mb-4"><span class="seo-intro-eyebrow-pill"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>{{ $eyebrow }}</span></div>
        <h2 class="reveal rd-1 text-3xl sm:text-5xl font-bold tracking-tight mb-4 leading-tight">{!! $heading !!}</h2>
        <p class="seo-intro-lead reveal rd-2 text-sm sm:text-base max-w-2xl mx-auto">{{ $lead }}</p>
        @if(!empty($chips))
            <div class="reveal rd-3 mt-7 flex flex-wrap justify-center gap-3">
                @foreach($chips as $c)
                    <a href="{{ $c[1] }}" class="seo-intro-card glass hover:bg-white/[.08]">
                        <i class="{{ $c[2] ?? 'fas fa-bolt' }}" aria-hidden="true"></i>{{ $c[0] }}
                    </a>
                @endforeach
            </div>
        @endif
        <div class="reveal rd-4 mt-7 flex flex-wrap justify-center gap-3">
            <a href="/register" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-3.5 grad-bar text-white rounded-full text-sm sm:text-base font-bold whitespace-nowrap">Get started free <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i></a>
            <a href="/pricing" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-3.5 glass-2 text-white rounded-full text-sm sm:text-base font-bold whitespace-nowrap">See pricing</a>
        </div>
    </div>
</section>
