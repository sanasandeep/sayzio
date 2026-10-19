@php
    // The five stages, their colours and their eyebrows all come from the same
    // class the folder Flow tab reads, so the dashboard teaches the same shape
    // of the product rather than inventing a second one. Same for the station
    // icons: FolderFlowNetwork draws these exact glyphs on the Flow tab.
    $dfStages  = \App\Services\Planner\FolderFlowStages::all();
    $dfLabels  = \App\Services\Planner\PlannerResources::typeLabels();
    $dfIcons   = \App\Services\Planner\FolderFlowNetwork::stations();
    $dfCounts  = $flowCounts ?? [];

    // A kind the viewer's role forbids is left out entirely. Inviting someone
    // to "set up" a thing they are not allowed to open is worse than silence.
    $dfVisible = [];
    foreach ($dfStages as $dfKey => $dfStage) {
        $dfKinds = array_values(array_filter(
            $dfStage['kinds'],
            fn ($k) => isset($dfCounts[$k]) && ! $dfCounts[$k]['locked']
        ));
        if ($dfKinds !== []) {
            $dfVisible[$dfKey] = $dfKinds;
        }
    }

    $dfAll    = array_merge(...array_values($dfVisible ?: [[]]));
    $dfTotal  = count($dfAll);
    $dfInUse  = count(array_filter($dfAll, fn ($k) => ($dfCounts[$k]['count'] ?? 0) > 0));

    // Open on the first stage that still has something switched off, because
    // that is the stage worth looking at. When everything is in use, open the
    // first stage so the panel still has a defined starting state.
    $dfOpen = array_key_first($dfVisible);
    foreach ($dfVisible as $dfKey => $dfKinds) {
        if (count(array_filter($dfKinds, fn ($k) => ($dfCounts[$k]['count'] ?? 0) === 0)) > 0) {
            $dfOpen = $dfKey;
            break;
        }
    }
@endphp

@if($dfTotal > 0)
<section class="ov-panel dflow" aria-labelledby="dflow-title">
    <div class="dflow-head">
        <div class="dflow-head-copy">
            <h2 id="dflow-title">How your Sayzio fits together</h2>
            <p class="ov-muted">Every part of the platform, in the order a customer moves through it. Open one to jump straight there.</p>
        </div>
        <p class="dflow-score"><strong>{{ $dfInUse }}</strong><span class="ov-muted">of {{ $dfTotal }} in use</span></p>
    </div>

    <div class="dflow-rail" role="tablist" aria-label="Stages">
        @foreach($dfVisible as $dfKey => $dfKinds)
        @php
            $dfUsed  = count(array_filter($dfKinds, fn ($k) => ($dfCounts[$k]['count'] ?? 0) > 0));
            $dfOn    = $dfKey === $dfOpen;
            $dfIndex = array_search($dfKey, array_keys($dfVisible), true);
            $dfPast  = $dfIndex <= array_search($dfOpen, array_keys($dfVisible), true);
        @endphp
        @if(! $loop->first)
        {{-- The stages are a sequence, so the gap between them carries a
             direction. One dash wave crosses the whole rail rather than four
             separate loops: same period, staggered start, so it reads as one
             gesture moving left to right instead of four things twitching. --}}
        <span class="dflow-arrow{{ $dfPast ? ' is-passed' : '' }}" aria-hidden="true"
              style="--df: {{ $dfStages[$dfKey]['color'] }};--dfa-delay: {{ $dfIndex * 0.26 }}s">
            <svg viewBox="0 0 26 12" fill="none" preserveAspectRatio="none">
                <path class="dfa-line" d="M1 6h17"/>
                <path class="dfa-head" d="M17 2.4 21.4 6 17 9.6"/>
            </svg>
        </span>
        @endif
        <button type="button" class="dflow-stop" role="tab"
                id="dflow-tab-{{ $dfKey }}"
                aria-controls="dflow-panel-{{ $dfKey }}"
                aria-selected="{{ $dfOn ? 'true' : 'false' }}"
                tabindex="{{ $dfOn ? '0' : '-1' }}"
                style="--df: {{ $dfStages[$dfKey]['color'] }}">
            <span class="dflow-stop-dot" aria-hidden="true">
                @if($dfUsed === count($dfKinds))<i class="fas fa-check"></i>@else{{ $dfUsed }}@endif
            </span>
            <span class="dflow-stop-label">{{ $dfStages[$dfKey]['eyebrow'] }}</span>
        </button>
        @endforeach
    </div>

    @foreach($dfVisible as $dfKey => $dfKinds)
    <div class="dflow-panel" role="tabpanel"
         id="dflow-panel-{{ $dfKey }}"
         aria-labelledby="dflow-tab-{{ $dfKey }}"
         style="--df: {{ $dfStages[$dfKey]['color'] }}"
         @if($dfKey !== $dfOpen) hidden @endif>
        <p class="dflow-panel-title">{{ $dfStages[$dfKey]['title'] }}</p>
        <div class="dflow-stations">
            @foreach($dfKinds as $dfKind)
            @php
                $dfCount = (int) ($dfCounts[$dfKind]['count'] ?? 0);
                // Null when the route is not registered. An anchor with no
                // href is inert and unfocusable, which is the honest rendering
                // of a station with nowhere to go -- better than a link that
                // 404s, and better than dropping the station so the catalogue
                // silently shrinks.
                $dfUrl   = \App\Modules\User\Support\DashboardFlowDestinations::urlFor($dfKind);
            @endphp
            <a class="dflow-station {{ $dfCount > 0 ? 'is-on' : '' }}{{ $dfUrl ? '' : ' is-inert' }}"
               @if($dfUrl) href="{{ $dfUrl }}" @endif>
                <span class="dflow-station-icon" data-kind="{{ $dfKind }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $dfIcons[$dfKind]['icon'] ?? '' !!}</svg>
                </span>
                <span class="dflow-station-copy">
                    <strong>{{ $dfLabels[$dfKind] ?? $dfKind }}</strong>
                    @if($dfCount > 0)
                        <small class="dflow-station-count">{{ number_format($dfCount) }}</small>
                    @else
                        <small class="dflow-station-cta">{{ \App\Modules\User\Support\DashboardFlowDestinations::ctaFor($dfKind) }} &rarr;</small>
                    @endif
                </span>
            </a>
            @endforeach
        </div>
    </div>
    @endforeach
</section>
@endif
