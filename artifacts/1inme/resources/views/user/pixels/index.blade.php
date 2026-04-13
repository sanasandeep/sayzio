@extends('user.layouts.app')
@section('title', 'Tracking Pixels')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Tracking Pixels</h1>
        <p class="text-gray-500 text-sm mt-1">Manage your tracking pixels for retargeting</p>
    </div>
    <a href="{{ route('user.pixels.create') }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> Add Pixel
    </a>
</div>

@if($pixels->isEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
    <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-bullseye text-primary-500 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">No tracking pixels yet</h3>
    <p class="text-gray-500 mb-4">Add tracking pixels to retarget link visitors.</p>
    <a href="{{ route('user.pixels.create') }}" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        <i class="fas fa-plus"></i> Add Pixel
    </a>
</div>
@else
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Pixel ID</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Links</th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($pixels as $pixel)
            <tr>
                <td class="px-6 py-4 font-medium text-gray-900">{{ $pixel->name }}</td>
                <td class="px-6 py-4 text-gray-600">
                    <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">{{ ucfirst(str_replace('_', ' ', $pixel->type)) }}</span>
                </td>
                <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ $pixel->pixel_id }}</td>
                <td class="px-6 py-4 text-gray-500">{{ $pixel->links_count }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('user.pixels.edit', $pixel) }}" class="text-gray-400 hover:text-primary-600"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('user.pixels.destroy', $pixel) }}" method="POST" onsubmit="return confirm('Delete this pixel?')">
                            @csrf @method('DELETE')
                            <button class="text-gray-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $pixels->links() }}</div>
@endif
@endsection
