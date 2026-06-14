{{--
    Compact credibility band — sits directly under the hero so a few headline
    trust numbers land above the fold. It is reinforced lower down by the fuller
    "By the numbers" section (public.partials.marketing-stats).

    Numbers are pulled from the same admin-editable SiteStat source of truth
    (Admin → Site stats), never hard-coded in markup. The count-up shares the
    `.js-stat-count` runtime defined in marketing-stats (which honours
    prefers-reduced-motion); `.reveal` entrance + glassmorphism inherit the
    existing dark/light theming.
--}}
@php
    try {
        $__heroStats = \App\Modules\Admin\Models\SiteStat::active()->ordered()->take(4)->get();
    } catch (\Throwable $e) {
        $__heroStats = collect();
    }
@endphp
@if($__heroStats->isNotEmpty())
<section class="py-8 sm:py-10 relative overflow-hidden" aria-label="1INME at a glance">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal glass rounded-2xl sm:rounded-3xl border border-white/10 px-4 py-5 sm:px-8 sm:py-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-5 lg:gap-8">
                <div class="flex items-center gap-3 shrink-0">
                    <div class="flex -space-x-2" aria-hidden="true">
                        @foreach(['#7c3aed', '#1bd4d9', '#e94e8c', '#ff8a3c'] as $c)
                            <span class="w-7 h-7 rounded-full border-2 border-white/30"
                                  style="background: linear-gradient(135deg, {{ $c }}, rgba(255,255,255,.18));"></span>
                        @endforeach
                    </div>
                    <div class="text-xs sm:text-sm leading-tight">
                        <span class="font-semibold text-white">Trusted worldwide</span>
                        <span class="block text-gray-400">by creators, brands &amp; teams</span>
                    </div>
                </div>

                <div class="hidden lg:block w-px h-12 bg-white/10 shrink-0"></div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-5 flex-1">
                    @foreach($__heroStats as $i => $stat)
                        @php $target = $stat->numericTarget(); @endphp
                        <div class="reveal rd-{{ ($i % 4) + 1 }} text-center sm:text-left">
                            <div class="text-xl sm:text-2xl font-extrabold tracking-tight grad-text leading-none whitespace-nowrap">
                                <span class="js-stat-count"
                                      data-target="{{ $target !== null ? (int) $target : '' }}"
                                      data-display="{{ $stat->value }}"
                                      data-duration="1600">{{ $target !== null ? '0' : $stat->value }}</span><span class="text-white/80">{{ $stat->suffix }}</span>
                            </div>
                            <div class="mt-1 text-[10px] sm:text-[11px] text-gray-400 uppercase tracking-wider leading-tight">{{ $stat->label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endif
