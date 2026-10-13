@php
 $plannerContextNotes=collect();$plannerContextHandoff=null;$plannerAvailablePlans=collect();
 if(auth()->check() && app()->bound('current_workspace')) {
  $refs=[];$folderIds=[];
  foreach(request()->route()?->parameters()??[] as $param){if(!$param instanceof \Illuminate\Database\Eloquent\Model)continue;
   foreach(\App\Services\Planner\PlannerResources::TYPES as $kind=>$class){if($param instanceof $class){$refs[]=[$kind,$param->id];if($param->project_id)$folderIds[]=$param->project_id;}}
   if($param instanceof \App\Modules\User\Models\Project)$folderIds[]=$param->id;
   if($param instanceof \App\Modules\User\Models\Link && $param->type==='calendar'){$calendar=\App\Modules\User\Models\Calendar::where('link_id',$param->id)->first();if($calendar)$refs[]=['calendar',$calendar->id];}
   if($param instanceof \App\Modules\User\Models\TaskBoard){foreach($param->cards()->limit(200)->pluck('id') as $id)$refs[]=['task',$id];}
  }
  if(preg_match('/^([a-z]+):([1-9][0-9]*)$/',(string)request('planner_resource'),$m)){app(\App\Services\Planner\PlannerResources::class)->get($m[1],(int)$m[2],auth()->user());$refs[]=[$m[1],(int)$m[2]];}
  if(request()->routeIs('user.ai.mind.show') && request('record'))$refs[]=['plan',(int)request('record')];
  foreach($refs as [$kind,$id]){$folderIds=array_merge($folderIds,\App\Modules\User\Models\ProjectItem::where('kind',$kind)->where('resource_id',$id)->pluck('project_id')->all());}
  if($refs || $folderIds)$plannerContextNotes=\App\Modules\User\Models\PlannerNote::where(fn($q)=>$q->where('user_id',auth()->id())->orWhere('shared',true))->whereNull('completed_at')->where(fn($q)=>$q->whereNull('snoozed_until')->orWhere('snoozed_until','<=',now()))->where(function($q)use($refs,$folderIds){$q->where(fn($q)=>$q->whereIn('project_id',$folderIds)->whereNull('resource_kind'));foreach($refs as [$kind,$id]){$q->orWhere(fn($q)=>$q->where('resource_kind',$kind)->where('resource_id',$id));if($kind==='plan')$q->orWhere('plan_id',$id);}})->orderByDesc('pinned')->latest()->limit(10)->get();
  if(session('planner_handoff'))$plannerContextHandoff=\App\Modules\User\Models\PlannerHandoff::where('user_id',auth()->id())->whereNull('closed_at')->find(session('planner_handoff'));
  $plannerIsAi=request()->is('user/ai/*','user/brand-*','user/minds*','user/companions*','user/links/*/ai-*','user/inbox/unified/agent','user/forms/*/builder');
  if($plannerIsAi && !request()->routeIs('user.ai.mind.*'))$plannerAvailablePlans=\App\Modules\User\Models\NoteActionPlan::where(fn($q)=>$q->where('user_id',auth()->id())->orWhere('shared',true))->latest()->limit(30)->get();
 }
@endphp
@if($plannerContextHandoff || $plannerAvailablePlans->isNotEmpty())
<div class="card-premium p-4 mb-4" x-data="{expanded:false}" style="border-left:4px solid #365ce9">
 <div class="flex flex-wrap gap-3 items-center justify-between"><strong class="text-sm">{{ $plannerContextHandoff?'AI Planner brief':'Connect this work to AI Planner' }}</strong><button type="button" class="btn-ghost text-xs" @click="expanded=!expanded">{{ $plannerContextHandoff?'View brief / save result':'Add to Planner' }}</button></div>
 <div x-show="expanded" x-cloak class="mt-3 space-y-3">
 @if($plannerContextHandoff)<form method="post" action="{{ route('user.ai.planner.dismiss') }}">@csrf<button class="btn-ghost text-xs" type="submit">Disconnect planner brief</button></form><p class="text-sm whitespace-pre-wrap">{{ $plannerContextHandoff->brief }}</p><button type="button" class="btn-ghost text-xs" @click="const brief=@js($plannerContextHandoff->brief);const input=document.querySelector('main textarea[name=prompt],main textarea[x-model=prompt],main textarea[x-model=instruction],main input[x-model=draft],main textarea[x-model=draft],main textarea[name=notes]');if(input){input.value=brief.slice(0,input.maxLength>0?input.maxLength:12000);input.dispatchEvent(new Event('input',{bubbles:true}));input.focus()}else{navigator.clipboard.writeText(brief)}">Use brief in this tool / copy</button>@endif
 <form method="POST" action="{{ route('user.ai.planner.capture') }}" class="space-y-3">@csrf
 @if($plannerContextHandoff)<input type="hidden" name="handoff_id" value="{{ $plannerContextHandoff->id }}">@else<select name="plan_id" class="theme-input w-full" aria-label="Plan to save result into">@foreach($plannerAvailablePlans as $record)<option value="{{ $record->id }}">{{ $record->title }}</option>@endforeach</select>@endif
 <label class="block text-xs">Result to keep<textarea name="result" rows="3" maxlength="20000" required class="theme-input w-full mt-2" placeholder="Paste the useful AI result or reviewed decision…"></textarea></label><button class="btn-primary px-3 py-2 text-xs">Save result to plan</button>
 </form>
 </div>
</div>
@endif
@if($plannerContextNotes->isNotEmpty())
<style>.planner-sticky{position:fixed;right:22px;bottom:100px;z-index:75;max-width:340px;width:calc(100vw - 32px);color:var(--text-primary)}.planner-sticky-pane{max-height:65vh;overflow:auto;background:var(--bg-card,#fff);border:1px solid #f59e0b66;border-radius:18px;padding:18px;box-shadow:0 16px 50px #0f172a30}.planner-sticky-toggle{float:right;background:#ffefb7;color:#624507;border:1px solid #eacb67;border-radius:12px;padding:10px 14px;font-size:13px}.planner-sticky-note{padding:12px 0;border-bottom:1px solid var(--border-glass);overflow-wrap:anywhere}</style>
<div class="planner-sticky" x-data="{open:false,init(){try{const key='planner-notes:'+location.pathname+@js(implode('-',$plannerContextNotes->pluck('id')->all()));this.open=@js($plannerContextNotes->contains('pinned',true))||!sessionStorage.getItem(key);sessionStorage.setItem(key,'1')}catch(e){}}}"><button class="planner-sticky-toggle" type="button" @click="open=!open">Planner notes · {{ $plannerContextNotes->count() }}</button><div x-show="open" x-cloak class="planner-sticky-pane mt-12"><div class="flex justify-between"><strong class="text-sm">Remember here</strong><button type="button" @click="open=false" aria-label="Close Planner notes">✕</button></div>@foreach($plannerContextNotes as $note)<div class="planner-sticky-note"><small>{{ $note->shared?'Workspace':'Private' }}{{ $note->pinned?' · Pinned':'' }}</small><p class="text-sm mt-2 whitespace-pre-wrap">{{ $note->body }}</p>@if($note->user_id===auth()->id())<div class="flex gap-3 mt-2">@foreach(['done'=>'Done','snooze'=>'Snooze'] as $action=>$label)<form method="POST" action="{{ route('user.ai.planner.notes.update',$note) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="text-xs" style="color:#365ce9">{{ $label }}</button></form>@endforeach</div>@endif</div>@endforeach</div></div>
@endif
