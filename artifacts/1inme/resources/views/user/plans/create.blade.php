@extends('user.layouts.app')
@section('title', 'Create Plan')
@section('breadcrumb_parent', 'Plans')
@section('breadcrumb_parent_url', route('user.plans.index'))

@section('content')
<div class="p-4 lg:p-8 max-w-3xl mx-auto">
    <div class="glass rounded-2xl p-6 lg:p-8" style="border: 1px solid var(--border-glass);">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                <i class="fas fa-plus text-white text-sm"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold" style="color: var(--text-primary);">Create New Plan</h2>
                <p class="text-xs" style="color: var(--text-dimmed);">Set up pricing, limits, and features</p>
            </div>
        </div>

        <form method="POST" action="{{ route('user.plans.store') }}">
            @csrf
            <div class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-muted);">Plan Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Starter"
                               class="w-full px-4 py-2.5 rounded-xl text-sm outline-none transition-all"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                               onfocus="this.style.borderColor='rgba(27,132,255,0.4)'; this.style.boxShadow='0 0 0 3px rgba(27,132,255,0.08)'"
                               onblur="this.style.borderColor='var(--border-glass)'; this.style.boxShadow='none'">
                        @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-muted);">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 rounded-xl text-sm outline-none"
                                style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-muted);">Description</label>
                    <textarea name="description" rows="2" placeholder="Brief plan description..."
                              class="w-full px-4 py-2.5 rounded-xl text-sm outline-none resize-none"
                              style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                              onfocus="this.style.borderColor='rgba(27,132,255,0.4)'"
                              onblur="this.style.borderColor='var(--border-glass)'">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-muted);">Monthly Price ($)</label>
                        <input type="number" name="monthly_price" value="{{ old('monthly_price', '0') }}" step="0.01" min="0" required
                               class="w-full px-4 py-2.5 rounded-xl text-sm outline-none"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-muted);">Annual Price ($)</label>
                        <input type="number" name="annual_price" value="{{ old('annual_price', '0') }}" step="0.01" min="0" required
                               class="w-full px-4 py-2.5 rounded-xl text-sm outline-none"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-muted);">Trial Days</label>
                        <input type="number" name="trial_days" value="{{ old('trial_days', '0') }}" min="0" required
                               class="w-full px-4 py-2.5 rounded-xl text-sm outline-none"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-muted);">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', '0') }}" min="0"
                           class="w-32 px-4 py-2.5 rounded-xl text-sm outline-none"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                </div>

                <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="fas fa-sliders-h text-blue-400 text-xs"></i>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Plan Features & Limits</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-medium mb-1" style="color: var(--text-dimmed);">Max Links</label>
                            <input type="number" name="features[max_links]" value="{{ old('features.max_links', '10') }}" min="0"
                                   class="w-full px-3 py-2 rounded-xl text-sm outline-none"
                                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium mb-1" style="color: var(--text-dimmed);">Max Biolinks</label>
                            <input type="number" name="features[max_biolinks]" value="{{ old('features.max_biolinks', '1') }}" min="0"
                                   class="w-full px-3 py-2 rounded-xl text-sm outline-none"
                                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium mb-1" style="color: var(--text-dimmed);">Max File Size (MB)</label>
                            <input type="number" name="features[max_file_size_mb]" value="{{ old('features.max_file_size_mb', '5') }}" min="0"
                                   class="w-full px-3 py-2 rounded-xl text-sm outline-none"
                                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium mb-1" style="color: var(--text-dimmed);">Max Projects</label>
                            <input type="number" name="features[max_projects]" value="{{ old('features.max_projects', '3') }}" min="0"
                                   class="w-full px-3 py-2 rounded-xl text-sm outline-none"
                                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium mb-1" style="color: var(--text-dimmed);">Storage Limit (MB)</label>
                            <input type="number" name="features[storage_limit_mb]" value="{{ old('features.storage_limit_mb', '100') }}" min="0"
                                   class="w-full px-3 py-2 rounded-xl text-sm outline-none"
                                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-4">
                        @foreach(['custom_domains' => 'Custom Domains', 'qr_customization' => 'QR Customization', 'pixels' => 'Tracking Pixels', 'utm_params' => 'UTM Parameters', 'link_protection' => 'Link Protection', 'seo_settings' => 'SEO Settings', 'teams' => 'Teams', 'ecommerce' => 'E-Commerce', 'custom_forms' => 'Custom Forms', 'custom_branding' => 'Custom Branding', 'custom_favicon' => 'Custom Favicon', 'custom_code' => 'Custom CSS/JS'] as $key => $label)
                        <label class="flex items-center gap-2 text-xs p-2.5 rounded-lg cursor-pointer transition-all"
                               style="color: var(--text-muted); border: 1px solid transparent;"
                               onmouseover="this.style.background='var(--bg-glass-hover)'; this.style.borderColor='var(--border-subtle)'"
                               onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
                            <input type="checkbox" name="features[{{ $key }}]" value="1" class="rounded border-gray-500 text-blue-500 focus:ring-blue-500/30 bg-transparent">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-5">
                    <button type="submit" class="btn-primary px-6 py-2.5 text-sm font-semibold inline-flex items-center gap-2">
                        <i class="fas fa-check text-xs"></i> Create Plan
                    </button>
                    <a href="{{ route('user.plans.index') }}" class="px-6 py-2.5 rounded-xl text-sm font-medium transition-all"
                       style="background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);"
                       onmouseover="this.style.background='var(--bg-glass-hover)'"
                       onmouseout="this.style.background='var(--bg-glass)'">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
