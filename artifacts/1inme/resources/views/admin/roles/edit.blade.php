@extends('admin.layouts.app')
@section('title', 'Edit Role')
@section('page-title', 'Edit Role: ' . $role->name)

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf @method('PUT')
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Role Name</label>
                        <input type="text" name="name" value="{{ old('name', $role->name) }}" required
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Slug</label>
                        <input type="text" value="{{ $role->slug }}" disabled
                               class="w-full px-4 py-2.5 border border-dark-200 rounded-lg bg-dark-50 text-dark-400">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-1">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">{{ old('description', $role->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-3">Permissions</label>
                    @foreach($permissions as $group => $perms)
                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-dark-600 mb-2 capitalize">{{ $group ?: 'General' }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($perms as $perm)
                            <label class="flex items-center gap-2 text-sm text-dark-600 p-2 rounded hover:bg-dark-50">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                       {{ in_array($perm->id, $rolePermissions) ? 'checked' : '' }}
                                       class="rounded border-dark-300 text-primary-600 focus:ring-primary-500">
                                {{ $perm->name }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition">Update Role</button>
                    <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 bg-dark-100 text-dark-700 rounded-lg font-medium hover:bg-dark-200 transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
