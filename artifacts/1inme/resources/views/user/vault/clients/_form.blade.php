@php
    $emails = old('emails', $item ? $item->emails->map(fn($e)=>['email'=>$e->email,'label'=>$e->label])->all() : [['email'=>'','label'=>'']]);
    $phones = old('phones', $item ? $item->phones->map(fn($p)=>['phone'=>$p->phone,'label'=>$p->label])->all() : [['phone'=>'','label'=>'']]);
    $addresses = old('addresses', $item ? $item->addresses->map(fn($a)=>$a->only(['label','line1','line2','city','region','postal_code','country']))->all() : []);
    $fields = $item?->getEncrypted('fields', true) ?? [];
    $socials = $item?->getEncrypted('social_handles', true) ?? [];
@endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <label class="block">
        <span class="text-xs uppercase tracking-wider text-gray-400">Name *</span>
        <input type="text" name="name" required value="{{ old('name', $item->name ?? '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider text-gray-400">Company</span>
        <input type="text" name="company" value="{{ old('company', $item->company ?? '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider text-gray-400">Website</span>
        <input type="text" name="website" value="{{ old('website', $item->website ?? '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider text-gray-400">Visibility</span>
        <select name="visibility" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <option value="shared" @selected(old('visibility', $item->visibility ?? 'shared') === 'shared')>Shared with workspace</option>
            <option value="private" @selected(old('visibility', $item->visibility ?? '') === 'private')>Private — creator + owner only</option>
        </select>
    </label>
    <label class="block md:col-span-2">
        <span class="text-xs uppercase tracking-wider text-gray-400">Tags (comma-separated)</span>
        <input type="text" name="tags" value="{{ old('tags', isset($item) ? implode(',', (array) ($item->tags ?? [])) : '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block md:col-span-2">
        <span class="text-xs uppercase tracking-wider text-gray-400">Notes (encrypted)</span>
        <textarea name="notes" rows="4" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">{{ old('notes', $item?->getEncrypted('notes')) }}</textarea>
    </label>
</div>

<div class="mt-6" x-data="{ rows: @json($emails ?: [['email'=>'','label'=>'']]) }">
    <h3 class="text-sm font-semibold text-gray-300 mb-2">Emails <span class="text-gray-500 text-xs">(first row is primary)</span></h3>
    <template x-for="(row, i) in rows" :key="i">
        <div class="grid grid-cols-12 gap-2 mb-2">
            <input type="email" :name="'emails['+i+'][email]'" x-model="row.email" placeholder="email@example.com" class="col-span-7 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <input type="text" :name="'emails['+i+'][label]'" x-model="row.label" placeholder="Label (work, billing…)" class="col-span-4 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <button type="button" @click="rows.splice(i,1)" class="col-span-1 text-red-400"><i class="fas fa-trash"></i></button>
        </div>
    </template>
    <button type="button" @click="rows.push({email:'',label:''})" class="text-xs text-amber-400">+ Add email</button>
</div>

<div class="mt-6" x-data="{ rows: @json($phones ?: [['phone'=>'','label'=>'']]) }">
    <h3 class="text-sm font-semibold text-gray-300 mb-2">Phones</h3>
    <template x-for="(row, i) in rows" :key="i">
        <div class="grid grid-cols-12 gap-2 mb-2">
            <input type="text" :name="'phones['+i+'][phone]'" x-model="row.phone" placeholder="+1 555 123 4567" class="col-span-7 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <input type="text" :name="'phones['+i+'][label]'" x-model="row.label" placeholder="Label" class="col-span-4 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <button type="button" @click="rows.splice(i,1)" class="col-span-1 text-red-400"><i class="fas fa-trash"></i></button>
        </div>
    </template>
    <button type="button" @click="rows.push({phone:'',label:''})" class="text-xs text-amber-400">+ Add phone</button>
</div>

