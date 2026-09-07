@extends('user.layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    /* ===================================================================
       Aurora dashboard.

       A separate view rather than a rewrite of dashboard/index.blade.php:
       the classic dashboard is 1,058 lines of working page and there is no
       reason to risk it while this is still behind a flag. The controller
       picks between the two on User::usesAuroraUi().

       Panels use .card-premium so they inherit the lit edge, the opaque
       surface and the hover from theme-styles rather than restating it.
       .au-pad exists because .card-premium upgrades the common Tailwind
       padding utilities to much roomier values, which this grid does not
       want.
       =================================================================== */
    .au-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 12px;
    }
    .au-pad { padding: 18px 20px; }
    .c12 { grid-column: span 12; }
    .c8  { grid-column: span 8; }
    .c4  { grid-column: span 4; }

    /* Microlabel and figure: the two type roles that carry the page. */
    .au-label {
        font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 10px; font-weight: 500; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--text-faint);
    }
    .au-fig {
        font-family: 'Archivo', 'Inter', system-ui, sans-serif;
        font-weight: 600; font-variant-numeric: tabular-nums;
        font-feature-settings: 'tnum' 1;
        letter-spacing: -0.05em; line-height: 0.88;
        color: var(--text-primary);
        font-size: 54px;
    }
    .au-fig.xl { font-size: clamp(3.5rem, 7vw, 5.5rem); }
    .au-fig.sm { font-size: 34px; }
    .au-fig.xs { font-size: 26px; letter-spacing: -0.03em; }
    .au-mono {
        font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px; color: var(--text-muted); font-variant-numeric: tabular-nums;
    }

    /* Hero */
    .au-hero { display: grid; grid-template-columns: minmax(0, 390px) minmax(0, 1fr); }
    .au-hero-left { padding: 26px; display: flex; flex-direction: column; gap: 16px; }
    .au-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .au-chip {
        font-family: 'IBM Plex Mono', ui-monospace, monospace;
        font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase;
        color: var(--text-muted); padding: 4px 9px; border-radius: 999px;
        border: 1px solid var(--border-glass);
    }
    .au-greet {
        font-size: clamp(1.6rem, 3vw, 2.05rem); line-height: 1.08;
        letter-spacing: -0.03em; margin: 0; color: var(--text-primary);
        font-weight: 400;
    }
    .au-greet b { font-weight: 700; }
    .au-insight { margin: 0; color: var(--text-muted); font-size: 13.5px; max-width: 40ch; }
    .au-today { margin-top: auto; }
    .au-live {
        width: 6px; height: 6px; border-radius: 50%; display: inline-block;
        background: var(--c-success);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--c-success) 20%, transparent);
    }
    .au-pill {
        font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 10.5px;
        padding: 3px 8px; border-radius: 999px; color: var(--c-success);
        background: color-mix(in srgb, var(--c-success) 14%, transparent);
        font-variant-numeric: tabular-nums;
    }
    .au-stage { position: relative; border-left: 1px solid var(--border-subtle); min-height: 330px; }
    #auGraph { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
    .au-graph-cap { position: absolute; right: 18px; top: 16px; text-align: right; pointer-events: none; }
    .au-key { position: absolute; left: 18px; bottom: 14px; display: flex; flex-wrap: wrap; gap: 11px; }
    .au-key span {
        display: inline-flex; align-items: center; gap: 6px;
        font-family: 'IBM Plex Mono', ui-monospace, monospace;
        font-size: 10px; color: var(--text-muted);
    }
    .au-key i { width: 7px; height: 7px; border-radius: 50%; }
    .au-tip {
        position: absolute; pointer-events: none; z-index: 3; opacity: 0;
        transform: translate(-50%, -136%); transition: opacity .12s;
        background: var(--bg-dropdown); border: 1px solid var(--border-glass);
        border-radius: 10px; padding: 7px 10px; white-space: nowrap;
        box-shadow: var(--lg-shadow);
    }
    .au-tip.on { opacity: 1; }

    .au-ph { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
    .au-note {
        font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 9.5px;
        letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-faint);
    }

    .au-area { width: 100%; height: 92px; display: block; }
    .au-ticks { display: flex; justify-content: space-between; margin-top: 6px; }
    .au-ticks span { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 9px; color: var(--text-faint); }

    .au-ring-wrap { display: flex; align-items: center; gap: 18px; }
    .au-ring { width: 106px; height: 106px; flex: 0 0 auto; }
    .au-legend { display: flex; flex-direction: column; gap: 7px; min-width: 0; flex: 1 1 auto; }
    .au-legend span { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-muted); }
    .au-legend i { width: 8px; height: 8px; border-radius: 2px; flex: 0 0 auto; }
    .au-legend b {
        margin-left: auto; font-family: 'IBM Plex Mono', ui-monospace, monospace;
        font-size: 11px; font-weight: 500; color: var(--text-primary); font-variant-numeric: tabular-nums;
    }

    .au-row { display: grid; grid-template-columns: 1fr auto; gap: 4px 10px; padding: 10px 0; border-bottom: 1px solid var(--border-subtle); }
    .au-row:last-child { border-bottom: 0; }
    .au-row-name { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
    .au-dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; }
    .au-bar { grid-column: 1 / -1; height: 3px; border-radius: 999px; background: var(--bg-glass-input); overflow: hidden; }
    .au-bar i { display: block; height: 100%; border-radius: 999px; }

    .au-rank { display: grid; grid-template-columns: 20px minmax(0, 1fr) 72px 60px; align-items: center; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--border-subtle); }
    .au-rank:last-child { border-bottom: 0; }
    .au-rank-n { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 10.5px; color: var(--text-faint); font-variant-numeric: tabular-nums; }
    .au-slug { font-size: 12.5px; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .au-slug em { font-style: normal; color: var(--text-faint); }
    .au-track { height: 4px; border-radius: 999px; background: var(--bg-glass-input); overflow: hidden; }
    .au-track i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--accent), var(--accent-light)); }
    .au-val { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11.5px; text-align: right; color: var(--text-primary); font-variant-numeric: tabular-nums; }

    .au-minis { display: flex; flex-direction: column; gap: 14px; }
    .au-mini { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
    .au-split { height: 5px; border-radius: 999px; background: var(--bg-glass-input); overflow: hidden; display: flex; gap: 2px; margin-top: 10px; }
    .au-split i { display: block; height: 100%; border-radius: 999px; }
    .au-sep { border-top: 1px solid var(--border-subtle); padding-top: 14px; }

    .au-empty { font-size: 12.5px; color: var(--text-muted); padding: 6px 0 2px; }

    @media (max-width: 1100px) {
        .c8, .c4 { grid-column: span 12; }
        .au-hero { grid-template-columns: 1fr; }
        .au-stage { border-left: 0; border-top: 1px solid var(--border-subtle); min-height: 270px; }
    }
    @media (max-width: 620px) {
        .au-hero-left { padding: 20px; }
        .au-rank { grid-template-columns: 18px minmax(0,1fr) 56px; }
        .au-rank .au-track { display: none; }
    }
</style>
@endpush

@section('content')
@php
    use Illuminate\Support\Str;

    $tz       = $user->timezone ?: config('app.timezone');
    $nowLocal = now($tz);
    $hour     = (int) $nowLocal->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

    // Folder colour, keyed by project id. Projects store their own colour, so
    // the folder list, the folder bars and the graph clusters all read the
    // same value rather than three lookalike palettes drifting apart.
    $fallbackHues = ['#6e8cff', '#46d3d9', '#ff83ab', '#edb44e', '#b08bff', '#46d592'];
    $folderColour = [];
    foreach ($deskFolders as $i => $f) {
        $c = (string) ($f->color ?? '');
        $folderColour[$f->id] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $c)
            ? $c
            : $fallbackHues[$i % count($fallbackHues)];
    }

    $filedCount   = (int) $deskFolders->sum('links_count');
    $unfiledCount = max(0, (int) $totalLinks - $filedCount);
    $maxFolder    = max(1, (int) $deskFolders->max('links_count'));

    // Graph payload: one node per link, coloured by folder, sized by clicks.
    $graphNodes = $auroraLinks->map(fn ($l) => [
        'p' => $l->project_id,
        'c' => $l->project_id && isset($folderColour[$l->project_id]) ? $folderColour[$l->project_id] : null,
        'v' => (int) $l->total_clicks,
        's' => $l->alias,
    ])->values();

    // Cluster anchors, one per folder, spread around the canvas.
    $anchors = [];
    $spots = [[0.30, 0.32], [0.66, 0.26], [0.74, 0.64], [0.36, 0.72], [0.52, 0.44], [0.18, 0.60]];
    foreach ($deskFolders as $i => $f) {
        $anchors[$f->id] = ['x' => $spots[$i % count($spots)][0], 'y' => $spots[$i % count($spots)][1]];
    }

    $topLinks = $auroraLinks->take(5);
    $maxTop   = max(1, (int) ($topLinks->max('total_clicks') ?: 0));

    // Channel mix. Real rows from the click log, so the panel is never a
    // placeholder; it just goes quiet when there is nothing to show yet.
    $channelTotal = (int) $channelStats->sum('count');
    $channelHues  = ['#6e8cff', '#b08bff', '#46d3d9', '#edb44e', '#ff83ab'];
    $channelTop   = $channelStats->sortByDesc('count')->take(5)->values();

    $spark    = collect($clicksSparkline)->values();
    $sparkMax = max(1, (int) $spark->max());
    $peakIdx  = $spark->search($spark->max());
    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $peakDay  = $nowLocal->copy()->subDays(max(0, $spark->count() - 1 - (int) $peakIdx))->format('D');

    $planPrice = $user->plan
        ? \App\Services\PricingResolver::priceFor($user->plan, $user, 'monthly')
        : null;

    // Built here rather than inline in the markup. Blade only treats a
    // directive as a directive when the character before its at-sign is not a
    // word character, so closing a conditional immediately after a word (the
    // "mo" of a price suffix, say) leaves that conditional open and the view
    // fails to compile. Composing the string up here keeps the trap out of
    // the template entirely.
    $planLabel = !empty($planPrice['formatted']) ? $planPrice['formatted'] . '/mo' : '';
