@extends('admin.layouts.app')
@section('title', 'Create Account')
@section('page-title', 'Create Account')

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <h3 class="text-lg font-semibold text-white mb-1">Create a new account</h3>
        <p class="text-xs text-white/40 mb-6">Provision a user with an initial plan and optional starting coins. Leave the password blank to auto-generate one and email sign-in credentials.</p>

        @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-200">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4"
              x-data="{ createStaff: {{ old('create_staff') ? 'true' : 'false' }} }">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Handle <span class="text-white/40">(optional)</span></label>
                    <input type="text" name="handle" value="{{ old('handle') }}" placeholder="letters, numbers, . _ -"
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Plan</label>
                    <select name="plan_id"
                            class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="">Default plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }}{{ $plan->is_internal ? ' (internal)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Starting coins <span class="text-white/40">(optional)</span></label>
                    <input type="number" name="starting_coins" value="{{ old('starting_coins') }}" min="1" max="1000000" placeholder="0"
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Password <span class="text-white/40">(blank = auto-generate)</span></label>
                    <input type="text" name="password" autocomplete="off" placeholder="Min 8 chars, or leave blank"
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-white/70">
                <input type="checkbox" name="send_invite" value="1" {{ old('send_invite') ? 'checked' : '' }}
                       class="rounded bg-white/5 border-white/20">
                Email sign-in credentials to the user
            </label>

            @if($canCreateStaff)
            <div class="rounded-xl border border-white/5 bg-white/[0.02] p-4 space-y-3">
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="checkbox" name="create_staff" value="1" x-model="createStaff"
                           class="rounded bg-white/5 border-white/20">
                    Also grant back-office staff access
                </label>
                <div x-show="createStaff" x-cloak>
                    <label class="block text-sm font-medium text-white/80 mb-1">Staff role</label>
                    <select name="role_id"
                            class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="">Select a role…</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">
                    <i class="fas fa-user-plus mr-1"></i> Create account
                </button>
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2.5 text-white/60 hover:text-white/90 text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
