@extends('user.layouts.app')
@section('title', 'Appearance - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $activeSettingsTab = 'appearance';
@endphp

<div class="max-w-4xl mx-auto">
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
        @csrf

        <div class="space-y-6">

            <div class="card-premium p-6" x-data="{ editing: false, alias: '{{ $link->alias }}' }">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-link text-purple-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Short URL</h3>
                </div>
                <div class="flex items-center rounded-xl overflow-hidden" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    <span class="px-3 py-2.5 text-sm flex-shrink-0" style="color: var(--text-faint); border-right: 1px solid var(--border-glass);">{{ request()->getHost() }}/</span>
                    <template x-if="!editing">
                        <span class="flex-1 px-3 py-2.5 text-sm font-medium cursor-pointer flex items-center justify-between gap-2 group" style="color: var(--text-primary);" @click="editing = true; $nextTick(() => $refs.aliasInput.focus())">
                            <span x-text="alias"></span>
                            <i class="fas fa-pen text-[10px] opacity-0 group-hover:opacity-60 transition-opacity" style="color: var(--text-faint);"></i>
                        </span>
                    </template>
                    <template x-if="editing">
                        <div class="flex-1 flex items-center">
                            <input x-ref="aliasInput" type="text" x-model="alias" class="flex-1 px-3 py-2.5 text-sm font-medium bg-transparent outline-none" style="color: var(--text-primary);" @keydown.escape="editing = false">
                            <div class="flex items-center gap-1 pr-2">
                                <button type="button" @click="editing = false; alias = '{{ $link->alias }}'" class="text-[10px] px-2 py-1 rounded" style="color: var(--text-faint);">Cancel</button>
                                <button type="button" class="text-[10px] px-2 py-1 rounded bg-purple-600 text-white"
                                   @click="fetch('{{ route('user.links.update-alias', $link) }}', { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}, body:JSON.stringify({alias:alias})}).then(r=>r.json()).then(d=>{if(d.success||!d.errors){editing=false;location.reload()}else{alert(d.errors?.alias?.[0]||'Error')}}).catch(()=>alert('Error'))">Save</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.1);"><i class="fas fa-palette text-pink-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page Design</h3>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Page Title</label>
                            <input type="text" name="biolink_title" value="{{ $bs['biolink_title'] ?? $link->title }}" class="theme-input w-full" placeholder="My Bio Link">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Family</label>
                            <select name="font_family" class="theme-input w-full">
                                @foreach(['Space Grotesk','Inter','Poppins','Roboto','Playfair Display','Montserrat','DM Sans','Outfit'] as $font)
                                <option value="{{ $font }}" {{ ($bs['font_family'] ?? '') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description</label>
                        <textarea name="biolink_description" rows="2" class="theme-input w-full" placeholder="A short description for your page">{{ $bs['biolink_description'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.1);"><i class="fas fa-fill-drip text-indigo-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Colors & Background</h3>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Type</label>
                        <select name="background_type" class="theme-input w-full">
                            <option value="color" {{ ($bs['background_type'] ?? '') === 'color' ? 'selected' : '' }}>Solid Color</option>
                            <option value="gradient" {{ ($bs['background_type'] ?? '') === 'gradient' ? 'selected' : '' }}>Gradient</option>
                            <option value="image" {{ ($bs['background_type'] ?? '') === 'image' ? 'selected' : '' }}>Image</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="background_color" value="{{ $bs['background_color'] ?? '#0a0612' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['background_color'] ?? '#0a0612' }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="font_color" value="{{ $bs['font_color'] ?? '#ffffff' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['font_color'] ?? '#ffffff' }}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Gradient CSS</label>
                        <input type="text" name="background_gradient" value="{{ $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)' }}" class="theme-input w-full font-mono text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Image</label>
                        <input type="file" name="background_image" accept="image/*" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-purple-500/10 file:text-purple-400 file:font-medium" style="color: var(--text-faint);">
                        @if(!empty($bs['background_image']))
                        <div class="flex items-center gap-2 mt-2 p-2 rounded-lg" style="background: var(--bg-glass);">
                            <img src="{{ $bs['background_image'] }}" class="w-10 h-10 rounded object-cover" alt="Current background">
                            <span class="text-[10px]" style="color: var(--text-faint);">Current background image</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-hand-pointer text-purple-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Button Style</h3>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shape</label>
                        <select name="button_style" class="theme-input w-full">
                            @foreach(['rounded'=>'Rounded','pill'=>'Pill','square'=>'Square','outline'=>'Outline','shadow'=>'Shadow'] as $val => $label)
                            <option value="{{ $val }}" {{ ($bs['button_style'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="button_color" value="{{ $bs['button_color'] ?? '#7c3aed' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['button_color'] ?? '#7c3aed' }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="button_text_color" value="{{ $bs['button_text_color'] ?? '#ffffff' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['button_text_color'] ?? '#ffffff' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('user.links.partials.settings-footer', ['link' => $link])
    </form>
</div>
@endsection
