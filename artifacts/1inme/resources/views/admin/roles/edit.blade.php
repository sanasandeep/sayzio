@extends('admin.layouts.app')
@section('title', 'Edit Role')
@section('page-title', 'Edit Role: ' . $role->name)

@section('content')
<div class="max-w-6xl">
    <div class="glass rounded-2xl border border-white/10  p-6">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            <input type="hidden" name="permission_selection_present" value="1">
            @csrf @method('PUT')
            <div class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="role-name">Role Name</label>
                        <input type="text" id="role-name" name="name" value="{{ old('name', $role->name) }}" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="role-slug">Slug</label>
                        <input id="role-slug" type="text" value="{{ $role->slug }}" disabled
                               class="w-full px-4 py-2.5 border border-white/10 rounded-xl bg-white/5 text-white/30 ak-note ak-input">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="role-description">Description</label>
                    <textarea id="role-description" name="description" rows="2"
                              class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">{{ old('description', $role->description) }}</textarea>
                </div>

                @if($role->slug === 'super-admin')
                    <p class="rounded-xl border border-white/10 p-4 text-red-400 ak-red font-semibold">Critical · Unrestricted system access. You can edit the name and description. Super Admin always has all current and future permissions.</p>
                @else
                    @include('admin.roles.permission-picker')
                @endif

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">Update Role</button>
                    <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition ak-strong">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
