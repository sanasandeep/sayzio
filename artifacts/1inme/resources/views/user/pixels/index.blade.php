@extends('user.layouts.app')
@section('title', 'Pixel')

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
    $__canEdit = $__can('stats.edit');
@endphp
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Pixel</h1>
        <p class="text-white/40 text-sm mt-1">Manage your tracking pixels for retargeting</p>
    </div>
    @if($__canEdit)
    <a href="{{ route('user.pixels.create') }}" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> Add Tracker
    </a>
    @else
    <span class="px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2 cursor-not-allowed opacity-60 bg-violet-600/40 text-white" title="Your role doesn't allow adding trackers — ask a workspace admin">
        <i class="fas fa-lock"></i> Add Tracker
    </span>
    @endif
</div>

@if($pixels->isEmpty())
<div class="glass rounded-2xl p-12 text-center">
    <div class="w-16 h-16 bg-violet-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-bullseye text-violet-400 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-2">No trackers yet</h3>
    @if($__canEdit)
    <p class="text-white/40 mb-4">Add trackers to retarget link visitors.</p>
    <a href="{{ route('user.pixels.create') }}" class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
        <i class="fas fa-plus"></i> Add Tracker
    </a>
    @else
    <p class="text-white/40 mb-4">No trackers have been set up in this workspace yet.</p>
    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold" style="background: rgba(245,158,11,0.08); color: #b45309;">
        <i class="fas fa-lock"></i> Ask a workspace admin to add one for you.
    </div>
    @endif
</div>
@else
<div class="glass rounded-2xl overflow-hidden p-3">
    <table class="enhanced-table w-full text-sm">
        <thead class="bg-white/5 border-b border-white/10">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-white/40 uppercase">Name</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-white/40 uppercase">Type</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-white/40 uppercase">Tracker ID</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-white/40 uppercase">Links</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-white/40 uppercase" data-no-sort>Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($pixels as $pixel)
            <tr>
                <td class="px-6 py-4 font-medium text-white">{{ $pixel->name }}</td>
                <td class="px-6 py-4 text-white/50">
                    <span class="bg-white/10 text-white/60 px-2 py-1 rounded text-xs">{{ ucfirst(str_replace('_', ' ', $pixel->type)) }}</span>
                </td>
                <td class="px-6 py-4 text-white/40 font-mono text-xs">{{ $pixel->pixel_id }}</td>
                <td class="px-6 py-4 text-white/40">{{ $pixel->links_count }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if($__canEdit)
                            <a href="{{ route('user.pixels.edit', $pixel) }}" class="text-white/30 hover:text-violet-400" title="Edit"><i class="fas fa-edit"></i></a>
                            <form action="{{ route('user.pixels.destroy', $pixel) }}" method="POST" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this tracker?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                @csrf @method('DELETE')
                                <button class="text-white/30 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        @else
                            <span class="text-white/20 cursor-not-allowed" title="Your role doesn't allow editing trackers — ask a workspace admin"><i class="fas fa-lock"></i></span>
                            <span class="text-white/20 cursor-not-allowed" title="Your role doesn't allow deleting trackers — ask a workspace admin"><i class="fas fa-lock"></i></span>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@include('common.partials.enhanced-table')
@endsection
