@php
$inputClass = 'w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none';
$labelClass = 'block text-xs text-white/40 mb-1';
$socialOptions = ['instagram','twitter','facebook','tiktok','youtube','linkedin','github','discord','telegram','whatsapp','snapchat','pinterest','twitch','dribbble','spotify','soundcloud','apple','reddit','medium','behance','website','email'];
@endphp
<div x-data="{ platforms: {{ json_encode($s['platforms'] ?? []) }} }">
    <label class="{{ $labelClass }}">Social Platforms</label>
    <template x-for="(p, i) in platforms" :key="i">
        <div class="glass rounded-lg p-3 mb-2">
            <div class="grid grid-cols-2 gap-2 mb-2">
                <select x-model="platforms[i].name" :name="'settings[platforms]['+i+'][name]'" class="{{ $inputClass }}">
                    <option value="" class="bg-[#0d0818]">Select...</option>
                    @foreach($socialOptions as $opt)
                    <option value="{{ $opt }}" class="bg-[#0d0818]">{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
                <input type="url" x-model="platforms[i].url" :name="'settings[platforms]['+i+'][url]'" placeholder="https://..." class="{{ $inputClass }}">
            </div>
            <button type="button" @click="platforms.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="platforms.push({name:'',url:''})" class="text-xs text-purple-400 hover:text-purple-300"><i class="fas fa-plus mr-1"></i>Add Platform</button>
</div>
