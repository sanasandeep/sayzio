@php
    $isEdit = $splashPage->exists;
    $action = $isEdit ? route('user.splash-pages.update', $splashPage) : route('user.splash-pages.store');
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6"
      x-data="{
        previewLogo:    @js($splashPage->logo),
        previewFavicon: @js($splashPage->favicon),
        previewOg:      @js($splashPage->og_image),
        autoRedirect:   {{ $splashPage->auto_redirect ? 'true' : 'false' }},
        extraButtons:   @js(old('extra_buttons', $splashPage->extra_buttons ?: [])),
        addButton(){
            if (this.extraButtons.length >= 10) return;
            this.extraButtons.push({ label: '', url: '', bg_color: '#8b5cf6', text_color: '#ffffff' });
        },
        removeButton(i){ this.extraButtons.splice(i, 1); },
        readPreview(input, target){
            var f = input.files && input.files[0]; if(!f) return;
            var r = new FileReader();
            r.onload = e => this[target] = e.target.result;
            r.readAsDataURL(f);
        }
      }">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @if($errors->any())
        <div class="card-premium p-4" style="border-color: var(--c-danger); background: var(--c-danger-soft);">
            <div class="font-semibold mb-1" style="color: var(--c-danger);">Please fix:</div>
            <ul class="text-xs list-disc pl-5" style="color: var(--c-danger);">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Basics --}}
    <div class="card-premium p-6">
        <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Basics</h3>
        <p class="text-xs mb-5" style="color: var(--text-muted);">Internal name (only you see it) and the optional visible title shown to visitors.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Internal name *</label>
                <input type="text" name="name" required value="{{ old('name', $splashPage->name) }}"
                       class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                       placeholder="e.g. Promo splash">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Project (optional)</label>
                <select name="project_id"
                        class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <option value="">— None —</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" @selected(old('project_id', $splashPage->project_id) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Visible title</label>
                <input type="text" name="title" maxlength="160" value="{{ old('title', $splashPage->title) }}"
                       class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                       placeholder="Headline shown to visitors">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Description</label>
                <textarea name="description" rows="3" maxlength="1000"
                          class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                          style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                          placeholder="One or two sentences shown under the title">{{ old('description', $splashPage->description) }}</textarea>
            </div>
        </div>
    </div>

    {{-- Branding --}}
    <div class="card-premium p-6">
        <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Branding</h3>
        <p class="text-xs mb-5" style="color: var(--text-muted);">Logo, favicon, and social-share image.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach([
                ['logo','Logo','previewLogo','remove_logo','splash.logo'],
                ['favicon','Favicon','previewFavicon','remove_favicon','splash.favicon'],
                ['og_image','Social image','previewOg','remove_og','splash.og'],
            ] as [$field,$label,$state,$rmFlag,$policyKey])
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">{{ $label }}</label>
                    @include('user.partials.dropzone-input', [
                        'name'        => $field,
                        'policy'      => \App\Services\UploadPolicy::for($policyKey, auth()->user()),
                        'currentUrl'  => null,
                        'currentName' => null,
                        'compact'     => true,
                    ])
                    <template x-if="{{ $state }}">
                        <div class="mt-2 flex items-center gap-2 p-2 rounded-lg bg-white/5">
                            <img :src="{{ $state }}" alt="" class="w-10 h-10 rounded object-cover">
                            <span class="text-xs flex-1" style="color: var(--text-muted);">Current image</span>
                            <label class="inline-flex items-center gap-1.5 text-[11px] cursor-pointer" style="color: #f87171;">
                                <input type="checkbox" name="{{ $rmFlag }}" value="1"> Remove
                            </label>
                        </div>
                    </template>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Call to action + auto redirect --}}
    <div class="card-premium p-6">
        <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Call to action</h3>
        <p class="text-xs mb-5" style="color: var(--text-muted);">If no URL is set, the CTA continues to the link's normal destination.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Button label</label>
                <input type="text" name="cta_label" maxlength="60" value="{{ old('cta_label', $splashPage->cta_label ?: 'Continue') }}"
                       class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Custom CTA URL (optional)</label>
                <input type="url" name="cta_url" maxlength="2000" value="{{ old('cta_url', $splashPage->cta_url) }}"
                       class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                       placeholder="https://…">
            </div>

            {{-- Main button colors --}}
            @php
                $mainBg   = old('cta_bg_color', $splashPage->cta_bg_color ?: '');
                $mainText = old('cta_text_color', $splashPage->cta_text_color ?: '');
            @endphp
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Button background color</label>
                <div class="flex items-center gap-2"
                     x-data="{ bg: @js($mainBg) }">
                    <input type="color" :value="bg || '#8b5cf6'" @input="bg = $event.target.value"
                           class="w-10 h-10 p-0 rounded-lg border-0 cursor-pointer bg-transparent">
                    <input type="text" name="cta_bg_color" x-model="bg" maxlength="7" placeholder="#8b5cf6"
                           class="flex-1 px-3 py-2 text-sm font-mono rounded-lg outline-none"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <button type="button" @click="bg = ''" class="text-[11px] px-2 py-1 rounded" style="color: var(--text-muted); background: var(--bg-glass-hover);">Reset</button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Button text color</label>
                <div class="flex items-center gap-2"
                     x-data="{ tc: @js($mainText) }">
                    <input type="color" :value="tc || '#ffffff'" @input="tc = $event.target.value"
                           class="w-10 h-10 p-0 rounded-lg border-0 cursor-pointer bg-transparent">
                    <input type="text" name="cta_text_color" x-model="tc" maxlength="7" placeholder="#ffffff"
                           class="flex-1 px-3 py-2 text-sm font-mono rounded-lg outline-none"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <button type="button" @click="tc = ''" class="text-[11px] px-2 py-1 rounded" style="color: var(--text-muted); background: var(--bg-glass-hover);">Reset</button>
                </div>
            </div>

            {{-- Extra buttons repeater --}}
            <div class="md:col-span-2 pt-2">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <div class="text-sm font-semibold" style="color: var(--text-primary);">Additional buttons</div>
                        <p class="text-xs" style="color: var(--text-muted);">Up to 10 extra buttons shown below the main one.</p>
                    </div>
                    <button type="button" @click="addButton()"
                            x-bind:disabled="extraButtons.length >= 10"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-50"
                            style="background: var(--accent); color: #fff;">
                        <i class="fas fa-plus"></i> Add button
                    </button>
                </div>

                <template x-if="extraButtons.length === 0">
                    <div class="text-xs italic px-3 py-4 rounded-lg text-center"
                         style="color: var(--text-muted); background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                        No additional buttons yet. Click <strong>Add button</strong> to create one.
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="(btn, i) in extraButtons" :key="i">
                        <div class="p-3 rounded-lg"
                             style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold" style="color: var(--text-secondary);"
                                      x-text="'Button ' + (i + 2)"></span>
                                <button type="button" @click="removeButton(i)"
                                        class="text-xs inline-flex items-center gap-1" style="color: #f87171;">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Label</label>
                                    <input type="text" maxlength="60"
                                           :name="'extra_buttons[' + i + '][label]'"
                                           x-model="btn.label"
                                           class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                                           style="background: var(--bg-glass-hover); border: 1px solid var(--border-glass); color: var(--text-primary);"
                                           placeholder="e.g. Learn more">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">URL</label>
                                    <input type="url" maxlength="2000"
                                           :name="'extra_buttons[' + i + '][url]'"
                                           x-model="btn.url"
                                           class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                                           style="background: var(--bg-glass-hover); border: 1px solid var(--border-glass); color: var(--text-primary);"
                                           placeholder="https://…">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Background color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" :value="btn.bg_color || '#8b5cf6'"
                                               @input="btn.bg_color = $event.target.value"
                                               class="w-9 h-9 p-0 rounded-lg border-0 cursor-pointer bg-transparent">
                                        <input type="text" maxlength="7"
                                               :name="'extra_buttons[' + i + '][bg_color]'"
                                               x-model="btn.bg_color"
                                               class="flex-1 px-3 py-2 text-sm font-mono rounded-lg outline-none"
                                               style="background: var(--bg-glass-hover); border: 1px solid var(--border-glass); color: var(--text-primary);"
                                               placeholder="#8b5cf6">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Text color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" :value="btn.text_color || '#ffffff'"
                                               @input="btn.text_color = $event.target.value"
                                               class="w-9 h-9 p-0 rounded-lg border-0 cursor-pointer bg-transparent">
                                        <input type="text" maxlength="7"
                                               :name="'extra_buttons[' + i + '][text_color]'"
                                               x-model="btn.text_color"
                                               class="flex-1 px-3 py-2 text-sm font-mono rounded-lg outline-none"
                                               style="background: var(--bg-glass-hover); border: 1px solid var(--border-glass); color: var(--text-primary);"
                                               placeholder="#ffffff">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="md:col-span-2 flex items-center gap-3 pt-2">
                <input type="hidden" name="auto_redirect" value="0">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="auto_redirect" value="1" x-model="autoRedirect" class="w-4 h-4">
                    <span class="text-sm font-semibold" style="color: var(--text-primary);">Auto-redirect after countdown</span>
                </label>
            </div>
            <div x-show="autoRedirect" x-cloak class="md:col-span-2">
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Countdown (seconds)</label>
                <input type="number" name="countdown" min="0" max="120" value="{{ old('countdown', $splashPage->countdown ?: 5) }}"
                       class="w-32 px-3 py-2 text-sm rounded-lg outline-none"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            </div>
        </div>
    </div>

    {{-- Advanced --}}
    <div class="card-premium p-6" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
            <div>
                <h3 class="text-base font-bold" style="color: var(--text-primary);">Custom CSS / JS</h3>
                <p class="text-xs" style="color: var(--text-muted);">Inject your own styles or scripts.</p>
            </div>
            <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" style="color: var(--text-muted);"></i>
        </button>
        <div x-show="open" x-cloak class="mt-4 space-y-4">
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Custom CSS</label>
                <textarea name="custom_css" rows="6" maxlength="50000"
                          class="w-full px-3 py-2 text-xs font-mono rounded-lg outline-none"
                          style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                          placeholder=".cta { background: red; }">{{ old('custom_css', $splashPage->custom_css) }}</textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Custom JS</label>
                <textarea name="custom_js" rows="6" maxlength="50000"
                          class="w-full px-3 py-2 text-xs font-mono rounded-lg outline-none"
                          style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"
                          placeholder="console.log('splash loaded');">{{ old('custom_js', $splashPage->custom_js) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold" style="background: var(--accent); color: #fff;">
            <i class="fas fa-save"></i> {{ $isEdit ? 'Save Changes' : 'Create Intro' }}
        </button>
        @if($isEdit)
            <a href="{{ route('user.splash-pages.preview', $splashPage) }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold"
               style="background: var(--bg-glass-hover); color: var(--text-primary);">
                <i class="fas fa-eye"></i> Preview
            </a>
        @endif
        <a href="{{ route('user.splash-pages.index') }}" class="text-sm" style="color: var(--text-muted);">Cancel</a>
    </div>
</form>
