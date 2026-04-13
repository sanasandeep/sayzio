@extends('admin.layouts.app')
@section('title', 'Create Role')
@section('page-title', 'Create Role')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Role Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug') }}" required
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                        @error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-1">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-3">Permissions</label>
                    @foreach($permissions as $group => $perms)
                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-dark-600 mb-2 capitalize">{{ $group ?: 'General' }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($perms as $perm)
                            <label class="flex items-center gap-2 text-sm text-dark-600 p-2 rounded hover:bg-dark-50">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="rounded border-dark-300 text-primary-600 focus:ring-primary-500">
                                {{ $perm->name }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition">Create Role</button>
                    <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 bg-dark-100 text-dark-700 rounded-lg font-medium hover:bg-dark-200 transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
