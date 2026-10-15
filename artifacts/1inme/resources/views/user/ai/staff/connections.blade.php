@php $connectionValues=old('connections',isset($staff)&&$staff instanceof \App\Modules\User\Models\AiStaff ? ($staff->config['connections']??[]) : []); @endphp
<div x-data="{ connectionTab:'knowledge' }" class="mt-4 rounded-xl border border-white/10 p-4">
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['knowledge'=>'AI Minds & profile','context'=>'Business context','advanced'=>'Advanced'] as $id=>$label)<button type="button" @click="connectionTab='{{ $id }}'" :class="connectionTab==='{{ $id }}'?'bg-blue-600 text-white':'bg-white/5 text-white/60'" class="rounded-lg px-3 py-2 text-xs">{{ $label }}</button>@endforeach
    </div>
    <div x-show="connectionTab==='knowledge'" class="space-y-3">
        <label class="block text-sm">Agent profile (optional)<select name="connections[agent_id]" class="theme-input w-full mt-1"><option value="">Use staff instructions only</option>@foreach($connectionAgents as $profile)<option value="{{ $profile->id }}" @selected(($connectionValues['agent_id']??null)==$profile->id)>{{ $profile->name }}</option>@endforeach</select></label>
        <p class="text-xs text-white/50">Reuses personality and instructions only. Does not inherit tools or permissions.</p>
        <fieldset><legend class="text-sm mb-2">AI Minds (up to 8)</legend><div class="grid sm:grid-cols-2 gap-2">@forelse($connectionMinds as $mind)<label class="text-xs border border-white/10 rounded-lg p-3"><input type="checkbox" name="connections[mind_ids][]" value="{{ $mind->id }}" @checked(in_array($mind->id,$connectionValues['mind_ids']??[]))> {{ $mind->name }}</label>@empty<p class="text-xs text-white/50">No personal AI Minds yet.</p>@endforelse</div></fieldset>
    </div>
    <div x-show="connectionTab==='context'" class="space-y-4">
        <p class="text-xs text-white/50">Choose context deliberately. Selecting clients or folders restricts context to their profile details and selected AI Minds; broad workspace summaries are excluded. Vault secrets are never included.</p>
        @foreach(['project_ids'=>['Folders / projects',$connectionProjects],'client_ids'=>['Clients you created',$connectionClients]] as $field=>$group)
        <fieldset><legend class="text-sm mb-2">{{ $group[0] }}</legend><div class="grid sm:grid-cols-2 gap-2">@foreach($group[1] as $record)<label class="text-xs"><input type="checkbox" name="connections[{{ $field }}][]" value="{{ $record->id }}" @checked(in_array($record->id,$connectionValues[$field]??[]))> {{ $record->name }}</label>@endforeach</div></fieldset>
        @endforeach
        <fieldset><legend class="text-sm mb-2">Allowed workspace summaries</legend><div class="grid grid-cols-2 sm:grid-cols-3 gap-2">@foreach($dataKeys as $key)<label class="text-xs"><input type="checkbox" name="connections[data_keys][]" value="{{ $key }}" @checked(in_array($key,$connectionValues['data_keys']??[]))> {{ ucfirst($key) }}</label>@endforeach</div></fieldset>
    </div>
    <div x-show="connectionTab==='advanced'" class="space-y-3">
        <label class="block text-sm">Action level<select name="connections[mode]" class="theme-input w-full"><option value="draft" @selected(($connectionValues['mode']??'draft')==='draft')>Draft for approval</option><option value="read" @selected(($connectionValues['mode']??'')==='read')>Read-only advice</option></select></label>
        <p class="text-xs text-white/50">Only Billing and Contacts have executable actions today. Other roles provide advice and drafts. No new autonomous sending or scheduling is enabled.</p>
        <label class="block text-sm">Monthly coin pause threshold (0 = no staff threshold)<input type="number" min="0" max="10000000" name="connections[monthly_budget]" value="{{ $connectionValues['monthly_budget']??0 }}" class="theme-input w-full"></label>
        <p class="text-xs text-white/50">Pauses new work when recorded spend reaches this amount. Work already in progress may finish above the threshold.</p>
    </div>
</div>
