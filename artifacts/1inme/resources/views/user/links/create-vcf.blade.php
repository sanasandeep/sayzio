@extends('user.layouts.app')
@section('title', 'Create Contact Card Link')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white/50" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Create Contact Card Link</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-violet-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.vcf.store') }}">
        @csrf
        <div class="glass rounded-2xl p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Personal Info</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $prefillTitle ?? '') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40" required>
                    @error('first_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Organization</label>
                    <input type="text" name="organization" value="{{ old('organization') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Job Title</label>
                    <input type="text" name="title" value="{{ old('title') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mt-4 space-y-4">
            <h2 class="text-lg font-semibold text-white">Contact Details</h2>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Phone (Mobile)</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Phone (Work)</label>
                    <input type="text" name="phone_work" value="{{ old('phone_work') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Website</label>
                <input type="url" name="website" value="{{ old('website') }}" placeholder="https://..." class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mt-4 space-y-4">
            <h2 class="text-lg font-semibold text-white">Address</h2>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Street</label>
                <input type="text" name="street" value="{{ old('street') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">State</label>
                    <input type="text" name="state" value="{{ old('state') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">ZIP</label>
                    <input type="text" name="zip" value="{{ old('zip') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Country</label>
                    <input type="text" name="country" value="{{ old('country') }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mt-4 space-y-4">
            <h2 class="text-lg font-semibold text-white">Notes & Link Settings</h2>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Notes</label>
                <textarea name="note" rows="2" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">{{ old('note') }}</textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Custom Alias</label>
                    <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}" placeholder="auto-generated" minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}" maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}" pattern="[A-Za-z0-9_\-]+" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-4 mt-4 flex items-start gap-3">
            <input type="hidden" name="show_preview_page" value="0">
            <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                <input type="checkbox" name="show_preview_page" value="1" {{ old('show_preview_page') ? 'checked' : '' }} class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-violet-600 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
            <div class="text-sm">
                <div class="text-white/80 font-medium">Show preview page before contact download</div>
                <p class="text-xs text-white/40 mt-0.5">Renders a contact preview that fires marketing pixels and tracks visitor dwell time before the .vcf file is delivered.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.links.index') }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Create Contact Link</button>
        </div>
    </form>
</div>
@endsection