<div class="mt-6" x-data="{ rows: @json($addresses) }">
    <h3 class="text-sm font-semibold text-gray-300 mb-2">Addresses</h3>
    <template x-for="(row, i) in rows" :key="i">
        <div class="grid grid-cols-12 gap-2 mb-3 p-3 rounded-lg bg-white/5">
            <input type="text" :name="'addresses['+i+'][label]'" x-model="row.label" placeholder="Label" class="col-span-3 px-3 py-2 rounded-lg bg-white/10 text-sm">
            <input type="text" :name="'addresses['+i+'][line1]'" x-model="row.line1" placeholder="Address line 1" class="col-span-9 px-3 py-2 rounded-lg bg-white/10 text-sm">
            <input type="text" :name="'addresses['+i+'][line2]'" x-model="row.line2" placeholder="Line 2" class="col-span-12 px-3 py-2 rounded-lg bg-white/10 text-sm">
            <input type="text" :name="'addresses['+i+'][city]'" x-model="row.city" placeholder="City" class="col-span-4 px-3 py-2 rounded-lg bg-white/10 text-sm">
            <input type="text" :name="'addresses['+i+'][region]'" x-model="row.region" placeholder="State/Region" class="col-span-3 px-3 py-2 rounded-lg bg-white/10 text-sm">
            <input type="text" :name="'addresses['+i+'][postal_code]'" x-model="row.postal_code" placeholder="Postal" class="col-span-2 px-3 py-2 rounded-lg bg-white/10 text-sm">
            <input type="text" :name="'addresses['+i+'][country]'" x-model="row.country" placeholder="Country" class="col-span-2 px-3 py-2 rounded-lg bg-white/10 text-sm">
            <button type="button" @click="rows.splice(i,1)" class="col-span-1 text-red-400 text-xs">Remove</button>
        </div>
    </template>
    <button type="button" @click="rows.push({label:'',line1:'',line2:'',city:'',region:'',postal_code:'',country:''})" class="text-xs text-amber-400">+ Add address</button>
</div>

<div class="mt-6" x-data='{ rows: @json(array_values(array_map(fn($r)=>["network"=>$r["network"]??"","handle"=>$r["handle"]??"","url"=>$r["url"]??""], $socials ?: []))) }'>
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-semibold text-gray-300">Social handles (encrypted)</h3>
        <button type="button" @click="rows.push({network:'',handle:'',url:''})" class="text-xs text-amber-400">+ Add</button>
    </div>
    <template x-for="(row, i) in rows" :key="i">
        <div class="grid grid-cols-12 gap-2 mb-2">
            <input type="text" :name="'social_handles['+i+'][network]'" x-model="row.network" placeholder="Network (twitter, instagram…)" class="col-span-3 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <input type="text" :name="'social_handles['+i+'][handle]'" x-model="row.handle" placeholder="@handle" class="col-span-3 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <input type="text" :name="'social_handles['+i+'][url]'" x-model="row.url" placeholder="https://…" class="col-span-5 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <button type="button" @click="rows.splice(i,1)" class="col-span-1 text-red-400"><i class="fas fa-trash"></i></button>
        </div>
    </template>
</div>

<div class="mt-6" x-data='{ rows: @json(array_values(array_map(fn($r)=>["key"=>$r["key"]??"","value"=>$r["value"]??""], $fields ?: []))) }'>
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-semibold text-gray-300">Custom fields (encrypted)</h3>
        <button type="button" @click="rows.push({key:'',value:''})" class="text-xs text-amber-400">+ Add</button>
    </div>
    <template x-for="(row, i) in rows" :key="i">
        <div class="grid grid-cols-12 gap-2 mb-2">
            <input type="text" :name="'fields['+i+'][key]'" x-model="row.key" placeholder="Key" class="col-span-4 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <input type="text" :name="'fields['+i+'][value]'" x-model="row.value" placeholder="Value" class="col-span-7 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <button type="button" @click="rows.splice(i,1)" class="col-span-1 text-red-400"><i class="fas fa-trash"></i></button>
        </div>
    </template>
</div>

<div class="mt-6 flex items-center gap-3">
    <button class="px-5 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">Save</button>
    <a href="{{ route('user.vault.clients.index') }}" class="text-sm text-gray-400 hover:text-white">Cancel</a>
</div>
