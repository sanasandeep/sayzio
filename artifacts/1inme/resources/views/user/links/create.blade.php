@extends('user.layouts.app')
@section('title', 'Create Link')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-gray-900">Create Link</h1>
    </div>

    <form method="POST" action="{{ route('user.links.store') }}" enctype="multipart/form-data" x-data="{ type: '{{ old('type', 'url') }}', showAdvanced: false, passwordProtect: {{ old('is_password_protected') ? 'true' : 'false' }} }">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Link Type</h2>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <label class="relative cursor-pointer">
                    <input type="radio" name="type" value="url" x-model="type" class="peer sr-only">
                    <div class="peer-checked:border-primary-500 peer-checked:bg-primary-50 border-2 border-gray-200 rounded-xl p-4 text-center transition-all">
                        <i class="fas fa-link text-blue-500 text-xl mb-2"></i>
                        <div class="text-sm font-medium text-gray-900">URL Shortener</div>
                    </div>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" name="type" value="biolink" x-model="type" class="peer sr-only">
                    <div class="peer-checked:border-primary-500 peer-checked:bg-primary-50 border-2 border-gray-200 rounded-xl p-4 text-center transition-all">
                        <i class="fas fa-id-card text-purple-500 text-xl mb-2"></i>
                        <div class="text-sm font-medium text-gray-900">Bio Link</div>
                    </div>
                </label>
                <a href="{{ route('user.links.file.create') }}" class="border-2 border-gray-200 rounded-xl p-4 text-center hover:border-primary-300 transition-all block">
                    <i class="fas fa-file text-green-500 text-xl mb-2"></i>
                    <div class="text-sm font-medium text-gray-900">File Link</div>
                </a>
                <a href="{{ route('user.links.ics.create') }}" class="border-2 border-gray-200 rounded-xl p-4 text-center hover:border-primary-300 transition-all block">
                    <i class="fas fa-calendar text-orange-500 text-xl mb-2"></i>
                    <div class="text-sm font-medium text-gray-900">ICS Event</div>
                </a>
                <a href="{{ route('user.links.vcf.create') }}" class="border-2 border-gray-200 rounded-xl p-4 text-center hover:border-primary-300 transition-all block">
                    <i class="fas fa-address-card text-pink-500 text-xl mb-2"></i>
                    <div class="text-sm font-medium text-gray-900">VCF Contact</div>
                </a>
            </div>
            @error('type') <p class="text-red-500 text-sm mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Info</h2>

            <div x-show="type === 'url'" class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Destination URL <span class="text-red-500">*</span></label>
                <input type="url" name="long_url" value="{{ old('long_url') }}" placeholder="https://example.com/your-long-url" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                @error('long_url') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div x-show="type === 'url'" class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Redirect Type</label>
                <select name="redirect_type" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                    <option value="301" {{ old('redirect_type', '301') === '301' ? 'selected' : '' }}>301 - Permanent Redirect</option>
                    <option value="302" {{ old('redirect_type') === '302' ? 'selected' : '' }}>302 - Temporary Redirect</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">301 passes SEO value; 302 is for temporary redirects</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="My awesome link" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Custom Alias</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary-500">
                        <span class="bg-gray-50 px-3 py-2.5 text-sm text-gray-500 border-r">{{ request()->getHost() }}/r/</span>
                        <input type="text" name="alias" value="{{ old('alias') }}" placeholder="auto-generated" class="flex-1 px-3 py-2.5 text-sm border-0 focus:ring-0">
                    </div>
                    @error('alias') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <button type="button" @click="showAdvanced = !showAdvanced" class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">Advanced Options</h2>
                <i class="fas" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'" class="text-gray-400"></i>
            </button>

            <div x-show="showAdvanced" x-cloak class="mt-4 space-y-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Protection</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="is_password_protected" value="1" x-model="passwordProtect" class="rounded text-primary-600 focus:ring-primary-500">
                            <span class="text-sm text-gray-700">Password protect this link</span>
                        </label>
                        <div x-show="passwordProtect" class="ml-7">
                            <input type="password" name="password" placeholder="Enter password" class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Expiration Date</label>
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">SEO Settings</h3>
                    <div class="space-y-3">
                        <input type="text" name="seo_title" value="{{ old('seo_title') }}" placeholder="SEO Title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        <textarea name="seo_description" placeholder="SEO Description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">{{ old('seo_description') }}</textarea>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">OG Image</label>
                            <input type="file" name="seo_image" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Favicon</label>
                            <input type="file" name="favicon" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                            <p class="text-xs text-gray-400 mt-1">Small icon shown in browser tab (recommended: 32x32 or 64x64 px)</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Country Restrictions</h3>
                    <input type="text" name="country_restrictions" value="{{ old('country_restrictions') }}" placeholder="e.g. US,GB,CA (comma-separated country codes, leave empty for no restriction)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                    <p class="text-xs text-gray-400 mt-1">Only allow access from these countries (ISO 2-letter codes)</p>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Device Targeting</h3>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="device_targeting[]" value="desktop" class="rounded text-primary-600" {{ is_array(old('device_targeting')) && in_array('desktop', old('device_targeting')) ? 'checked' : '' }}>
                            Desktop
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="device_targeting[]" value="mobile" class="rounded text-primary-600" {{ is_array(old('device_targeting')) && in_array('mobile', old('device_targeting')) ? 'checked' : '' }}>
                            Mobile
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="device_targeting[]" value="tablet" class="rounded text-primary-600" {{ is_array(old('device_targeting')) && in_array('tablet', old('device_targeting')) ? 'checked' : '' }}>
                            Tablet
                        </label>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Leave unchecked to allow all devices</p>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">UTM Parameters</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input type="text" name="utm_source" value="{{ old('utm_source') }}" placeholder="UTM Source" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        <input type="text" name="utm_medium" value="{{ old('utm_medium') }}" placeholder="UTM Medium" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        <input type="text" name="utm_campaign" value="{{ old('utm_campaign') }}" placeholder="UTM Campaign" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        <input type="text" name="utm_term" value="{{ old('utm_term') }}" placeholder="UTM Term" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        <input type="text" name="utm_content" value="{{ old('utm_content') }}" placeholder="UTM Content" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>

                @if($pixels->count())
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Tracking Pixels</h3>
                    <div class="space-y-2">
                        @foreach($pixels as $pixel)
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="pixel_ids[]" value="{{ $pixel->id }}" class="rounded text-primary-600 focus:ring-primary-500">
                            <span class="text-sm text-gray-700">{{ $pixel->name }} ({{ ucfirst(str_replace('_', ' ', $pixel->type)) }})</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.index') }}" class="px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Create Link</button>
        </div>
    </form>
</div>
@endsection
