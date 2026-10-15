<div class="rounded-2xl border border-white/10 p-5 space-y-3" x-data="agentDraft()">
    <h3 class="text-white font-semibold"><i class="fas fa-wand-magic-sparkles mr-2"></i> Set up with AI</h3>
    <p class="text-xs text-white/50">Describe the job and select AI Minds below. AI uses available source excerpts to draft identity, instructions and greeting. Review before applying.</p>
    <textarea x-model="brief" @input="quote=null" rows="2" maxlength="2000" placeholder="e.g. A friendly sales assistant that answers product questions and helps visitors choose the right service." class="theme-input w-full"></textarea>
    <div class="agent-draft-actions"><button type="button" @click="requestDraft(false)" :disabled="busy || brief.trim().length<10">Estimate coins</button><button type="button" @click="requestDraft(true)" :disabled="busy || !quote" class="btn-primary">Generate draft</button></div>
    <p role="status" class="text-xs text-white/60" x-text="status"></p>
    <template x-if="draft"><div class="space-y-3"><pre class="agent-draft-result" x-text="JSON.stringify(draft,null,2)"></pre><button type="button" class="btn-primary" @click="applyDraft()">Apply to form</button><p class="text-xs text-white/50">Applying only changes this form. Save as new version when ready.</p></div></template>
</div>
@push('scripts')
<script>
function agentDraft(){return {brief:'',quote:null,busy:false,status:'',draft:null,
async requestDraft(generate){this.busy=true;this.status=generate?'Generating draft…':'Estimating…';try{var f=document.getElementById('agent-editor'),data={brief:this.brief,mind_ids:Array.from(f.querySelectorAll('input[name="mind_ids[]"]:checked')).map(n=>Number(n.value)),include_platform:!!f.querySelector('input[name="use_default_mind"]:checked'),generate:generate,quote:this.quote};var r=await fetch(@js(route('user.ai-personas.draft',$persona)),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@js(csrf_token())},body:JSON.stringify(data)});var out=await r.json();if(!r.ok)throw new Error(out.message||'Could not prepare draft.');if(generate){this.draft=out.draft;this.quote=null;this.status='Draft ready · '+out.credits_spent+' coins used. Review before applying.';}else{this.quote=out.quote;this.status='Estimated up to '+out.coins+' coins · valid for 5 minutes. '+(out.sources_available?'Available Mind source excerpts included.':'No ready text sources found; the brief will be used.');}}catch(e){this.status=e.message;this.quote=null;}finally{this.busy=false;}},
applyDraft(){if(!confirm('Replace the matching fields with this draft? Model, permissions and AI Minds will stay as selected.'))return;var f=document.getElementById('agent-editor');Object.entries(this.draft).forEach(([k,v])=>{if(f.elements[k]){f.elements[k].value=v;f.elements[k].dispatchEvent(new Event('input',{bubbles:true}));}});this.status='Draft applied to form. Review the tabs and save a new version.';}}}
</script>
@endpush
