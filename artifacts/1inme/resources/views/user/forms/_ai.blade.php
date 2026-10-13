@canInWorkspace('inbox.edit')
<details class="card-premium forms-ai-panel mb-5" x-data="formAiStudio({fields:()=>fields,estimate:@js(route('user.forms.ai.estimate',$form)),generate:@js(route('user.forms.ai.generate',$form))})" @form-ai-source.stop="file=$event.detail.file;quote=null;draft=null">
<summary class="forms-ai-heading"><span class="forms-ai-mark"><i class="fas fa-wand-magic-sparkles"></i></span><span class="flex-1 min-w-0"><strong class="block">Create or edit with AI</strong><span class="text-xs" style="color:var(--text-muted)">Describe your form or bring your own document</span></span><span class="forms-ai-badge">Estimate first</span><i class="fas fa-chevron-down forms-ai-chevron"></i></summary>
<div class="forms-ai-body">
<div class="forms-ai-columns">
<div class="forms-ai-prompt">
<label class="block text-xs font-semibold mb-2">What would you like to do?</label>
<div class="flex flex-wrap gap-2 mb-4"><button type="button" class="form-chip text-xs" :class="mode==='edit'?'active':''" @click="mode='edit';quote=null">Edit existing form</button><button type="button" class="form-chip text-xs" :class="mode==='create'?'active':''" @click="mode='create';quote=null">Create new questions</button></div>
<label for="form-ai-instruction" class="block text-xs font-semibold mb-2">Your instructions</label>
<textarea id="form-ai-instruction" x-model="instruction" @input="quote=null" class="theme-input w-full forms-ai-instruction" rows="5" maxlength="6000" placeholder="Tell us your industry, audience and purpose. Paste questions or explain the changes you need…"></textarea>
<p class="text-xs mt-2" style="color:var(--text-muted)">Example: Volunteer registration with availability, skills and consent.</p>
</div>
<div class="forms-ai-source">
<h3 class="text-xs font-semibold mb-2">Source document <span class="font-normal" style="color:var(--text-muted)">· optional</span></h3>
@include('user.partials.dropzone-input', ['name'=>'form_ai_source','accept'=>'image/jpeg,image/png,image/webp,application/pdf','maxMb'=>10,'multiple'=>false,'currentUrl'=>null,'currentName'=>null,'label'=>null,'hint'=>'JPEG, PNG, WebP or PDF · 10 MB','previewKind'=>'image','allowAlternateSources'=>true,'allowStock'=>false,'browseType'=>'all'])
<p class="text-xs mt-3" style="color:var(--text-muted)">Printed or handwritten forms · up to 6 PDF pages. Review unclear handwriting in the draft.</p>
</div>
</div>
<div class="forms-ai-footer"><div class="flex flex-wrap gap-2 items-center"><button type="button" @click="estimate()" :disabled="busy||!instruction.trim()" class="form-chip text-sm disabled:opacity-50"><i class="fas fa-coins mr-1"></i> Estimate coins</button><button type="button" @click="generate()" :disabled="busy||!quote||quote.balance<quote.estimated_coins" class="btn-primary px-5 py-2.5 text-sm disabled:opacity-50"><i class="fas fa-wand-magic-sparkles mr-1"></i> Generate draft</button><span x-show="busy" x-cloak class="text-xs"><i class="fas fa-spinner fa-spin"></i> Processing…</span></div><template x-if="quote"><span class="forms-ai-quote text-xs" x-text="'Up to '+quote.estimated_coins+' coins · balance '+quote.balance+' · valid 10 minutes'"></span></template></div>
<p class="text-xs mt-3" style="color:var(--text-muted)">Up to 40 proposed fields. Review before applying; Save Form publishes your changes. Text and source documents are sent to the configured AI provider when you generate.</p>
<p role="alert" x-show="message" x-text="message" class="text-sm text-red-500"></p>
<template x-if="draft"><div class="card-premium p-4 space-y-3"><h3 class="font-bold" x-text="draft.title"></h3><p class="text-sm" x-text="draft.description"></p><p class="text-xs" x-text="draft.coins_spent+' coins used'"></p><template x-for="(warning,i) in draft.warnings" :key="i"><p class="text-sm text-amber-600" x-text="warning"></p></template><ol class="space-y-2 max-h-80 overflow-auto"><template x-for="f in draft.fields" :key="f.id"><li class="text-sm"><strong x-text="f.label||f.type"></strong><span class="text-xs ml-2" x-text="f.type+(f.required?' · required':'')"></span><p x-show="f.help" x-text="f.help"></p><p x-show="f.options" x-text="(f.options||[]).join(' · ')"></p></li></template></ol><button type="button" class="btn-primary" @click="apply()">Apply reviewed draft to editor</button><button type="button" class="btn-ghost" @click="draft=null">Discard draft</button></div></template>
</div></details>
@endcanInWorkspace
