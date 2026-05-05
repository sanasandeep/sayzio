@extends('admin.layouts.app')
@section('title', 'User Roles')
@section('page-title', 'Roles for ' . $user->name)

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-white">{{ $user->name }}</h2>
                <p class="text-sm text-white/50">{{ $user->email }}</p>
            </div>
            <a href="{{ route('admin.users.show', $user) }}"
               class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70">
                <i class="fas fa-arrow-left mr-1"></i> Back to user
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <p class="text-xs text-white/50 mb-4">
            Roles below are scoped to the user pool (web guard). Each role
            grants a bundle of permissions used across the user-facing app.
        </p>

        <form method="POST" action="{{ route('admin.users.roles.update', $user) }}" class="space-y-3">
            @csrf @method('PUT')

            @forelse($roles as $role)
                <label class="flex items-start gap-3 p-3 rounded-xl border border-white/10 hover:bg-white/5 cursor-pointer">
                    <input type="checkbox" name="role_ids[]" value="{{ $role->id }}"
                           class="mt-1"
                           {{ in_array($role->id, $assigned, true) ? 'checked' : '' }}>
                    <div>
                        <div class="text-sm font-medium text-white">{{ $role->name }}</div>
                        <div class="text-xs text-white/40">{{ $role->slug }}</div>
                        @if($role->description)
                            <div class="text-xs text-white/60 mt-1">{{ $role->description }}</div>
                        @endif
                    </div>
                </label>
            @empty
                <p class="text-sm text-white/50">No user-pool roles are defined.</p>
            @endforelse

            <div class="pt-3 flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-xl bg-violet-500/20 text-violet-200 hover:bg-violet-500/30 text-sm font-medium">
                    Save roles
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
