@extends('admin.layouts.app')
@section('title', 'Edit Staff')
@section('page-title', 'Edit Staff Member')

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10  p-6">
        <form method="POST" action="{{ route('admin.staff.update', $staff) }}">
            @csrf @method('PUT')
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $staff->name) }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                    @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $staff->email) }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                    @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Password <span class="text-white/30">(leave blank to keep)</span></label>
                        @include('common.partials.password-field', [
                            'name' => 'password',
                            'autocomplete' => 'new-password',
                            'inputClass' => 'w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none',
                        ])
                        @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Confirm Password</label>
                        @include('common.partials.password-field', [
                            'name' => 'password_confirmation',
                            'autocomplete' => 'new-password',
                            'inputClass' => 'w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none',
                        ])
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Role</label>
                        <select name="role_id" required class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ $staff->role_id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
                        <select name="status" required @if(!empty($isProtected)) disabled @endif class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none @if(!empty($isProtected)) opacity-60 cursor-not-allowed @endif">
                            <option value="active" {{ $staff->status == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $staff->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @if(!empty($isProtected))
                        {{-- A disabled select isn't submitted; keep the current status in the payload. --}}
                        <input type="hidden" name="status" value="{{ $staff->status }}">
                        <p class="mt-1 text-xs text-emerald-400/80"><i class="fas fa-shield-alt mr-1"></i> Protected account, cannot be deactivated.</p>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">
                        Update Staff Member
                    </button>
                    <a href="{{ route('admin.staff.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
