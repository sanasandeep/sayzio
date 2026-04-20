@extends('user.layouts.app')
@section('title', 'Profile')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-white mb-6">Profile Settings</h1>

    <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Personal Information</h2>
        <form method="POST" action="{{ route('user.profile.update') }}">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                    @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                    @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/60 mb-1.5">Timezone</label>
                        <select name="timezone" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" {{ $user->timezone == $tz ? 'selected' : '' }} class="bg-[#0d0818]">{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/60 mb-1.5">Language</label>
                        <select name="language" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <option value="en" {{ $user->language == 'en' ? 'selected' : '' }} class="bg-[#0d0818]">English</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition-all hover:shadow-lg hover:shadow-violet-500/20">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-1">Sign-in security</h2>
        <p class="text-sm text-white/50 mb-4">Your account is protected by one-time codes — there's no password to manage.</p>
        <div class="flex items-start gap-3 rounded-xl px-4 py-3" style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.20);">
            <i class="fas fa-shield-alt text-violet-400 mt-0.5"></i>
            <div class="text-sm text-white/70">
                Each time you sign in, we send a fresh 6-digit code to your email{{ auth()->user()->mobile ? ' or mobile number' : '' }}. Keep your contact details up to date above so you can always receive it.
            </div>
        </div>
    </div>
</div>
@endsection
