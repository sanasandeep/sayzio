@extends('portal.layout')
@section('title', $project->title)
@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold">{{ $project->title }}</h1>
        <p class="text-sm text-slate-500">Read-only project view</p>
    </div>
    <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-600">{{ $project->statusLabel() }}</span>
</div>

@if($project->description)
    <p class="text-sm text-slate-600 mb-4">{{ $project->description }}</p>
@endif

@include('delivery-projects._readonly', ['project' => $project])
@endsection
