@extends('user.layouts.app')
@section('title', 'Profile')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Profile Settings</h1>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Personal Information</h2>
        <form method="POST" action="{{ route('user.profile.update') }}">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                        <select name="timezone" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" {{ $user->timezone == $tz ? 'selected' : '' }}>{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                        <select name="language" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                            <option value="en" {{ $user->language == 'en' ? 'selected' : '' }}>English</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Change Password</h2>
        <form method="POST" action="{{ route('user.profile.password') }}">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                    @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" name="password_confirmation" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none">
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
