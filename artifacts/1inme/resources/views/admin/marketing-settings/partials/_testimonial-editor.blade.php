{{--
    Re-usable testimonial repeater. Inputs:
      $fieldName (e.g. 'landing_testimonials'), $modelKey (e.g. 'landing'),
      $title, $helper
      $defaultsKey (Alpine var name holding the defaults, e.g. 'testDefaults')
--}}
@php($defaultsKey = $defaultsKey ?? 'testDefaults')
<div class="glass rounded-2xl p-6 space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-white">{{ $title }}</h2>
            <p class="text-xs text-white/50">{{ $helper }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="resetTo('{{ $modelKey }}', {{ $defaultsKey }})"
                    class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-white/80">
                <i class="fas fa-rotate-left mr-1"></i> Reset to defaults
            </button>
            <button type="button" @click="if({{ $modelKey }}.length<24) {{ $modelKey }}.push({quote:'',name:'',role:'',photo:''})"
                    class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                <i class="fas fa-plus mr-1"></i> Add testimonial
            </button>
        </div>
    </div>
    <template x-for="(t,i) in {{ $modelKey }}" :key="i">
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase tracking-wider text-white/40">Testimonial <span x-text="i+1"></span></span>
                <button type="button" @click="{{ $modelKey }}.splice(i,1)" class="text-red-400 hover:text-red-300 text-xs"><i class="fas fa-trash"></i></button>
            </div>
            <textarea :name="'{{ $fieldName }}['+i+'][quote]'" x-model="t.quote" rows="3" placeholder="What they said about 1INME…"
                      class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
            <div class="grid sm:grid-cols-3 gap-2">
                <input type="text" :name="'{{ $fieldName }}['+i+'][name]'" x-model="t.name" placeholder="Name"
                       class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <input type="text" :name="'{{ $fieldName }}['+i+'][role]'" x-model="t.role" placeholder="Role / handle"
                       class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <input type="url" :name="'{{ $fieldName }}['+i+'][photo]'" x-model="t.photo" placeholder="Photo URL (optional)"
                       class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
        </div>
    </template>
    <p x-show="{{ $modelKey }}.length===0" class="text-xs text-white/40">No testimonials yet — add one to show the section publicly.</p>

    {{-- Live preview --}}
    <div x-show="{{ $modelKey }}.length>0" class="mt-2 pt-4 border-t border-white/5">
        <div class="text-[10px] uppercase tracking-wider text-white/40 mb-3">Live preview</div>
        <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-white/10 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <template x-for="(t,i) in {{ $modelKey }}" :key="'{{ $modelKey }}p'+i">
                    <figure x-show="(t.quote || '').trim() !== ''"
                            class="bg-white/[0.04] border border-white/10 rounded-2xl p-4 flex flex-col">
                        <div class="text-violet-300/70 text-xl leading-none mb-2" aria-hidden="true">&ldquo;</div>
                        <blockquote class="text-gray-200 text-xs leading-relaxed flex-1" x-text="t.quote"></blockquote>
                        <figcaption class="mt-3 flex items-center gap-2.5">
                            <template x-if="(t.photo || '').trim() !== ''">
                                <img :src="t.photo" alt="" loading="lazy"
                                     class="w-8 h-8 rounded-full object-cover border border-white/10">
                            </template>
                            <template x-if="(t.photo || '').trim() === ''">
                                <div class="w-8 h-8 rounded-full text-white text-xs font-bold flex items-center justify-center"
                                     style="background:linear-gradient(135deg,#7c3aed,#ec4899);"
                                     x-text="((t.name || '·').trim().charAt(0) || '·').toUpperCase()"></div>
                            </template>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-white truncate" x-text="t.name || ''"></div>
                                <div class="text-[11px] text-gray-400 truncate" x-show="(t.role || '').trim() !== ''" x-text="t.role"></div>
                            </div>
                        </figcaption>
                    </figure>
                </template>
            </div>
            <p x-show="{{ $modelKey }}.filter(t => (t.quote || '').trim() !== '').length === 0"
               class="text-xs text-white/40">Add a quote to see the testimonial card.</p>
        </div>
    </div>
</div>
