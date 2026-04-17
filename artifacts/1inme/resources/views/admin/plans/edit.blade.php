@extends('admin.layouts.app')
@section('title', 'Edit Plan')
@section('page-title', 'Edit Plan: ' . $plan->name)

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10  p-6">
        <form method="POST" action="{{ route('admin.plans.update', $plan) }}">
            @csrf @method('PUT')
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Plan Name</label>
                    <input type="text" name="name" value="{{ old('name', $plan->name) }}" required
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">{{ old('description', $plan->description) }}</textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Monthly Price ($)</label>
                        <input type="number" name="monthly_price" value="{{ old('monthly_price', $plan->monthly_price) }}" step="0.01" min="0" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Annual Price ($)</label>
                        <input type="number" name="annual_price" value="{{ old('annual_price', $plan->annual_price) }}" step="0.01" min="0" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Trial Days</label>
                        <input type="number" name="trial_days" value="{{ old('trial_days', $plan->trial_days) }}" min="0" required
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <option value="active" {{ $plan->status == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $plan->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $plan->sort_order) }}" min="0"
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                </div>

                @php $features = $plan->features ?? []; @endphp
                <div class="border-t border-white/10 pt-5">
                    <h3 class="text-sm font-medium text-white/80 mb-3">Plan Features / Limits</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max Links</label>
                            <input type="number" name="features[max_links]" value="{{ $features['max_links'] ?? 10 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max Link in Bio pages</label>
                            <input type="number" name="features[max_biolinks]" value="{{ $features['max_biolinks'] ?? 1 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max File Size (MB)</label>
                            <input type="number" name="features[max_file_size_mb]" value="{{ $features['max_file_size_mb'] ?? 5 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Max Projects</label>
                            <input type="number" name="features[max_projects]" value="{{ $features['max_projects'] ?? 3 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Storage Limit (MB)</label>
                            <input type="number" name="features[storage_limit_mb]" value="{{ $features['storage_limit_mb'] ?? 100 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1" title="Additional aliases per Link in Bio (does NOT include the primary alias). Use -1 for unlimited, 0 to disable.">Extra Aliases per Link in Bio</label>
                            <input type="number" name="features[max_aliases_per_link]" value="{{ $features['max_aliases_per_link'] ?? 0 }}" min="-1"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <p class="text-[10px] text-white/30 mt-1">-1 = unlimited · 0 = primary alias only</p>
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1" title="Minimum length for the visitor-facing custom URL alias.">Min Custom URL Length</label>
                            <input type="number" name="features[min_alias_length]" value="{{ $features['min_alias_length'] ?? 3 }}" min="1" max="191"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <p class="text-[10px] text-white/30 mt-1">Letters, numbers, dashes only.</p>
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1" title="Maximum length for the visitor-facing custom URL alias.">Max Custom URL Length</label>
                            <input type="number" name="features[max_alias_length]" value="{{ $features['max_alias_length'] ?? 50 }}" min="1" max="191"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                            <p class="text-[10px] text-white/30 mt-1">Hard cap is 191 characters.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4">
                        @foreach(['custom_domains' => 'Custom Domains', 'qr_customization' => 'QR Customization', 'pixels' => 'Tracking Pixels', 'utm_params' => 'UTM Parameters', 'link_protection' => 'Link Protection', 'seo_settings' => 'SEO Settings', 'teams' => 'Teams', 'ecommerce' => 'E-Commerce', 'custom_forms' => 'Custom Forms', 'custom_branding' => 'Custom Branding', 'custom_favicon' => 'Custom Favicon', 'custom_code' => 'Custom CSS/JS'] as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-white/60 p-2 rounded hover:bg-white/5">
                            <input type="checkbox" name="features[{{ $key }}]" value="1"
                                   {{ !empty($features[$key]) ? 'checked' : '' }}
                                   class="rounded border-white/10 text-violet-400">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">Update Plan</button>
                    <a href="{{ route('admin.plans.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
