@extends('admin.layouts.app')
@section('title', 'Edit Role')
@section('page-title', 'Edit Role: ' . $role->name)

@section('content')
<div class="max-w-3xl">
    <div class="glass rounded-2xl border border-white/10  p-6">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf @method('PUT')
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Role Name</label>
                        <input type="text" name="name" value="{{ old('name', $role->name) }}" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Slug</label>
                        <input type="text" value="{{ $role->slug }}" disabled
                               class="w-full px-4 py-2.5 border border-white/10 rounded-xl bg-white/5 text-white/30">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">{{ old('description', $role->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-3">Permissions</label>
                    @foreach($permissions as $group => $perms)
                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-white/60 mb-2 capitalize">{{ $group ?: 'General' }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($perms as $perm)
                            <label class="flex items-center gap-2 text-sm text-white/60 p-2 rounded hover:bg-white/5">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                       {{ in_array($perm->id, $rolePermissions) ? 'checked' : '' }}
                                       class="rounded border-white/10 text-violet-400 focus:ring-violet-500/40">
                                {{ $perm->name }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">Update Role</button>
                    <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
