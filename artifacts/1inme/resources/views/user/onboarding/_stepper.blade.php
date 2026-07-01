{{--
    Onboarding progress indicator. Alpine-driven: reads a numeric `stepIndex`
    from the surrounding Alpine scope (the onboarding() component on the index
    page, or a constant on the WhatsApp step), so the same markup renders a
    reactive stepper and a static one.

    Expects: $steps (array of ['key'=>, 'label'=>, 'optional'?=>bool]).
--}}
<nav aria-label="Onboarding progress" class="mb-6">
    <ol class="flex items-center">
        @foreach($steps as $i => $step)
            <li class="flex items-center min-w-0 {{ $loop->last ? '' : 'flex-1' }}">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold border transition-colors"
                          :class="stepIndex > {{ $i }}
                                    ? 'bg-blue-600 border-blue-500 text-white'
                                    : (stepIndex === {{ $i }}
                                        ? 'bg-blue-500/20 border-blue-500 text-blue-100 ring-2 ring-blue-500/30'
                                        : 'bg-white/5 border-white/15 text-white/40')">
                        <template x-if="stepIndex > {{ $i }}"><i class="fas fa-check"></i></template>
                        <template x-if="stepIndex <= {{ $i }}"><span>{{ $i + 1 }}</span></template>
                    </span>
                    <span class="hidden sm:block text-[11.5px] font-semibold truncate transition-colors"
                          :class="stepIndex >= {{ $i }} ? 'text-white' : 'text-white/40'">
                        {{ $step['label'] }}@if(!empty($step['optional']))<span class="text-white/30 font-normal"> · optional</span>@endif
                    </span>
                </div>
                @unless($loop->last)
                    <span class="flex-1 h-px mx-2 sm:mx-3 transition-colors"
                          :class="stepIndex > {{ $i }} ? 'bg-blue-500/50' : 'bg-white/10'"></span>
                @endunless
            </li>
        @endforeach
    </ol>
    <p class="sm:hidden mt-2 text-xs font-semibold text-white/60">
        Step <span x-text="stepIndex + 1"></span> of {{ count($steps) }}
    </p>
</nav>