@endphp

<div class="au-grid">

    {{-- ============ HERO: greeting, today, and the link graph ============ --}}
    <section class="card-premium au-hero c12">
        <div class="au-hero-left">
            <div class="au-chips">
                <span class="au-chip">{{ $nowLocal->format('l') }}</span>
                <span class="au-chip">{{ number_format($totalLinks) }} links</span>
                <span class="au-chip">{{ $deskFolders->count() }} folders</span>
            </div>
            <h1 class="au-greet">{{ $greeting }},<br><b>{{ Str::limit($user->name, 28) }}</b></h1>
            <p class="au-insight">
                @if($totalClicks < 1)
                    No clicks recorded yet. Share a link and this page starts filling in.
                @elseif($unfiledCount > 0)
                    {{ number_format($unfiledCount) }} of your {{ number_format($totalLinks) }} links are still unfiled.
                @else
                    Every link is filed. {{ number_format($activeLinks) }} of {{ number_format($totalLinks) }} are active.
                @endif
            </p>
            <div class="au-today">
                <div class="au-label">Clicks today</div>
                <div class="au-fig xl" style="margin-top:8px">{{ number_format($clicksToday) }}</div>
                <div class="flex items-center gap-2 flex-wrap" style="margin-top:12px">
                    <span class="au-live"></span>
                    <span class="au-pill">Live</span>
                    <span class="au-mono">{{ number_format($totalClicks) }} all-time</span>
                </div>
            </div>
        </div>
        <div class="au-stage" id="auStage">
            <canvas id="auGraph"
                    data-nodes='@json($graphNodes)'
                    data-anchors='@json($anchors)'
                    aria-label="Your links drawn as a network, clustered by folder and sized by clicks."></canvas>
            <div class="au-graph-cap">
                <div class="au-label">Your link graph</div>
                <div class="au-label" style="color: var(--text-muted); letter-spacing:.08em; margin-top:4px">
                    {{ number_format($graphNodes->count()) }} of {{ number_format($totalLinks) }} &middot; hover to read
                </div>
            </div>
            <div class="au-key">
                @foreach($deskFolders->take(5) as $f)
                    <span><i style="background: {{ $folderColour[$f->id] }}"></i>{{ Str::limit($f->name, 14) }} {{ $f->links_count }}</span>
                @endforeach
                @if($unfiledCount > 0)
                    <span><i style="background: var(--text-faint)"></i>Unfiled {{ number_format($unfiledCount) }}</span>
                @endif
            </div>
            <div class="au-tip" id="auTip">
                <div class="au-mono" id="auTipSlug" style="color: var(--text-primary)"></div>
                <div class="au-mono" id="auTipMeta" style="margin-top:2px"></div>
            </div>
        </div>
    </section>

    {{-- ============ TOTAL CLICKS ============ --}}
    <section class="card-premium au-pad c4">
        <div class="au-ph">
            <span class="au-label">Total clicks</span>
            <span class="au-note">Last 7 days</span>
        </div>
        <div class="au-fig">{{ number_format($totalClicks) }}</div>
        @php
            $pts = [];
            foreach ($spark as $i => $v) {
                $x = $spark->count() > 1 ? ($i / ($spark->count() - 1)) * 300 : 0;
                $y = 84 - (((int) $v) / $sparkMax) * 66;
                $pts[] = round($x, 1) . ' ' . round($y, 1);
            }
            $line = 'M' . implode(' L', $pts);
        @endphp
        <svg class="au-area" viewBox="0 0 300 92" preserveAspectRatio="none" role="img"
             aria-label="Clicks per day over the last seven days, peaking at {{ (int) $spark->max() }}.">
            <defs>
                <linearGradient id="auArea" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.34"></stop>
                    <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"></stop>
                </linearGradient>
            </defs>
            <line x1="0" y1="30" x2="300" y2="30" stroke="var(--border-subtle)" stroke-width="1"></line>
            <line x1="0" y1="60" x2="300" y2="60" stroke="var(--border-subtle)" stroke-width="1"></line>
            <path d="{{ $line }} L300 92 L0 92 Z" fill="url(#auArea)"></path>
            <path d="{{ $line }}" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
        </svg>
        <div class="au-ticks">
            <span>{{ $nowLocal->copy()->subDays(6)->format('D') }}</span>
            <span>{{ $nowLocal->format('D') }}</span>
        </div>
        <div class="au-mono" style="margin-top:8px">Peak {{ (int) $spark->max() }} &middot; {{ $peakDay }}</div>
    </section>

    {{-- ============ WHERE CLICKS COME FROM ============ --}}
    <section class="card-premium au-pad c4">
        <div class="au-ph">
            <span class="au-label">Where clicks come from</span>
            <span class="au-note">Lifetime</span>
        </div>
        @if($channelTotal < 1)
            <p class="au-empty">Nothing recorded yet. This fills in as your links get opened.</p>
        @else
            <div class="au-ring-wrap">
                <svg class="au-ring" viewBox="0 0 42 42" role="img" aria-label="Click sources by share.">
                    <circle cx="21" cy="21" r="15.9" fill="none" stroke="var(--bg-glass-input)" stroke-width="5"></circle>
                    @php $offset = 25; @endphp
                    @foreach($channelTop as $i => $row)
                        @php
                            $pct = round(($row->count / max(1, $channelTotal)) * 100, 1);
                            $dash = $pct . ' ' . (100 - $pct);
                        @endphp
                        <circle cx="21" cy="21" r="15.9" fill="none"
                                stroke="{{ $channelHues[$i % count($channelHues)] }}" stroke-width="5"
                                stroke-dasharray="{{ $dash }}" stroke-dashoffset="{{ $offset }}"></circle>
                        @php $offset -= $pct; @endphp
                    @endforeach
                </svg>
                <div class="au-legend">
                    @foreach($channelTop as $i => $row)
                        <span>
                            <i style="background: {{ $channelHues[$i % count($channelHues)] }}"></i>
                            {{ Str::limit(Str::headline((string) $row->channel), 16) }}
                            <b>{{ round(($row->count / max(1, $channelTotal)) * 100) }}%</b>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- ============ FOLDERS ============ --}}
    <section class="card-premium au-pad c4">
        <div class="au-ph">
            <span class="au-label">Folders</span>
            <span class="au-note">{{ number_format($filedCount) }} of {{ number_format($totalLinks) }} filed</span>
        </div>
        @forelse($deskFolders->take(5) as $f)
            <a href="{{ route('user.projects.show', $f) }}" class="au-row" style="text-decoration:none">
                <span class="au-row-name"><i class="au-dot" style="background: {{ $folderColour[$f->id] }}"></i>{{ Str::limit($f->name, 20) }}</span>
                <span class="au-mono">{{ $f->links_count }} {{ Str::plural('link', $f->links_count) }}</span>
                <span class="au-bar"><i style="width: {{ round(($f->links_count / $maxFolder) * 100) }}%; background: {{ $folderColour[$f->id] }}"></i></span>
            </a>
        @empty
            <p class="au-empty">No folders yet. Group your links to see them here.</p>
        @endforelse
    </section>

    {{-- ============ TOP LINKS ============ --}}
    <section class="card-premium au-pad c8">
        <div class="au-ph">
            <span class="au-label">Top links</span>
            <span class="au-note">By lifetime clicks</span>
        </div>
        @forelse($topLinks as $i => $l)
            <div class="au-rank">
                <span class="au-rank-n">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <span class="au-slug"><em>/</em>{{ $l->alias }}@if($l->title) <em>&middot; {{ Str::limit($l->title, 28) }}</em>@endif</span>
                <span class="au-track"><i style="width: {{ max(3, round((((int) $l->total_clicks) / $maxTop) * 100)) }}%"></i></span>
                <span class="au-val">{{ number_format($l->total_clicks) }}</span>
            </div>
        @empty
            <p class="au-empty">No links yet. Create one and it will show up here.</p>
        @endforelse
    </section>

    {{-- ============ MINIS ============ --}}
    <section class="card-premium au-pad c4">
        <div class="au-minis">
            <div>
                <div class="au-ph" style="margin-bottom:6px">
                    <span class="au-label">Links</span>
                    <span class="au-note">{{ number_format($activeLinks) }} active</span>
                </div>
                <div class="au-mini">
                    <span class="au-fig sm">{{ number_format($totalLinks) }}</span>
                    <span class="au-mono">{{ number_format(max(0, $totalLinks - $activeLinks)) }} paused</span>
                </div>
                <div class="au-split">
                    <i style="width: {{ $totalLinks > 0 ? round(($activeLinks / $totalLinks) * 100) : 0 }}%; background: var(--accent)"></i>
                    <i style="width: {{ $totalLinks > 0 ? 100 - round(($activeLinks / $totalLinks) * 100) : 100 }}%; background: var(--text-faint)"></i>
                </div>
            </div>
            <div class="au-sep">
                <div class="au-ph" style="margin-bottom:6px">
                    <span class="au-label">Backlinks</span>
                    <span class="au-note">Last 7 days</span>
                </div>
                <div class="au-mini">
                    <span class="au-fig sm">{{ number_format($backlinksThisWeek) }}</span>
                    <span class="au-mono">captured</span>
                </div>
            </div>
            <div class="au-sep">
                <div class="au-ph" style="margin-bottom:6px">
                    <span class="au-label">Plan</span>
                    <span class="au-note">{{ $planLabel }}</span>
                </div>
                <div class="au-mini">
                    <span class="au-fig xs">{{ Str::limit($user->plan->name ?? 'Free', 14) }}</span>
                    <span class="au-pill">Active</span>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
