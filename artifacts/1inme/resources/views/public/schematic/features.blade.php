@extends('public.layouts.site', ['skin' => 'schematic'])

@section('content')
@include('public.partials.schematic.styles')

@php
    $__blockCount = 22;
@endphp

<section class="sch-wrap sch-hero">
    <div class="sch-hero-grid">
        <div>
            <p class="sch-mono sch-route" style="margin-bottom:24px">
                <span class="slug">sayz.io/features</span><span>the specification</span>
            </p>
            <h1 class="sch-disp sch-h1">Everything Sayzio does,<br>on one sheet.</h1>
            <p class="sch-lede" style="margin-top:20px">
                {{ $typeCount }} link types and the systems around them: AI, analytics, inbox, broadcasts,
                teams, storage, billing. No feature is hidden behind a sales call.
            </p>
            <div class="sch-cta-row">
                <a class="sch-btn lg" href="{{ route('user.register') }}">Start free</a>
                <a class="sch-btn lg ghost" href="{{ route('site.schematic.pricing') }}">See the plans</a>
            </div>
        </div>

        <div class="sch-panel">
            <p class="sch-mono" style="color:var(--ink-3);margin:0">Coverage</p>
            <table class="sch-ledger" style="margin-top:12px">
                <tr><td>Link types</td><td class="v sch-tnum">{{ $typeCount }}</td></tr>
                <tr><td>Capability areas</td><td class="v sch-tnum">{{ count($categories) }}</td></tr>
                <tr><td>Link in Bio block types</td><td class="v sch-tnum">{{ $__blockCount }}</td></tr>
                @if($stats->count())
                    <tr><td>{{ $stats->first()->label }}</td><td class="v sch-tnum">{{ $stats->first()->value }}{{ $stats->first()->suffix }}</td></tr>
                @endif
            </table>
        </div>
    </div>
</section>

<section class="sch-section">
    <div class="sch-wrap sch-feat">
        <nav class="sch-margin-index" aria-label="On this page">
            <h4>On this page</h4>
            @foreach($categories as $cat)
                @php $__id = 'cap-' . \Illuminate\Support\Str::slug((string) ($cat['id'] ?? $cat['heading'] ?? 'section')); @endphp
                <a href="#{{ $__id }}">{{ \Illuminate\Support\Str::before((string) ($cat['heading'] ?? ''), ':') }}</a>
            @endforeach
        </nav>

        <div>
            @foreach($categories as $catIndex => $cat)
                @php
                    $__id = 'cap-' . \Illuminate\Support\Str::slug((string) ($cat['id'] ?? $cat['heading'] ?? 'section'));
                    $__features = (array) ($cat['features'] ?? []);
                    $__isLinkTypes = (string) ($cat['id'] ?? '') === 'link-types';
                @endphp
                <section class="sch-cap" id="{{ $__id }}">
                    <h3 class="sch-disp sch-h3">{{ $cat['heading'] ?? '' }}</h3>
                    <p>{{ $cat['intro'] ?? '' }}</p>

                    @if($__isLinkTypes)
                        <div style="margin-top:18px">
                            @include('public.partials.schematic.type-index', ['types' => $types])
                        </div>
                    @elseif(count($__features))
                        <ul class="sch-chips">
                            @foreach($__features as $f)
                                <li>{{ $f['name'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</section>

<section class="sch-section">
    <div class="sch-wrap sch-cta">
        <h2 class="sch-disp sch-h2">All of it starts free.</h2>
        <div><a class="sch-btn lg" href="{{ route('site.schematic.pricing') }}">See the plans</a></div>
    </div>
</section>
@endsection
