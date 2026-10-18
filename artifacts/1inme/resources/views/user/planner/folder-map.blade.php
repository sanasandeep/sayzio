@php
$fnetMeasures = \App\Services\Planner\FolderFlowMeasures::all($resources, $companionConversations ?? 0);
$fnetActivity = $kindActivity ?? app(\App\Services\Planner\FolderKindActivity::class)->data($project, $resources);
$fnetFaces = \App\Services\Planner\FolderKindActivity::FACES;
$fnet = \App\Services\Planner\FolderFlowNetwork::map($resources, $relations, $fnetMeasures, $fnetActivity);
$fnetLabels = \App\Services\Planner\PlannerResources::typeLabels();
$fnetStages = \App\Services\Planner\FolderFlowStages::all();
$fnetDrawn = \App\Services\Planner\FolderFlowNetwork::drawnCount($fnet['edges']);
$fnetMissed = \App\Services\Planner\FolderFlowNetwork::undrawableCount($fnet['edges']);
$fnetItems = collect($resources)->groupBy('kind');
$fnetMeta = \App\Services\Planner\FolderFlowNetwork::filterMeta($fnet['stations']);
$guideMeta = \App\Services\Planner\FolderFlowNetwork::filterMeta(\App\Services\Planner\FolderFlowDemo::map()['stations']);
$mapColor=preg_match('/^#[0-9a-f]{6}$/i',$project->color??'')?$project->color:'#365ce9';
// Two shapes per view: the diagram, and the same content as an outline.
// 'guide' is built by hand because it is not one of MODES -- the exporter
// has no folder to render it from, and the view used to look it up anyway,
// which is why that tab's export downloaded a file saying "undefined".
$chartExports = [];
foreach (\App\Services\Planner\FolderChartExport::MODES as $chartMode) {
    $chartExports[$chartMode] = [
        'map'  => \App\Services\Planner\FolderChartExport::render($chartMode, $project->name, $resources, $peopleData['people'], $mapColor, $relations),
        'tree' => \App\Services\Planner\FolderChartExport::renderTree($chartMode, $project->name, $resources, $peopleData['people'], $mapColor, $relations),
    ];
}
$chartExports['guide'] = [
    'map'  => \App\Services\Planner\FolderChartExport::renderGuide(),
    'tree' => \App\Services\Planner\FolderChartExport::renderTree('guide', $project->name, [], []),
];
@endphp
<section id="project-map" class="notes-card folder-map visual-folder-map folder-flow-net" style="--folder-color:{{ $mapColor }};--ov-tone:{{ $mapColor }}" x-data="folderChart(@js($chartExports),@js($fnetMeta),@js($guideMeta))" @keydown.escape.window="closePanel()">
<div class="folder-panel-head"><div><h2>Map view · explore connections</h2><p class="notes-hint">Select a node to explore its items, assign resources or create something new.</p></div><div class="folder-view-switch"><button @click="mode='flow'" :aria-pressed="mode==='flow'">Flow</button><button @click="mode='people'" :aria-pressed="mode==='people'">People / Process</button><button @click="mode='guide'" :aria-pressed="mode==='guide'">How it works</button></div></div>
<div class="notes-actions folder-export-actions"><span class="fnet-shape" role="group" aria-label="What to export"><button class="form-chip" type="button" @click="shape='map'" :aria-pressed="shape==='map'" :class="shape==='map'?'is-on':''">Map</button><button class="form-chip" type="button" @click="shape='tree'" :aria-pressed="shape==='tree'" :class="shape==='tree'?'is-on':''">Outline</button></span><button class="form-chip" type="button" @click="download('png')" :disabled="exporting">Export PNG</button><button class="form-chip" type="button" @click="download('svg')" :disabled="exporting">Export SVG</button><span class="notes-hint" role="status" x-text="exportError"></span></div>
<div class="map-panel-backdrop" x-show="selected!==null" x-cloak @click.self="closePanel()">
<aside id="folder-map-panel" class="map-detail-panel notes-card" role="dialog" aria-modal="true" aria-label="Assigned folder items" tabindex="-1" x-ref="panel" @keydown.tab="trapTab($event)">
@foreach($fnet['stations'] as $fnetPanel)
@php $fnetPanelItems = $fnetItems->get($fnetPanel['kind'], collect()); @endphp
<div class="map-panel-section fnet-panel" style="--ln:var(--fnet-{{ $fnetPanel['stage'] }})" x-show="selected==='flow:{{ $fnetPanel['kind'] }}'">
<header class="map-panel-header"><span class="map-panel-emblem fnet-panel-emblem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $fnetPanel['icon'] !!}</svg></span><div class="map-panel-heading"><h2>{{ $fnetLabels[$fnetPanel['kind']] ?? $fnetPanel['kind'] }}</h2><p>{{ number_format($fnetPanelItems->count()) }} in this folder &middot; {{ $fnetStages[$fnetPanel['stage']]['eyebrow'] ?? '' }}@if($fnetPanel['ai']) &middot; runs on AI @endif</p></div><button class="map-panel-close" type="button" @click="closePanel()" aria-label="Close station details"><i class="fas fa-xmark" aria-hidden="true"></i></button></header>
@if($fnetPanel['kind'] === 'task' && $fnetPanelItems->isNotEmpty())
@php $fnetDone = $fnetPanelItems->filter(fn($v) => $v['row']->completed_at)->count(); @endphp
<p class="notes-hint fnet-panel-note"><span class="folder-progress"><span style="width:{{ round($fnetDone / $fnetPanelItems->count() * 100) }}%"></span></span>{{ $fnetDone }} of {{ $fnetPanelItems->count() }} done</p>
@endif
<div class="map-panel-body">
@forelse($fnetPanelItems as $fnetItem)
<a class="map-item-card" href="{{ $fnetItem['url'] }}"><span class="map-item-copy"><strong>{{ $fnetItem['label'] }}</strong><small>
@if($fnetPanel['kind'] === 'brand')
@foreach(collect($fnetItem['row']->palette())->only(['primary','secondary','accent']) as $fnetSwatch)
@if(preg_match('/^#[0-9a-f]{3,8}$/i', (string) $fnetSwatch))<i class="fnet-swatch" style="background:{{ $fnetSwatch }}"></i>@endif
@endforeach
@elseif($fnetPanel['kind'] === 'task')
{{ $fnetItem['row']->completed_at ? 'Done' : ($fnetItem['row']->due_date?->format('d M') ?? 'No due date') }}
@elseif($fnetPanel['kind'] === 'link')
{{ $fnetItem['row']->is_active ? 'Active' : 'Inactive' }}
@else
{{ $fnetLabels[$fnetPanel['kind']] ?? $fnetPanel['kind'] }}
@endif
</small></span><span class="map-item-open">Open <i class="fas fa-arrow-right" aria-hidden="true"></i></span></a>
@empty
<div class="map-panel-empty"><strong>Nothing of this kind in {{ $project->name }} yet.</strong><p>Assign something that already exists, or make one from its own tab.</p></div>
@endforelse
</div>
@if($fnetPanel['people'])
<div class="fnet-panel-people"><h3>Worked here in the last {{ $fnetActivity['window_days'] }} days</h3>
@foreach($fnetPanel['people'] as $fnetPerson)
<span class="fnet-panel-person"><img src="{{ $fnetPerson['avatar_url'] }}" alt="" loading="lazy"><b>{{ $fnetPerson['name'] }}</b><small>{{ number_format($fnetPerson['changes']) }} change{{ $fnetPerson['changes'] === 1 ? '' : 's' }}</small></span>
@endforeach
<p class="notes-hint">{{ $fnetActivity['owner_name'] ?? 'The owner' }} is left out on purpose: they are in every folder, so counting them says nothing about who else is.</p>
</div>
@elseif($fnetActivity['first_recorded_at'] === null)
<p class="notes-hint fnet-panel-note">No changes recorded yet in this workspace, so nobody is shown here. That means recording has just started, not that nobody has worked on it.</p>
@endif
<footer class="map-panel-footer"><a class="form-chip" href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$fnetPanel['tab']]) }}">View all &rarr;</a><a class="form-chip" href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'resources']) }}#assign-resources">+ Assign existing</a></footer>
</div>
@endforeach
@php $guidePanel = \App\Services\Planner\FolderFlowDemo::map(); $guidePanelActivity = \App\Services\Planner\FolderFlowDemo::activity(); @endphp
@foreach($guidePanel['stations'] as $gp)
<div class="map-panel-section fnet-panel" style="--ln:var(--fnet-{{ $gp['stage'] }})" x-show="selected==='guide:{{ $gp['kind'] }}'">
<header class="map-panel-header"><span class="map-panel-emblem fnet-panel-emblem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $gp['icon'] !!}</svg></span><div class="map-panel-heading"><h2>{{ $fnetLabels[$gp['kind']] ?? $gp['kind'] }}</h2><p>{{ number_format($gp['count']) }} in this folder &middot; {{ $fnetStages[$gp['stage']]['eyebrow'] ?? '' }}@if($gp['ai']) &middot; runs on AI @endif</p></div><button class="map-panel-close" type="button" @click="closePanel()" aria-label="Close station details"><i class="fas fa-xmark" aria-hidden="true"></i></button></header>
<p class="notes-hint fnet-panel-note fnet-sample-note">Worked example &mdash; invented items for {{ \App\Services\Planner\FolderFlowDemo::COMPANY }}.</p>
@if($gp['kind'] === 'task' && $gp['count'])
<p class="notes-hint fnet-panel-note"><span class="folder-progress"><span style="width:68%"></span></span>26 of 38 done</p>
@endif
<div class="map-panel-body">
@forelse(\App\Services\Planner\FolderFlowDemo::items($gp['kind']) as $gi)
<span class="map-item-card is-static"><span class="map-item-copy"><strong>{{ $gi['label'] }}</strong><small>{{ $gi['detail'] }}</small></span></span>
@empty
<div class="map-panel-empty"><strong>Nothing of this kind yet.</strong><p>This is what an unused station looks like: dashed on the map, and offering to assign something that already exists or make one from its own tab.</p></div>
@endforelse
@if($gp['count'] > count(\App\Services\Planner\FolderFlowDemo::items($gp['kind'])) && $gp['count'])
<p class="notes-hint">&hellip; and {{ number_format($gp['count'] - count(\App\Services\Planner\FolderFlowDemo::items($gp['kind']))) }} more. The count on the station is the whole folder; the panel lists them.</p>
@endif
</div>
@if($gp['people'])
<div class="fnet-panel-people"><h3>Worked here in the last {{ $guidePanelActivity['window_days'] }} days</h3>
@foreach($gp['people'] as $gperson)
<span class="fnet-panel-person"><i class="fnet-av fnet-initial">{{ mb_substr($gperson['name'], 0, 1) }}</i><b>{{ $gperson['name'] }}</b><small>{{ number_format($gperson['changes']) }} changes</small></span>
@endforeach
</div>
@endif
<footer class="map-panel-footer is-stacked"><span class="map-panel-footer-row"><span class="form-chip is-disabled" aria-disabled="true">View all &rarr;</span><span class="form-chip is-disabled" aria-disabled="true">+ Assign existing</span></span><span class="notes-hint">Both work on your own map.</span></footer>
</div>
@endforeach
</aside></div>
<div x-show="mode==='flow'" x-cloak>
@include('user.planner.fnet-filters',['stages'=>$fnetStages,'filterPeople'=>$fnetActivity['people']])
<div class="fnet-board">
<div class="fnet-canvas">
<svg class="fnet-wires" data-fnet-wires aria-hidden="true"></svg>
<div class="fnet-grid" data-fnet-grid style="--fnet-cols:{{ $fnet['cols'] }}">
@foreach($fnet['stations'] as $fnetStation)
@include('user.planner.fnet-station',['station'=>$fnetStation,'stages'=>$fnetStages,'labels'=>$fnetLabels,'faces'=>$fnetFaces,'activity'=>$fnetActivity,'interactive'=>true,'panelKey'=>'flow'])

