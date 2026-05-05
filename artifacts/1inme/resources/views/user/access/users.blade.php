@extends('user.layouts.app')
@section('title', 'User access')

@section('content')
<div class="max-w-4xl mx-auto p-4 lg:p-6 space-y-5">
    <header class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-white">User access</h1>
                <p class="text-sm text-white/50">
                    Promote or demote other users on the user pool. Roles listed
                    here are scoped to the user-facing app — back-office admin
                    roles are managed separately.
                </p>
            </div>
            <a href="{{ route('user.access.roles.index') }}"
               class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap">
                <i class="fas fa-sliders mr-1"></i> Edit roles
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" class="flex items-center gap-2">
        <input type="text" name="q" value="{{ $search }}"
               placeholder="Search by name or email…"
               class="flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
        <button type="submit" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm">
            Search
        </button>
        @if($search !== '')
            <a href="{{ route('user.access.users.index') }}"
               class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/60 text-sm">Clear</a>
        @endif
    </form>

    @if($search === '')
        <p class="text-xs text-white/40">
            Showing users that already hold at least one role. Use search
            above to find a user that doesn't appear in this list yet.
        </p>
    @endif

    <div class="space-y-3">
        @forelse($users as $u)
            <form method="POST"
                  action="{{ route('user.access.users.update', ['user' => $u->id, 'q' => $search]) }}"
                  class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                @csrf
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-white">{{ $u->name }}</div>
                        <div class="text-xs text-white/50">{{ $u->email }}</div>
                    </div>
                    <button type="submit"
                            class="px-3 py-1.5 rounded-lg bg-cyan-500/20 text-cyan-200 hover:bg-cyan-500/30 text-xs font-medium">
                        Save
                    </button>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($roles as $role)
                        @php $checked = $u->roles->contains('id', $role->id); @endphp
                        <label class="flex items-start gap-2 text-sm text-white/80 p-2 rounded-lg hover:bg-white/5 cursor-pointer">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" {{ $checked ? 'checked' : '' }}>
                            <span>
                                <span class="text-white">{{ $role->name }}</span>
                                <span class="block text-xs text-white/40">{{ $role->slug }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </form>
        @empty
            <div class="text-sm text-white/50 p-4 rounded-xl border border-white/10 bg-white/[0.02]">
                No matching users.
            </div>
        @endforelse
    </div>
</div>
@endsection
