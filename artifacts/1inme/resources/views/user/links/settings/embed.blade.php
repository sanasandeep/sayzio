@extends('user.layouts.app')
@section('title', 'Embed - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $activeSettingsTab = 'embed';
@endphp

<div class="w-full max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'settings'])
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div id="settings-tab-content">
        @include('user.links.partials.embed-panel', ['link' => $link])
    </div>
</div>
@endsection
