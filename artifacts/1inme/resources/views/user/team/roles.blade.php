@extends('user.layouts.app')
@section('title', 'Roles & Permissions')
@push('styles')
<style>
.permissions-editor table tr,.permissions-editor .border-b{border-color:var(--border-strong)}
.permissions-editor .permission-search{min-width:180px}
.permissions-editor table th:first-child{position:sticky;left:0;z-index:1;background:var(--bg-card);min-width:180px}
.permissions-editor input[type=checkbox]{accent-color:#3d6bff;cursor:pointer}
.permissions-editor table tbody tr:hover{background:var(--bg-glass-light)}
@media(max-width:640px){.permissions-editor .permission-search{flex-basis:100%;order:1}.permissions-editor table{font-size:13px}.permissions-editor table th:first-child{min-width:150px}}
</style>
@endpush
@section('content')
<div class="permissions-editor max-w-7xl mx-auto px-4 py-8" x-data="permissionEditor()">
    <div class="rounded-2xl border p-6 mb-6" style="background:var(--bg-card);border-color:var(--border-strong)">
        <div class="flex flex-wrap gap-4 justify-between items-start">
            <div><p class="text-xs uppercase tracking-widest opacity-60 mb-2">Workspace access</p>
                <h1 class="text-3xl font-bold">Roles &amp; Permissions</h1>
                <p class="text-sm opacity-70 mt-2">Choose exactly what teammates can do in {{ $workspace->name }}.</p></div>
            <a href="{{ route('user.team.index') }}" class="text-sm px-4 py-2 border rounded-xl">Back to team</a>
        </div>
        <p class="text-sm opacity-70 mt-4">Owners always have full access. Billing, ownership transfer and deleting the workspace remain owner-only. Saved changes apply on the next request.</p>
    </div>
    @foreach(['success', 'error'] as $message)
        @if(session($message))<div class="p-4 rounded-xl border mb-4">{{ session($message) }}</div>@endif
    @endforeach
    @if($errors->any())<div class="p-4 rounded-xl border mb-4">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('user.team.roles.update') }}" @submit="serialize($event)" class="rounded-2xl border overflow-hidden" style="background:var(--bg-card);border-color:var(--border-strong)">
        @csrf @method('PUT')
        <input type="hidden" name="matrix_json" x-ref="payload">
        <div class="flex flex-wrap gap-3 p-5 border-b items-center">
            <label class="text-sm font-semibold">Role
                <select x-model="role" class="ml-2 border rounded-xl p-2">
                    @foreach($roles as $role)<option value="{{ $role }}">{{ \App\Modules\User\Services\WorkspacePermissions::roleLabel($role) }}</option>@endforeach
                </select>
            </label>
            <input type="search" x-model="search" placeholder="Find a feature…" class="permission-search border rounded-xl p-2 flex-1 min-w-0" aria-label="Find a feature">
            <button type="button" @click="setAll(true)" class="border rounded-xl px-3 py-2 text-sm">Check all</button>
            <button type="button" @click="setAll(false)" class="border rounded-xl px-3 py-2 text-sm">Uncheck all</button>
            <button type="button" @click="duplicateOpen = !duplicateOpen" class="border rounded-xl px-3 py-2 text-sm">Duplicate role</button>
        </div>
        <div class="px-5 py-3 text-sm opacity-70">Own records means records created by you. View, edit and delete apply to these records. Enable the matching “Others” permission to include teammates’ records. Shared tools without record ownership use workspace-wide access.</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="min-width:850px">
                <thead><tr class="border-b text-left opacity-70"><th class="p-4">Feature</th>
                    @foreach($actions as $action)<th class="p-3 text-center capitalize">{{ $action }}</th>@endforeach
                    <th class="p-3 text-center">View others</th><th class="p-3 text-center">Edit others</th><th class="p-3 text-center">Delete others</th><th class="p-3">All</th>
                </tr></thead>
                <tbody>
                @foreach($features as $feature => $label)
                    @php $own = in_array($feature, array_column(\App\Modules\User\Services\WorkspaceRecordAccess::models(), 0), true); @endphp
                    <tr class="border-b" x-show="matches(@js($label))">
                        <th class="p-4 text-left font-medium">{{ $label }}<div class="text-xs font-normal opacity-50 mt-1">{{ $own ? 'Own records + others' : 'Workspace-wide' }}</div></th>
                        @foreach(array_merge($actions, ['view_others', 'edit_others', 'delete_others']) as $action)
                            <td class="p-3 text-center">
                                @if((!str_ends_with($action, '_others') && in_array($action, \App\Modules\User\Services\WorkspacePermissionCatalog::featureActions($feature))) || (str_ends_with($action, '_others') && $own))
                                    <input type="checkbox" x-model="matrix[role]['{{ $feature }}.{{ $action }}']" aria-label="{{ $label }}: {{ str_replace('_', ' ', $action) }}" class="w-4 h-4">
                                @else<span class="opacity-40" title="Workspace-wide access">—</span>@endif
                            </td>
                        @endforeach
                        <td class="p-3"><button type="button" @click="setFeature('{{ $feature }}', true)" class="px-2 py-1 border rounded" aria-label="Check all {{ $label }}">✓</button> <button type="button" @click="setFeature('{{ $feature }}', false)" class="px-2 py-1 border rounded" aria-label="Uncheck all {{ $label }}">−</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap gap-3 justify-between items-center p-5">
            <button type="submit" class="px-5 py-3 bg-primary-600 text-white rounded-xl font-semibold">Save permissions</button>
            <span class="text-sm opacity-60" x-text="changes() + ' unsaved changes'"></span>
            <button type="button" onclick="document.getElementById('reset-roles-form').requestSubmit()" class="text-sm opacity-70">Reset built-in roles</button>
        </div>
    </form>
    <form x-show="duplicateOpen" x-cloak method="POST" action="{{ route('user.team.roles.duplicate') }}" class="mt-4 p-5 border rounded-2xl" style="background:var(--bg-card)">
        @csrf <input type="hidden" name="source" :value="role">
        <h2 class="font-semibold mb-2">Duplicate saved role</h2><p class="text-sm opacity-60 mb-3">Copies the selected role’s saved permissions. Save pending edits first.</p>
        <div class="flex flex-wrap gap-3"><input name="name" required maxlength="60" placeholder="New role name" class="border rounded-xl p-3"><button class="px-4 py-2 bg-primary-600 text-white rounded-xl">Create role</button></div>
    </form>
    <form id="reset-roles-form" method="POST" action="{{ route('user.team.roles.reset') }}" class="hidden" onsubmit="return window.themedConfirmSubmit(this, {title: 'Reset built-in roles?', message: 'Custom roles are preserved. Built-in roles return to their original permissions.', confirmText: 'Reset roles'})">@csrf</form>
    <div class="mt-8 rounded-lg border" style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="px-4 py-3 border-b font-semibold" style="border-color: var(--border-strong);">
            Recent changes
        </div>
        @if(empty($audits))
            <div class="px-4 py-6 text-sm opacity-60">No changes recorded yet.</div>
        @else
            <ul class="divide-y" style="--tw-divide-opacity: 1;">
                @foreach($audits as $a)
                    <li class="px-4 py-3 text-sm border-t" style="border-color: var(--border-strong);">
                        <div class="flex items-center justify-between">
                            <span><strong>{{ $a['actor'] }}</strong>
                                {{ ($a['noop'] ?? false) ? 'reviewed permissions (no changes)' : 'updated permissions' }}
                            </span>
                            <span class="opacity-60 text-xs">{{ optional($a['when'])->diffForHumans() }}</span>
                        </div>
                        @if(!empty($a['lines']))
                            <ul class="mt-1 ml-4 list-disc text-xs opacity-80">
                                @foreach($a['lines'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
<script>
function permissionEditor() {
    const matrix = @json($matrix);
    const features = @json(array_keys($features));
    @php $editorActions = array_merge($actions, ['view_others','edit_others','delete_others']); @endphp
    const actions = @json($editorActions);
    @php $supported = []; foreach($features as $feature => $label) $supported[$feature] = array_merge(\App\Modules\User\Services\WorkspacePermissionCatalog::featureActions($feature), ['view_others','edit_others','delete_others']); @endphp
    const supported = @json($supported);
    for (const row of Object.values(matrix)) for (const feature of features) for (const action of supported[feature]) {
        if (!(feature+'.'+action in row)) row[feature+'.'+action] = !!row[({export:'view',publish:'edit',run:'edit'})[action] || action.replace('_others','')];
    }
    return {
        matrix, original: JSON.parse(JSON.stringify(matrix)), role: @json($roles[0]), search: '', duplicateOpen: false,
        matches(label) { return label.toLowerCase().includes(this.search.toLowerCase()); },
        setFeature(feature, value) { for (const action of supported[feature]) this.matrix[this.role][feature+'.'+action] = value; },
        setAll(value) { for (const feature of features) this.setFeature(feature, value); for(const action of ['view','create','edit','delete','reply']) this.matrix[this.role][action] = value; },
        changes() { let count=0; for(const [role,row] of Object.entries(this.matrix)) for(const [key,value] of Object.entries(row)) if(value !== this.original[role][key]) count++; return count; },
        serialize() { this.$refs.payload.value = JSON.stringify(this.matrix); }
    };
}
</script>
@endsection
