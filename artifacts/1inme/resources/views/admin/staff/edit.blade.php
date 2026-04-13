@extends('admin.layouts.app')
@section('title', 'Edit Staff')
@section('page-title', 'Edit Staff Member')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
        <form method="POST" action="{{ route('admin.staff.update', $staff) }}">
            @csrf @method('PUT')
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $staff->name) }}" required
                           class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $staff->email) }}" required
                           class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Password <span class="text-dark-400">(leave blank to keep)</span></label>
                        <input type="password" name="password"
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation"
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Role</label>
                        <select name="role_id" required class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ $staff->role_id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Status</label>
                        <select name="status" required class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                            <option value="active" {{ $staff->status == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $staff->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition">
                        Update Staff Member
                    </button>
                    <a href="{{ route('admin.staff.index') }}" class="px-6 py-2.5 bg-dark-100 text-dark-700 rounded-lg font-medium hover:bg-dark-200 transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
