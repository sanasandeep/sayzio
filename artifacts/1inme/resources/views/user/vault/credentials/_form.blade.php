@php
    $cfs = $item?->getEncrypted('custom_fields', true) ?? [];
@endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <label class="block">
        <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Label *</span>
        <input type="text" name="label" required value="{{ old('label', $item->label ?? '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">URL</span>
        <input type="text" name="url" value="{{ old('url', $item->url ?? '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Username</span>
        <input type="text" name="username" value="{{ old('username', $item->username ?? '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Password {{ isset($item) ? '(leave blank to keep)' : '*' }}</span>
        <input type="password" name="password" autocomplete="new-password" value="" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block md:col-span-2">
        <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Notes</span>
        <textarea name="notes" rows="3" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">{{ old('notes', $item?->getEncrypted('notes')) }}</textarea>
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Tags (comma-separated)</span>
        <input type="text" name="tags" value="{{ old('tags', isset($item) ? implode(',', (array) ($item->tags ?? [])) : '') }}" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
    </label>
    <label class="block">
        <span class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Visibility</span>
        <select name="visibility" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <option value="shared" @selected(old('visibility', $item->visibility ?? 'shared') === 'shared')>Shared with workspace</option>
            <option value="private" @selected(old('visibility', $item->visibility ?? '') === 'private')>Private — creator + owner only</option>
        </select>
    </label>
</div>

<div class="mt-6" x-data='{ rows: @json(array_values(array_map(fn($r)=>["key"=>$r["key"]??"","value"=>$r["value"]??""], $cfs ?: []))) }'>
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-semibold text-gray-300">Custom fields</h3>
        <button type="button" @click="rows.push({key:'',value:''})" class="text-xs text-amber-400 hover:underline">+ Add field</button>
    </div>
    <template x-for="(row, i) in rows" :key="i">
        <div class="grid grid-cols-12 gap-2 mb-2">
            <input type="text" :name="'custom_fields['+i+'][key]'" x-model="row.key" placeholder="Field name" class="col-span-4 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <input type="text" :name="'custom_fields['+i+'][value]'" x-model="row.value" placeholder="Value" class="col-span-7 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            <button type="button" @click="rows.splice(i,1)" class="col-span-1 text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
        </div>
    </template>
</div>

<div class="mt-6 flex items-center gap-3">
    <button class="px-5 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">Save</button>
    <a href="{{ route('user.vault.credentials.index') }}" class="text-sm hover:text-white" style="color: var(--text-faint);">Cancel</a>
</div>
