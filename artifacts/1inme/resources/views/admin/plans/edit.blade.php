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
                            <label class="block text-xs text-white/40 mb-1" title="Max contacts a user can store. -1 = unlimited.">Max Contacts</label>
                            <input type="number" name="features[contacts_max]" value="{{ $features['contacts_max'] ?? 100 }}" min="-1"
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
                        @foreach(['custom_domains' => 'Custom Domains', 'qr_customization' => 'QR Customization', 'pixels' => 'Tracking', 'utm_params' => 'UTM Parameters', 'link_protection' => 'Link Protection', 'seo_settings' => 'SEO Settings', 'teams' => 'Teams', 'ecommerce' => 'E-Commerce', 'custom_forms' => 'Custom Forms', 'custom_branding' => 'Custom Branding', 'custom_favicon' => 'Custom Favicon', 'custom_code' => 'Custom CSS/JS', 'contacts_google_sync' => 'Google Contacts Sync'] as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-white/60 p-2 rounded hover:bg-white/5">
                            <input type="checkbox" name="features[{{ $key }}]" value="1"
                                   {{ !empty($features[$key]) ? 'checked' : '' }}
                                   class="rounded border-white/10 text-violet-400">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-white/10 pt-5">
                    <h3 class="text-sm font-medium text-white/80 mb-3">Referral Program (free days)</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-white/40 mb-1" title="Free days awarded to a referrer on this plan when one of their invitees activates a paid plan.">Days awarded to referrer</label>
                            <input type="number" name="features[referrer_free_days]" value="{{ $features['referrer_free_days'] ?? 0 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1" title="Free days awarded to the new customer when they activate THIS plan via a referral.">Days awarded to referred user</label>
                            <input type="number" name="features[referred_free_days]" value="{{ $features['referred_free_days'] ?? 0 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1" title="Optional bonus to a referrer on this plan the moment one of their invitees signs up (before any payment).">Signup bonus to referrer (optional)</label>
                            <input type="number" name="features[signup_bonus_days]" value="{{ $features['signup_bonus_days'] ?? 0 }}" min="0"
                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </div>
                    </div>
                </div>

                @php
                    $uploadRows = \App\Services\UploadPolicy::contextsForPlan($features);
                    $uploadGroups = collect($uploadRows)->groupBy('group');
                @endphp
                <div class="border-t border-white/10 pt-5">
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-sm font-medium text-white/80">Upload Limits per Location</h3>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300 border border-violet-500/20">PER PLAN</span>
                    </div>
                    <p class="text-[11px] text-white/40 mb-4">Override the maximum file size and allowed file types for each upload location. Leave blank to use the system default. Extensions are comma-separated, no dots (e.g. <span class="font-mono">jpg, png, webp</span>).</p>

                    <div x-data="{ open: {} }" class="space-y-3">
                        @foreach($uploadGroups as $groupName => $rows)
                            <div class="rounded-xl border border-white/10 bg-white/[0.02]">
                                <button type="button" @click="open['{{ $loop->index }}'] = !open['{{ $loop->index }}']"
                                        class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/[0.04] transition rounded-xl">
                                    <span class="text-xs font-semibold text-white/80">{{ $groupName }} <span class="text-white/30 font-normal ml-1">({{ $rows->count() }})</span></span>
                                    <i class="fas fa-chevron-down text-[10px] text-white/40 transition-transform" :class="open['{{ $loop->index }}'] ? 'rotate-180' : ''"></i>
                                </button>
                                <div x-show="open['{{ $loop->index }}']" x-cloak class="px-4 pb-4 space-y-3">
                                    @foreach($rows as $key => $row)
                                        <div class="grid grid-cols-12 gap-3 items-end pt-3 border-t border-white/5 first:border-0 first:pt-0">
                                            <div class="col-span-12 md:col-span-4">
                                                <label class="block text-[11px] text-white/60">{{ $row['label'] }}</label>
                                                <p class="text-[10px] text-white/30 font-mono mt-0.5">{{ $key }}</p>
                                            </div>
                                            <div class="col-span-4 md:col-span-2">
                                                <label class="block text-[10px] text-white/40 mb-1">Max MB</label>
                                                <input type="number" min="0" step="1"
                                                       name="features[upload_limits][{{ $key }}][max_mb]"
                                                       value="{{ $row['max_mb'] }}"
                                                       placeholder="{{ $row['default_max_mb'] }}"
                                                       class="w-full px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-white text-xs focus:ring-2 focus:ring-violet-500/40 outline-none">
                                            </div>
                                            <div class="col-span-8 md:col-span-6">
                                                <label class="block text-[10px] text-white/40 mb-1">Allowed extensions <span class="text-white/30">(default: {{ implode(', ', $row['default_extensions']) ?: 'any' }})</span></label>
                                                <input type="text"
                                                       name="features[upload_limits][{{ $key }}][extensions]"
                                                       value="{{ implode(',', $row['extensions']) }}"
                                                       placeholder="{{ implode(',', $row['default_extensions']) }}"
                                                       class="w-full px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-white text-xs font-mono focus:ring-2 focus:ring-violet-500/40 outline-none">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-white/10 pt-5">
                    <h3 class="text-sm font-medium text-white/80 mb-3">Eligible Addons</h3>
                    <p class="text-[11px] text-white/40 mb-3">Addons that customers on this plan may purchase. Manage the addon catalog from <a href="{{ route('admin.addons.index') }}" class="text-violet-400 hover:underline">Addons</a>.</p>
                    @if(($addons ?? collect())->isEmpty())
                        <p class="text-sm text-white/40">No addons yet. <a href="{{ route('admin.addons.create') }}" class="text-violet-400 hover:underline">Create one</a>.</p>
                    @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach($addons as $addon)
                            <label class="flex items-start gap-2 text-sm text-white/70 p-2 rounded hover:bg-white/5 {{ $addon->is_archived ? 'opacity-60' : '' }}">
                                <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}"
                                       {{ in_array($addon->id, $attachedAddonIds ?? []) ? 'checked' : '' }}
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
                    <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">Update Plan</button>
                    <a href="{{ route('admin.plans.index') }}" class="px-6 py-2.5 bg-white/10 text-white/80 rounded-xl font-medium hover:bg-white/[0.06] transition">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
