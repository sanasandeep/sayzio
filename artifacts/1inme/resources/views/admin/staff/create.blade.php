@extends('admin.layouts.app')
@section('title', 'Add Staff')
@section('page-title', 'Add Staff Member')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
        <form method="POST" action="{{ route('admin.staff.store') }}">
            @csrf
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-dark-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Password</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" required
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Role</label>
                        <select name="role_id" required class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                            <option value="">Select Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('role_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Status</label>
                        <select name="status" required class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition">
                        Create Staff Member
                    </button>
                    <a href="{{ route('admin.staff.index') }}" class="px-6 py-2.5 bg-dark-100 text-dark-700 rounded-lg font-medium hover:bg-dark-200 transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
