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
    $meta = $bs['meta'] ?? [];
    $og = $bs['og'] ?? [];
    $twitter = $bs['twitter'] ?? [];
    $manifest = $bs['manifest'] ?? [];
    $favicons = $bs['favicons'] ?? [];
    $shareBtn = $bs['share_button'] ?? [];
    $menuBar = $bs['menu_bar'] ?? [];
    $autoTranslate = $bs['auto_translate'] ?? [];
    $menuBarItems = $menuBar['items'] ?? [];
@endphp

<div class="w-full max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'settings'])
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7">
            <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
                @csrf

                <div class="space-y-6">

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.1);"><i class="fas fa-search text-violet-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">SEO & Meta Tags</h3>
                </div>
                <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Control how search engines index and display your page.</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">SEO Title</label>
                        <input type="text" name="meta[seo_title]" value="{{ $meta['seo_title'] ?? $link->seo_title ?? '' }}" placeholder="My Awesome Link in Bio" class="theme-input w-full" maxlength="70">
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Recommended: 50-60 characters. Shown in browser tabs & search results.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Meta Description</label>
                        <textarea name="meta[seo_description]" rows="2" class="theme-input w-full text-xs" placeholder="A brief description of your page for search engines..." maxlength="320">{{ $meta['seo_description'] ?? $link->seo_description ?? '' }}</textarea>
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Recommended: 150-160 characters. Appears below title in search results.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Meta Keywords</label>
                        <input type="text" name="meta[keywords]" value="{{ $meta['keywords'] ?? '' }}" placeholder="link in bio, portfolio, social media" class="theme-input w-full">
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Comma-separated keywords relevant to your page.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Author</label>
                            <input type="text" name="meta[author]" value="{{ $meta['author'] ?? '' }}" placeholder="Your Name" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Language</label>
                            <select name="meta[language]" class="theme-input w-full text-xs">
                                @foreach(['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'ru' => 'Russian', 'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese', 'ar' => 'Arabic', 'hi' => 'Hindi', 'tr' => 'Turkish'] as $code => $label)
                                <option value="{{ $code }}" {{ ($meta['language'] ?? 'en') === $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Canonical URL</label>
                        <input type="url" name="meta[canonical_url]" value="{{ $meta['canonical_url'] ?? '' }}" placeholder="https://yourdomain.com/your-page" class="theme-input w-full">
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Prevents duplicate content issues. Leave blank to use the default URL.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Robots</label>
                            <select name="meta[robots]" class="theme-input w-full text-xs">
                                @foreach(['index,follow' => 'Index & Follow (Default)', 'index,nofollow' => 'Index, No Follow', 'noindex,follow' => 'No Index, Follow', 'noindex,nofollow' => 'No Index, No Follow'] as $val => $label)
                                <option value="{{ $val }}" {{ ($meta['robots'] ?? 'index,follow') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Content Rating</label>
                            <select name="meta[rating]" class="theme-input w-full text-xs">
                                @foreach(['general' => 'General', 'mature' => 'Mature', 'restricted' => 'Restricted'] as $val => $label)
                                <option value="{{ $val }}" {{ ($meta['rating'] ?? 'general') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.1);"><i class="fas fa-share-alt text-pink-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Open Graph & Social Media</h3>
                </div>
                <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Control how your page looks when shared on Facebook, LinkedIn, Discord, etc.</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">OG Title</label>
                        <input type="text" name="og[title]" value="{{ $og['title'] ?? '' }}" placeholder="Leave blank to use SEO title" class="theme-input w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">OG Description</label>
                        <textarea name="og[description]" rows="2" class="theme-input w-full text-xs" placeholder="Leave blank to use meta description">{{ $og['description'] ?? '' }}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">OG Type</label>
                            <select name="og[type]" class="theme-input w-full text-xs">
                                @foreach(['website' => 'Website', 'profile' => 'Profile', 'article' => 'Article', 'product' => 'Product'] as $val => $label)
                                <option value="{{ $val }}" {{ ($og['type'] ?? 'website') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">OG Site Name</label>
                            <input type="text" name="og[site_name]" value="{{ $og['site_name'] ?? '' }}" placeholder="1INME" class="theme-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">OG Image URL</label>
                        <input type="url" name="og[image_url]" value="{{ $og['image_url'] ?? '' }}" placeholder="https://example.com/preview.jpg" class="theme-input w-full">
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Recommended: 1200×630px. Shows as preview when shared on social media.</p>
                    </div>
                    <div>
                        @include('user.partials.dropzone-input', [
                            'name'        => 'og_image_upload',
                            'label'       => 'Or Upload OG Image',
                            'accept'      => 'image/png,image/jpeg,image/webp',
                            'currentUrl'  => $og['image_url'] ?? $link->seo_image,
                            'currentName' => ($og['image_url'] ?? $link->seo_image) ? 'Saved OG image' : null,
                            'maxMb'       => 2,
                            'hint'        => 'PNG, JPG or WebP',
                            'compact'     => true,
                        ])
                    </div>

                    <div class="pt-3 mt-3" style="border-top: 1px solid var(--border-glass);">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fab fa-twitter text-violet-400 text-xs"></i>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Twitter Card</span>
                        </div>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Card Type</label>
                                    <select name="twitter[card]" class="theme-input w-full text-xs">
                                        @foreach(['summary_large_image' => 'Large Image', 'summary' => 'Summary (Small)', 'app' => 'App', 'player' => 'Player'] as $val => $label)
                                        <option value="{{ $val }}" {{ ($twitter['card'] ?? 'summary_large_image') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">@username</label>
                                    <input type="text" name="twitter[site]" value="{{ $twitter['site'] ?? '' }}" placeholder="@yourhandle" class="theme-input w-full">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Twitter Title</label>
                                <input type="text" name="twitter[title]" value="{{ $twitter['title'] ?? '' }}" placeholder="Leave blank to use OG/SEO title" class="theme-input w-full">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Twitter Description</label>
                                <input type="text" name="twitter[description]" value="{{ $twitter['description'] ?? '' }}" placeholder="Leave blank to use OG/meta description" class="theme-input w-full">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(34,211,238,0.1);"><i class="fas fa-image text-cyan-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Favicon & Touch Icons</h3>
                    @if(!$canFavicon)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(34,211,238,0.15), rgba(6,182,212,0.1)); color: #22d3ee;">PRO</span>@endif
                </div>
                @if($canFavicon)
                <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Set browser tab icons and home screen icons for all devices.</p>
                <div class="space-y-4">
                    <div class="p-4 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-star text-yellow-400 text-[10px]"></i>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Favicon (Browser Tab)</span>
                        </div>
                        @if($link->favicon)
                        <div class="flex items-center gap-2 p-2 rounded-lg mb-3" style="background: rgba(0,0,0,0.15); border: 1px solid var(--border-glass);">
                            <img src="{{ $link->favicon }}" class="w-6 h-6 rounded object-contain" alt="Favicon"><span class="text-[10px]" style="color: var(--text-muted);">Current favicon</span>
                        </div>
                        @endif
                        <div class="space-y-2">
                            <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Favicon URL</label><input type="url" name="favicon_url" value="{{ $bs['favicon_url'] ?? $link->favicon ?? '' }}" placeholder="https://example.com/favicon.png" class="theme-input w-full"></div>
                            @include('user.partials.dropzone-input', [
                                'name'    => 'favicon_upload',
                                'label'   => 'Or Upload',
                                'accept'  => 'image/png,image/x-icon,image/svg+xml,image/jpeg',
                                'maxMb'   => 1,
                                'hint'    => 'PNG, ICO, SVG or JPG · 32×32 or 64×64',
                                'compact' => true,
                            ])
                        </div>
                    </div>

                    <div class="p-4 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fab fa-apple text-gray-300 text-[10px]"></i>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Apple Touch Icon</span>
                        </div>
                        @if(!empty($favicons['apple_touch_icon']))
                        <div class="flex items-center gap-2 p-2 rounded-lg mb-3" style="background: rgba(0,0,0,0.15); border: 1px solid var(--border-glass);">
                            <img src="{{ $favicons['apple_touch_icon'] }}" class="w-8 h-8 rounded-lg object-contain" alt="Apple Touch"><span class="text-[10px]" style="color: var(--text-muted);">Current touch icon</span>
                        </div>
                        @endif
                        <div class="space-y-2">
                            <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Apple Touch Icon URL</label><input type="url" name="favicons[apple_touch_icon]" value="{{ $favicons['apple_touch_icon'] ?? '' }}" placeholder="https://example.com/apple-touch-icon.png" class="theme-input w-full"></div>
                            @include('user.partials.dropzone-input', [
                                'name'    => 'apple_touch_upload',
                                'label'   => 'Or Upload (180×180)',
                                'accept'  => 'image/png',
                                'maxMb'   => 1,
                                'hint'    => 'PNG only · iOS home-screen icon',
                                'compact' => true,
                            ])
                        </div>
                    </div>

                    <div class="p-4 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-expand text-indigo-400 text-[10px]"></i>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Large Icon (512×512)</span>
                        </div>
                        @if(!empty($favicons['icon_512']))
                        <div class="flex items-center gap-2 p-2 rounded-lg mb-3" style="background: rgba(0,0,0,0.15); border: 1px solid var(--border-glass);">
                            <img src="{{ $favicons['icon_512'] }}" class="w-10 h-10 rounded-lg object-contain" alt="512 Icon"><span class="text-[10px]" style="color: var(--text-muted);">Current 512×512 icon</span>
                        </div>
                        @endif
                        <div class="space-y-2">
                            <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Icon URL</label><input type="url" name="favicons[icon_512]" value="{{ $favicons['icon_512'] ?? '' }}" placeholder="https://example.com/icon-512.png" class="theme-input w-full"></div>
                            @include('user.partials.dropzone-input', [
                                'name'    => 'icon_512_upload',
                                'label'   => 'Or Upload',
                                'accept'  => 'image/png',
                                'maxMb'   => 1,
                                'hint'    => 'PNG only · PWA splash & Android home',
                                'compact' => true,
                            ])
                        </div>
                    </div>
                </div>
                @else
                <p class="text-xs mt-2 mb-3 ml-11" style="color: var(--text-dimmed);">Set custom browser tab icons, Apple Touch icons, and large app icons.</p>
                <div class="ml-11">
                    <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #22d3ee, #06b6d4); color: #fff;">Upgrade Plan</a>
                </div>
                @endif
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(124,58,237,0.1);"><i class="fas fa-mobile-alt text-violet-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Web App Manifest (PWA)</h3>
                </div>
                <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Make your page installable as a Progressive Web App on mobile devices.</p>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                        <input type="hidden" name="manifest[enabled]" value="0">
                        <input type="checkbox" name="manifest[enabled]" value="1" {{ ($manifest['enabled'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500 focus:ring-violet-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <div>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Enable Web App Manifest</span>
                            <p class="text-[10px]" style="color: var(--text-dimmed);">Adds a manifest.json so visitors can install your page as an app</p>
                        </div>
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">App Name</label>
                            <input type="text" name="manifest[name]" value="{{ $manifest['name'] ?? '' }}" placeholder="{{ $link->title ?: 'My Link in Bio' }}" class="theme-input w-full" maxlength="100">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Short Name</label>
                            <input type="text" name="manifest[short_name]" value="{{ $manifest['short_name'] ?? '' }}" placeholder="{{ \Illuminate\Support\Str::limit($link->title ?: 'Bio', 12, '') }}" class="theme-input w-full" maxlength="25">
                            <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Shown under the icon. Max 12 chars recommended.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description</label>
                        <textarea name="manifest[description]" rows="2" class="theme-input w-full text-xs" placeholder="A short description of your app" maxlength="300">{{ $manifest['description'] ?? '' }}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Display Mode</label>
                            <select name="manifest[display]" class="theme-input w-full text-xs">
                                @foreach(['standalone' => 'Standalone (App-like)', 'fullscreen' => 'Fullscreen', 'minimal-ui' => 'Minimal UI', 'browser' => 'Browser Tab'] as $val => $label)
                                <option value="{{ $val }}" {{ ($manifest['display'] ?? 'standalone') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Orientation</label>
                            <select name="manifest[orientation]" class="theme-input w-full text-xs">
                                @foreach(['any' => 'Any', 'portrait' => 'Portrait', 'landscape' => 'Landscape'] as $val => $label)
                                <option value="{{ $val }}" {{ ($manifest['orientation'] ?? 'any') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Theme Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="manifest[theme_color]" value="{{ $manifest['theme_color'] ?? '#7c3aed' }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                                <input type="text" value="{{ $manifest['theme_color'] ?? '#7c3aed' }}" class="theme-input flex-1 text-xs font-mono"
                                    oninput="this.previousElementSibling.value = this.value"
                                    onchange="this.previousElementSibling.value = this.value">
                            </div>
                            <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Browser toolbar and status bar color.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="manifest[background_color]" value="{{ $manifest['background_color'] ?? '#0a0612' }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                                <input type="text" value="{{ $manifest['background_color'] ?? '#0a0612' }}" class="theme-input flex-1 text-xs font-mono"
                                    oninput="this.previousElementSibling.value = this.value"
                                    onchange="this.previousElementSibling.value = this.value">
                            </div>
                            <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Splash screen background when launching the app.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Start URL</label>
                        <input type="text" name="manifest[start_url]" value="{{ $manifest['start_url'] ?? '' }}" placeholder="/ (defaults to this page's URL)" class="theme-input w-full">
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Leave blank to use this page's URL. Use "/" for root or a custom path.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Categories</label>
                        <input type="text" name="manifest[categories]" value="{{ $manifest['categories'] ?? '' }}" placeholder="social, lifestyle, entertainment" class="theme-input w-full">
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Comma-separated. Helps app stores categorize your PWA.</p>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6" x-data="shareButtonSettings()">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(124,58,237,0.1);"><i class="fas fa-share-nodes text-violet-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Share Button & QR Code</h3>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="share_button[enabled]" value="0">
                        <input type="checkbox" name="share_button[enabled]" value="1" x-model="enabled" class="sr-only peer">
                        <div class="w-9 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-violet-500/40 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:rounded-full after:h-4 after:w-4 after:transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" :style="enabled ? 'background: #7c3aed' : ''">
                            <div class="absolute top-[2px] start-[2px] rounded-full h-4 w-4 transition-all bg-white" :class="enabled ? 'translate-x-4' : ''"></div>
                        </div>
                    </label>
                </div>
                <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Add a floating share button so visitors can share your profile or scan a QR code.</p>

                <div x-show="enabled" x-transition class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                        <input type="hidden" name="share_button[show_qr]" value="0">
                        <input type="checkbox" name="share_button[show_qr]" value="1" {{ ($shareBtn['show_qr'] ?? true) ? 'checked' : '' }} class="rounded text-violet-500 focus:ring-violet-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <div>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Show QR Code</span>
                            <p class="text-[10px]" style="color: var(--text-dimmed);">Display a scannable QR code in the share popup</p>
                        </div>
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Style</label>
                            <select name="share_button[style]" class="theme-input w-full text-xs">
                                @foreach(['fab' => 'Floating (FAB)', 'bar' => 'Share Bar', 'icon' => 'Icon Only'] as $val => $label)
                                <option value="{{ $val }}" {{ ($shareBtn['style'] ?? 'fab') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Position</label>
                            <select name="share_button[position]" class="theme-input w-full text-xs">
                                @foreach(['bottom-right' => 'Bottom Right', 'bottom-left' => 'Bottom Left', 'bottom-center' => 'Bottom Center', 'top-right' => 'Top Right', 'top-left' => 'Top Left'] as $val => $label)
                                <option value="{{ $val }}" {{ ($shareBtn['position'] ?? 'bottom-right') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Size</label>
                            <select name="share_button[size]" class="theme-input w-full text-xs">
                                @foreach(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $val => $label)
                                <option value="{{ $val }}" {{ ($shareBtn['size'] ?? 'md') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Label</label>
                            <input type="text" name="share_button[label]" value="{{ $shareBtn['label'] ?? 'Share' }}" placeholder="Share" class="theme-input w-full" maxlength="30">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="share_button[color]" value="{{ $shareBtn['color'] ?? '#7c3aed' }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                                <input type="text" value="{{ $shareBtn['color'] ?? '#7c3aed' }}" class="theme-input flex-1 text-xs font-mono" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="share_button[text_color]" value="{{ $shareBtn['text_color'] ?? '#ffffff' }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                                <input type="text" value="{{ $shareBtn['text_color'] ?? '#ffffff' }}" class="theme-input flex-1 text-xs font-mono" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-qrcode text-violet-400 text-[10px]"></i>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">QR Code Appearance</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Size (px)</label>
                                <input type="number" name="share_button[qr_size]" value="{{ $shareBtn['qr_size'] ?? 200 }}" min="100" max="400" step="10" class="theme-input w-full text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">FG Color</label>
                                <div class="flex items-center gap-1">
                                    <input type="color" name="share_button[qr_fg_color]" value="{{ $shareBtn['qr_fg_color'] ?? '#000000' }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                                    <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $shareBtn['qr_fg_color'] ?? '#000000' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">BG Color</label>
                                <div class="flex items-center gap-1">
                                    <input type="color" name="share_button[qr_bg_color]" value="{{ $shareBtn['qr_bg_color'] ?? '#ffffff' }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                                    <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $shareBtn['qr_bg_color'] ?? '#ffffff' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6" x-data="menuBarSettings()">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.1);"><i class="fas fa-bars text-violet-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Navigation Menu Bar</h3>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="menu_bar[enabled]" value="0">
                        <input type="checkbox" name="menu_bar[enabled]" value="1" x-model="enabled" class="sr-only peer">
                        <div class="w-9 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-violet-500/40 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:rounded-full after:h-4 after:w-4 after:transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" :style="enabled ? 'background: #3b82f6' : ''">
                            <div class="absolute top-[2px] start-[2px] rounded-full h-4 w-4 transition-all bg-white" :class="enabled ? 'translate-x-4' : ''"></div>
                        </div>
                    </label>
                </div>
                <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Add a sticky navigation bar linking to other Link in Bio pages or external URLs.</p>

                <div x-show="enabled" x-transition class="space-y-4" x-data="{ mbPos: '{{ $menuBar['position'] ?? 'top' }}' }">
                    <input type="hidden" name="menu_bar[items]" :value="JSON.stringify(items)">

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Position</label>
                            <select name="menu_bar[position]" x-model="mbPos" class="theme-input w-full text-xs">
                                <optgroup label="Bar (Sticky)">
                                    <option value="top" {{ ($menuBar['position'] ?? 'top') === 'top' ? 'selected' : '' }}>Top Bar</option>
                                    <option value="bottom" {{ ($menuBar['position'] ?? 'top') === 'bottom' ? 'selected' : '' }}>Bottom Bar</option>
                                </optgroup>
                                <optgroup label="Floating Button">
                                    <option value="floating-bottom-right" {{ ($menuBar['position'] ?? 'top') === 'floating-bottom-right' ? 'selected' : '' }}>Bottom Right</option>
                                    <option value="floating-bottom-left" {{ ($menuBar['position'] ?? 'top') === 'floating-bottom-left' ? 'selected' : '' }}>Bottom Left</option>
                                    <option value="floating-top-right" {{ ($menuBar['position'] ?? 'top') === 'floating-top-right' ? 'selected' : '' }}>Top Right</option>
                                    <option value="floating-top-left" {{ ($menuBar['position'] ?? 'top') === 'floating-top-left' ? 'selected' : '' }}>Top Left</option>
                                </optgroup>
                            </select>
                        </div>
                        <div x-show="!mbPos.startsWith('floating')">
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Style</label>
                            <select name="menu_bar[style]" class="theme-input w-full text-xs">
                                @foreach(['pills' => 'Pills', 'underline' => 'Underline', 'flat' => 'Flat'] as $val => $label)
                                <option value="{{ $val }}" {{ ($menuBar['style'] ?? 'pills') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background</label>
                            <div class="flex items-center gap-1">
                                <input type="color" name="menu_bar[bg_color]" value="{{ $menuBar['bg_color'] ?? '#0a0612' }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                                <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $menuBar['bg_color'] ?? '#0a0612' }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Text</label>
                            <div class="flex items-center gap-1">
                                <input type="color" name="menu_bar[text_color]" value="{{ $menuBar['text_color'] ?? '#ffffff' }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                                <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $menuBar['text_color'] ?? '#ffffff' }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Active</label>
                            <div class="flex items-center gap-1">
                                <input type="color" name="menu_bar[active_color]" value="{{ $menuBar['active_color'] ?? '#7c3aed' }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                                <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $menuBar['active_color'] ?? '#7c3aed' }}</span>
                            </div>
                        </div>
                    </div>

                    <div x-show="mbPos.startsWith('floating')" x-transition class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Icon</label>
                            <div class="flex items-center gap-1">
                                <input type="color" name="menu_bar[icon_color]" value="{{ $menuBar['icon_color'] ?? '#ffffff' }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                                <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $menuBar['icon_color'] ?? '#ffffff' }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Overlay BG</label>
                            <div class="flex items-center gap-1">
                                <input type="color" name="menu_bar[overlay_bg]" value="{{ $menuBar['overlay_bg'] ?? '#0a0612' }}" class="w-7 h-7 rounded border-0 cursor-pointer" style="background: transparent;">
                                <span class="text-[10px] font-mono" style="color: var(--text-dimmed);">{{ $menuBar['overlay_bg'] ?? '#0a0612' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-link text-violet-400 text-[10px]"></i>
                                <span class="text-xs font-semibold" style="color: var(--text-primary);">Menu Items</span>
                            </div>
                            <button type="button" @click="addItem()" class="text-[10px] font-semibold px-2.5 py-1 rounded-lg transition-all" style="background: rgba(59,130,246,0.15); color: #a78bfa;">
                                <i class="fas fa-plus mr-1"></i>Add Item
                            </button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(item, idx) in items" :key="idx">
                                <div class="flex items-center gap-2 p-2.5 rounded-lg" style="background: rgba(0,0,0,0.15); border: 1px solid var(--border-glass);">
                                    <div class="flex-1 grid grid-cols-3 gap-2">
                                        <input type="text" x-model="item.label" placeholder="Label" class="theme-input w-full text-xs" maxlength="30">
                                        <template x-if="item.target !== 'tab'">
                                            <input type="text" x-model="item.url" placeholder="URL or /alias" class="theme-input w-full text-xs" maxlength="500">
                                        </template>
                                        <template x-if="item.target === 'tab'">
                                            <div class="theme-input w-full text-xs flex items-center gap-1.5" style="opacity: 0.85;">
                                                <i class="fas fa-folder-open text-violet-400 text-[10px]"></i>
                                                <span style="color: var(--text-dimmed);" x-text="'Tab #' + (item.id || (item.label || 'unnamed').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''))"></span>
                                            </div>
                                        </template>
                                        <select x-model="item.target" class="theme-input w-full text-xs">
                                            <option value="_self">Same Tab</option>
                                            <option value="_blank">New Tab</option>
                                            <option value="tab">Page Tab</option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-1 cursor-pointer shrink-0">
                                        <input type="checkbox" x-model="item.is_active" class="rounded text-violet-500 focus:ring-violet-500/40 w-3 h-3" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                                        <span class="text-[9px]" style="color: var(--text-dimmed);">Active</span>
                                    </label>
                                    <button type="button" @click="removeItem(idx)" class="text-red-400/60 hover:text-red-400 transition-colors shrink-0">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </div>
                            </template>
                            <template x-if="items.length === 0">
                                <p class="text-[10px] text-center py-3" style="color: var(--text-dimmed);">No menu items yet. Click "Add Item" to get started.</p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6" x-data="{ atEnabled: {{ ($autoTranslate['enabled'] ?? false) ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(34,211,238,0.1);"><i class="fas fa-language text-cyan-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Auto Page Translation</h3>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="auto_translate[enabled]" value="0">
                        <input type="checkbox" name="auto_translate[enabled]" value="1" x-model="atEnabled" class="sr-only peer">
                        <div class="w-9 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-cyan-500/40 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:rounded-full after:h-4 after:w-4 after:transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" :style="atEnabled ? 'background: #06b6d4' : ''">
                            <div class="absolute top-[2px] start-[2px] rounded-full h-4 w-4 transition-all bg-white" :class="atEnabled ? 'translate-x-4' : ''"></div>
                        </div>
                    </label>
                </div>
                <p class="text-[11px] mb-4 ml-11" style="color: var(--text-dimmed);">Let visitors translate your page into their language using Google Translate.</p>

                <div x-show="atEnabled" x-transition class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Widget Position</label>
                            <select name="auto_translate[position]" class="theme-input w-full text-xs">
                                @foreach(['top-right' => 'Top Right', 'top-left' => 'Top Left', 'bottom-right' => 'Bottom Right', 'bottom-left' => 'Bottom Left'] as $val => $label)
                                <option value="{{ $val }}" {{ ($autoTranslate['position'] ?? 'top-right') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Widget Style</label>
                            <select name="auto_translate[style]" class="theme-input w-full text-xs">
                                @foreach(['dropdown' => 'Dropdown Menu', 'flags' => 'Flag Icons', 'minimal' => 'Minimal'] as $val => $label)
                                <option value="{{ $val }}" {{ ($autoTranslate['style'] ?? 'dropdown') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Default Language</label>
                        <select name="auto_translate[default_lang]" class="theme-input w-full text-xs">
                            @foreach(['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'ru' => 'Russian', 'ja' => 'Japanese', 'ko' => 'Korean', 'zh-CN' => 'Chinese (Simplified)', 'zh-TW' => 'Chinese (Traditional)', 'ar' => 'Arabic', 'hi' => 'Hindi', 'tr' => 'Turkish', 'th' => 'Thai', 'vi' => 'Vietnamese', 'id' => 'Indonesian', 'ms' => 'Malay', 'pl' => 'Polish', 'sv' => 'Swedish', 'da' => 'Danish', 'no' => 'Norwegian', 'fi' => 'Finnish', 'el' => 'Greek', 'cs' => 'Czech', 'ro' => 'Romanian', 'hu' => 'Hungarian', 'uk' => 'Ukrainian', 'he' => 'Hebrew', 'bn' => 'Bengali'] as $code => $label)
                            <option value="{{ $code }}" {{ ($autoTranslate['default_lang'] ?? 'en') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-data="langSelector()">
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Available Languages</label>
                        <input type="hidden" name="auto_translate[languages]" :value="selectedLangs.join(',')">
                        <div class="p-3 rounded-xl max-h-48 overflow-y-auto space-y-1" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                            <template x-for="lang in allLangs" :key="lang.code">
                                <label class="flex items-center gap-2 cursor-pointer px-2 py-1 rounded-lg transition-all hover:bg-white/[0.04]">
                                    <input type="checkbox" :value="lang.code" :checked="selectedLangs.includes(lang.code)" @change="toggleLang(lang.code)" class="rounded text-cyan-500 focus:ring-cyan-500/40 w-3 h-3" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                                    <span class="text-xs" style="color: var(--text-primary);" x-text="lang.flag + ' ' + lang.name"></span>
                                    <span class="text-[9px] font-mono ml-auto" style="color: var(--text-dimmed);" x-text="lang.code"></span>
                                </label>
                            </template>
                        </div>
                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);"><span x-text="selectedLangs.length"></span> languages selected</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Widget BG</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="auto_translate[bg_color]" value="{{ $autoTranslate['bg_color'] ?? '#1a1a2e' }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                                <input type="text" value="{{ $autoTranslate['bg_color'] ?? '#1a1a2e' }}" class="theme-input flex-1 text-xs font-mono" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Widget Text</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="auto_translate[text_color]" value="{{ $autoTranslate['text_color'] ?? '#ffffff' }}" class="w-8 h-8 rounded-lg border-0 cursor-pointer" style="background: transparent;">
                                <input type="text" value="{{ $autoTranslate['text_color'] ?? '#ffffff' }}" class="theme-input flex-1 text-xs font-mono" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.1);"><i class="fas fa-check-circle text-emerald-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Badges & Branding</h3>
                </div>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                        <input type="hidden" name="verified_badge" value="0">
                        <input type="checkbox" name="verified_badge" value="1" {{ ($bs['verified_badge'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500 focus:ring-violet-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <div>
                            <span class="text-xs font-semibold" style="color: var(--text-primary);">Verified badge</span>
                            <p class="text-[10px]" style="color: var(--text-dimmed);">Show a verified checkmark on your page</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                        <input type="hidden" name="branding_hidden" value="0">
                        <input type="checkbox" name="branding_hidden" value="1" {{ ($bs['branding_hidden'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500 focus:ring-violet-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
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
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-code text-violet-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Custom CSS & JS</h3>
                    @if(!$canCode)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(124,58,237,0.1)); color: #8b5cf6;">PRO</span>@endif
                </div>
                @if($canCode)
                <p class="text-[11px] mb-4" style="color: var(--text-dimmed);">Inject custom code into your Link in Bio page.</p>
                <div class="space-y-4" x-data="{ codeTab: 'css' }">
                    <div class="flex gap-1 p-0.5 rounded-lg" style="background: var(--bg-glass-input);">
                        @foreach(['css' => 'CSS', 'js_head' => 'JS (Head)', 'js_body' => 'JS (Body)'] as $ctKey => $ctLabel)
                        <button type="button" @click="codeTab = '{{ $ctKey }}'"
                                :class="codeTab === '{{ $ctKey }}' ? 'text-white shadow-sm' : ''"
                                :style="codeTab === '{{ $ctKey }}' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                                class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">{{ $ctLabel }}</button>
                        @endforeach
                    </div>
                    <div x-show="codeTab === 'css'"><textarea name="custom_css" rows="6" class="theme-input w-full font-mono text-xs" placeholder="/* Custom styles */">{{ $bs['custom_css'] ?? '' }}</textarea></div>
                    <div x-show="codeTab === 'js_head'"><textarea name="custom_js_head" rows="6" class="theme-input w-full font-mono text-xs" placeholder="// Before page loads">{{ $bs['custom_js_head'] ?? '' }}</textarea></div>
                    <div x-show="codeTab === 'js_body'"><textarea name="custom_js_body" rows="6" class="theme-input w-full font-mono text-xs" placeholder="// After page loads">{{ $bs['custom_js_body'] ?? '' }}</textarea></div>
                </div>
                @else
                <p class="text-xs mt-2 mb-3" style="color: var(--text-dimmed);">Add custom CSS and JavaScript to your page.</p>
                <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff;">Upgrade Plan</a>
                @endif
            </div>
        </div>

                @include('user.links.partials.settings-footer', ['link' => $link])
            </form>
        </div>

        <div class="lg:col-span-5 hidden lg:block">
            @include('user.links.partials.device-preview', ['link' => $link])
        </div>
    </div>
</div>
<script>
function shareButtonSettings() {
    return {
        enabled: {{ ($shareBtn['enabled'] ?? false) ? 'true' : 'false' }}
    };
}
function menuBarSettings() {
    return {
        enabled: {{ ($menuBar['enabled'] ?? false) ? 'true' : 'false' }},
        items: @json($menuBarItems ?: []),
        addItem() {
            this.items.push({ label: '', url: '', target: '_self', is_active: true });
        },
        removeItem(idx) {
            this.items.splice(idx, 1);
        }
    };
}

function langSelector() {
    const allLangs = [
        { code: 'en', name: 'English', flag: '🇬🇧' },
        { code: 'hi', name: 'Hindi', flag: '🇮🇳' },
        { code: 'es', name: 'Spanish', flag: '🇪🇸' },
        { code: 'fr', name: 'French', flag: '🇫🇷' },
        { code: 'de', name: 'German', flag: '🇩🇪' },
        { code: 'pt', name: 'Portuguese', flag: '🇧🇷' },
        { code: 'it', name: 'Italian', flag: '🇮🇹' },
        { code: 'nl', name: 'Dutch', flag: '🇳🇱' },
        { code: 'ru', name: 'Russian', flag: '🇷🇺' },
        { code: 'ja', name: 'Japanese', flag: '🇯🇵' },
        { code: 'ko', name: 'Korean', flag: '🇰🇷' },
        { code: 'zh-CN', name: 'Chinese (Simplified)', flag: '🇨🇳' },
        { code: 'zh-TW', name: 'Chinese (Traditional)', flag: '🇹🇼' },
        { code: 'ar', name: 'Arabic', flag: '🇸🇦' },
        { code: 'tr', name: 'Turkish', flag: '🇹🇷' },
        { code: 'th', name: 'Thai', flag: '🇹🇭' },
        { code: 'vi', name: 'Vietnamese', flag: '🇻🇳' },
        { code: 'id', name: 'Indonesian', flag: '🇮🇩' },
        { code: 'ms', name: 'Malay', flag: '🇲🇾' },
        { code: 'pl', name: 'Polish', flag: '🇵🇱' },
        { code: 'sv', name: 'Swedish', flag: '🇸🇪' },
        { code: 'da', name: 'Danish', flag: '🇩🇰' },
        { code: 'no', name: 'Norwegian', flag: '🇳🇴' },
        { code: 'fi', name: 'Finnish', flag: '🇫🇮' },
        { code: 'el', name: 'Greek', flag: '🇬🇷' },
        { code: 'cs', name: 'Czech', flag: '🇨🇿' },
        { code: 'ro', name: 'Romanian', flag: '🇷🇴' },
        { code: 'hu', name: 'Hungarian', flag: '🇭🇺' },
        { code: 'uk', name: 'Ukrainian', flag: '🇺🇦' },
        { code: 'he', name: 'Hebrew', flag: '🇮🇱' },
        { code: 'bn', name: 'Bengali', flag: '🇧🇩' },
        { code: 'ta', name: 'Tamil', flag: '🇮🇳' },
        { code: 'te', name: 'Telugu', flag: '🇮🇳' },
        { code: 'mr', name: 'Marathi', flag: '🇮🇳' },
        { code: 'gu', name: 'Gujarati', flag: '🇮🇳' },
        { code: 'kn', name: 'Kannada', flag: '🇮🇳' },
        { code: 'ml', name: 'Malayalam', flag: '🇮🇳' },
        { code: 'pa', name: 'Punjabi', flag: '🇮🇳' },
        { code: 'ur', name: 'Urdu', flag: '🇵🇰' },
    ];
    const saved = '{{ $autoTranslate['languages'] ?? 'en,hi' }}';
    const initial = saved ? saved.split(',').filter(c => c.trim()) : ['en', 'hi'];
    return {
        allLangs,
        selectedLangs: initial,
        toggleLang(code) {
            const idx = this.selectedLangs.indexOf(code);
            if (idx >= 0) {
                this.selectedLangs.splice(idx, 1);
            } else {
                this.selectedLangs.push(code);
            }
        }
    };
}
</script>
@endsection