@endforeach
</div>
@include('user.planner.fnet-tree',['stations'=>$fnet['stations'],'stages'=>$fnetStages,'labels'=>$fnetLabels,'panelKey'=>'flow'])
</div>
</div>
@php
$fnetWire = [
    'labels' => $fnetLabels,
    'stages' => array_map(fn ($st) => $st['eyebrow'], $fnetStages),
    'mesh'   => \App\Services\Planner\FolderFlowMesh::reach(),
    'rails'  => $fnet['rails'],
    'edges'  => array_values(array_filter($fnet['edges'], fn ($e) => $e['from'] !== null)),
];
@endphp
<script type="application/json" data-fnet-json>@json($fnetWire)</script>
<div class="fnet-key">
@foreach($fnetStages as $fnetKey => $fnetStage)
<span style="--ln:var(--fnet-{{ $fnetKey }})"><i></i>{{ $fnetStage['eyebrow'] }}</span>
@endforeach
<span><i class="is-join"></i>Saved connection</span>
<span><i class="is-mesh"></i>Can connect &mdash; hover a station</span>
<span><i class="is-ai"></i>Runs on AI</span>
<span><i class="is-plain"></i>Not AI</span>
<span><i class="is-unused"></i>Not used in this folder</span>
</div>
@if($fnetActivity['first_recorded_at'] === null)
<p class="notes-hint mt-4">No changes recorded in this workspace yet, so no station shows a face &mdash; recording has only just started.</p>
@else
<p class="notes-hint mt-4">Faces cover changes recorded since {{ \Illuminate\Support\Carbon::parse($fnetActivity['first_recorded_at'])->format('j M Y') }}; an empty station means nobody since then, not nobody ever.
@if($fnetActivity['owner_changes'])
{{ number_format($fnetActivity['owner_changes']) }} change{{ $fnetActivity['owner_changes'] === 1 ? '' : 's' }} by {{ $fnetActivity['owner_name'] ?? 'the owner' }} counted but not shown.
@endif
@if($fnetActivity['unattributed'])
{{ number_format($fnetActivity['unattributed']) }} cannot say who made {{ $fnetActivity['unattributed'] === 1 ? 'it' : 'them' }}.
@endif</p>
@endif
<p class="notes-hint mt-4"><span class="folder-suggestion-badge">Suggested order</span> Rails are a possible process, not measured attribution. <button type="button" class="fnet-guide-link" @click="mode='guide'">What the map shows &rarr;</button></p>
@if($fnetDrawn)
<p class="notes-hint mt-2">{{ number_format($fnetDrawn) }} saved connection{{ $fnetDrawn === 1 ? '' : 's' }} drawn as junctions. Manage them under Resources.</p>
@else
<p class="notes-hint mt-2">Nothing connected in this folder yet, so no junction is drawn &mdash; none were saved, not nothing happened.</p>
@endif
@if($fnetMissed)
<p class="notes-hint mt-2">{{ number_format($fnetMissed) }} saved connection{{ $fnetMissed === 1 ? '' : 's' }} point at an item kind with no station, so they are counted but not drawn.</p>
@endif
</div>
<script>
(function () {
  // Booted once per board: the real map, and the worked example on the
  // How-it-works view. Same drawing code, so the example cannot drift into
  // looking like a different map.
  var draws = [];
  function boot(board) {
  var grid = board.querySelector('[data-fnet-grid]');
  var svg = board.querySelector('[data-fnet-wires]');
  var json = board.parentNode.querySelector('[data-fnet-json]');
  if (!grid || !svg || !json) { return; }

  var data;
  var baseMarkup = '';
  try { data = JSON.parse(json.textContent); } catch (e) { return; }
  var rails = data.rails || [];
  var edges = data.edges || [];

  function box(kind) {
    var el = grid.querySelector('[data-fnet-kind="' + kind + '"]');
    // A station the filters hid is not on the map. Returning null here drops
    // every rail segment that touched it, leaving an honest gap rather than a
    // line running to somewhere nothing is drawn.
    if (!el || window.getComputedStyle(el).display === 'none') { return null; }
    var g = grid.getBoundingClientRect();
    var r = el.getBoundingClientRect();
    return {
      x: r.left - g.left, y: r.top - g.top, w: r.width, h: r.height,
      cx: r.left - g.left + r.width / 2, cy: r.top - g.top + r.height / 2
    };
  }

  function centre(kind) {
    var b = box(kind);
    return b ? { x: b.cx, y: b.cy } : null;
  }

  function waypoint(spec) {
    if (typeof spec === 'string') { return centre(spec); }
    var a = centre(spec.x);
    var b = centre(spec.y);
    if (!a || !b) { return null; }
    return { x: a.x, y: b.y };
  }

  // Where a rail should leave a station: the point on that card's edge facing
  // the next one, plus a small gap. Drawing centre-to-centre ran the line
  // under every card, and an unused card is see-through, so the rail showed
  // straight across it.
  function edgeToward(kind, target, pad) {
    var b = box(kind);
    if (!b || !target) { return null; }
    var dx = target.x - b.cx;
    var dy = target.y - b.cy;
    if (!dx && !dy) { return { x: b.cx, y: b.cy }; }
    var halfW = b.w / 2 + pad;
    var halfH = b.h / 2 + pad;
    // Scale the direction until it lands on the padded border.
    var sx = dx ? halfW / Math.abs(dx) : Infinity;
    var sy = dy ? halfH / Math.abs(dy) : Infinity;
    var s = Math.min(sx, sy);
    return { x: b.cx + dx * s, y: b.cy + dy * s };
  }

  // A gentle bow, so the rail reads as a flowing line rather than a ruled one.
  function bowed(a, b, lift) {
    var mx = (a.x + b.x) / 2;
    var my = (a.y + b.y) / 2;
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var len = Math.hypot(dx, dy);
    if (!len) { return ''; }
    var nx = -dy / len;
    var ny = dx / len;
    if (len < 110) { return 'M ' + a.x + ' ' + a.y + ' L ' + b.x + ' ' + b.y; }
    var k = Math.min(lift, len / 5);
    return 'M ' + a.x + ' ' + a.y + ' Q ' + (mx + nx * k) + ' ' + (my + ny * k) + ' ' + b.x + ' ' + b.y;
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  var labels = data.labels || {};
  var stageNames = data.stages || {};
  var mesh = data.mesh || {};

  function label(kind) { return labels[kind] || kind; }

  // A rail is a whole chain, not a segment, so the tooltip names the chain.
  function railTitle(rail) {
    var names = [];
    for (var i = 0; i < rail.points.length; i++) {
      if (typeof rail.points[i] === 'string') { names.push(label(rail.points[i])); }
    }
    return (stageNames[rail.stage] || rail.stage) + ': ' + names.join(' \u2192 ')
      + ' \u2014 a suggested order. Drawn whether or not you work this way, and counting nothing.';
  }

  // The third kind of line: what the product allows, rather than what this
  // folder has. Drawn only while a station is hovered or focused, because
  // sixty-three of them at rest is a hairball, not a map.
  function meshMarkup(kind) {
    var targets = mesh[kind] || [];
    var m = '';
    for (var i = 0; i < targets.length; i++) {
      var other = targets[i];
      var ca = centre(kind), cb = centre(other);
      if (!ca || !cb) { continue; }
      var pa = edgeToward(kind, cb, 3), pb = edgeToward(other, ca, 3);
      if (!pa || !pb) { continue; }
      var t = label(kind) + ' can connect to ' + label(other)
        + ' \u2014 something the product allows in every folder. Counts nothing.';
      m += '<path class="fnet-mesh" d="M ' + pa.x + ' ' + pa.y + ' L ' + pb.x + ' ' + pb.y + '"'
        +  ' fill="none" stroke="var(--fnet-ink)" stroke-width="1.3" stroke-linecap="round"'
        +  ' opacity=".26" stroke-dasharray="1.5 5">'
        +  '<title>' + esc(t) + '</title></path>';
    }
    return m;
  }

  function draw() {
    // Hidden below the mobile breakpoint, where the grid is a plain list and
    // a rail between two stacked cards would say something untrue.
    if (window.getComputedStyle(svg).display === 'none') { svg.innerHTML = ''; return; }
    var gbox = grid.getBoundingClientRect();
    if (!gbox.width) { return; }
    svg.setAttribute('viewBox', '0 0 ' + gbox.width + ' ' + gbox.height);
    svg.setAttribute('width', gbox.width);
    svg.setAttribute('height', gbox.height);

    var out = '';

    for (var r = 0; r < rails.length; r++) {
      var pts = rails[r].points;
      var stroke = 'var(--fnet-' + esc(rails[r].stage) + ')';
      for (var i = 0; i < pts.length - 1; i++) {
        var fromSpec = pts[i], toSpec = pts[i + 1];
        var from = waypoint(fromSpec), to = waypoint(toSpec);
        if (!from || !to) { continue; }
        // Start and end on the card's border, never at its centre.
        var a = (typeof fromSpec === 'string') ? edgeToward(fromSpec, to, 3) : from;
        var b = (typeof toSpec === 'string') ? edgeToward(toSpec, from, 3) : to;
        if (!a || !b) { continue; }
        // Straight. A bowed dashed line read as though it were tracing
        // something; a ruled one reads as the diagram it is.
        var d = 'M ' + a.x + ' ' + a.y + ' L ' + b.x + ' ' + b.y;
        out += '<path class="fnet-rail" d="' + d + '" fill="none" stroke="' + stroke + '"'
            +  ' stroke-width="3.5" stroke-linecap="round" opacity=".5" stroke-dasharray="9 8">'
            +  '<title>' + esc(railTitle(rails[r])) + '</title></path>';
      }
    }

    for (var e = 0; e < edges.length; e++) {
      var edge = edges[e];
      if (edge.from === edge.to) { continue; }
      var ca = centre(edge.from), cb = centre(edge.to);
      if (!ca || !cb) { continue; }
      var pa = edgeToward(edge.from, cb, 3);
      var pb = edgeToward(edge.to, ca, 3);
      if (!pa || !pb) { continue; }
      var dx = pb.x - pa.x, dy = pb.y - pa.y;
      var sideways = Math.abs(dx) > Math.abs(dy);
      var c1x = sideways ? pa.x + dx * 0.55 : pa.x;
      var c1y = sideways ? pa.y : pa.y + dy * 0.55;
      var c2x = sideways ? pb.x - dx * 0.55 : pb.x;
      var c2y = sideways ? pb.y : pb.y - dy * 0.55;
      var width = Math.min(2.5 + edge.count, 7);
      var joinTitle = edge.count + ' saved connection' + (edge.count === 1 ? '' : 's')
          + ' between ' + label(edge.from) + ' and ' + label(edge.to)
          + ' \u2014 item to item, saved under Resources. Not a suggestion: these exist.';
      out += '<path class="fnet-join" d="M ' + pa.x + ' ' + pa.y + ' C ' + c1x + ' ' + c1y
          +  ', ' + c2x + ' ' + c2y + ', ' + pb.x + ' ' + pb.y + '" fill="none"'
          +  ' stroke="var(--fnet-join)" stroke-width="' + width + '" stroke-linecap="round" opacity=".9">'
          +  '<title>' + esc(joinTitle) + '</title></path>';
      var mx = (pa.x + c1x * 3 + c2x * 3 + pb.x) / 8;
      var my = (pa.y + c1y * 3 + c2y * 3 + pb.y) / 8;
      out += '<circle cx="' + mx + '" cy="' + my + '" r="10" fill="var(--fnet-card)"'
          +  ' stroke="var(--fnet-join)" stroke-width="1.7">'
          +  '<title>' + esc(joinTitle) + '</title></circle>';
      out += '<text x="' + mx + '" y="' + (my + 3.6) + '" text-anchor="middle" font-size="10.5"'
          +  ' font-weight="600" fill="var(--fnet-ink)">' + esc(edge.count) + '</text>';
    }

    baseMarkup = out;
    svg.innerHTML = out;
  }

  function showMesh(kind) { svg.innerHTML = baseMarkup + meshMarkup(kind); }
  function clearMesh() { if (baseMarkup) { svg.innerHTML = baseMarkup; } }
  function meshFrom(ev) {
    var stn = (ev.target && ev.target.closest) ? ev.target.closest('[data-fnet-kind]') : null;
    if (stn) { showMesh(stn.getAttribute('data-fnet-kind')); }
  }
  grid.addEventListener('mouseover', meshFrom);
  grid.addEventListener('focusin', meshFrom);
  grid.addEventListener('mouseleave', clearMesh);
  grid.addEventListener('focusout', clearMesh);

  if (window.ResizeObserver) { new ResizeObserver(draw).observe(grid); }
  // A board inside a hidden view measures zero at boot, so its rails are
  // never drawn. This catches the moment it is revealed, whatever revealed
  // it -- the view switch, a link in the prose, or a deep link.
  if (window.IntersectionObserver) {
    new IntersectionObserver(function (entries) {
      if (entries.some(function (e) { return e.isIntersecting; })) { draw(); }
    }).observe(grid);
  }
  draws.push(draw);
  requestAnimationFrame(draw);
  }
  // The How-it-works board is declared further down this file, so booting
  // mid-parse only ever found the first one -- which is why the example
  // never drew a rail. Wait for the document before looking.
  function bootAll() { document.querySelectorAll('.fnet-board').forEach(boot); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', bootAll); }
  else { bootAll(); }
  function drawAll() { draws.forEach(function (d) { d(); }); }
  window.addEventListener('resize', drawAll);
  // Hiding a card does not change the grid's size, so the ResizeObserver will
  // not fire. The filters say when to redraw.
  window.addEventListener('fnet:redraw', drawAll);
  document.addEventListener('click', function (ev) {
    // Switching views reveals a board that was display:none, so it has to be
    // measured again before its rails mean anything.
    if (ev.target && ev.target.closest && ev.target.closest('.folder-view-switch,.fnet-guide-link')) {
      setTimeout(drawAll, 60);
    }
  });
})();
</script>
<div x-show="mode==='guide'" x-cloak>
@php
$guide = \App\Services\Planner\FolderFlowDemo::map();
$guideActivity = \App\Services\Planner\FolderFlowDemo::activity();
@endphp
<div class="fnet-sample-band" role="note"><strong>Worked example</strong><span>Every number below is invented &mdash; a fictional folder for {{ \App\Services\Planner\FolderFlowDemo::COMPANY }}, busy mid-launch. Nothing here comes from {{ $project->name }}.</span></div>
@include('user.planner.fnet-filters',['stages'=>$fnetStages,'filterPeople'=>$guideActivity['people']])
<div class="fnet-board is-sample">
<div class="fnet-canvas">
<svg class="fnet-wires" data-fnet-wires aria-hidden="true"></svg>
<div class="fnet-grid" data-fnet-grid style="--fnet-cols:{{ $guide['cols'] }}">
@foreach($guide['stations'] as $guideStation)
@include('user.planner.fnet-station',['station'=>$guideStation,'stages'=>$fnetStages,'labels'=>$fnetLabels,'faces'=>$fnetFaces,'activity'=>$guideActivity,'interactive'=>true,'panelKey'=>'guide'])
@endforeach
</div>
@include('user.planner.fnet-tree',['stations'=>$guide['stations'],'stages'=>$fnetStages,'labels'=>$fnetLabels,'panelKey'=>'guide'])
</div>
</div>
@php
$guideWire = [
    'labels' => $fnetLabels,
    'stages' => array_map(fn ($st) => $st['eyebrow'], $fnetStages),
    'mesh'   => \App\Services\Planner\FolderFlowMesh::reach(),
    'rails'  => $guide['rails'],
    'edges'  => $guide['edges'],
];
@endphp
<script type="application/json" data-fnet-json>@json($guideWire)</script>
<div class="fnet-key">
@foreach($fnetStages as $fnetGk => $fnetGs)
<span style="--ln:var(--fnet-{{ $fnetGk }})"><i></i>{{ $fnetGs['eyebrow'] }}</span>
@endforeach
<span><i class="is-join"></i>Saved connection</span>
<span><i class="is-mesh"></i>Can connect &mdash; hover a station</span>
<span><i class="is-ai"></i>Runs on AI</span>
<span><i class="is-unused"></i>Not used in this folder</span>
</div>
<div class="fnet-guide-grid">
@foreach(\App\Services\Planner\FolderFlowDemo::legend() as $guideNote)
<article class="fnet-guide-card"><h3>{{ $guideNote['title'] }}</h3><p>{{ $guideNote['body'] }}</p></article>
@endforeach
</div>
<p class="notes-hint mt-4">Your own folder starts empty and fills as you use it. <button type="button" class="fnet-guide-link" @click="mode='flow'">Back to {{ $project->name }} &rarr;</button></p>
</div>
<div x-show="mode==='people'" x-cloak>
    <p class="notes-hint mb-4">Who has access, what they are assigned, and their recorded task activity. Shared tasks count for every assignee; this view does not indicate live presence.</p>
    <div class="folder-card-grid">@foreach($peopleData['people'] as $person)<article class="folder-person-card"><div class="folder-person-heading"><span class="folder-person-avatar" x-data="{photoFailed:false}"><span x-show="photoFailed" x-cloak>{{ mb_substr($person['name'],0,1) }}</span><img src="{{ $person['avatar_url'] }}" alt="{{ $person['name'] }}" loading="lazy" x-show="!photoFailed" x-on:error="photoFailed=true" style="width:100%;height:100%;object-fit:cover;border-radius:inherit"></span><div><strong>{{ $person['name'] }}</strong><small>{{ $person['role'] }}</small></div></div><div class="folder-person-stats"><span><strong>{{ $person['open'] }}</strong>Open tasks</span><span><strong>{{ $person['completed'] }}</strong>Completed</span><span><strong>{{ $person['actions'] }}</strong>Task actions · 30d</span><span><strong>{{ $person['changes'] === null ? '—' : number_format($person['changes']) }}</strong>Changes · 30d</span></div><div class="folder-person-process"><span>Assigned {{ $person['assigned'] }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Open {{ $person['open'] }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Done {{ $person['completed'] }}</span></div>@forelse(array_slice($person['tasks'],0,4) as $personTask)<div class="folder-mini-row"><i class="fas {{ $personTask['completed']?'fa-circle-check':'fa-circle' }}" aria-hidden="true"></i><span>{{ $personTask['title'] }}</span><small>{{ $personTask['completed']?'Done':($personTask['due']??'No due date') }}</small></div>@empty<p class="notes-hint mt-3">No visible task assignments yet.</p>@endforelse</article>@endforeach</div>
</div>
<div class="folder-connection-summary"><span><span class="map-legend-solid"></span> Assigned project resources</span><span><span class="map-legend-dashed"></span> Suggested paths</span></div>
@if($relations->isNotEmpty())
@php $mapItems=collect($resources)->keyBy(fn($i)=>$i['kind'].':'.$i['row']->id); @endphp
<div class="folder-recorded-pairs">@foreach($relations->take(3) as $pair)<a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'resources']) }}"><strong>{{ $mapItems[$pair->source_kind.':'.$pair->source_id]['label'] }}</strong> {{ $pair->label }} → <strong>{{ $mapItems[$pair->target_kind.':'.$pair->target_id]['label'] }}</strong></a>@endforeach</div><p class="notes-hint mt-2">{{ $relations->count() }} recorded item connections · manage under Resources.</p>
@endif
@php $eligible=collect($resources)->whereIn('kind',['link','form','task','plan','mind','staff','strategy'])->values(); @endphp
@if($eligible->count()>1 && $relations->isEmpty())<p class="notes-hint mt-3">Suggestion: connect a task or plan to the content it supports. Review and save it in Resources; no association is created automatically.</p>@endif
@foreach(collect($resources)->where('kind','plan')->take(2) as $suggestionPlan)
@if(!empty($suggestionPlan['row']->result['tasks']))<div class="folder-suggested-task"><span class="folder-suggestion-badge">AI plan suggestion · review first</span><strong>{{ $suggestionPlan['row']->result['tasks'][0]['title'] }}</strong><a href="{{ $suggestionPlan['url'] }}" class="notes-hint">Review in {{ $suggestionPlan['label'] }} →</a></div>@endif
@endforeach
</section>

