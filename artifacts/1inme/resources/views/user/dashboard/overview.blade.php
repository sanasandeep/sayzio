@extends('user.layouts.app')
@section('title', 'Dashboard')
@push('styles')
@include('user.partials.overview-styles')
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
    <div class="ov-grid">
        @foreach($counts as $label => $count)
        <article class="ov-panel ov-count" style="--ov-color: {{ $colors[$loop->index] }};--ov-tone: {{ $cardTones[$loop->index] }};--ov-number-size: {{ strlen(number_format($count)) > 7 ? '22px' : (strlen(number_format($count)) > 5 ? '30px' : '54px') }}">
            <div class="ov-count-top"><h2 class="ov-count-label">{{ $label }}</h2></div>
            @include('user.partials.count-card-art', ['kind'=>$cardKinds[$loop->index], 'artId'=>'workspace-count-'.$loop->index])
            <div class="ov-count-bottom"><strong>{{ number_format($count) }}</strong><p>In this workspace</p></div>
        </article>
        @endforeach
    </div>
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
    <section class="ov-panel"><h2>Recently updated</h2><p class="ov-muted">Continue working on your latest links.</p>
        @forelse($recentLinks as $link)
        <div class="ov-recent"><div><a href="{{ route('user.links.show', $link) }}">{{ $link->title ?: $link->alias }}</a><p class="ov-muted">{{ $link->project?->name ?? 'Unfiled' }} · {{ $link->is_active ? 'Active' : 'Inactive' }}</p></div><span class="ov-muted">{{ $link->updated_at->diffForHumans() }}</span></div>
        @empty
        <p class="ov-empty">Your first link starts here. Choose Create Link to get started.</p>
        @endforelse
    </section>
</div>
@endsection
