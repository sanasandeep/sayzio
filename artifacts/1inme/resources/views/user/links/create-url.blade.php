@extends('user.layouts.app')
@section('title', 'Create Short Link')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white transition-colors" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Short Link</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-violet-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.store') }}" enctype="multipart/form-data" x-data="{ showAdvanced: false, passwordProtect: {{ old('is_password_protected') ? 'true' : 'false' }} }">
        @csrf
        <input type="hidden" name="type" value="url">

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-4">Basic Info</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1.5">Destination URL <span class="text-red-400">*</span></label>
                <input type="url" name="long_url" value="{{ old('long_url') }}" placeholder="https://example.com/your-long-url" required
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none transition-all">
                @error('long_url') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1.5">Redirect Type</label>
                <select name="redirect_type" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    <option value="301" {{ old('redirect_type', '301') === '301' ? 'selected' : '' }} class="bg-[#0d0818]">301 - Permanent Redirect</option>
                    <option value="302" {{ old('redirect_type') === '302' ? 'selected' : '' }} class="bg-[#0d0818]">302 - Temporary Redirect</option>
                </select>
                <p class="text-xs text-white/20 mt-1">301 passes SEO value; 302 is for temporary redirects</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1.5">Title</label>
                <input type="text" name="title" value="{{ old('title', $prefillTitle ?? '') }}" placeholder="My awesome link"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none transition-all">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Custom Alias</label>
                    <div class="flex items-center bg-white/5 border border-white/10 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-violet-500/40">
                        <span class="bg-white/5 px-3 py-2.5 text-sm text-white/30 border-r border-white/10">{{ request()->getHost() }}/</span>
                        <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}" placeholder="auto-generated"
                               minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}"
                               maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}"
                               pattern="[A-Za-z0-9_\-]+"
                               class="flex-1 px-3 py-2.5 text-sm bg-transparent text-white placeholder-white/20 border-0 focus:ring-0 outline-none">
                    </div>
                    @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Project</label>
                    <select name="project_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="" class="bg-[#0d0818]">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <button type="button" @click="showAdvanced = !showAdvanced" class="flex items-center justify-between w-full">
                <h2 class="text-base font-semibold text-white">Advanced Options</h2>
                <i class="fas text-white/30" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
            </button>

            <div x-show="showAdvanced" x-cloak class="mt-5 space-y-6">
                <div>
                    <h3 class="text-sm font-medium text-violet-400 mb-3"><i class="fas fa-shield-alt mr-2"></i>Protection</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_password_protected" value="1" x-model="passwordProtect"
                                   class="rounded bg-white/5 border-white/20 text-violet-600 focus:ring-violet-500/40">
                            <span class="text-sm text-white/60">Password protect this link</span>
                        </label>
                        <div x-show="passwordProtect" class="ml-7">
                            <input type="password" name="password" placeholder="Enter password"
                                   class="w-full max-w-xs bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm text-white/60 mb-1.5">Expiration Date</label>
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                                   class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                    </div>
                </div>

                <div class="h-px bg-white/5"></div>

                <div>
                    <h3 class="text-sm font-medium text-violet-400 mb-3"><i class="fas fa-search mr-2"></i>SEO Settings</h3>
                    <div class="space-y-3">
                        <input type="text" name="seo_title" value="{{ old('seo_title') }}" placeholder="SEO Title"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <textarea name="seo_description" placeholder="SEO Description" rows="2"
                                  class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">{{ old('seo_description') }}</textarea>
                        @include('user.partials.dropzone-input', [
                            'name'   => 'seo_image',
                            'label'  => 'OG Image',
                            'policy' => \App\Services\UploadPolicy::for('link.seo_image', auth()->user()),
                        ])
                        @include('user.partials.dropzone-input', [
                            'name'   => 'favicon',
                            'label'  => 'Favicon',
                            'policy' => \App\Services\UploadPolicy::for('link.favicon', auth()->user()),
                            'hint'   => 'Browser-tab icon · recommended 32x32 or 64x64',
                        ])
                    </div>
                </div>

                <div class="h-px bg-white/5"></div>

                {{-- Country / device targeting removed — Smart Redirect Rules covers this. --}}

                <div x-data="{ help: false }">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div>
                            <h3 class="text-sm font-medium text-violet-400"><i class="fas fa-chart-bar mr-2"></i>Campaign Tracking <span class="text-white/30 font-normal">(UTM tags &middot; optional)</span></h3>
                            <p class="text-[11px] text-white/40 mt-1">Tiny labels that tell Google Analytics where each visitor came from. Skip this if you're not tracking campaigns &mdash; most people don't need it.</p>
                        </div>
                        <button type="button" @click="help = !help" class="text-[10px] px-2 py-1 rounded-md flex-shrink-0 bg-white/5 text-white/50 hover:text-white"><i class="fas fa-question-circle mr-1"></i> What is this?</button>
                    </div>
                    <div x-show="help" x-cloak x-transition class="mb-3 p-3 rounded-lg text-[11px] leading-relaxed bg-violet-500/5 border border-violet-500/20 text-white/70">
                        Sharing the same link in your newsletter <em>and</em> on Instagram? Set <em>Where it lives</em> to <code class="text-violet-300">newsletter</code> for one and <code class="text-violet-300">instagram</code> for the other. Your analytics tool will then tell you which one brought more visitors.
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-white/60 mb-1">Where it lives <span class="text-white/30">(source)</span></label>
                            <input type="text" name="utm_source" value="{{ old('utm_source') }}" placeholder="e.g. newsletter, twitter"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-white/60 mb-1">How they'll see it <span class="text-white/30">(medium)</span></label>
                            <input type="text" name="utm_medium" value="{{ old('utm_medium') }}" placeholder="e.g. email, social, paid-ad"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-white/60 mb-1">Why you're sharing it <span class="text-white/30">(campaign)</span></label>
                            <input type="text" name="utm_campaign" value="{{ old('utm_campaign') }}" placeholder="e.g. spring-sale"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-white/60 mb-1">Search keyword <span class="text-white/30">(term)</span></label>
                            <input type="text" name="utm_term" value="{{ old('utm_term') }}" placeholder="e.g. running shoes"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-medium text-white/60 mb-1">Which version of the ad <span class="text-white/30">(content)</span></label>
                            <input type="text" name="utm_content" value="{{ old('utm_content') }}" placeholder="e.g. blue-button, banner-top"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                    </div>
                </div>

                @if($pixels->count())
                <div class="h-px bg-white/5"></div>
                <div>
                    <h3 class="text-sm font-medium text-violet-400 mb-3"><i class="fas fa-bullseye mr-2"></i>Tracking</h3>
                    <div class="space-y-2">
                        @foreach($pixels as $pixel)
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="pixel_ids[]" value="{{ $pixel->id }}" class="rounded bg-white/5 border-white/20 text-violet-600 focus:ring-violet-500/40">
                            <span class="text-sm text-white/60">{{ $pixel->name }} <span class="text-white/25">({{ ucfirst(str_replace('_', ' ', $pixel->type)) }})</span></span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="glass rounded-2xl p-4 my-4 flex items-start gap-3">
            <input type="hidden" name="show_preview_page" value="0">
            <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                <input type="checkbox" name="show_preview_page" value="1" {{ old('show_preview_page') ? 'checked' : '' }} class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-violet-600 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
            <div class="text-sm">
                <div class="text-white/80 font-medium">Show preview page before redirect</div>
                <p class="text-xs text-white/40 mt-0.5">Renders a branded interstitial that fires marketing pixels and tracks visitor dwell time before forwarding to the destination URL.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Back</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20">
                Create Link
            </button>
        </div>
    </form>
</div>
@endsection
