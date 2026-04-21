{{--
    Testimonials grid. Inputs:
      $testimonials (array of ['quote','name','role','photo'])
      $eyebrow, $heading, $subheading (optional)
--}}
@php
    $__items = collect($testimonials ?? [])
        ->filter(fn ($t) => is_array($t) && trim((string) ($t['quote'] ?? '')) !== '')
        ->values();
@endphp
@if($__items->isNotEmpty())
<section class="py-20 lg:py-24" aria-labelledby="testimonials-h">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <div class="text-xs font-bold uppercase tracking-[.2em] text-violet-300 mb-3">{{ $eyebrow ?? 'Loved by creators' }}</div>
            <h2 id="testimonials-h" class="text-3xl sm:text-4xl font-bold tracking-tight text-white">
                {{ $heading ?? 'Real stories from people using 1INME every day.' }}
            </h2>
            @if(!empty($subheading))
                <p class="mt-3 text-gray-400">{{ $subheading }}</p>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($__items as $t)
                <figure class="bg-white/[0.04] border border-white/10 rounded-2xl p-6 flex flex-col">
                    <div class="text-violet-300/70 text-2xl leading-none mb-3" aria-hidden="true">&ldquo;</div>
                    <blockquote class="text-gray-200 text-sm leading-relaxed flex-1">{{ $t['quote'] }}</blockquote>
                    <figcaption class="mt-5 flex items-center gap-3">
                        @if(!empty($t['photo']))
                            <img src="{{ $t['photo'] }}" alt="" loading="lazy"
                                 class="w-10 h-10 rounded-full object-cover border border-white/10">
                        @else
                            <div class="w-10 h-10 rounded-full grad-bar text-white text-sm font-bold flex items-center justify-center"
                                 style="background:linear-gradient(135deg,#7c3aed,#ec4899);" aria-hidden="true">
                                {{ strtoupper(mb_substr(trim((string) ($t['name'] ?? '·')), 0, 1)) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-white truncate">{{ $t['name'] ?? '' }}</div>
                            @if(!empty($t['role']))
                                <div class="text-xs text-gray-400 truncate">{{ $t['role'] }}</div>
                            @endif
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif
