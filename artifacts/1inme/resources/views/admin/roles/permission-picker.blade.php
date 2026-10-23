@php
    $catalog = \App\Modules\Admin\Support\StaffPermissionCatalog::definitions();
    $riskLabels = \App\Modules\Admin\Support\StaffRoleTools::labels();
    $permissionRows = $permissions->flatten()->map(fn ($p) => ['id' => (string) $p->id, 'group' => $p->group ?: 'General', 'search' => strtolower(($p->group ?: 'General').' '.$p->name.' '.$p->slug.' '.($catalog[$p->slug]['description'] ?? '')), 'risk' => \App\Modules\Admin\Support\StaffRoleTools::risk($p->slug)])->values();
    $presetRows = \App\Modules\Admin\Support\StaffRoleTools::resolvedPresets($permissions->flatten(), auth('admin')->user());
    $picked = array_map('strval', old('permission_selection_present') ? old('permissions', []) : ($rolePermissions ?? []));
@endphp
<div x-data="staffRolePicker(@js($picked), @js($permissionRows), @js($presetRows))" class="space-y-4">
    <div class="rounded-xl border border-white/10 p-4">
        <h2 class="text-lg font-semibold ak-strong">Specific permissions</h2>
        <p class="text-sm ak-muted mt-1">Choose only what this role needs. Viewing totals, revealing customer details, changing records and exporting data are separate powers.</p>
        <p class="text-sm ak-muted mt-2">To hide all customer identity, leave name, email and phone visibility off. Unreviewed screens that may reveal customer data will be blocked. A super administrator always has full access.</p>
        <div class="mt-4 rounded-xl border border-white/10 p-4 space-y-2">
            <label for="role-preset" class="block font-medium">Standard staff role presets</label>
            <select id="role-preset" x-model="preset" class="ak-input w-full rounded-xl border border-white/10 bg-white/5 p-3">
                <option value="">Choose a starting point</option>
                @foreach($presetRows as $key => $template)
                    <option value="{{ $key }}" @disabled(!$template['available'])>{{ $template['name'] }}{{ $template['available'] ? '' : ' ('.$template['unavailable_reason'].')' }}</option>
                @endforeach
            </select>
            @if($presetRows->contains(fn ($preset) => !empty($preset['missing'])))
                <p class="text-sm ak-amber text-amber-300" role="status">Some presets need a permission catalog update. Missing records: {{ $presetRows->pluck('missing')->flatten()->unique()->implode(', ') }}. This does not indicate reduced Super Admin access.</p>
            @endif
            <p class="text-sm ak-muted" x-text="presets[preset]?.description || 'Presets use explicit permissions. They do not create or assign a role until you save.'"></p>
            <button type="button" @click="applyPreset()" :disabled="!presets[preset]?.available" class="px-3 py-2 rounded-xl border border-white/10 font-medium disabled:opacity-40">Replace selection with preset</button>
            <p class="text-xs ak-muted">Applying a preset replaces all selected permissions, including hidden selections.</p>
        </div>
        <div class="flex flex-wrap gap-2 mt-4">
            @foreach($riskLabels as $level => $label)<span class="role-risk role-risk-{{ $level }}">{{ $label }}</span>@endforeach
        </div>
        <p class="text-sm ak-muted mt-2">Levels describe access risk, not a hierarchy. Higher levels do not include lower-level permissions.</p>
        <label for="permission-search" class="block text-sm font-medium mt-4">Find a permission</label>
        <input id="permission-search" type="search" x-model="search" placeholder="Search finances, purchases, coins, user details…" class="ak-input w-full rounded-xl border border-white/10 bg-white/5 p-3 mt-1">
        <label for="permission-risk" class="block text-sm font-medium mt-3">Filter by access level</label>
        <select id="permission-risk" x-model="risk" class="ak-input rounded-xl border border-white/10 bg-white/5 p-3 w-full">
            <option value="">All levels</option>
            @foreach($riskLabels as $level => $label)<option value="{{ $level }}">{{ $label }}</option>@endforeach
        </select>
        <label class="flex items-start gap-2 text-sm mt-3"><input type="checkbox" x-model="includeCritical" class="mt-1">Include Critical permissions in category bulk selection</label>
        <p class="text-sm ak-muted mt-2" aria-live="polite"><span x-text="picked.length"></span> selected. Search changes visibility only; hidden selections remain selected.</p>
        <p class="role-risk role-risk-4 mt-2" x-show="criticalCount()" x-cloak><span x-text="criticalCount()"></span> Critical permissions selected. Review security and account access before saving.</p>
        <p class="text-sm ak-muted mt-2" role="status" x-text="notice"></p>
    </div>
    @foreach($permissions as $group => $perms)
    <fieldset class="rounded-xl border border-white/10 p-4" x-show="visible(@js($group ?: 'General')).length">
        <legend class="px-2 font-semibold capitalize ak-strong">{{ $group ?: 'General' }}</legend>
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <span class="text-sm ak-muted" x-text="selectedIn(@js($group ?: 'General')) + ' / ' + rows.filter(row => row.group === @js($group ?: 'General')).length + ' selected'"></span>
            <button type="button" class="text-sm rounded-lg border border-white/10 px-3 py-2" @click="selectCategory(@js($group ?: 'General'))">Select visible category</button>
            <button type="button" class="text-sm rounded-lg border border-white/10 px-3 py-2" @click="clearCategory(@js($group ?: 'General'))">Clear visible category</button>
            <span class="text-xs ak-muted">Respects search and level filters. Critical skipped unless enabled.</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach($perms as $perm)
            <label class="flex items-start gap-3 p-3 rounded-xl border border-white/10 cursor-pointer hover:bg-white/5" x-show="matches({search: @js(strtolower(($group ?: 'General').' '.$perm->name.' '.$perm->slug.' '.($catalog[$perm->slug]['description'] ?? ''))), risk: {{ \App\Modules\Admin\Support\StaffRoleTools::risk($perm->slug) }}})">
                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" x-model="picked" @checked(in_array((string) $perm->id, $picked, true)) class="mt-1 rounded">
                <span><span class="block text-sm font-medium ak-strong">{{ $perm->name }}</span>
                    <span class="role-risk role-risk-{{ \App\Modules\Admin\Support\StaffRoleTools::risk($perm->slug) }}">{{ $riskLabels[\App\Modules\Admin\Support\StaffRoleTools::risk($perm->slug)] }}</span>
                    <code class="block text-xs ak-muted mt-1 break-all">{{ $perm->slug }}</code>
                    @if(isset($catalog[$perm->slug]))<span class="block text-xs ak-muted mt-1">{{ $catalog[$perm->slug]['description'] }}</span>@endif
                    @if(in_array($perm->slug, ['users.credits', 'users.edit']))<span class="block text-xs ak-muted mt-1">Legacy bundle. Use the specific controls above for coins, passwords, badges and profile editing.</span>@endif
                </span>
            </label>
        @endforeach
        </div>
    </fieldset>
    @endforeach
</div>

@once
@push('styles')
<style>
.role-risk{display:inline-block;border:1px solid currentColor;border-radius:999px;padding:3px 9px;font-size:12px;font-weight:600;margin-top:5px;line-height:1.5}
.role-risk-1{color:#60a5fa}.role-risk-2{color:#c4b5fd}.role-risk-3{color:#fbbf24}.role-risk-4{color:#fda4af;background:rgba(244,63,94,.1)}
html.light-mode .role-risk-1{color:#1d4ed8}html.light-mode .role-risk-2{color:#6d28d9}html.light-mode .role-risk-3{color:#92400e}html.light-mode .role-risk-4{color:#be123c}
</style>
@endpush
@push('scripts')
<script src="{{ asset('js/admin-role-permissions.js') }}"></script>
@endpush
@endonce
