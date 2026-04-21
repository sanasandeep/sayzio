{{--
    Re-usable testimonial repeater. Inputs:
      $fieldName (e.g. 'landing_testimonials'), $modelKey (e.g. 'landing'),
      $title, $helper
--}}
<div class="glass rounded-2xl p-6 space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-white">{{ $title }}</h2>
            <p class="text-xs text-white/50">{{ $helper }}</p>
        </div>
        <button type="button" @click="if({{ $modelKey }}.length<24) {{ $modelKey }}.push({quote:'',name:'',role:'',photo:''})"
                class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
            <i class="fas fa-plus mr-1"></i> Add testimonial
        </button>
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
</div>
