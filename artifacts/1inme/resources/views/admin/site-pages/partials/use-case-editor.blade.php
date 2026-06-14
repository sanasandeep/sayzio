@php
    use App\Modules\Common\Support\SitePagesContent;
    $ucPersona  = \Illuminate\Support\Str::after($page->slug, 'for-');
    $ucDefaults = SitePagesContent::useCaseExtraDefault($ucPersona);
    $ucSaved    = (is_array($page->extra) && isset($page->extra['use_case']) && is_array($page->extra['use_case']))
        ? SitePagesContent::normalizeUseCaseExtra($page->extra['use_case'], $ucPersona)
        : $ucDefaults;
    $uc         = old('extra.use_case', $ucSaved);
    $ucFeatures = array_values((array) ($uc['features'] ?? []));
    $ucFaqs     = array_values((array) ($uc['faqs'] ?? []));
@endphp
<details class="glass rounded-2xl p-5" open>
    <summary class="cursor-pointer text-sm font-semibold text-white">Use-case hero, featured features &amp; FAQ</summary>
    <p class="text-xs text-white/50 mt-1 mb-4">Edit the hero badge, tagline, accent colour, the featured-feature cards that deep-link into the Features page, and the persona FAQ — changes go live with no redeploy. The benefit paragraphs are edited in the Content sections above.</p>
    <div class="space-y-6">

        {{-- Hero chrome --}}
        <div class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Hero badge label</label>
                    <input type="text" name="extra[use_case][eyebrow]" value="{{ $uc['eyebrow'] ?? '' }}" maxlength="60" placeholder="For creators" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Hero / badge icon</label>
                    <input type="text" name="extra[use_case][icon]" value="{{ $uc['icon'] ?? '' }}" maxlength="60" placeholder="fa-wand-magic-sparkles" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    <p class="mt-1 text-[11px] text-white/40">Font Awesome class, e.g. <code>fa-music</code>.</p>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Hero tagline</label>
                <input type="text" name="extra[use_case][tagline]" value="{{ $uc['tagline'] ?? '' }}" maxlength="200" placeholder="One link that turns followers into a living." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                <p class="mt-1 text-[11px] text-white/40">Shown as the gradient sub-headline under the page title. Leave blank to hide.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Accent colour</label>
                    <div class="flex items-center gap-2">
                        <input type="color" value="{{ \Illuminate\Support\Str::startsWith((string)($uc['accent'] ?? ''), '#') ? $uc['accent'] : '#7c3aed' }}"
                               oninput="this.nextElementSibling.value = this.value"
                               class="h-9 w-12 rounded-lg border border-white/10 bg-white/5 cursor-pointer">
                        <input type="text" name="extra[use_case][accent]" value="{{ $uc['accent'] ?? '#7c3aed' }}" maxlength="7" placeholder="#7c3aed" class="w-32 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    </div>
                    <p class="mt-1 text-[11px] text-white/40">6-digit hex, e.g. <code>#7c3aed</code>.</p>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Nav / cross-sell description</label>
                    <input type="text" name="extra[use_case][nav_desc]" value="{{ $uc['nav_desc'] ?? '' }}" maxlength="160" placeholder="Monetise your audience from one link" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    <p class="mt-1 text-[11px] text-white/40">Used on the "more ways to use 1INME" cards.</p>
                </div>
            </div>
        </div>

        {{-- Featured features repeater --}}
        <div x-data="{ items: {{ \Illuminate\Support\Js::from($ucFeatures) }} }" class="pt-2 border-t border-white/10">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Featured features</label>
                    <p class="text-[11px] text-white/40">Cards that deep-link into the Features page via an anchor (e.g. <code>cat-analytics</code>).</p>
                </div>
                <button type="button" @click="items.push({label:'',icon:'fa-circle-dot',anchor:''})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white shrink-0">Add feature</button>
            </div>
            <template x-for="(it, i) in items" :key="i">
                <div class="mb-3 p-3 rounded-xl bg-white/5 border border-white/10 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] uppercase tracking-wider text-white/40">Feature <span x-text="i+1"></span></span>
                        <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400 hover:text-red-300" title="Remove"><i class="fas fa-trash"></i></button>
                    </div>
                    <input type="text" :name="'extra[use_case][features]['+i+'][label]'" x-model="it.label" placeholder="Live audience analytics" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    <div class="grid sm:grid-cols-2 gap-2">
                        <input type="text" :name="'extra[use_case][features]['+i+'][icon]'" x-model="it.icon" placeholder="fa-chart-line" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                        <input type="text" :name="'extra[use_case][features]['+i+'][anchor]'" x-model="it.anchor" placeholder="cat-analytics" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    </div>
                </div>
            </template>
            <div x-show="items.length===0" class="text-xs text-white/40 text-center py-3">No featured features — click "Add feature".</div>
        </div>

        {{-- Persona FAQ repeater --}}
        <div x-data="{ faqs: {{ \Illuminate\Support\Js::from($ucFaqs) }} }" class="pt-2 border-t border-white/10">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Persona FAQ</label>
                    <p class="text-[11px] text-white/40">Questions and answers shown in the FAQ section of this page.</p>
                </div>
                <button type="button" @click="faqs.push({q:'',a:''})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white shrink-0">Add question</button>
            </div>
            <template x-for="(f, i) in faqs" :key="i">
                <div class="mb-3 p-3 rounded-xl bg-white/5 border border-white/10 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] uppercase tracking-wider text-white/40">Question <span x-text="i+1"></span></span>
                        <button type="button" @click="faqs.splice(i,1)" class="text-xs text-red-400 hover:text-red-300" title="Remove"><i class="fas fa-trash"></i></button>
                    </div>
                    <input type="text" :name="'extra[use_case][faqs]['+i+'][q]'" x-model="f.q" placeholder="Is there really a free plan?" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    <textarea :name="'extra[use_case][faqs]['+i+'][a]'" x-model="f.a" rows="3" placeholder="Answer shown to visitors." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm"></textarea>
                </div>
            </template>
            <div x-show="faqs.length===0" class="text-xs text-white/40 text-center py-3">No FAQ entries — click "Add question".</div>
        </div>

    </div>
</details>
