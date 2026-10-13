@extends('user.layouts.app')
@section('title', 'Design · ' . $form->title)

@section('content')
<link rel="stylesheet" href="{{ asset('css/forms-surfaces.css') }}">
<div class="forms-workspace"
     x-data="{
        styleTab:'controls',styleCategory:'All',surfaceStyle:@js($design['surface_style']??'clean'),labelStyle:@js($design['label_style']??'above'),characterBoxes:{{ !empty($design['character_boxes'])?'true':'false' }},
        customCss:@js($design['custom_css']??''),choiceStyle:@js($design['choice_style']??'plain'),previewMode: 'phone',
        applyPreset(p) { Object.assign(this,p); },

        init() {
            this.$nextTick(() => {
                const render = window.formStudioPreview(@js(route('user.forms.preview',$form)), this.$refs.liveFrame, () => ({design: {surface_style:this.surfaceStyle,label_style:this.labelStyle,character_boxes:this.characterBoxes,custom_css:this.customCss,choice_style:this.choiceStyle,theme:this.theme,accent:this.accent,background:this.background,card_color:this.cardColor,card_image:this.cardImage,card_image_mode:this.cardImageMode,card_image_opacity:this.cardImageOpacity,text:this.text,border_radius:this.radius,button_label:this.buttonLabel,button_style:this.buttonStyle,layout:this.layout,show_branding:this.showBranding,input_style:this.inputStyle,input_shape:this.inputShape,density:this.density,focus_style:this.focusStyle,font:this.font,logo:this.logoUrl,cover:this.coverUrl}}));
                this.$root.addEventListener('form-asset-preview',e=>{const key={card_image:'cardImage',logo:'logoUrl',cover_image:'coverUrl'}[e.detail.name];if(key)this[key]=e.detail.url;});
                this.$root.addEventListener('change',e=>{const key={remove_card_image:'cardImage',remove_logo:'logoUrl',remove_cover:'coverUrl'}[e.target.name];if(key)this[key]=e.target.checked?null:({cardImage:@js($design['card_image']??null),logoUrl:@js($design['logo']??null),coverUrl:@js($design['cover']??null)})[key];});
                ['surfaceStyle','labelStyle','characterBoxes','customCss','choiceStyle','theme','accent','background','cardColor','cardImage','cardImageMode','cardImageOpacity','text','radius','buttonLabel','buttonStyle','layout','showBranding','inputStyle','inputShape','density','focusStyle','font','logoUrl','coverUrl'].forEach(key=>this.$watch(key,render)); render();
            });
        },
        font: @js($design['font']), logoUrl: @js($design['logo']??null), coverUrl: @js($design['cover']??null),
        theme: @js($design['theme']),
        accent: @js($design['accent']),
        background: @js($design['background']),
        cardColor: @js($design['card_color'] ?? '#ffffff'),
        cardImage: @js($design['card_image'] ?? null),
        cardImageMode: @js($design['card_image_mode'] ?? 'cover'),
        cardImageOpacity: {{ (int) ($design['card_image_opacity'] ?? 100) }},
        text: @js($design['text']),
        radius: {{ (int) $design['border_radius'] }},
        buttonLabel: @js($design['button_label']),
        buttonStyle: @js($design['button_style']),
        layout: @js($design['layout']),
        showBranding: {{ ($design['show_branding'] ?? true) ? 'true' : 'false' }},
        inputStyle: @js($design['input_style'] ?? 'soft'),
        inputShape: @js($design['input_shape'] ?? 'auto'),
        density: @js($design['density'] ?? 'comfortable'),
        focusStyle: @js($design['focus_style'] ?? 'glow'),
        inputCss(kind) {
            const light = this.theme === 'light';
            const txt = light ? this.text : 'white';
            const bd = light ? '#e5e7eb' : 'rgba(255,255,255,0.12)';
            const bg = light ? '#f5f6fa' : 'rgba(255,255,255,0.06)';
            const solid = light ? '#ffffff' : 'rgba(255,255,255,0.10)';
            const boxed = this.inputStyle !== 'underline' && this.inputStyle !== 'ghost';
            let radius = Math.max(4, this.radius / 2) + 'px';
            if (boxed && this.inputShape === 'pill') radius = kind === 'textarea' ? '1rem' : '999px';
            if (boxed && this.inputShape === 'square') radius = '0';
            let css = 'width: 100%; font-family: inherit; font-size: 0.72rem; outline: none;'
                + ' color: ' + txt + ';'
                + ' padding: ' + (this.density === 'compact' ? '0.35rem 0.7rem' : '0.5rem 0.75rem') + ';'
                + ' border-radius: ' + radius + '; background: ' + bg + '; border: 1px solid ' + bd + ';';
            if (boxed && this.inputShape === 'pill') css += ' padding-left: 1rem; padding-right: 1rem;';
            if (this.inputStyle === 'outline') css += ' background: transparent; border-color: ' + (light ? '#cbd5e1' : 'rgba(255,255,255,0.24)') + ';';
            if (this.inputStyle === 'underline') css += ' background: transparent; border: 0; border-bottom: 2px solid ' + bd + '; border-radius: 0; padding-left: 0; padding-right: 0;';
            if (this.inputStyle === 'filled') css += ' background: ' + (light ? '#eceff5' : 'rgba(255,255,255,0.08)') + '; border-color: transparent;';
            if (this.inputStyle === 'ghost') css += ' background: transparent; border-color: transparent; padding-left: 0; padding-right: 0;';
            if (this.inputStyle === 'elevated') css += ' background: ' + solid + '; border-color: transparent; box-shadow: 0 1px 2px rgba(0,0,0,0.05), 0 6px 16px -8px rgba(0,0,0,0.2);';
            if (this.inputStyle === 'accent_bar') css += ' border-left: 3px solid ' + this.accent + ';';
            return css;
        },
     }">

    @include('user.partials.page-hero', [
        'title' => 'Design: ' . $form->title,
        'subtitle' => 'Match your brand, colors, fonts, layout and custom CSS.',
        'icon' => 'fa-palette',
        'back' => route('user.forms.show', $form),
    ])

    @include('user.forms._tabs')
    <div class="card-premium p-5 mb-5">
        <div class="flex flex-wrap gap-2 mb-4"><button type="button" class="form-chip" :style="styleTab==='controls'?'background:#365ce9;color:white':''" @click="styleTab='controls'">Design controls</button><button type="button" class="form-chip" :style="styleTab==='gallery'?'background:#365ce9;color:white':''" @click="styleTab='gallery'">Style gallery · 28 styles</button></div>
        <div x-show="styleTab==='gallery'" x-cloak>
        <h3 class="font-bold">Choose a complete visual style</h3><p class="text-sm my-3">Distinct surfaces, typography and input treatments. Refine every style using the controls below.</p>
        <div class="flex flex-wrap gap-2 mb-4">@foreach(['All','Modern','Glass','Paper','Editorial'] as $category)<button type="button" class="form-chip" @click="styleCategory=@js($category)" :style="styleCategory===@js($category)?'background:#365ce9;color:white':''">{{ $category }}</button>@endforeach</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3" style="max-height:460px;overflow:auto;padding:3px">
        @foreach(\App\Modules\User\Services\FormStyleCatalog::all() as $id=>$preset)
        @php($values=array_merge(\Illuminate\Support\Arr::except($preset,['name','category','surface']),['surfaceStyle'=>$preset['surface'],'theme'=>in_array($id,['midnight','blueprint','terminal','luxury'])?'dark':'light','radius'=>in_array($id,['newspaper','ledger','blueprint','receipt','brutalist','terminal','omr'])?0:20,'inputShape'=>'auto','font'=>in_array($preset['surface'],['newspaper','museum'])?'Georgia':(in_array($preset['surface'],['terminal','receipt','ledger'])?'Courier New':'Plus Jakarta Sans'),'characterBoxes'=>in_array($id,['ledger','omr']),'choiceStyle'=>$id==='omr'?'cards':'plain']))
        <button type="button" x-show="styleCategory==='All'||styleCategory===@js($preset['category'])" class="form-style-tile rounded-xl p-4 text-left border" :aria-pressed="surfaceStyle===@js($preset['surface']) &amp;&amp; accent===@js($preset['accent'])" style="background:{{ $preset['background'] }};color:{{ $preset['text'] }};border-color:{{ $preset['accent'] }}80" @click='applyPreset(@json($values))'><div class="style-sample surface-{{ $preset['surface'] }}" style="--form-accent:{{ $preset['accent'] }};background:{{ $preset['cardColor'] }}"><span class="block text-xs mb-2">{{ $preset['category'] }}</span><span class="block border rounded p-2 text-xs" style="border-color:{{ $preset['accent'] }}">Your answer</span></div><strong class="block mt-3">{{ $preset['name'] }}</strong><span class="text-xs">{{ ucfirst($preset['surface']) }} · {{ $preset['inputStyle'] }}</span></button>
        @endforeach</div></div>
    </div>

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.forms.design.update', $form) }}" enctype="multipart/form-data" class="form-studio-grid grid grid-cols-1 lg:grid-cols-3 gap-6">
        @csrf @method('PUT')
        <input type="hidden" name="surface_style" :value="surfaceStyle">

        {{-- Settings --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Theme & Colors</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">Pick a base theme then fine-tune colors. The preview on the right updates live.</p>

                <div class="grid grid-cols-3 gap-3 mb-5">
                    @foreach(['light' => 'Light', 'dark' => 'Dark', 'glass' => 'Glass'] as $val => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="theme" value="{{ $val }}" x-model="theme" class="sr-only">
                            <div class="p-3 rounded-xl text-center text-xs font-semibold" :class="theme === '{{ $val }}' ? 'ring-2 ring-blue-500' : ''" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                                {{ $label }}
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach(['accent' => 'Accent', 'text' => 'Text'] as $key => $label)
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

            {{-- Card Surface — separate from page background so users can have a
                 colored page with a contrasting card, or use a card image. --}}
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Card Surface & Background</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">The page background sits behind the form card. Use a different card color (or an image) to make the form pop.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Page background</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="background" class="w-10 h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass);">
                            <input type="text" name="background" x-model="background" class="theme-input flex-1 text-xs font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Card color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="cardColor" class="w-10 h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass);">
                            <input type="text" name="card_color" x-model="cardColor" class="theme-input flex-1 text-xs font-mono">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Card background image (optional)</label>
                        @if(!empty($design['card_image']))
                            <img src="{{ $design['card_image'] }}" class="h-20 mb-2 rounded-lg w-full object-cover" style="border: 1px solid var(--border-glass);">
                            <label class="text-[10px] inline-flex items-center gap-1.5 mb-2 cursor-pointer" style="color: #f87171;">
                                <input type="checkbox" name="remove_card_image" value="1" class="rounded"> Remove card image
                            </label>
                        @endif
                        @include('user.partials.dropzone-input', [
                            'name'        => 'card_image',
                            'policy'      => \App\Services\UploadPolicy::for('forms.card_image', auth()->user()),
                            'currentUrl'  => $design['card_image'] ?? null,
                            'currentName' => !empty($design['card_image']) ? 'Saved card image' : null,
                            'compact'     => true,
                        ])
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Image fit</label>
                        <select name="card_image_mode" x-model="cardImageMode" class="theme-input w-full text-xs">
                            <option value="cover">Cover (fill, may crop)</option>
                            <option value="contain">Contain (fit, no crop)</option>
                            <option value="tile">Tile (repeat)</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Image opacity (<span x-text="cardImageOpacity"></span>%) <span class="text-[10px]" style="color: var(--text-faint);"> - lower = more card color showing through</span></label>
                        <input type="range" name="card_image_opacity" min="0" max="100" x-model.number="cardImageOpacity" class="w-full accent-blue-500">
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Layout & Typography</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">How the form is arranged and the font used throughout.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Layout</label>
                        <select name="layout" x-model="layout" class="theme-input w-full text-sm">
                            <option value="grid">Grid · two columns</option><option value="grid3">Grid · three columns</option><option value="stacked">Stacked (one field per row)</option>
                            <option value="inline">Inline (compact)</option>
                            <option value="oneq">One question at a time (Typeform-style)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font family</label>
                        <select name="font" x-model="font" class="theme-input w-full text-sm">
                            @foreach(['Plus Jakarta Sans', 'Inter', 'Manrope', 'Poppins', 'Roboto', 'Georgia', 'Courier New', 'System default'] as $f)
                                <option value="{{ $f }}" @selected(($design['font'] ?? '') === $f)>{{ $f }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Corner radius (<span x-text="radius"></span>px)</label>
                        <input type="range" name="border_radius" x-model.number="radius" min="0" max="32" class="w-full accent-blue-500">
                    </div>
                </div>
            </div>

            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Input Fields</h3>
                <p class="text-[11px] mb-5" style="color: var(--text-faint);">Four independent controls: the skin, the shape, how tight the spacing is, and what focus looks like. The preview updates as you pick.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Input style</label>
                        <select name="input_style" x-model="inputStyle" class="theme-input w-full text-sm">
                            <option value="soft">Soft (default)</option>
                            <option value="outline">Outline, no fill</option>
                            <option value="underline">Underline only</option>
                            <option value="filled">Filled, no border</option>
                            <option value="ghost">Borderless</option>
                            <option value="elevated">Elevated, with shadow</option>
                            <option value="accent_bar">Accent bar on the left</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Input shape</label>
                        <select name="input_shape" x-model="inputShape" class="theme-input w-full text-sm">
                            <option value="auto">Follow corner radius</option>
                            <option value="pill">Pill, fully rounded</option>
                            <option value="square">Square corners</option>
                        </select>
                        <p x-show="inputShape !== 'auto' && (inputStyle === 'underline' || inputStyle === 'ghost')" x-cloak class="text-[10px] mt-1.5" style="color: #f59e0b;">
                            Underline and borderless fields have no box to shape, so this is ignored.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Density</label>
                        <select name="density" x-model="density" class="theme-input w-full text-sm">
                            <option value="comfortable">Comfortable (default)</option>
                            <option value="compact">Compact</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Focus effect</label>
                        <select name="focus_style" x-model="focusStyle" class="theme-input w-full text-sm">
                            <option value="glow">Glow ring (default)</option>
                            <option value="border">Accent border only</option>
                            <option value="fill">Accent tint</option>
                        </select>
                        <p class="text-[10px] mt-1.5" style="color: var(--text-faint);">Click into the preview field to see it.</p>
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
                        @include('user.partials.dropzone-input', [
                            'name'        => 'logo',
                            'policy'      => \App\Services\UploadPolicy::for('forms.logo', auth()->user()),
                            'currentUrl'  => $design['logo'] ?? null,
                            'currentName' => !empty($design['logo']) ? 'Saved logo' : null,
                            'compact'     => true,
                        ])
                        @if(!empty($design['logo']))
                            <label class="text-[10px] inline-flex items-center gap-1.5 mt-2 cursor-pointer" style="color: #f87171;"><input type="checkbox" name="remove_logo" value="1" class="rounded"> Remove saved logo</label>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Cover image</label>
                        @include('user.partials.dropzone-input', [
                            'name'        => 'cover_image',
                            'policy'      => \App\Services\UploadPolicy::for('forms.cover', auth()->user()),
                            'currentUrl'  => $design['cover'] ?? null,
                            'currentName' => !empty($design['cover']) ? 'Saved cover image' : null,
                            'compact'     => true,
                        ])
                        @if(!empty($design['cover']))
                            <label class="text-[10px] inline-flex items-center gap-1.5 mt-2 cursor-pointer" style="color: #f87171;"><input type="checkbox" name="remove_cover" value="1" class="rounded"> Remove saved cover</label>
                        @endif
                    </div>
                </div>

                <label class="flex items-center gap-2 text-xs mt-5 cursor-pointer" style="color: var(--text-secondary);">
                    <input type="hidden" name="show_branding" value="0">
                    <input type="checkbox" name="show_branding" value="1" x-model="showBranding" class="rounded text-blue-500">
                    Show "Powered by Sayzio" branding
                </label>
            </div>

            <div class="card-premium p-6"><h3 class="font-bold mb-3">Labels & character boxes</h3><label class="block text-sm mb-2">Label position<select name="label_style" x-model="labelStyle" class="theme-input w-full mt-2"><option value="above">Above input</option><option value="inside">Inside input</option><option value="floating">Floating · moves on focus or answer</option></select></label><input type="hidden" name="character_boxes" value="0"><label class="flex gap-2 mt-4"><input type="checkbox" name="character_boxes" value="1" x-model="characterBoxes">Character boxes · bank application style</label><p class="text-xs mt-2">Applies to short text, number and phone fields, one character per box. Long answers scroll within the field; answer limits are unchanged. Other field types keep their normal controls. Floating labels retain a visible label when an answer is entered.</p></div>
            <div class="card-premium p-6"><h3 class="font-bold mb-3">Choice presentation</h3><p class="text-sm mb-4" style="color:var(--text-muted)">Style radio buttons and checkbox answers with clear selected states.</p><select name="choice_style" x-model="choiceStyle" class="theme-input w-full"><option value="plain">Simple list</option><option value="cards">Selectable cards</option><option value="pills">Compact pills</option></select></div>
            <div class="card-premium p-6" x-data="{ open: {{ !empty($design['custom_css']) ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="flex items-center gap-3 w-full text-left">
                    <i class="fas fa-code text-cyan-400 text-xs"></i>
                    <h3 class="text-sm font-bold flex-1" style="color: var(--text-primary);">Advanced, custom CSS</h3>
                    <i class="fas fa-chevron-down text-xs" :class="open ? 'rotate-180' : ''" style="color: var(--text-faint); transition: transform 0.2s;"></i>
                </button>
                <div x-show="open" x-transition class="mt-4">
                    <textarea name="custom_css" x-model="customCss" rows="8" placeholder=".form-card { background: …; }" class="theme-input w-full font-mono text-[12px]">{{ old('custom_css', $design['custom_css'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Live preview --}}
        <aside class="forms-preview" :class="previewMode==='desktop'?'forms-expanded':''">
            <div class="card-premium p-5 ">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Live Preview</h4>
                <header><button type="button" class="form-chip" :class="previewMode==='phone'?'active':''" @click="previewMode='phone'">Phone</button><button type="button" class="form-chip" :class="previewMode==='desktop'?'active':''" @click="previewMode='desktop'">Desktop</button><button type="button" class="form-chip" x-show="previewMode==='desktop'" @click="previewMode='phone'">Close expanded preview</button><span class="text-xs">Actual fields · unsaved preview</span></header>
                <iframe x-ref="liveFrame" title="Live form design" sandbox="allow-scripts" :style="previewMode==='phone'?'max-width:390px;margin:auto':'max-width:none'"></iframe>
                @canInWorkspace('inbox.edit')
                <button type="submit" class="btn-primary w-full mt-4 px-6 py-3 text-sm font-semibold inline-flex items-center justify-center gap-2">
                    <i class="fas fa-save text-xs"></i> Save Design
                </button>
                @else
                <button type="button" disabled class="btn-primary w-full mt-4 px-6 py-3 text-sm font-semibold inline-flex items-center justify-center gap-2 opacity-60 cursor-not-allowed" title="Your role doesn't allow editing forms, ask a workspace admin">
                    <i class="fas fa-lock text-xs"></i> Save Design
                </button>
                <p class="text-xs mt-2 text-center" style="color:#b45309;"><i class="fas fa-lock"></i> View-only, saving is reserved for admins.</p>
                @endcanInWorkspace
            </div>
        </aside>
    </form>
</div>
<script src="{{ asset('js/forms-studio.js') }}"></script>
@endsection
