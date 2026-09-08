@extends('public.layouts.site', ['skin' => 'schematic'])

@section('content')
@include('public.partials.schematic.styles')

<section class="sch-wrap sch-hero">
    <div class="sch-hero-grid">
        <div>
            <p class="sch-mono sch-route" style="margin-bottom:24px">
                <span class="slug">sayz.io/</span><span>one address</span>
            </p>
            <h1 class="sch-disp sch-h1">One address.<br><span class="sch-quiet">Everything</span> behind it.</h1>
            <p class="sch-lede" style="margin-top:20px">
                A Sayzio link is not a destination, it is a junction. Point it at a landing page, a menu,
                a file, a form, a QR code, or a conversation with Zio, your AI. Repoint it whenever you
                like. The address never changes.
            </p>
            <div class="sch-cta-row">
                <a class="sch-btn lg" href="{{ route('user.register') }}">Create your link, free</a>
                <a class="sch-btn lg ghost" href="{{ route('site.schematic.features') }}">See all {{ $typeCount }} link types</a>
            </div>
            <p class="sch-note">{{ $heroProof }}</p>
        </div>

        @include('public.partials.schematic.routing-diagram')
    </div>
</section>

@if($stats->count())
<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/</span><span>at scale</span></p>
            <div></div>
        </div>
        <div class="sch-proof">
            <div class="sch-figure sch-tnum">
                {{ $stats->first()->value }}{{ $stats->first()->suffix }}
                <small>{{ $stats->first()->label }}</small>
            </div>
            <table class="sch-ledger">
                @foreach($stats->slice(1) as $stat)
                    @php
                        $target = $stat->numericTarget();
                        $width  = ($statsMax > 0 && $target !== null)
                            ? max(6, (int) round(($target / $statsMax) * 100))
                            : 0;
                    @endphp
                    <tr>
                        <td>{{ $stat->label }}</td>
                        <td class="v sch-tnum">{{ $stat->value }}{{ $stat->suffix }}</td>
                        <td class="bar">@if($width > 0)<i style="width:{{ $width }}%"></i>@endif</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</section>
@endif

<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/</span><span>how it works</span></p>
            <div><h2 class="sch-disp sch-h2">Four steps, then it runs itself.</h2></div>
        </div>
        <div class="sch-principles">
            <div class="sch-principle">
                <b>Claim the address</b>
                <p>Pick your handle. It is yours across every link type, and it survives every change you make behind it.</p>
            </div>
            <div class="sch-principle">
                <b>Point it somewhere</b>
                <p>Build a page, shorten a URL, generate a QR code, open a form. Change the destination later without reprinting anything.</p>
            </div>
            <div class="sch-principle">
                <b>Let Zio answer</b>
                <p>Your AI reads your own content and replies to visitors while you sleep: on the page, in chat, on WhatsApp.</p>
            </div>
            <div class="sch-principle">
                <b>Watch what happens</b>
                <p>Every click, scan and reply lands in one dashboard, by source, country and hour.</p>
            </div>
        </div>
    </div>
</section>

<section class="sch-section">
    <div class="sch-wrap">
        <div class="sch-head">
            <p class="sch-mono sch-route"><span class="slug">/</span><span>the catalogue</span></p>
            <div><h2 class="sch-disp sch-h2">{{ $typeCount }} kinds of link.<br>One place to run them.</h2></div>
        </div>

        <div class="sch-lead">
            <div>
                <p class="sch-kicker">Most used</p>
                <h3 class="sch-disp sch-h3">{{ $featured['name'] ?? 'Link in Bio' }}</h3>
                <p class="sch-lede" style="margin-top:12px">{{ $featured['description'] ?? '' }}</p>
                <p style="margin-top:18px">
                    <a class="sch-btn ghost" href="{{ route('site.schematic.features') }}">See every link type</a>
                </p>
            </div>
            @include('public.partials.schematic.bio-figure')
        </div>

        @include('public.partials.schematic.type-index', ['types' => $types])
    </div>
</section>

<section class="sch-section">
    <div class="sch-wrap sch-cta">
        <h2 class="sch-disp sch-h2">Claim your address<br>before someone else does.</h2>
        <div><a class="sch-btn lg" href="{{ route('user.register') }}">Create your link, free</a></div>
    </div>
</section>
@endsection
