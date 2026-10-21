{{--
    The attendee journey for this event, as a rail of stages.

    Borrowed from the dashboard's "How your Sayzio fits together" on
    purpose -- same stops, same connectors, same colour variable -- so
    the two screens read as one product rather than two dialects. What
    is NOT borrowed is the component itself: that one answers "is this
    switched on", five booleans, and a funnel has to show magnitude and
    the loss between stages. The arrows carry that here; on the
    dashboard they carry nothing.

    Every number comes from EventFunnel, which counts rows that already
    exist. Nothing on this panel is estimated, and nothing is summed
    across tables that count different things.

    Expects `$link`.
--}}
@php
    $evfun    = \App\Services\Events\EventFunnel::for($link);
    $evStages = $evfun['stages'];

    // Colours in the order the journey runs: wide and cool at the top,
    // warm where people actually turn up. Used only through color-mix
    // against the theme's own card and border tokens, the way the
    // dashboard rail does it, so both themes stay the shell's.
    $evColors = [
        'opened'    => '#3b82f6',
        'responded' => '#6366f1',
        'coming'    => '#8b5cf6',
        'arrived'   => '#10b981',
        'connected' => '#f59e0b',
    ];

    $evOpened  = $evStages[0]['count'];
    $evArrived = collect($evStages)->firstWhere('key', 'arrived')['count'] ?? 0;

    // An event nobody has opened and nobody has answered has nothing to
    // analyse. Five zeros and four dashes is not a finding, it is
    // furniture, so the panel stays away until there is something in it.
    $evHasAnything = $evOpened > 0 || ($evStages[1]['count'] ?? 0) > 0;
@endphp

@if($evHasAnything)
@once
    @include('user.partials.dashboard-flow-styles')
    <style>
        /*
            Funnel-only additions. Everything else is the dashboard rail's
            own CSS, including both themes -- these are built on the same
            tokens for the same reason.
        */
        .evfun-rate{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
            font-size:10px;font-weight:700;font-variant-numeric:tabular-nums;
            color:var(--text-muted);background:var(--bg-card);
            padding:1px 5px;border-radius:999px;line-height:1.5;white-space:nowrap}
        .dflow-arrow{position:relative}
        /* A stage nobody could reach is not a result. It is drawn as
           absent rather than as a zero, because a zero here reads as
           "offered and refused" when the truth is "never offered". */
        .evfun-stop.is-na{opacity:.45}
        .evfun-stop.is-na .dflow-stop-dot{border-style:dashed}
        .evfun-notes{margin:14px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:5px}
        .evfun-notes li{font-size:11.5px;color:var(--text-muted);display:flex;gap:7px;align-items:flex-start}
        .evfun-notes i{margin-top:3px;font-size:9px;opacity:.7}
        /* .ov-panel and .ov-muted belong to the dashboard's own stylesheet
           and are not loaded on the link page, so the two things this
           panel needed from them are defined here against the same
           tokens. The rail's CSS is token-based and carries over as-is. */
        .evfun-sub{font-size:12px;color:var(--text-muted);margin:0}
        .evfun-score{flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;gap:2px;margin:0;line-height:1.1}
        .evfun-score strong{font-size:26px;font-weight:750;letter-spacing:-.04em;font-variant-numeric:tabular-nums;color:var(--text-primary)}
        .evfun-score span{font-size:11.5px;color:var(--text-muted)}
        /* The rail's stop is a <button> on the dashboard and carries
           cursor:pointer. Here it is a span that does nothing, so the
           pointer would be a lie about what clicking does. */
        .evfun-stop{cursor:default}
    </style>
@endonce

<section class="glass rounded-2xl px-5 py-4 mb-4 dflow" aria-labelledby="evfun-title">
    <div class="dflow-head">
        <div class="dflow-head-copy">
            <h2 id="evfun-title" class="text-base font-semibold" style="color:var(--text-primary)">How this event filled up</h2>
            <p class="evfun-sub">Everyone who met this event, in the order they met it. Each step counts a record that already exists: a click, an RSVP, a ticket, a scan at the door.</p>
        </div>
        <p class="evfun-score">
            <strong>{{ number_format($evArrived) }}</strong>
            <span>arrived of {{ number_format($evOpened) }} who looked</span>
        </p>
    </div>

    <div class="dflow-rail" aria-label="Stages of this event">
        @foreach($evStages as $evStage)
            @php
                $evKey   = $evStage['key'];
                $evNa    = ! $evStage['applicable'];
                $evRate  = $evStage['rate'];
                $evIndex = $loop->index;
            @endphp

            @if(! $loop->first)
                {{-- The connector carries the conversion. A null rate
                     prints nothing at all rather than 0% -- the stage
                     before it was empty, so there is no share to state. --}}
                <span class="dflow-arrow" aria-hidden="true"
                      style="--df: {{ $evColors[$evKey] ?? '#3b82f6' }};--dfa-delay: {{ $evIndex * 0.26 }}s">
                    <svg viewBox="0 0 26 12" fill="none" preserveAspectRatio="none">
                        <path class="dfa-line" d="M1 6h17"/>
                        <path class="dfa-head" d="M17 2.4 21.4 6 17 9.6"/>
                    </svg>
                    @if($evRate !== null && ! $evNa)
                        <span class="evfun-rate">{{ $evRate < 0.1 ? number_format($evRate * 100, 1) : round($evRate * 100) }}%</span>
                    @endif
                </span>
            @endif

            <span class="dflow-stop evfun-stop{{ $evNa ? ' is-na' : '' }}"
                  style="--df: {{ $evColors[$evKey] ?? '#3b82f6' }}"
                  @if($evNa) title="Nobody turned this on, so there was nothing to measure" @endif>
                <span class="dflow-stop-dot" aria-hidden="true">
                    {{-- An icon rather than a dash, the way the dashboard rail
                         puts fa-check in this dot. It also keeps a U+2014 out
                         of user-visible copy, which the em-dash guard scans
                         these views for. --}}
                    @if($evNa)<i class="fas fa-minus"></i>@else{{ number_format($evStage['count']) }}@endif
                </span>
                <span class="dflow-stop-label">{{ $evStage['label'] }}</span>
            </span>
        @endforeach
    </div>

    @if($evfun['notes'])
        <ul class="evfun-notes">
            @foreach($evfun['notes'] as $evNote)
                <li><i class="fas fa-circle"></i><span>{{ $evNote }}</span></li>
            @endforeach
        </ul>
    @endif
</section>
@endif
