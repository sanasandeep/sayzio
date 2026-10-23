@extends('admin.layouts.app')
@section('title', 'Create Role')
@section('page-title', 'Create Role')

@section('content')
<div class="max-w-6xl">
    <div class="glass rounded-2xl border border-white/10  p-6">
        <form method="POST" action="{{ route('admin.roles.store') }}">
            <input type="hidden" name="permission_selection_present" value="1">
            @csrf
            <div class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="role-name">Role Name</label>
                        <input type="text" id="role-name" name="name" value="{{ old('name', $copyName ?? '') }}" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                        @error('name')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="role-slug">Slug</label>
                        <input type="text" id="role-slug" name="slug" value="{{ old('slug', $copySlug ?? '') }}" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                        @error('slug')<p class="mt-1 text-sm text-red-400 ak-red">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1 ak-strong" for="role-description">Description</label>
                    <textarea id="role-description" name="description" rows="2"
                              class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">{{ old('description', $copyDescription ?? '') }}</textarea>
                </div>

                @isset($copyNotice)<p class="rounded-xl border p-4 ak-muted">{{ $copyNotice }}</p>@endisset

                @include('admin.roles.permission-picker')

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">Create Role</button>
                    <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition ak-strong">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
