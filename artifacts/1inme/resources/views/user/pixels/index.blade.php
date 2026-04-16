@extends('user.layouts.app')
@section('title', 'Tracking Pixels')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Tracking Pixels</h1>
        <p class="text-white/40 text-sm mt-1">Manage your tracking pixels for retargeting</p>
    </div>
    <a href="{{ route('user.pixels.create') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> Add Pixel
    </a>
</div>

@if($pixels->isEmpty())
<div class="glass rounded-2xl p-12 text-center">
    <div class="w-16 h-16 bg-purple-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-bullseye text-purple-400 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-2">No tracking pixels yet</h3>
    <p class="text-white/40 mb-4">Add tracking pixels to retarget link visitors.</p>
    <a href="{{ route('user.pixels.create') }}" class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
        <i class="fas fa-plus"></i> Add Pixel
    </a>
</div>
@else
<div class="glass rounded-2xl overflow-hidden p-3">
    <table class="enhanced-table w-full text-sm">
        <thead class="bg-white/5 border-b border-white/10">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-white/40 uppercase">Name</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-white/40 uppercase">Type</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-white/40 uppercase">Pixel ID</th>
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
                        <a href="{{ route('user.pixels.edit', $pixel) }}" class="text-white/30 hover:text-purple-400"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('user.pixels.destroy', $pixel) }}" method="POST" onsubmit="return confirm('Delete this pixel?')">
                            @csrf @method('DELETE')
                            <button class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                        </form>
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
