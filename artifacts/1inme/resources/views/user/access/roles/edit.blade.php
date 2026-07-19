@extends('user.layouts.app')
@section('title', $isNew ? 'New role' : 'Edit role')

@section('content')
<div class="max-w-3xl mx-auto p-4 lg:p-6 space-y-5">
    <header class="flex flex-col gap-1">
        <div class="flex items-center gap-2 text-xs text-white/40">
            <a href="{{ route('user.access.roles.index') }}" class="hover:text-white/70">Roles</a>
            <i class="fas fa-chevron-right text-[9px]"></i>
            <span>{{ $isNew ? 'New role' : $role->name }}</span>
        </div>
        <h1 class="text-xl font-semibold text-white">{{ $isNew ? 'New role' : 'Edit role' }}</h1>
        <p class="text-sm text-white/50">
            Define a name, slug, and the permissions this role grants. Permissions are limited
            to the user-app group, back-office permissions are managed separately.
        </p>
    </header>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-xl bg-rose-500/10 text-rose-300 text-sm space-y-1">
            @foreach($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST"
          action="{{ $isNew ? route('user.access.roles.store') : route('user.access.roles.update', $role) }}"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-5">
        @csrf
        @if(!$isNew)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="block">
                <span class="text-xs uppercase tracking-wider text-white/50">Name</span>
                <input type="text" name="name" required maxlength="80"
                       value="{{ old('name', $role->name) }}"
                       class="mt-1 w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
            </label>
            <label class="block">
                <span class="text-xs uppercase tracking-wider text-white/50">Slug</span>
                <input type="text" name="slug" maxlength="80"
                       value="{{ old('slug', $role->slug) }}"
                       placeholder="auto-from-name"
                       class="mt-1 w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white font-mono focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                <span class="block text-[11px] text-white/40 mt-1">Lowercase, numbers and dashes. Leave blank to auto-generate from the name.</span>
            </label>
        </div>

        <label class="block">
            <span class="text-xs uppercase tracking-wider text-white/50">Description</span>
            <textarea name="description" rows="2" maxlength="255"
                      class="mt-1 w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">{{ old('description', $role->description) }}</textarea>
        </label>

        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs uppercase tracking-wider text-white/50">Permissions</span>
                <div class="flex items-center gap-2 text-[11px]">
                    <button type="button" onclick="document.querySelectorAll('input[name=&quot;permission_ids[]&quot;]').forEach(c=>c.checked=true)"
                            class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white/60">All</button>
                    <button type="button" onclick="document.querySelectorAll('input[name=&quot;permission_ids[]&quot;]').forEach(c=>c.checked=false)"
                            class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white/60">None</button>
                </div>
            </div>

            @php
                $oldAssigned = collect(old('permission_ids', $assigned))->map(fn($v) => (int) $v)->all();
            @endphp

            @forelse($permissions as $group => $items)
                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3 space-y-2">
                    <div class="text-[11px] uppercase tracking-wider text-white/40 px-1">{{ $group }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                        @foreach($items as $perm)
                            <label class="flex items-start gap-2 text-sm text-white/80 p-2 rounded-lg hover:bg-white/5 cursor-pointer">
                                <input type="checkbox" name="permission_ids[]" value="{{ $perm->id }}"
                                       {{ in_array((int) $perm->id, $oldAssigned, true) ? 'checked' : '' }}
                                       class="mt-1">
                                <span class="min-w-0">
                                    <span class="text-white">{{ $perm->name }}</span>
                                    <span class="block text-[11px] text-white/40 font-mono break-all">{{ $perm->slug }}</span>
                                    @if($perm->description)
                                        <span class="block text-[11px] text-white/50 mt-0.5">{{ $perm->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-sm text-white/50 p-3 rounded-xl border border-white/10 bg-white/[0.02]">
                    No permissions are registered for the user-app group yet.
                </div>
            @endforelse
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-white/10">
            <a href="{{ route('user.access.roles.index') }}"
               class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/70 text-sm">Cancel</a>
            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-cyan-500/20 text-cyan-200 hover:bg-cyan-500/30 text-sm font-medium">
                {{ $isNew ? 'Create role' : 'Save changes' }}
            </button>
        </div>
    </form>
</div>
@endsection
