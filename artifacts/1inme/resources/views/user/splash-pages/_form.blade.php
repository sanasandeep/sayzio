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
                ['logo','Logo','previewLogo','remove_logo','PNG/JPG up to 2 MB'],
                ['favicon','Favicon','previewFavicon','remove_favicon','PNG/ICO up to 512 KB'],
                ['og_image','Social image','previewOg','remove_og','PNG/JPG up to 4 MB'],
            ] as [$field,$label,$state,$rmFlag,$hint])
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">{{ $label }}</label>
                    <div class="rounded-lg p-3 flex items-center justify-center min-h-[100px]"
                         style="background: var(--bg-glass-hover); border: 1px dashed var(--border-glass);">
                        <template x-if="{{ $state }}">
                            <img :src="{{ $state }}" alt="" class="max-h-20 max-w-full object-contain">
                        </template>
                        <template x-if="!{{ $state }}">
                            <span class="text-xs" style="color: var(--text-faint);">No image</span>
                        </template>
                    </div>
                    <input type="file" name="{{ $field }}" accept="image/*" class="text-xs mt-2 w-full"
                           @change="readPreview($event.target, '{{ $state }}')">
                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">{{ $hint }}</p>
                    <label class="inline-flex items-center gap-1.5 text-[11px] mt-1.5" style="color: var(--text-muted);">
                        <input type="checkbox" name="{{ $rmFlag }}" value="1"> Remove
                    </label>
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
            <i class="fas fa-save"></i> {{ $isEdit ? 'Save Changes' : 'Create Splash Page' }}
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
