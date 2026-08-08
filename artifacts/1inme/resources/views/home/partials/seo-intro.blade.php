{{-- Keyword-focused intro section used by the short homepage designs.
     Include with: eyebrow (string), heading (HTML from checked-in fragments
     ONLY, never user/admin input, may contain grad-text span), lead (string),
     chips (optional array of [label, href]), anchorId (optional id so the
     shared hero's #ai-zone CTA has a target on designs without an AI zone).
     Styling mirrors the FAQ section header so it blends with the classic
     page shell; light-mode rules keep contrast on white. --}}
<style>
    .seo-intro-lead { color: #9ca3af; }
    html.light-mode .seo-intro-lead { color: #475569; }
    html.light-mode .seo-intro-eyebrow { color: #1d4ed8 !important; }
    html.light-mode .seo-intro-chip { color: #0f172a; border-color: rgba(15,23,42,.15); }
</style>
<section @if(!empty($anchorId)) id="{{ $anchorId }}" @endif class="pt-14 pb-4 lg:pt-16 relative overflow-hidden">
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="seo-intro-eyebrow reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">{{ $eyebrow }}</div>
        <h2 class="reveal rd-1 text-3xl sm:text-4xl font-bold tracking-tight mb-3">{!! $heading !!}</h2>
        <p class="seo-intro-lead reveal rd-2 text-sm max-w-2xl mx-auto">{{ $lead }}</p>
        @if(!empty($chips))
            <div class="reveal rd-3 mt-5 flex flex-wrap justify-center gap-2">
                @foreach($chips as $c)
                    <a href="{{ $c[1] }}" class="seo-intro-chip glass rounded-full px-4 py-2 text-xs font-semibold hover:bg-white/[.08] transition-colors">{{ $c[0] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