(function () {
    /* Link graph. Nodes are real links: coloured by folder, radius by clicks,
       drifting slowly around their folder's anchor. Unfiled links orbit the
       middle at low opacity so the filed clusters stay readable. */
    var cv = document.getElementById('auGraph');
    if (!cv || !cv.getContext) return;
    var ctx = cv.getContext('2d');
    var stage = document.getElementById('auStage');
    var tip = document.getElementById('auTip');
    var tipSlug = document.getElementById('auTipSlug');
    var tipMeta = document.getElementById('auTipMeta');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var raw = [], anchors = {};
    try {
        raw = JSON.parse(cv.getAttribute('data-nodes') || '[]');
        anchors = JSON.parse(cv.getAttribute('data-anchors') || '{}');
    } catch (e) { return; }
    if (!raw.length) return;

    var faint = getComputedStyle(document.documentElement).getPropertyValue('--text-faint').trim() || '#5e5a70';

    /* Radius is on a square root of clicks so one runaway link does not
       swallow the canvas: doubling the clicks widens the dot by ~40%, not
       100%. */
    var maxV = 1;
    raw.forEach(function (n) { if (n.v > maxV) maxV = n.v; });

    var nodes = raw.map(function (n, i) {
        var a = anchors[n.p];
        var filed = !!(a && n.c);
        return {
            hx: filed ? a.x : 0.5,
            hy: filed ? a.y : 0.5,
            ang: (i * 2.399) % (Math.PI * 2),
            dist: filed ? (0.05 + (i % 3) * 0.022) : (0.20 + ((i * 37) % 100) / 100 * 0.28),
            spd: filed ? (0.00015 + (i % 4) * 0.00006) : (0.00004 + ((i * 13) % 7) * 0.00002),
            r: filed ? (2.6 + Math.sqrt(n.v / maxV) * 4.4) : (1.0 + Math.sqrt(n.v / maxV) * 1.6),
            col: filed ? n.c : faint,
            filed: filed,
            slug: n.s,
            v: n.v
        };
    });

    var w = 0, h = 0, dpr = 1, px = 0.5, py = 0.5, hover = null, pts = [];

    function size() {
        var rect = cv.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        w = rect.width; h = rect.height;
        cv.width = Math.round(w * dpr);
        cv.height = Math.round(h * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function draw(t) {
        if (!w || !h) { size(); if (!w) return; }
        ctx.clearRect(0, 0, w, h);
        var i, n, a, ox = (px - 0.5) * 16, oy = (py - 0.5) * 12;
        pts = [];
        for (i = 0; i < nodes.length; i++) {
            n = nodes[i];
            a = n.ang + t * n.spd;
            pts.push({
                x: (n.hx + Math.cos(a) * n.dist) * w + ox * (n.filed ? 1 : 0.45),
                y: (n.hy + Math.sin(a) * n.dist * 1.5) * h + oy * (n.filed ? 1 : 0.45),
                n: n
            });
        }
        ctx.lineWidth = 1;
        for (i = 0; i < pts.length; i++) {
            n = pts[i].n;
            if (!n.filed) continue;
            ctx.beginPath();
            ctx.moveTo(n.hx * w + ox, n.hy * h + oy);
            ctx.lineTo(pts[i].x, pts[i].y);
            ctx.strokeStyle = n.col;
            ctx.globalAlpha = (hover && hover.n === n) ? 0.5 : 0.18;
            ctx.stroke();
        }
        ctx.globalAlpha = 1;
        for (i = 0; i < pts.length; i++) {
            n = pts[i].n;
            var hot = hover && hover.n === n;
            if (n.filed) {
                ctx.globalAlpha = hot ? 0.30 : 0.15;
                ctx.beginPath();
                ctx.arc(pts[i].x, pts[i].y, n.r * (hot ? 4 : 3), 0, Math.PI * 2);
                ctx.fillStyle = n.col; ctx.fill();
            }
            ctx.globalAlpha = n.filed ? 0.95 : 0.45;
            ctx.beginPath();
            ctx.arc(pts[i].x, pts[i].y, n.r * (hot ? 1.35 : 1), 0, Math.PI * 2);
            ctx.fillStyle = n.col; ctx.fill();
        }
        ctx.globalAlpha = 1;
    }

    function pick(mx, my) {
        var best = null, bestD = 256, i, dx, dy, d;
        for (i = 0; i < pts.length; i++) {
            dx = pts[i].x - mx; dy = pts[i].y - my; d = dx * dx + dy * dy;
            if (d < bestD) { bestD = d; best = pts[i]; }
        }
        return best;
    }

    stage.addEventListener('pointermove', function (ev) {
        var rect = cv.getBoundingClientRect();
        var mx = ev.clientX - rect.left, my = ev.clientY - rect.top;
        px = mx / rect.width; py = my / rect.height;
        hover = pick(mx, my);
        if (hover) {
            tipSlug.textContent = '/' + hover.n.slug;
            tipMeta.textContent = hover.n.v + (hover.n.v === 1 ? ' click' : ' clicks');
            tip.style.left = hover.x + 'px';
            tip.style.top = hover.y + 'px';
            tip.classList.add('on');
        } else {
            tip.classList.remove('on');
        }
        if (reduce) draw(0);
    });
    stage.addEventListener('pointerleave', function () {
        hover = null; px = 0.5; py = 0.5;
        tip.classList.remove('on');
        if (reduce) draw(0);
    });

    size();
    draw(0);
    if (!reduce) {
        var start = performance.now();
        requestAnimationFrame(function loop(now) {
            draw(now - start);
            requestAnimationFrame(loop);
        });
    }
    window.addEventListener('resize', function () { size(); draw(reduce ? 0 : performance.now()); });
})();
</script>
@endpush
