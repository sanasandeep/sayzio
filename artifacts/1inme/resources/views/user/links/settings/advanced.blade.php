@extends('user.layouts.app')
@section('title', 'Advanced Settings - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $activeSettingsTab = 'advanced';
    $canBrand = auth()->user()->getPlanFeature('custom_branding', false);
    $canFavicon = auth()->user()->getPlanFeature('custom_favicon', false);
    $canCode = auth()->user()->getPlanFeature('custom_code', false);
@endphp

<div class="max-w-4xl mx-auto">
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
        @csrf

        <div class="space-y-6">

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.1);"><i class="fas fa-check-circle text-emerald-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Badges & Branding</h3>
                </div>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                        <input type="hidden" name="verified_badge" value="0">
                        <input type="checkbox" name="verified_badge" value="1" {{ ($bs['verified_badge'] ?? false) ? 'checked' : '' }} class="rounded text-purple-500 focus:ring-purple-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <div>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Verified badge</span>
                            <p class="text-[10px]" style="color: var(--text-dimmed);">Show a verified checkmark on your page</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                        <input type="hidden" name="branding_hidden" value="0">
                        <input type="checkbox" name="branding_hidden" value="1" {{ ($bs['branding_hidden'] ?? false) ? 'checked' : '' }} class="rounded text-purple-500 focus:ring-purple-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <div>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Hide "Powered by 1INME"</span>
                            <p class="text-[10px]" style="color: var(--text-dimmed);">Remove the default branding footer</p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(251,146,60,0.1);"><i class="fas fa-copyright text-orange-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Custom Branding</h3>
                    @if(!$canBrand)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(251,146,60,0.15), rgba(245,158,11,0.1)); color: #fb923c;">PRO</span>@endif
                </div>
                @if($canBrand)
                <p class="text-[11px] mb-4" style="color: var(--text-dimmed);">Replace the default footer with your own brand.</p>
                <div class="space-y-3">
                    <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Brand Name</label><input type="text" name="custom_branding_text" value="{{ $bs['custom_branding_text'] ?? '' }}" placeholder="Your Brand" class="theme-input w-full"></div>
                    <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Brand URL</label><input type="url" name="custom_branding_url" value="{{ $bs['custom_branding_url'] ?? '' }}" placeholder="https://yourbrand.com" class="theme-input w-full"></div>
                    <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Logo URL</label><input type="url" name="custom_branding_logo" value="{{ $bs['custom_branding_logo'] ?? '' }}" placeholder="https://yourbrand.com/logo.png" class="theme-input w-full"></div>
                </div>
                @else
                <p class="text-xs mt-2 mb-3" style="color: var(--text-dimmed);">Replace "Powered by 1INME" with your own brand name, logo, and URL.</p>
                <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #fb923c, #f59e0b); color: #fff;">Upgrade Plan</a>
                @endif
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(34,211,238,0.1);"><i class="fas fa-image text-cyan-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Custom Favicon</h3>
                    @if(!$canFavicon)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(34,211,238,0.15), rgba(6,182,212,0.1)); color: #22d3ee;">PRO</span>@endif
                </div>
                @if($canFavicon)
                <p class="text-[11px] mb-4" style="color: var(--text-dimmed);">Custom browser tab icon for this page.</p>
                @if($link->favicon)
                <div class="flex items-center gap-2 p-2 rounded-lg mb-3" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                    <img src="{{ $link->favicon }}" class="w-6 h-6 rounded object-contain" alt="Favicon"><span class="text-[10px]" style="color: var(--text-muted);">Current favicon</span>
                </div>
                @endif
                <div class="space-y-3">
                    <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Favicon URL</label><input type="url" name="favicon_url" value="{{ $bs['favicon_url'] ?? $link->favicon ?? '' }}" placeholder="https://example.com/favicon.png" class="theme-input w-full"></div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Or Upload</label>
                        <input type="file" name="favicon_upload" accept="image/png,image/x-icon,image/svg+xml,image/jpeg" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-cyan-500/10 file:text-cyan-400 file:font-medium" style="color: var(--text-faint);">
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">PNG, ICO, SVG or JPG. 32x32 or 64x64px recommended.</p>
                    </div>
                </div>
                @else
                <p class="text-xs mt-2 mb-3" style="color: var(--text-dimmed);">Set a custom browser tab icon for your page.</p>
                <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #22d3ee, #06b6d4); color: #fff;">Upgrade Plan</a>
                @endif
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(168,85,247,0.1);"><i class="fas fa-code text-purple-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Custom CSS & JS</h3>
                    @if(!$canCode)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(168,85,247,0.15), rgba(139,92,246,0.1)); color: #a855f7;">PRO</span>@endif
                </div>
                @if($canCode)
                <p class="text-[11px] mb-4" style="color: var(--text-dimmed);">Inject custom code into your biolink page.</p>
                <div class="space-y-4" x-data="{ codeTab: 'css' }">
                    <div class="flex gap-1 p-0.5 rounded-lg" style="background: var(--bg-glass-input);">
                        @foreach(['css' => 'CSS', 'js_head' => 'JS (Head)', 'js_body' => 'JS (Body)'] as $ctKey => $ctLabel)
                        <button type="button" @click="codeTab = '{{ $ctKey }}'"
                                :class="codeTab === '{{ $ctKey }}' ? 'text-white shadow-sm' : ''"
                                :style="codeTab === '{{ $ctKey }}' ? 'background: linear-gradient(135deg, #a855f7, #7c3aed);' : 'color: var(--text-faint);'"
                                class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">{{ $ctLabel }}</button>
                        @endforeach
                    </div>
                    <div x-show="codeTab === 'css'"><textarea name="custom_css" rows="6" class="theme-input w-full font-mono text-xs" placeholder="/* Custom styles */">{{ $bs['custom_css'] ?? '' }}</textarea></div>
                    <div x-show="codeTab === 'js_head'"><textarea name="custom_js_head" rows="6" class="theme-input w-full font-mono text-xs" placeholder="// Before page loads">{{ $bs['custom_js_head'] ?? '' }}</textarea></div>
                    <div x-show="codeTab === 'js_body'"><textarea name="custom_js_body" rows="6" class="theme-input w-full font-mono text-xs" placeholder="// After page loads">{{ $bs['custom_js_body'] ?? '' }}</textarea></div>
                </div>
                @else
                <p class="text-xs mt-2 mb-3" style="color: var(--text-dimmed);">Add custom CSS and JavaScript to your page.</p>
                <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #a855f7, #7c3aed); color: #fff;">Upgrade Plan</a>
                @endif
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.1);"><i class="fas fa-search text-blue-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">SEO & Tracking</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-sm p-2 rounded-lg" style="background: var(--bg-glass);">
                        <span style="color: var(--text-faint);">SEO Title</span>
                        <span style="color: var(--text-muted);">{{ $link->seo_title ?: 'Not set' }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm p-2 rounded-lg" style="background: var(--bg-glass);">
                        <span style="color: var(--text-faint);">Description</span>
                        <span class="truncate max-w-[200px]" style="color: var(--text-muted);">{{ $link->seo_description ?: 'Not set' }}</span>
                    </div>
                    @if($link->pixels->count())
                    <div class="flex flex-wrap gap-1.5 p-2 rounded-lg" style="background: var(--bg-glass);">
                        <span class="text-[10px] font-semibold mr-1" style="color: var(--text-faint);">Pixels:</span>
                        @foreach($link->pixels as $pixel)
                        <span class="px-2 py-0.5 rounded text-[10px] font-medium" style="background: rgba(139,92,246,0.08); color: #a78bfa;">{{ $pixel->name }}</span>
                        @endforeach
                    </div>
                    @endif
                    <a href="{{ route('user.links.edit', $link) }}" class="inline-flex items-center gap-1.5 text-xs text-purple-400 hover:underline mt-1">
                        <i class="fas fa-external-link-alt text-[9px]"></i> Edit SEO, pixels & UTM in link settings
                    </a>
                </div>
            </div>
        </div>

        @include('user.links.partials.settings-footer', ['link' => $link])
    </form>
</div>
@endsection
