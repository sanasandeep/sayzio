@extends('user.layouts.app')
@section('title', 'Dashboard')
@push('styles')
@include('user.partials.overview-styles')
@include('user.partials.dashboard-flow-styles')
@endpush
@section('content')
@php
    $workspace = app()->bound('current_workspace') ? app('current_workspace') : null;
    $canCreate = $workspace && auth()->user()->canInWorkspace($workspace, 'links.create');
    $colors = ['#3b82f6', '#8b5cf6', '#0d9488', '#f59e0b'];
    $cardKinds = ['links', 'pages', 'folders', 'qr'];
    $cardTones = ['#123c85', '#512b89', '#075953', '#804210'];
@endphp
<div class="ov-wrap">
    <header class="ov-head">
        <div><p class="ov-muted">{{ now(\App\Support\PlatformTimezone::forUser(auth()->user()))->format('l, j M') }} · Your workspace</p><h1>Everything you need to keep building.</h1><p class="ov-muted">Manage your links, check your allowance, and pick up where you left off.</p></div>
    </header>
    {{-- Above the counts, unlike the flow panel below them, and for the
         opposite reason: this is not orientation. It is a door opening in
         an hour. It renders only when there is something in the window,
         so on an ordinary day the dashboard is exactly as it was. --}}
    @include('user.dashboard.partials.events-soon', ['eventsSoon' => $eventsSoon ?? collect()])
    <div class="ov-grid">
        @foreach($counts as $label => $count)
        <article class="ov-panel ov-count" style="--ov-color: {{ $colors[$loop->index] }};--ov-tone: {{ $cardTones[$loop->index] }};--ov-number-size: {{ strlen(number_format($count)) > 7 ? '22px' : (strlen(number_format($count)) > 5 ? '30px' : '54px') }}">
            <div class="ov-count-top"><h2 class="ov-count-label">{{ $label }}</h2></div>
            @include('user.partials.count-card-art', ['kind'=>$cardKinds[$loop->index], 'artId'=>'workspace-count-'.$loop->index])
            <div class="ov-count-bottom"><strong>{{ number_format($count) }}</strong><p>In this workspace</p></div>
        </article>
        @endforeach
    </div>
    {{-- The flow panel sits BELOW the workspace cards on purpose. The dashboard
         is a working surface people open every day; an orientation panel above
         the numbers would be a wall between them and the thing they signed in
         to do, every time, forever. Below the cards it is found when wanted and
         scrolled past when not.

         It is fetched rather than rendered inline because its counts are
         nineteen COUNT(*) queries on a cold cache. First paint stays exactly as
         fast as it is today. --}}
    <section class="ov-panel dflow-skeleton" id="dashboard-flow" data-flow-url="{{ route('user.dashboard.flow') }}" aria-busy="true" aria-live="polite">
        <p class="ov-muted">Working out which parts of Sayzio you are using&hellip;</p>
        <span class="dflow-skeleton-bar" aria-hidden="true"></span>
        <span class="dflow-skeleton-bar" aria-hidden="true"></span>
    </section>
    <div class="ov-columns">
        <section class="ov-panel">
            <div class="ov-line"><h2>Plan &amp; usage</h2><span class="ov-muted">{{ $user->plan->name ?? 'Free' }}</span></div>
            <p class="ov-muted">Account allowance across all your workspaces.</p>
            @foreach($usage as $item)
            @php
                $unlimited = $item['limit'] < 0 || $item['limit'] === PHP_INT_MAX;
                $percent = $unlimited ? 0 : ($item['limit'] > 0 ? min(100, round($item['used'] / $item['limit'] * 100)) : 0);
            @endphp
            <div class="ov-usage" style="--ov-color: {{ $item['color'] }}">
                <div class="ov-line"><strong>{{ $item['label'] }}</strong><span class="ov-muted">{{ number_format($item['used'], $item['unit'] ? 1 : 0) }}{{ $item['unit'] }} / {{ $unlimited ? 'Unlimited' : number_format($item['limit'], $item['unit'] ? 1 : 0) . $item['unit'] }}</span></div>
                @unless($unlimited)
                <div class="ov-meter" role="progressbar" aria-label="{{ $item['label'] }} allowance used" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}"><span style="width: {{ $percent }}%"></span></div>
                @if($item['limit'] === 0)<p class="ov-muted">Not included in this plan.</p>@elseif($item['used'] >= $item['limit'])<p class="ov-muted">Allowance reached.</p>@endif
                @endunless
            </div>
            @endforeach
            @if($aiCoins !== null)
            <div class="ov-usage"><div class="ov-line"><strong>AI coins</strong><a href="{{ route('user.wallet.show') }}" class="ov-muted">{{ number_format($aiCoins) }} available →</a></div><p class="ov-muted">Paid from your wallet as you use AI.</p></div>
            @endif
        </section>
        <section class="ov-panel" id="folders">
            <div class="ov-line"><h2>Your folders</h2><a href="{{ route('user.links.index') }}" class="ov-muted">All links →</a></div>
            <p class="ov-muted">Open a folder to manage its links and view its reports.</p>
            <div class="ov-folders">
                @foreach($folders as $folder)
                <a class="ov-folder" style="--ov-color: {{ preg_match('/^#[0-9a-f]{6}$/i', (string) $folder->color) ? $folder->color : '#3b82f6' }}" href="{{ route('user.projects.show', $folder) }}">@include('user.partials.folder-art')<div class="ov-folder-caption"><strong>{{ $folder->name }}</strong><span class="ov-muted">{{ number_format($folder->links_count) }} links</span></div><span class="ov-folder-arrow" aria-hidden="true">↗</span></a>
                @endforeach
            </div>
            @if($folders->isEmpty())<p class="ov-empty">Create a folder to keep related links together.</p>@endif
            <div class="ov-attention">
                @if($unfiled)<a href="{{ route('user.links.index', ['project_id' => 'none']) }}">{{ $unfiled }} unfiled links</a>@endif
                @if($inactive)<a href="{{ route('user.links.index', ['status' => 'inactive']) }}">{{ $inactive }} inactive links</a>@endif
            </div>
            @if($canCreate)
            <details><summary class="ov-muted">Create a folder</summary><form method="POST" action="{{ route('user.projects.store') }}" class="mt-3">@csrf<label for="folder-name" class="ov-muted">Folder name</label><input id="folder-name" name="name" class="theme-input w-full" required maxlength="255" value="{{ old('name') }}">@error('name')<p class="ov-muted">{{ $message }}</p>@enderror<button type="submit" class="btn-primary mt-3">Create folder</button></form></details>
            @endif
            <a class="ov-muted" href="{{ route('user.stats.index') }}">Open account analytics →</a>
        </section>
    </div>
    <section class="ov-panel"><div class="ov-line"><h2>Recently updated</h2><a href="{{ route('user.links.index') }}" class="ov-allbtn">All links <span aria-hidden="true">&rarr;</span></a></div><p class="ov-muted">Continue working on your latest links.</p>
        @forelse($recentLinks as $link)
        @php
            // Same resolver the My Links rows use, so a link wears the same
            // type colour, label and favicon in both places. A dashboard that
            // calls a page "Restaurant Menu" and the list that calls it
            // something else is how people stop trusting either one.
            $ovTs = \App\Modules\User\Support\LinkTileStyle::for($link);
            $ovIcon = $link->favicon ?: ($link->settings['biolink']['favicons']['icon_512'] ?? $link->settings['biolink']['favicons']['apple_touch_icon'] ?? null);
            if (! $ovIcon && $link->long_url) {
                $ovHost = parse_url($link->long_url, PHP_URL_HOST);
                if ($ovHost) { $ovIcon = 'https://www.google.com/s2/favicons?sz=64&domain='.urlencode($ovHost); }
            }
            $ovIcon = \App\Support\PublicStorageUrl::resolve($ovIcon);
            if ($ovIcon && ! preg_match('~^(https?://|/(?!/))~i', $ovIcon)) { $ovIcon = null; }
            $ovFallback = url('/favicon-96x96.png');
            $ovClicks = (int) ($link->total_clicks ?? 0);
        @endphp
        <div class="ov-link" style="--lt:{{ $ovTs['color'] }};--ltbg:{{ $ovTs['bg'] }};--ltbd:{{ $ovTs['border'] }}">
            <span class="ov-link-icon"><img src="{{ $ovIcon ?: $ovFallback }}" data-fallback="{{ $ovFallback }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="if(this.src!==this.dataset.fallback){this.src=this.dataset.fallback;}"></span>
            <div class="ov-link-main">
                <div class="ov-link-head">
                    <a href="{{ route('user.links.show', $link) }}">{{ $link->title ?: $link->alias }}</a>
                    <span class="ov-type">{{ $ovTs['label'] }}</span>
                    @if($link->is_active)<span class="ov-on"><i class="fas fa-circle" aria-hidden="true"></i>Active</span>@else<span class="ov-off">Inactive</span>@endif
                </div>
                <div class="ov-link-meta" x-data="{ copied: false }">
                    <span class="ov-url" title="{{ $link->getShortUrl() }}">{{ $link->getShortUrl() }}</span>
                    <button type="button" class="ov-copy" :aria-label="copied ? 'Link copied' : 'Copy link'"
                            @click="navigator.clipboard.writeText(@js($link->getShortUrl())).then(() => { copied = true; setTimeout(() => copied = false, 2000); }).catch(() => { copied = false; })">
                        <i x-show="!copied" class="fas fa-copy" aria-hidden="true"></i>
                        <i x-show="copied" x-cloak class="fas fa-check" aria-hidden="true"></i>
                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                    </button>
                    <span class="ov-sep" aria-hidden="true">&middot;</span>
                    <span class="ov-where">{{ $link->project?->name ?? 'No folder' }}</span>
                    <span class="ov-sep" aria-hidden="true">&middot;</span>
                    <span class="ov-age">{{ $link->updated_at->diffForHumans() }}</span>
                </div>
            </div>
            <div class="ov-link-clicks"><b>{{ number_format($ovClicks) }}</b><small>{{ $ovClicks === 1 ? 'click' : 'clicks' }}</small></div>
        </div>
        @empty
        <p class="ov-empty">Your first link starts here. Choose Create Link to get started.</p>
        @endforelse
    </section>
