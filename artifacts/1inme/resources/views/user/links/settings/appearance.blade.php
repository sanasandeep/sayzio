@extends('user.layouts.app')
@section('title', 'Appearance - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $activeSettingsTab = 'appearance';
    $bgType = $bs['background_type'] ?? 'color';
    $bgColor = $bs['background_color'] ?? '#0a0612';
    $fontColor = $bs['font_color'] ?? '#ffffff';
    $bgGradient = $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)';
    $btnStyle = $bs['button_style'] ?? 'rounded';
    $btnColor = $bs['button_color'] ?? '#7c3aed';
    $btnTextColor = $bs['button_text_color'] ?? '#ffffff';
    $fontFamily = $bs['font_family'] ?? 'Space Grotesk';
@endphp

<div class="w-full">
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7">
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
                                        <option value="{{ $font }}" {{ $fontFamily === $font ? 'selected' : '' }}>{{ $font }}</option>
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

                    <div class="card-premium p-6" x-data="{ bgType: '{{ $bgType }}', bgColor: '{{ $bgColor }}', fontColor: '{{ $fontColor }}' }">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.1);"><i class="fas fa-fill-drip text-indigo-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Colors & Background</h3>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Background Type</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button" @click="bgType = 'color'"
                                        :class="bgType === 'color' ? 'ring-2 ring-purple-500 ring-offset-1' : ''"
                                        class="flex flex-col items-center gap-1.5 p-3 rounded-xl transition-all text-center"
                                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                                        :style="bgType === 'color' ? 'border-color: rgba(139,92,246,0.5); background: rgba(139,92,246,0.08);' : ''">
                                        <div class="w-8 h-8 rounded-lg" style="background: linear-gradient(135deg, #6b21a8, #3b0764);"></div>
                                        <span class="text-[10px] font-semibold" style="color: var(--text-muted);">Solid Color</span>
                                    </button>
                                    <button type="button" @click="bgType = 'gradient'"
                                        :class="bgType === 'gradient' ? 'ring-2 ring-purple-500 ring-offset-1' : ''"
                                        class="flex flex-col items-center gap-1.5 p-3 rounded-xl transition-all text-center"
                                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                                        :style="bgType === 'gradient' ? 'border-color: rgba(139,92,246,0.5); background: rgba(139,92,246,0.08);' : ''">
                                        <div class="w-8 h-8 rounded-lg" style="background: linear-gradient(135deg, #ec4899, #8b5cf6, #06b6d4);"></div>
                                        <span class="text-[10px] font-semibold" style="color: var(--text-muted);">Gradient</span>
                                    </button>
                                    <button type="button" @click="bgType = 'image'"
                                        :class="bgType === 'image' ? 'ring-2 ring-purple-500 ring-offset-1' : ''"
                                        class="flex flex-col items-center gap-1.5 p-3 rounded-xl transition-all text-center"
                                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                                        :style="bgType === 'image' ? 'border-color: rgba(139,92,246,0.5); background: rgba(139,92,246,0.08);' : ''">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--bg-glass); border: 1px dashed var(--border-glass);">
                                            <i class="fas fa-image text-[10px]" style="color: var(--text-faint);"></i>
                                        </div>
                                        <span class="text-[10px] font-semibold" style="color: var(--text-muted);">Image</span>
                                    </button>
                                </div>
                                <input type="hidden" name="background_type" :value="bgType">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="background_color" x-model="bgColor" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                        <span class="text-xs font-mono" style="color: var(--text-faint);" x-text="bgColor">{{ $bgColor }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="font_color" x-model="fontColor" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                        <span class="text-xs font-mono" style="color: var(--text-faint);" x-text="fontColor">{{ $fontColor }}</span>
                                    </div>
                                </div>
                            </div>

                            <div x-show="bgType === 'gradient'" x-transition class="space-y-2">
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Gradient CSS</label>
                                <input type="text" name="background_gradient" value="{{ $bgGradient }}" class="theme-input w-full font-mono text-xs" placeholder="linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)">
                                <p class="text-[10px]" style="color: var(--text-dimmed);">Use CSS gradient syntax. Example: linear-gradient(135deg, #color1, #color2)</p>
                            </div>

                            <div x-show="bgType === 'image'" x-transition class="space-y-2">
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Image</label>
                                <input type="file" name="background_image" accept="image/*" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-purple-500/10 file:text-purple-400 file:font-medium" style="color: var(--text-faint);">
                                @if(!empty($bs['background_image']))
                                <div class="flex items-center gap-2 mt-2 p-2 rounded-lg" style="background: var(--bg-glass);">
                                    <img src="{{ $bs['background_image'] }}" class="w-10 h-10 rounded object-cover" alt="Current background">
                                    <span class="text-[10px]" style="color: var(--text-faint);">Current background image</span>
                                </div>
                                @endif
                            </div>

                            <div class="p-3 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                                <p class="text-[10px] font-medium" style="color: var(--text-dimmed);"><i class="fas fa-info-circle mr-1 text-purple-400"></i> Background color is always used as the base. Gradient or image will overlay it.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-premium p-6" x-data="{ btnStyle: '{{ $btnStyle }}', btnColor: '{{ $btnColor }}', btnTextColor: '{{ $btnTextColor }}' }">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-hand-pointer text-purple-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Button Style</h3>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Shape</label>
                                <div class="grid grid-cols-5 gap-2">
                                    @foreach(['rounded'=>['Rounded','12px','fa-square'], 'pill'=>['Pill','999px','fa-circle'], 'square'=>['Square','4px','fa-stop'], 'outline'=>['Outline','12px','fa-square fa-regular'], 'shadow'=>['Shadow','12px','fa-clone']] as $val => $info)
                                    <button type="button" @click="btnStyle = '{{ $val }}'"
                                        :class="btnStyle === '{{ $val }}' ? 'ring-2 ring-purple-500' : ''"
                                        class="flex flex-col items-center gap-1 p-2 rounded-lg transition-all"
                                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                        <i class="fas {{ $info[2] }} text-xs" style="color: var(--text-muted);"></i>
                                        <span class="text-[9px] font-semibold" style="color: var(--text-faint);">{{ $info[0] }}</span>
                                    </button>
                                    @endforeach
                                </div>
                                <input type="hidden" name="button_style" :value="btnStyle">
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Preview</label>
                                <div class="flex justify-center p-4 rounded-xl" style="background: var(--bg-glass-input);">
                                    <div class="px-6 py-2.5 text-sm font-semibold transition-all"
                                         :style="'background:' + btnColor + '; color:' + btnTextColor + '; border-radius:' + (btnStyle === 'pill' ? '999px' : btnStyle === 'square' ? '4px' : '12px') + ';' + (btnStyle === 'outline' ? 'background:transparent; border:2px solid ' + btnColor + '; color:' + btnColor + ';' : '') + (btnStyle === 'shadow' ? 'box-shadow: 0 4px 14px ' + btnColor + '40;' : '')">
                                        Sample Button
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="button_color" x-model="btnColor" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                        <span class="text-xs font-mono" style="color: var(--text-faint);" x-text="btnColor">{{ $btnColor }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Text Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="button_text_color" x-model="btnTextColor" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                        <span class="text-xs font-mono" style="color: var(--text-faint);" x-text="btnTextColor">{{ $btnTextColor }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('user.links.partials.settings-footer', ['link' => $link])
            </form>
        </div>

        <div class="lg:col-span-5 hidden lg:block">
            <div class="sticky top-6">
                @include('user.links.partials.settings-device-preview', ['link' => $link])
            </div>
        </div>
    </div>
</div>
@endsection
