{{-- Audience-tailored benefits grid used by the short homepage designs.
     Include with:
       title (HTML from checked-in fragments ONLY, may contain grad-text span),
       sub (string), items (array of [faIcon, title, text], up to 6),
       pills (optional array of use-case strings shown under the grid). --}}
<style>
    .aud-card { transition: transform .3s ease, box-shadow .3s ease; }
    .aud-card:hover { transform: translateY(-5px); box-shadow: 0 26px 52px -26px rgba(61,107,255,.5); }
    .aud-icon {
        width: 44px; height: 44px; border-radius: 14px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 17px; color: #fff;
        background: linear-gradient(135deg, #3d6bff, #8b5cf6);
        box-shadow: 0 12px 26px -10px rgba(61,107,255,.75);
    }
    .aud-text { color: #9ca3af; }
    html.light-mode .aud-text { color: #475569; }
    .aud-pill {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 7px 14px; border-radius: 9999px;
        font-size: 12px; font-weight: 700; color: #cbd5e1;
        border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.04);
    }
    .aud-pill i { color: #4ade80; font-size: 10px; }
    html.light-mode .aud-pill { color: #334155; border-color: rgba(15,23,42,.14); background: rgba(15,23,42,.03); }
</style>
<section class="pt-10 pb-14 lg:pb-16 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <h2 class="reveal text-2xl sm:text-4xl font-bold tracking-tight mb-3">{!! $title !!}</h2>
            <p class="aud-text reveal rd-1 text-sm sm:text-base max-w-2xl mx-auto">{{ $sub }}</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-5">
            @foreach($items as $__i => $__it)
                <div class="aud-card reveal {{ 'rd-' . min($__i + 1, 6) }} glass rounded-2xl p-6 flex flex-col gap-3">
                    <span class="aud-icon"><i class="{{ $__it[0] }}" aria-hidden="true"></i></span>
                    <h3 class="text-base font-bold">{{ $__it[1] }}</h3>
                    <p class="aud-text text-sm leading-relaxed">{{ $__it[2] }}</p>
                </div>
            @endforeach
        </div>
        @if(!empty($pills))
            <div class="reveal mt-8 flex flex-wrap justify-center gap-2.5">
                @foreach($pills as $__p)
                    <span class="aud-pill"><i class="fas fa-check" aria-hidden="true"></i>{{ $__p }}</span>
                @endforeach
            </div>
        @endif
    </div>
</section>
