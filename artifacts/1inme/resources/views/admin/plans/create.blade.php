@extends('admin.layouts.app')
@section('title', 'Create Plan')
@section('page-title', 'Create Plan')

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10  p-6">
        <form method="POST" action="{{ route('admin.plans.store') }}">
            @csrf
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Plan Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Monthly Price (USD)</label>
                        <input type="number" name="monthly_price" value="{{ old('monthly_price', '0') }}" step="0.01" min="0" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Annual Price (USD)</label>
                        <input type="number" name="annual_price" value="{{ old('annual_price', '0') }}" step="0.01" min="0" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Trial Days</label>
                        <input type="number" name="trial_days" value="{{ old('trial_days', '0') }}" min="0" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Monthly Price (secondary, e.g. INR)</label>
                        <input type="number" name="monthly_price_secondary" value="{{ old('monthly_price_secondary') }}" step="0.01" min="0"
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <p class="text-[11px] text-white/30 mt-1">Optional. Country-based selection lands in the next billing task.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Annual Price (secondary)</label>
                        <input type="number" name="annual_price_secondary" value="{{ old('annual_price_secondary') }}" step="0.01" min="0"
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', '0') }}" min="0"
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>

                <div class="border-t border-white/10 pt-5">
                    <h3 class="text-sm font-medium text-white/80 mb-3">Plan Features / Limits</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max Links</label>
                            <input type="number" name="features[max_links]" value="{{ old('features.max_links', '10') }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max Link in Bio pages</label>
                            <input type="number" name="features[max_biolinks]" value="{{ old('features.max_biolinks', '1') }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max File Size (MB)</label>
                            <input type="number" name="features[max_file_size_mb]" value="{{ old('features.max_file_size_mb', '5') }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max Projects</label>
                            <input type="number" name="features[max_projects]" value="{{ old('features.max_projects', '3') }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Storage Limit (MB)</label>
                            <input type="number" name="features[storage_limit_mb]" value="{{ old('features.storage_limit_mb', '100') }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1" title="Max contacts a user can store. -1 = unlimited.">Max Contacts</label>
                            <input type="number" name="features[contacts_max]" value="{{ old('features.contacts_max', '100') }}" min="-1"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4">
                        @foreach(['custom_domains' => 'Custom Domains', 'qr_customization' => 'QR Customization', 'pixels' => 'Tracking', 'utm_params' => 'UTM Parameters', 'link_protection' => 'Link Protection', 'seo_settings' => 'SEO Settings', 'teams' => 'Teams', 'ecommerce' => 'E-Commerce', 'custom_forms' => 'Custom Forms', 'custom_branding' => 'Custom Branding', 'custom_favicon' => 'Custom Favicon', 'custom_code' => 'Custom CSS/JS', 'contacts_google_sync' => 'Google Contacts Sync'] as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-white/60 p-2 rounded hover:bg-white/5">
                            <input type="checkbox" name="features[{{ $key }}]" value="1" class="rounded border-white/10 text-violet-400">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-white/10 pt-5">
                    <h3 class="text-sm font-medium text-white/80 mb-3">Eligible Addons</h3>
                    <p class="text-[11px] text-white/40 mb-3">Pick which addons customers on this plan may purchase. You can also manage this from <a href="{{ route('admin.addons.index') }}" class="text-violet-400 hover:underline">Addons</a>.</p>
                    @if(($addons ?? collect())->isEmpty())
                        <p class="text-sm text-white/40">No addons in the catalog yet.</p>
                    @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach($addons as $addon)
                            <label class="flex items-start gap-2 text-sm text-white/70 p-2 rounded hover:bg-white/5 {{ $addon->is_archived ? 'opacity-60' : '' }}">
                                <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}"
                                       {{ in_array($addon->id, old('addon_ids', $attachedAddonIds ?? [])) ? 'checked' : '' }}
                                       class="mt-1 rounded border-white/10 text-violet-400">
                                <span>
                                    <span class="block">{{ $addon->name }} @if($addon->is_archived)<span class="text-[10px] text-white/40">(archived)</span>@endif</span>
                                    <span class="block text-[11px] text-white/40">${{ number_format($addon->monthly_price, 2) }}/mo · ${{ number_format($addon->annual_price, 2) }}/yr · {{ str_replace('_',' ',$addon->type) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">Create Plan</button>
                    <a href="{{ route('admin.plans.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