<script>
function folderChart(exports, stations, guideStations) {
    return { mode:'flow', shape:'map', selected:null, trigger:null,
      stations: stations || [],
      guideStations: guideStations || [],
      fnetHideUnused:false, fnetStageOn:{}, fnetAi:'all', fnetPerson:'',
      fnetShow(s) {
        if (this.fnetHideUnused && !s.used) { return false; }
        if (this.fnetStageOn[s.stage] === false) { return false; }
        if (this.fnetAi === 'ai' && !s.ai) { return false; }
        if (this.fnetAi === 'plain' && s.ai) { return false; }
        if (this.fnetPerson !== '' && !s.people.includes(Number(this.fnetPerson))) { return false; }
        return true;
      },
      // One set of filter state, two boards. Whichever is on screen is the
      // one the chips are describing, so a filter set while reading the
      // example is still set when you look at your own map.
      fnetMetaFor() { return this.mode === 'guide' ? this.guideStations : this.stations; },
      fnetVisible(kind) { const s = this.fnetMetaFor().find(x => x.kind === kind); return s ? this.fnetShow(s) : true; },
      fnetShown() { return this.fnetMetaFor().filter(s => this.fnetShow(s)).length; },
      fnetHidden() { return this.fnetMetaFor().length - this.fnetShown(); },
      fnetFiltered() { return this.fnetHidden() > 0; },
      fnetClear() { this.fnetHideUnused=false; this.fnetStageOn={}; this.fnetAi='all'; this.fnetPerson=''; this.fnetRedraw(); },
      // The rails have to follow: a segment to a hidden station must go.
      fnetRedraw() { this.$nextTick(() => window.dispatchEvent(new Event('fnet:redraw'))); },
      fnetToggleStage(key) { this.fnetStageOn[key] = this.fnetStageOn[key] === false; this.fnetRedraw(); },
      fnetStageIsOn(key) { return this.fnetStageOn[key] !== false; }, openPanel(index,trigger){this.selected=index;this.trigger=trigger;this.previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';this.$nextTick(()=>this.$refs.panel.focus())}, closePanel(){if(this.selected===null)return;this.selected=null;document.body.style.overflow=this.previousOverflow||'';this.trigger?.focus()}, trapTab(event){const items=[...this.$refs.panel.querySelectorAll('a,button,[tabindex]')].filter(el=>el.getClientRects().length&&!el.disabled);const first=items[0],last=items.at(-1);if(!first){event.preventDefault();return}if(event.shiftKey&&(document.activeElement===first||document.activeElement===this.$refs.panel)){event.preventDefault();last.focus()}else if(!event.shiftKey&&(document.activeElement===last||document.activeElement===this.$refs.panel)){event.preventDefault();first.focus()}}, exporting:false, exportError:'', async download(format) {
        this.exporting=true; this.exportError=''; let sourceUrl;
        try {
            const svg=(exports[this.mode]||{})[this.shape];
            // Downloading undefined writes a file containing the word
            // "undefined", which is what this tab did for every export
            // it has ever had. Say so instead.
            if(!svg) throw new Error('No export for this view');
            let blob=new Blob([svg],{type:'image/svg+xml;charset=utf-8'});
            if(format==='png') {
                sourceUrl=URL.createObjectURL(blob); const img=new Image();
                await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=sourceUrl;});
                const canvas=document.createElement('canvas'); canvas.width=img.naturalWidth*2;canvas.height=img.naturalHeight*2;
                canvas.getContext('2d').drawImage(img,0,0,canvas.width,canvas.height);
                blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
                if(!blob) throw new Error('Export failed');
            }
            const url=URL.createObjectURL(blob), a=document.createElement('a'); a.href=url;a.download='project-'+this.mode+'-'+this.shape+'.'+format;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
        } catch(error) {this.exportError='Image export failed. Try the SVG download.';} finally {if(sourceUrl)URL.revokeObjectURL(sourceUrl);this.exporting=false;}
    }};
}
</script>
