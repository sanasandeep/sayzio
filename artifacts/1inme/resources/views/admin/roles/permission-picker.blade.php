@php
    $catalog = \App\Modules\Admin\Support\StaffPermissionCatalog::definitions();
    $picked = array_map('strval', old('permission_selection_present') ? old('permissions', []) : ($rolePermissions ?? []));
@endphp
<div x-data="{ search: '', picked: @js($picked) }" class="space-y-4">
    <div class="rounded-xl border border-white/10 p-4">
        <h2 class="text-lg font-semibold ak-strong">Specific permissions</h2>
        <p class="text-sm ak-muted mt-1">Choose only what this role needs. Viewing totals, revealing customer details, changing records and exporting data are separate powers.</p>
        <p class="text-sm ak-muted mt-2">To hide all customer identity, leave name, email and phone visibility off. Unreviewed screens that may reveal customer data will be blocked. A super administrator always has full access.</p>
        <label for="permission-search" class="block text-sm font-medium mt-4">Find a permission</label>
        <input id="permission-search" type="search" x-model="search" placeholder="Search finances, purchases, coins, user details…" class="ak-input w-full rounded-xl border p-3 mt-1">
        <p class="text-sm ak-muted mt-2"><span x-text="picked.length"></span> selected. Search changes visibility only; hidden selections remain selected.</p>
    </div>
    @foreach($permissions as $group => $perms)
    <fieldset class="rounded-xl border border-white/10 p-4" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" data-search="{{ strtolower($group.' '.$perms->map(fn ($p) => $p->name.' '.$p->slug.' '.($catalog[$p->slug]['description'] ?? ''))->implode(' ')) }}">
        <legend class="px-2 font-semibold capitalize ak-strong">{{ $group ?: 'General' }}</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach($perms as $perm)
            <label class="flex items-start gap-3 p-3 rounded-xl border border-white/10 cursor-pointer hover:bg-white/5" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" data-search="{{ strtolower($group.' '.$perm->name.' '.$perm->slug.' '.($catalog[$perm->slug]['description'] ?? '')) }}">
                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" x-model="picked" @checked(in_array((string) $perm->id, $picked, true)) class="mt-1 rounded">
                <span><span class="block text-sm font-medium ak-strong">{{ $perm->name }}</span>
                    <code class="block text-xs ak-muted mt-1">{{ $perm->slug }}</code>
                    @if(isset($catalog[$perm->slug]))<span class="block text-xs ak-muted mt-1">{{ $catalog[$perm->slug]['description'] }}</span>@endif
                    @if(in_array($perm->slug, ['users.credits', 'users.edit']))<span class="block text-xs ak-muted mt-1">Legacy bundle. Use the specific controls above for coins, passwords, badges and profile editing.</span>@endif
                </span>
            </label>
        @endforeach
        </div>
    </fieldset>
    @endforeach
</div>
