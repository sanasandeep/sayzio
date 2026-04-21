@php
    $aboutDefaults = \App\Modules\Common\Support\SitePagesContent::aboutExtraDefault();
    $aboutExtra = old('extra', is_array($page->extra) && !empty($page->extra)
        ? \App\Modules\Common\Support\SitePagesContent::normalizeAboutExtra($page->extra)
        : $aboutDefaults);
    $founder = $aboutExtra['founder'] ?? $aboutDefaults['founder'];
    $coFounders = array_values((array)($aboutExtra['co_founders'] ?? []));
    $teamRows = array_values((array)($aboutExtra['team'] ?? []));
    $milestoneRows = array_values((array)($aboutExtra['milestones'] ?? []));
@endphp

<div class="pt-2 border-t border-white/10 space-y-6">
    <div>
        <h3 class="text-sm font-semibold text-white">Founder</h3>
        <p class="text-xs text-white/50 mb-3">The featured founder card at the top of /about.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Name</label>
                <input type="text" name="extra[founder][name]" value="{{ $founder['name'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Role / title</label>
                <input type="text" name="extra[founder][role]" value="{{ $founder['role'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Photo URL <span class="normal-case tracking-normal text-white/40">(optional)</span></label>
                <input type="url" name="extra[founder][photo]" value="{{ $founder['photo'] ?? '' }}" placeholder="https://…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Short bio</label>
                <textarea name="extra[founder][bio]" rows="3" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $founder['bio'] ?? '' }}</textarea>
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Twitter / X URL</label>
                <input type="url" name="extra[founder][links][twitter]" value="{{ $founder['links']['twitter'] ?? '' }}" placeholder="https://x.com/…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">LinkedIn URL</label>
                <input type="url" name="extra[founder][links][linkedin]" value="{{ $founder['links']['linkedin'] ?? '' }}" placeholder="https://linkedin.com/in/…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
        </div>
    </div>

    <div x-data="{ rows: {{ json_encode($coFounders) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Co-founders</h3>
                <p class="text-xs text-white/50">Three cards by default.</p>
            </div>
            <button type="button" @click="rows.push({name:'',role:'',photo:'',bio:'',links:{twitter:'',linkedin:''}})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add co-founder</button>
        </div>
        <template x-for="(p, i) in rows" :key="i">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Co-founder <span x-text="i+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input type="text" :name="'extra[co_founders]['+i+'][name]'" x-model="p.name" placeholder="Name" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'extra[co_founders]['+i+'][role]'" x-model="p.role" placeholder="Role" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <input type="url" :name="'extra[co_founders]['+i+'][photo]'" x-model="p.photo" placeholder="Photo URL (optional)" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <textarea :name="'extra[co_founders]['+i+'][bio]'" x-model="p.bio" rows="2" placeholder="Short bio" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input type="url" :name="'extra[co_founders]['+i+'][links][twitter]'" x-model="p.links.twitter" placeholder="Twitter / X URL" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="url" :name="'extra[co_founders]['+i+'][links][linkedin]'" x-model="p.links.linkedin" placeholder="LinkedIn URL" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
            </div>
        </template>
        <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No co-founders yet — click "Add co-founder".</div>
    </div>

    <div x-data="{ rows: {{ json_encode($teamRows) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Team grid</h3>
                <p class="text-xs text-white/50">Smaller cards under the co-founders.</p>
            </div>
            <button type="button" @click="rows.push({name:'',role:'',photo:'',bio:'',links:{twitter:'',linkedin:''}})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add team member</button>
        </div>
        <template x-for="(p, i) in rows" :key="i">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Member <span x-text="i+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input type="text" :name="'extra[team]['+i+'][name]'" x-model="p.name" placeholder="Name" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'extra[team]['+i+'][role]'" x-model="p.role" placeholder="Role" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <input type="url" :name="'extra[team]['+i+'][photo]'" x-model="p.photo" placeholder="Photo URL (optional)" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <textarea :name="'extra[team]['+i+'][bio]'" x-model="p.bio" rows="2" placeholder="One-line bio" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
            </div>
        </template>
        <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No team members yet — click "Add team member".</div>
    </div>

    <div x-data="{ rows: {{ json_encode($milestoneRows) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Milestones timeline</h3>
                <p class="text-xs text-white/50">Use <code class="text-white/60">YYYY-MM</code> or <code class="text-white/60">YYYY-MM-DD</code> for dates.</p>
            </div>
            <button type="button" @click="rows.push({date:'',title:'',description:''})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add milestone</button>
        </div>
        <template x-for="(m, i) in rows" :key="i">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Milestone <span x-text="i+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="grid sm:grid-cols-3 gap-3">
                    <input type="text" :name="'extra[milestones]['+i+'][date]'" x-model="m.date" placeholder="2024-03" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    <input type="text" :name="'extra[milestones]['+i+'][title]'" x-model="m.title" placeholder="Title" class="sm:col-span-2 w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <textarea :name="'extra[milestones]['+i+'][description]'" x-model="m.description" rows="2" placeholder="What happened" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
            </div>
        </template>
        <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No milestones yet — click "Add milestone".</div>
    </div>
</div>
