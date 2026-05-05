@extends('user.layouts.app')
@section('title', 'Roles')

@section('content')
<div class="max-w-4xl mx-auto p-4 lg:p-6 space-y-5">
    <header class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-white">Roles</h1>
                <p class="text-sm text-white/50">
                    Define which permissions belong to each user-pool role.
                    Changes take effect on each affected user's next request.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('user.access.users.index') }}"
                   class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/70 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> User access
                </a>
                <a href="{{ route('user.access.roles.create') }}"
                   class="px-3 py-2 rounded-xl bg-cyan-500/20 text-cyan-200 hover:bg-cyan-500/30 text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i> New role
                </a>
            </div>
        </div>
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

    <div class="space-y-3">
        @forelse($roles as $role)
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-white">{{ $role->name }}</div>
                    <div class="text-xs text-white/40 font-mono">{{ $role->slug }}</div>
                    @if($role->description)
                        <div class="text-xs text-white/60 mt-1">{{ $role->description }}</div>
                    @endif
                    <div class="text-xs text-white/50 mt-2 flex items-center gap-3">
                        <span><i class="fas fa-key mr-1 text-white/40"></i>{{ $role->permissions_count }} permission{{ $role->permissions_count === 1 ? '' : 's' }}</span>
                        <span><i class="fas fa-users mr-1 text-white/40"></i>{{ $role->users_count }} user{{ $role->users_count === 1 ? '' : 's' }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('user.access.roles.edit', $role) }}"
                       class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-white/80 text-xs font-medium">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('user.access.roles.destroy', $role) }}"
                          onsubmit="return confirm('Delete role &quot;{{ $role->name }}&quot;? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-rose-500/15 text-rose-300 hover:bg-rose-500/25 text-xs font-medium"
                                @if($role->users_count > 0) title="Detach the {{ $role->users_count }} attached user(s) first" @endif>
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-sm text-white/50 p-4 rounded-xl border border-white/10 bg-white/[0.02]">
                No roles yet. Create one to get started.
            </div>
        @endforelse
    </div>
</div>
@endsection
