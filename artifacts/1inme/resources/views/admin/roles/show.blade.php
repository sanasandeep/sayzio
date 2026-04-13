@extends('admin.layouts.app')
@section('title', $role->name)

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.roles.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-900">{{ $role->name }}</h1>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-gray-500">Slug</dt>
            <dd class="text-gray-900 mt-1 font-mono">{{ $role->slug }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Guard</dt>
            <dd class="text-gray-900 mt-1">{{ $role->guard ?? 'admin' }}</dd>
        </div>
        @if($role->description)
        <div class="md:col-span-2">
            <dt class="text-gray-500">Description</dt>
            <dd class="text-gray-900 mt-1">{{ $role->description }}</dd>
        </div>
        @endif
    </dl>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Permissions ({{ $role->permissions->count() }})</h2>
    @if($role->permissions->isEmpty())
        <p class="text-gray-500 text-sm">No permissions assigned.</p>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach($role->permissions->groupBy('group') as $group => $perms)
                @foreach($perms as $perm)
                    <span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded text-xs">{{ $perm->name }}</span>
                @endforeach
            @endforeach
        </div>
    @endif
    <div class="mt-6 flex gap-3">
        <a href="{{ route('admin.roles.edit', $role) }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Edit</a>
        <a href="{{ route('admin.roles.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm">Back to list</a>
    </div>
</div>
@endsection
