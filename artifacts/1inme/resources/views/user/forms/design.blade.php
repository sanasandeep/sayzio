@extends('user.layouts.app')
@section('title', 'Design · ' . $form->title)

@section('content')
<div class="max-w-6xl mx-auto"
     x-data="{
        theme: @js($design['theme']),
        accent: @js($design['accent']),
        background: @js($design['background']),
        text: @js($design['text']),
        radius: {{ (int) $design['border_radius'] }},
        buttonLabel: @js($design['button_label']),
        buttonStyle: @js($design['button_style']),
        layout: @js($design['layout']),
        showBranding: {{ ($design['show_branding'] ?? true) ? 'true' : 'false' }},
     }">

    @include('user.partials.page-hero', [
        'title' => 'Design: ' . $form->title,
        'subtitle' => 'Match your brand — colors, fonts, layout and custom CSS.',
        'icon' => 'fa-palette',
        'back' => route('user.forms.show', $form),
    ])

    @include('user.forms._tabs')

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.forms.design.update', $form) }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @csrf @method('PUT')

        {{-- Settings --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Theme & Colors</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">Pick a base theme then fine-tune colors. The preview on the right updates live.</p>

                <div class="grid grid-cols-3 gap-3 mb-5">
                    @foreach(['light' => 'Light', 'dark' => 'Dark', 'glass' => 'Glass'] as $val => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="theme" value="{{ $val }}" x-model="theme" class="sr-only">
                            <div class="p-3 rounded-xl text-center text-xs font-semibold" :class="theme === '{{ $val }}' ? 'ring-2 ring-violet-500' : ''" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                                {{ $label }}
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach(['accent' => 'Accent', 'background' => 'Background', 'text' => 'Text'] as $key => $label)
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">{{ $label }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="{{ $key }}" class="w-10 h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass);">
                                <input type="text" name="{{ $key }}" x-model="{{ $key }}" class="theme-input flex-1 text-xs font-mono">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Layout & Typography</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">How the form is arranged and the font used throughout.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Layout</label>
                        <select name="layout" x-model="layout" class="theme-input w-full text-sm">
                            <option value="stacked">Stacked (one field per row)</option>
                            <option value="inline">Inline (compact)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font family</label>
                        <select name="font" class="theme-input w-full text-sm">
                            @foreach(['Plus Jakarta Sans', 'Inter', 'Manrope', 'Poppins', 'Roboto', 'System default'] as $f)
                                <option value="{{ $f }}" @selected(($design['font'] ?? '') === $f)>{{ $f }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Corner radius (<span x-text="radius"></span>px)</label>
                        <input type="range" name="border_radius" x-model.number="radius" min="0" max="32" class="w-full accent-violet-500">
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Button</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">The submit button label and its visual style.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button label</label>
                        <input type="text" name="button_label" x-model="buttonLabel" maxlength="60" class="theme-input w-full text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button style</label>
                        <select name="button_style" x-model="buttonStyle" class="theme-input w-full text-sm">
                            <option value="gradient">Gradient (premium)</option>
                            <option value="solid">Solid color</option>
                            <option value="outline">Outline</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Branding & Imagery</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">Add a logo and a header cover image. Both are optional.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Logo</label>
                        @if(!empty($design['logo']))
                            <img src="{{ $design['logo'] }}" class="h-14 mb-2 rounded-lg" style="background: rgba(0,0,0,0.05); padding: 6px;">
                            <label class="text-[10px] inline-flex items-center gap-1.5 mb-2 cursor-pointer" style="color: #f87171;"><input type="checkbox" name="remove_logo" value="1" class="rounded"> Remove</label>
                        @endif
                        <input type="file" name="logo" accept="image/*" class="w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-violet-500/10 file:text-violet-400" style="color: var(--text-faint);">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Cover image</label>
                        @if(!empty($design['cover']))
                            <img src="{{ $design['cover'] }}" class="h-20 mb-2 rounded-lg w-full object-cover">
                            <label class="text-[10px] inline-flex items-center gap-1.5 mb-2 cursor-pointer" style="color: #f87171;"><input type="checkbox" name="remove_cover" value="1" class="rounded"> Remove</label>
                        @endif
                        <input type="file" name="cover_image" accept="image/*" class="w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-violet-500/10 file:text-violet-400" style="color: var(--text-faint);">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-xs mt-5 cursor-pointer" style="color: var(--text-secondary);">
                    <input type="hidden" name="show_branding" value="0">
                    <input type="checkbox" name="show_branding" value="1" x-model="showBranding" class="rounded text-violet-500">
                    Show "Powered by 1INME" branding
                </label>
            </div>

            <div class="card-premium p-6" x-data="{ open: {{ !empty($design['custom_css']) ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="flex items-center gap-3 w-full text-left">
                    <i class="fas fa-code text-cyan-400 text-xs"></i>
                    <h3 class="text-sm font-bold flex-1" style="color: var(--text-primary);">Advanced — custom CSS</h3>
                    <i class="fas fa-chevron-down text-xs" :class="open ? 'rotate-180' : ''" style="color: var(--text-faint); transition: transform 0.2s;"></i>
                </button>
                <div x-show="open" x-transition class="mt-4">
                    <textarea name="custom_css" rows="8" placeholder=".form-card { background: …; }" class="theme-input w-full font-mono text-[12px]">{{ old('custom_css', $design['custom_css'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Live preview --}}
        <aside>
            <div class="card-premium p-5 lg:sticky lg:top-4">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Live Preview</h4>
                <div class="rounded-2xl overflow-hidden" :style="`background: ${theme === 'dark' ? '#0f172a' : (theme === 'glass' ? 'linear-gradient(160deg,#1a1230,#0a0b10)' : background)}; padding: 1.5rem;`">
                    <div :style="`background: ${theme === 'dark' ? '#1e293b' : (theme === 'glass' ? 'rgba(255,255,255,0.05)' : 'white')}; border-radius: ${radius}px; padding: 1.5rem; ${theme === 'glass' ? 'border:1px solid rgba(255,255,255,0.1); backdrop-filter: blur(20px);' : ''}`">
                        <div :style="`color: ${theme === 'light' ? text : 'white'}; font-weight: 800; font-size: 1.25rem; margin-bottom: 0.5rem;`">{{ $form->title }}</div>
                        <div class="text-xs mb-4" :style="`color: ${theme === 'light' ? text : 'rgba(255,255,255,0.6)'}; opacity: 0.7;`">Preview of how visitors will see your form.</div>
                        <div class="space-y-3 mb-4">
                            <input type="text" placeholder="Sample input" class="w-full px-3 py-2 text-xs outline-none" :style="`background: ${theme === 'light' ? '#f5f6fa' : 'rgba(255,255,255,0.06)'}; border: 1px solid ${theme === 'light' ? '#e5e7eb' : 'rgba(255,255,255,0.1)'}; border-radius: ${Math.max(4, radius/2)}px; color: ${theme === 'light' ? text : 'white'};`">
                            <textarea rows="3" placeholder="Another example field" class="w-full px-3 py-2 text-xs outline-none" :style="`background: ${theme === 'light' ? '#f5f6fa' : 'rgba(255,255,255,0.06)'}; border: 1px solid ${theme === 'light' ? '#e5e7eb' : 'rgba(255,255,255,0.1)'}; border-radius: ${Math.max(4, radius/2)}px; color: ${theme === 'light' ? text : 'white'};`"></textarea>
                        </div>
                        <button type="button"
                                :style="buttonStyle === 'gradient' ? `background: linear-gradient(135deg, ${accent}, ${accent}cc); color: white; border-radius: ${Math.max(4, radius/2)}px; padding: 0.7rem 1.5rem; font-weight: 700; font-size: 0.85rem; box-shadow: 0 8px 24px -8px ${accent}88;`
                                  : (buttonStyle === 'outline' ? `background: transparent; color: ${accent}; border: 2px solid ${accent}; border-radius: ${Math.max(4, radius/2)}px; padding: 0.7rem 1.5rem; font-weight: 700; font-size: 0.85rem;`
                                  : `background: ${accent}; color: white; border-radius: ${Math.max(4, radius/2)}px; padding: 0.7rem 1.5rem; font-weight: 700; font-size: 0.85rem;`)"
                                x-text="buttonLabel"></button>
                        <div x-show="showBranding" class="text-[10px] mt-4 opacity-50" :style="`color: ${theme === 'light' ? text : 'white'};`">Powered by 1INME</div>
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full mt-4 px-6 py-3 text-sm font-semibold inline-flex items-center justify-center gap-2">
                    <i class="fas fa-save text-xs"></i> Save Design
                </button>
            </div>
        </aside>
    </form>
</div>
@endsection
