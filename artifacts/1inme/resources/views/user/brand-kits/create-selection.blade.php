@php
$brandChoices=\App\Modules\User\Models\BrandKit::where('user_id',workspace_owner_id())->get()->filter(fn($brand)=>empty($brand->config['archived_at']));
@endphp
@if($brandChoices->isNotEmpty())
<div class="glass rounded-2xl p-5 mb-5" x-data="{selected:@js((string)old('brand_kit_id',request('brand_kit_id',''))),choices:@js($brandChoices->mapWithKeys(fn($brand)=>[(string)$brand->id=>['name'=>$brand->name,'color'=>$brand->palette()['primary']??'#3454ef','font'=>$brand->fonts()['body']??'Inter']]))}">
<label class="block text-sm font-semibold mb-2">Brand Kit</label><select class="w-full rounded-xl border p-3" style="background:var(--bg-card);color:var(--text-primary)" name="brand_kit_id" x-model="selected"><option value="">Use default appearance</option>@foreach($brandChoices as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach</select>
<div x-show="selected && choices[selected]" class="mt-3 rounded-xl border p-4"><strong x-text="choices[selected]?.name"></strong><p class="text-sm mt-2" x-text="'Saved palette and font: '+(choices[selected]?.font||'')"></p><span class="inline-block mt-2 rounded-full" :style="{background:choices[selected]?.color,width:'28px',height:'28px'}"></span><p class="text-xs mt-2">Creating applies the saved kit and records an assignment. You can customize it in settings.</p></div>
</div>
@endif
