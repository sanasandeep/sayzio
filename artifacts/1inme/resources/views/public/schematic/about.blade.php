@extends('public.layouts.site', ['skin' => 'schematic'])

@section('content')
@include('public.partials.schematic.styles')

@php
    $__hero     = (array) ($extra['hero'] ?? []);
    $__values   = (array) ($extra['values'] ?? []);
    $__cards    = (array) ($__values['cards'] ?? []);
    $__heroStats = array_values(array_filter((array) ($__hero['stats'] ?? []), fn ($s) => ($s['visible'] ?? true)));
    $__countries = $stats->first(fn ($s) => str_contains(strtolower((string) $s->label), 'countr'));
    $__countryCount = $__countries ? (int) ($__countries->numericTarget() ?? 0) : 0;
@endphp

<section class="sch-wrap sch-hero">
    <div>
        <p class="sch-mono sch-route" style="margin-bottom:24px">
            <span class="slug">sayz.io/about</span><span>the company</span>
        </p>
        <h1 class="sch-disp sch-h1" style="max-width:15ch">Built in Hyderabad, for people with one link.</h1>
        <p class="sch-lede" style="margin-top:20px">
            We make the simplest way to turn a single address into a complete online presence,
            and to hear back from the people who find it.
        </p>
    </div>
</section>

<section class="sch-section">
    <div class="sch-wrap sch-about">
        <div class="sch-prose">
            <p>Sayzio started in 2023 because small businesses and creators were paying for five tools to do one thing: share their work and capture the people who found it.</p>
            <p>The first version was just Link in Bio pages. Then people asked for short links, then QR codes, then a way to answer the same three questions every day without having to be awake for it. That last one became Zio.</p>
            <p>We stayed small on purpose. Everything in Sayzio is built by people who use it daily, and the free plan stays genuinely free, because that is how most of our users started and a tool you cannot try is a tool you cannot trust.</p>
        </div>

        <ol class="sch-timeline">
            <li><time>2023</time><span><b>Founded</b><span>One workspace in Hyderabad. Link in Bio pages only.</span></span></li>
            <li><time>2024</time><span><b>Short links and QR</b><span>One address, many formats, with analytics behind all of them.</span></span></li>
            <li><time>2025</time><span><b>Zio, the AI suite</b><span>Pages that answer visitors instead of only showing them things.</span></span></li>
            <li><time>2026</time><span><b>Where we are now</b><span>{{ $stats->first()?->value }}{{ $stats->first()?->suffix }} {{ strtolower((string) ($stats->first()?->label ?? 'users')) }}, on {{ $__countryCount ?: 'many' }} countries' worth of links.</span></span></li>
        </ol>
    </div>
</section>

@if($stats->count())
<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/about</span><span>by the numbers</span></p>
            <div><h2 class="sch-disp sch-h2">The same figures<br>as every other page.</h2></div>
        </div>
        <div class="sch-proof">
            <div class="sch-figure sch-tnum">
                {{ $stats->first()->value }}{{ $stats->first()->suffix }}
                <small>{{ $stats->first()->label }}</small>
            </div>
            <table class="sch-ledger">
                @foreach($stats->slice(1) as $stat)
                    <tr>
                        <td>{{ $stat->label }}</td>
                        <td class="v sch-tnum">{{ $stat->value }}{{ $stat->suffix }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</section>
@endif

@if($__countryCount > 0)
<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/about</span><span>reach</span></p>
            <div><h2 class="sch-disp sch-h2">{{ $__countryCount }} countries,<br>one address format.</h2></div>
        </div>
        <div class="sch-panel">
            <p class="sch-mono" style="color:var(--ink-3);margin:0">Each mark is a country with active Sayzio links</p>
            <div class="sch-dots" aria-hidden="true" data-lit="{{ $__countryCount }}"></div>
        </div>
    </div>
</section>
@endif

@if(count($__cards))
<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/about</span><span>how we work</span></p>
            <div><h2 class="sch-disp sch-h2">{{ $__values['heading'] ?? 'What we believe in' }}</h2></div>
        </div>
        <div class="sch-principles">
            @foreach($__cards as $card)
                <div class="sch-principle">
                    <b>{{ $card['title'] ?? '' }}</b>
                    <p>{{ $card['desc'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="sch-section">
    <div class="sch-wrap sch-cta">
        <h2 class="sch-disp sch-h2">Come and take an address.</h2>
        <div><a class="sch-btn lg" href="{{ route('user.register') }}">Start free</a></div>
    </div>
</section>

@push('scripts')
@verbatim
<script>
(function () {
  var grid = document.querySelector('.sch-dots[data-lit]');
  if (!grid) return;
  var lit = Math.max(0, parseInt(grid.getAttribute('data-lit'), 10) || 0);
  var total = Math.max(lit, 168);
  var on = {}, seed = 7;
  for (var i = 0; i < lit; i++) {
    seed = (seed * 1103515245 + 12345) % 2147483648;
    var pos = seed % total;
    while (on[pos]) { pos = (pos + 1) % total; }
    on[pos] = true;
  }
  var html = '';
  for (var d = 0; d < total; d++) { html += '<i' + (on[d] ? ' class="on"' : '') + '></i>'; }
  grid.innerHTML = html;
})();
</script>
@endverbatim
@endpush
@endsection
