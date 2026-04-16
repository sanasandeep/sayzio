@extends('user.layouts.app')
@section('title', 'Edit Link')

@section('content')
@php
    $favSrc = $link->favicon
        ?? ($link->settings['biolink']['favicons']['icon_512'] ?? null)
        ?? ($link->settings['biolink']['favicons']['apple_touch_icon'] ?? null);
    if (!$favSrc && !empty($link->long_url)) {
        $host = parse_url($link->long_url, PHP_URL_HOST);
        if ($host) $favSrc = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
    }
@endphp
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Edit Link',
        'subtitle' => $link->title ?: $link->alias,
        'icon'     => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link',
        'favicon'  => $favSrc,
        'back'     => route('user.links.show', $link),
        'chips'    => [
            ['icon' => 'fa-circle ' . ($link->is_active ? 'text-emerald-400' : 'text-red-400'), 'text' => $link->is_active ? 'Active' : 'Inactive'],
            ['icon' => 'fa-' . ($link->type === 'biolink' ? 'th-large' : 'link'), 'text' => ucfirst($link->type ?? 'link')],
        ],
    ])

    <form method="POST" action="{{ route('user.links.update', $link) }}" enctype="multipart/form-data" x-data="{ passwordProtect: {{ $link->is_password_protected ? 'true' : 'false' }} }">
        @csrf @method('PUT')

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Link Details</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Short URL <span class="text-[10px] text-white/30">(primary alias)</span></label>
                <div class="flex items-center gap-2 text-sm text-blue-400 bg-blue-500/10 px-3 py-2.5 rounded-xl">
                    <span>{{ $link->getShortUrl() }}</span>
                    <span class="text-xs text-white/40 bg-white/10 px-2 py-0.5 rounded uppercase">{{ $link->type }}</span>
                </div>
            </div>

            @php
                $maxExtras = auth()->user()->getMaxAliasesPerLink();
                $extras = $link->aliases()->orderBy('created_at')->get();
                $usedExtras = $extras->count();
                $canAddMore = $maxExtras === -1 || $usedExtras < $maxExtras;
                $base = rtrim(config('app.url', url('/')), '/');
            @endphp
            <div class="mb-6 p-4 bg-white/5 border border-white/10 rounded-xl">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h3 class="text-sm font-semibold text-white/80">Alternative URLs (aliases)</h3>
                        <p class="text-xs text-white/40 mt-0.5">All aliases serve the same page — no redirect. Useful for campaign-specific links.</p>
                    </div>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-white/10 text-white/60">
                        @if($maxExtras === -1) Unlimited
                        @else {{ $usedExtras }} / {{ $maxExtras }}
                        @endif
                    </span>
                </div>

                @if($maxExtras === 0)
                    <p class="text-xs text-amber-400/80 mt-2"><i class="fas fa-lock mr-1"></i> Your current plan does not include additional aliases. Upgrade your plan to unlock this feature.</p>
                @else
                    @if($extras->isNotEmpty())
                        <ul class="divide-y divide-white/5 mb-3">
                            @foreach($extras as $a)
                                <li class="flex items-center justify-between py-2">
                                    <a href="{{ $base }}/{{ $a->alias }}" target="_blank" class="text-sm text-blue-300 hover:underline truncate">{{ $base }}/{{ $a->alias }}</a>
                                    <div class="flex items-center gap-2 ml-3">
                                        <form method="POST" action="{{ route('user.links.aliases.promote', [$link, $a]) }}" class="inline" onsubmit="return confirm('Make this the primary alias? The current primary will become an alternative.')">
                                            @csrf
                                            <button type="submit" class="text-xs text-white/50 hover:text-white px-2 py-1 rounded hover:bg-white/10" title="Make this the primary alias"><i class="fas fa-star"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('user.links.aliases.destroy', [$link, $a]) }}" class="inline" onsubmit="return confirm('Delete this alias? Anyone visiting it will get a 404.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400/70 hover:text-red-400 px-2 py-1 rounded hover:bg-red-500/10"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($canAddMore)
                        <form method="POST" action="{{ route('user.links.aliases.store', $link) }}" class="flex items-center gap-2">
                            @csrf
                            <span class="text-xs text-white/40 whitespace-nowrap">{{ $base }}/</span>
                            <input type="text" name="alias" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9_-]+"
                                   placeholder="my-campaign" class="flex-1 border border-white/10 rounded-lg px-3 py-2 text-sm bg-white/5 text-white focus:ring-2 focus:ring-blue-500/40">
                            <button type="submit" class="px-3 py-2 text-sm bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 rounded-lg whitespace-nowrap"><i class="fas fa-plus mr-1"></i>Add</button>
                        </form>
                        @error('alias') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    @else
                        <p class="text-xs text-white/40 mt-2"><i class="fas fa-info-circle mr-1"></i> You've reached your plan's alias limit. Upgrade for more.</p>
                    @endif
                @endif
            </div>

            @if($link->type === 'url')
            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Destination URL</label>
                <input type="url" name="long_url" value="{{ old('long_url', $link->long_url) }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40">
                @error('long_url') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Redirect Type</label>
                <select name="redirect_type" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                    <option value="301" {{ old('redirect_type', $link->redirect_type) == 301 ? 'selected' : '' }}>301 - Permanent Redirect</option>
                    <option value="302" {{ old('redirect_type', $link->redirect_type) == 302 ? 'selected' : '' }}>302 - Temporary Redirect</option>
                </select>
            </div>
            @endif

            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title', $link->title) }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id', $link->project_id) == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Status</label>
                    <select name="is_active" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                        <option value="1" {{ $link->is_active ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ !$link->is_active ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Protection & Expiry</h2>
            <div class="space-y-3">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="is_password_protected" value="1" x-model="passwordProtect" class="rounded text-blue-400 focus:ring-blue-500/40">
                    <span class="text-sm text-white/60">Password protect this link</span>
                </label>
                <div x-show="passwordProtect" class="ml-7">
                    <input type="password" name="password" placeholder="New password (leave empty to keep current)" class="w-full max-w-xs border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                </div>
                <div>
                    <label class="block text-sm text-white/60 mb-1">Expiration Date</label>
                    <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $link->expires_at?->format('Y-m-d\TH:i')) }}" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">SEO Settings</h2>
            <div class="space-y-3">
                <input type="text" name="seo_title" value="{{ old('seo_title', $link->seo_title) }}" placeholder="SEO Title" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                <textarea name="seo_description" placeholder="SEO Description" rows="2" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">{{ old('seo_description', $link->seo_description) }}</textarea>
                <div>
                    <label class="block text-sm text-white/60 mb-1">OG Image</label>
                    @if($link->seo_image)
                        <div class="mb-2"><img src="{{ $link->seo_image }}" alt="Current OG image" class="h-20 rounded border"></div>
                    @endif
                    <input type="file" name="seo_image" accept="image/*" class="w-full text-sm text-white/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:bg-white/10 file:text-white/60 hover:file:bg-white/15">
                </div>
                <div>
                    <label class="block text-sm text-white/60 mb-1">Favicon</label>
                    @if($link->favicon)
                        <div class="mb-2"><img src="{{ $link->favicon }}" alt="Current favicon" class="h-8 rounded border"></div>
                    @endif
                    <input type="file" name="favicon" accept="image/*" class="w-full text-sm text-white/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:bg-white/10 file:text-white/60 hover:file:bg-white/15">
                    <p class="text-xs text-white/30 mt-1">Small icon shown in browser tab (recommended: 32x32 or 64x64 px)</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Targeting</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Country Restrictions</label>
                    <input type="text" name="country_restrictions" value="{{ old('country_restrictions', implode(',', $link->settings['country_restrictions'] ?? [])) }}" placeholder="e.g. US,GB,CA" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                    <p class="text-xs text-white/30 mt-1">Comma-separated ISO country codes. Leave empty for no restriction.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-2">Device Targeting</label>
                    @php $deviceTargeting = $link->settings['device_targeting'] ?? []; @endphp
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-white/60">
                            <input type="checkbox" name="device_targeting[]" value="desktop" class="rounded text-blue-400" {{ in_array('desktop', $deviceTargeting) ? 'checked' : '' }}>
                            Desktop
                        </label>
                        <label class="flex items-center gap-2 text-sm text-white/60">
                            <input type="checkbox" name="device_targeting[]" value="mobile" class="rounded text-blue-400" {{ in_array('mobile', $deviceTargeting) ? 'checked' : '' }}>
                            Mobile
                        </label>
                        <label class="flex items-center gap-2 text-sm text-white/60">
                            <input type="checkbox" name="device_targeting[]" value="tablet" class="rounded text-blue-400" {{ in_array('tablet', $deviceTargeting) ? 'checked' : '' }}>
                            Tablet
                        </label>
                    </div>
                    <p class="text-xs text-white/30 mt-1">Leave unchecked to allow all devices.</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">UTM Parameters</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="text" name="utm_source" value="{{ old('utm_source', $link->utm_source) }}" placeholder="UTM Source" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                <input type="text" name="utm_medium" value="{{ old('utm_medium', $link->utm_medium) }}" placeholder="UTM Medium" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                <input type="text" name="utm_campaign" value="{{ old('utm_campaign', $link->utm_campaign) }}" placeholder="UTM Campaign" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                <input type="text" name="utm_term" value="{{ old('utm_term', $link->utm_term) }}" placeholder="UTM Term" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                <input type="text" name="utm_content" value="{{ old('utm_content', $link->utm_content) }}" placeholder="UTM Content" class="border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
            </div>
        </div>

        @if($pixels->count())
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">Tracking Pixels</h2>
            <div class="space-y-2">
                @foreach($pixels as $pixel)
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="pixel_ids[]" value="{{ $pixel->id }}" {{ $link->pixels->contains($pixel->id) ? 'checked' : '' }} class="rounded text-blue-400 focus:ring-blue-500/40">
                    <span class="text-sm text-white/60">{{ $pixel->name }} ({{ ucfirst(str_replace('_', ' ', $pixel->type)) }})</span>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.show', $link) }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