</div>
@push('scripts')
<script>
(function () {
    var host = document.getElementById('dashboard-flow');
    if (!host) return;

    function stops(root) { return Array.prototype.slice.call(root.querySelectorAll('.dflow-stop')); }

    function select(root, stop) {
        var all = stops(root), picked = all.indexOf(stop);
        all.forEach(function (s, i) {
            var on = i === picked;
            s.setAttribute('aria-selected', on ? 'true' : 'false');
            s.setAttribute('tabindex', on ? '0' : '-1');
            var panel = document.getElementById(s.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
        });
        // The arrows colour in up to the open stage, so the rail shows how far
        // along you are as well as which way it runs. Arrow i sits between stop
        // i and stop i+1, so it is 'passed' once the selection reaches i+1.
        Array.prototype.forEach.call(root.querySelectorAll('.dflow-arrow'), function (a, i) {
            a.classList.toggle('is-passed', i + 1 <= picked);
        });
    }

    function wire(root) {
        root.addEventListener('click', function (e) {
            var stop = e.target.closest ? e.target.closest('.dflow-stop') : null;
            if (stop) { select(root, stop); }
        });
        // Roving tabindex, the standard tablist keyboard contract. Without it
        // the rail is a row of buttons that a keyboard user has to tab through
        // one at a time, and the aria-selected state is a decoration.
        root.addEventListener('keydown', function (e) {
            var stop = e.target.closest ? e.target.closest('.dflow-stop') : null;
            if (!stop) return;
            var all = stops(root), i = all.indexOf(stop), next = null;
            if (e.key === 'ArrowRight') next = all[(i + 1) % all.length];
            if (e.key === 'ArrowLeft') next = all[(i - 1 + all.length) % all.length];
            if (e.key === 'Home') next = all[0];
            if (e.key === 'End') next = all[all.length - 1];
            if (!next) return;
            e.preventDefault();
            select(root, next);
            next.focus();
        });
    }

    fetch(host.dataset.flowUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(function (html) {
            var slot = document.createElement('div');
            slot.innerHTML = html;
            var panel = slot.firstElementChild;
            if (!panel) { host.remove(); return; }
            host.replaceWith(panel);
            wire(panel);
        })
        .catch(function () {
            // The dashboard is complete without this panel, so a failure takes
            // the placeholder away rather than leaving a stuck spinner or an
            // error the reader can do nothing about.
            host.remove();
        });
})();
</script>
@endpush
@endsection
