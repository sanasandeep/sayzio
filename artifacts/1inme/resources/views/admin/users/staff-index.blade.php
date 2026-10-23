@extends('admin.layouts.app')
@section('title', 'Accounts')
@section('page-title', 'Accounts')
@section('content')
<div class="space-y-5">
    <div class="glass rounded-2xl border border-white/10 p-5">
        <h1 class="text-xl font-semibold">Find an account</h1>
        <p class="text-sm ak-muted mt-1">Only the fields your role may reveal can be searched, sorted or displayed.</p>
        <form method="GET" class="flex flex-wrap gap-3 items-end mt-4">
            @if($actor->hasPermission('users.search'))
            <div><label for="search-field" class="block text-sm">Search field</label><select id="search-field" name="field" class="ak-input border rounded-xl p-2">
                <option value="id">Account number</option>
                @if($actor->hasPermission('users.search_identity'))
                    @foreach(['name' => 'users.identity.view', 'handle' => 'users.identity.view', 'email' => 'users.email.view', 'phone' => 'users.phone.view'] as $field => $permission)
                        @if($actor->hasPermission($permission))<option value="{{ $field }}" @selected(request('field') === $field)>{{ ucfirst($field) }}</option>
@endif


@endforeach


@endif

            </select></div>
            <div><label for="account-search" class="block text-sm">Search value</label><input id="account-search" type="search" name="search" value="{{ request('search') }}" class="ak-input border rounded-xl p-2" maxlength="190"></div>

@endif

            @if($actor->hasPermission('users.filter'))
                @if($actor->hasPermission('users.status.view'))<div><label for="status" class="block text-sm">Status</label><select id="status" name="status" class="ak-input border rounded-xl p-2"><option value="">All statuses</option>@foreach(['active','inactive','banned','suspended'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>
@endforeach
</select></div>
@endif

                @if($actor->hasPermission('users.plan.view'))<div><label for="plan" class="block text-sm">Plan</label><select id="plan" name="plan" class="ak-input border rounded-xl p-2"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(request('plan') == $plan->id)>{{ $plan->name }}</option>
@endforeach
</select></div>
@endif

                @if($actor->hasPermission('users.badges.view'))<div><label for="badge" class="block text-sm">Badge</label><select id="badge" name="badge" class="ak-input border rounded-xl p-2"><option value="">All badges</option>@foreach($badges as $badge)<option value="{{ $badge->id }}" @selected(request('badge') == $badge->id)>{{ $badge->name }}</option>
@endforeach
</select></div>
@endif

                @if($actor->hasPermission('users.joined.view'))
                <div><label for="joined-from" class="block text-sm">Joined from</label><input id="joined-from" type="date" name="joined_from" value="{{ request('joined_from') }}" class="ak-input border rounded-xl p-2"></div>
                <div><label for="joined-to" class="block text-sm">Joined to</label><input id="joined-to" type="date" name="joined_to" value="{{ request('joined_to') }}" class="ak-input border rounded-xl p-2"></div>

@endif


@endif

            <div><label for="sort" class="block text-sm">Sort by</label><select id="sort" name="sort" class="ak-input border rounded-xl p-2">
                <option value="id">Account number</option>
                @foreach(['name' => 'users.identity.view', 'email' => 'users.email.view', 'created_at' => 'users.joined.view'] as $field => $permission)
                    @if($actor->hasPermission($permission))<option value="{{ $field }}" @selected(request('sort') === $field)>{{ $field === 'created_at' ? 'Registration date' : ucfirst($field) }}</option>
@endif


@endforeach

            </select></div>
            <div><label for="direction" class="block text-sm">Order</label><select id="direction" name="direction" class="ak-input border rounded-xl p-2"><option value="desc">Descending</option><option value="asc" @selected(request('direction') === 'asc')>Ascending</option></select></div>
            <div><label for="per-page" class="block text-sm">Rows</label><select id="per-page" name="per_page" class="ak-input border rounded-xl p-2">@foreach([15,30,50,100] as $size)<option @selected((int) request('per_page',30) === $size)>{{ $size }}</option>
@endforeach
</select></div>
            <button class="rounded-xl bg-blue-600 text-white px-4 py-2">Apply</button><a href="{{ route('admin.users.index') }}" class="p-2 underline">Reset</a>
        </form>
        @if($actor->hasPermission('users.create') && \App\Modules\Admin\Support\StaffPermissionCatalog::fullIdentity($actor))<a href="{{ route('admin.users.create') }}" class="inline-block mt-4 underline">Create account</a>
@endif

    </div>
    <div class="glass rounded-2xl border border-white/10 p-4 overflow-x-auto">
        <table class="w-full text-sm"><thead><tr class="text-left"><th class="p-3">Account</th>
            @foreach(['name' => 'users.identity.view', 'email' => 'users.email.view', 'phone' => 'users.phone.view', 'status' => 'users.status.view', 'plan' => 'users.plan.view', 'created_at' => 'users.joined.view'] as $field => $permission)
                @if($actor->hasPermission($permission))<th class="p-3">{{ $field === 'created_at' ? 'Joined' : ucfirst($field) }}</th>
@endif


@endforeach

        </tr></thead><tbody>
        @forelse($accounts as $account)<tr class="border-t border-white/10"><td class="p-3">@if($actor->hasPermission('users.details'))<a class="underline" href="{{ route('admin.users.show', ['user' => $account['id'], 'workbench' => 1]) }}">{{ $account['label'] }}</a>
@else
{{ $account['label'] }}
@endif
</td>
            @foreach(['name', 'email', 'phone', 'status', 'plan', 'created_at'] as $field)@if(array_key_exists($field, $account))<td class="p-3">{{ $account[$field] ?? '—' }}</td>
@endif

@endforeach

        </tr>
@empty
<tr><td class="p-3">No matching accounts.</td></tr>
@endforelse

        </tbody></table>
        <div class="mt-4">{{ $accounts->links() }}</div>
    </div>
    @if($actor->hasAnyPermission(['users.bulk_plan','users.bulk_credits','users.badges.bulk']))
    <details class="glass border border-white/10 rounded-2xl p-5"><summary class="cursor-pointer font-semibold">Bulk assignments</summary>
        <form method="POST" action="{{ route('admin.users.bulk') }}" x-data="{ action: '' }" class="space-y-3 mt-4">@csrf
            <label class="block">Account numbers, one per line<textarea required class="ak-input border rounded-xl p-2 w-full" aria-label="Bulk account numbers" oninput="this.form.querySelector('[data-user-ids]').replaceChildren(...this.value.trim().split(/\s+/).filter(v => /^\d+$/.test(v)).map(v => { const el = document.createElement('input'); el.type = 'hidden'; el.name = 'user_ids[]'; el.value = v; return el; }))"></textarea></label><div data-user-ids></div>
            <label class="block">Action<select required name="action" x-model="action" class="ak-input border rounded-xl p-2"><option value="">Choose action</option>@if($actor->hasPermission('users.bulk_plan'))<option value="assign_plan">Assign plan</option>
@endif
 @if($actor->hasPermission('users.bulk_credits'))<option value="grant_coins">Grant coins</option>
@endif
 @if($actor->hasPermission('users.badges.bulk'))<option value="assign_badge">Assign badge</option><option value="remove_badge">Remove badge</option>
@endif
</select></label>
            @if($actor->hasPermission('users.bulk_plan'))<label class="block" x-show="action === 'assign_plan'">Plan<select name="plan_id" class="ak-input border rounded-xl p-2"><option value="">Choose plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>
@endforeach
</select></label>
@endif

            @if($actor->hasPermission('users.bulk_credits'))<label class="block" x-show="action === 'grant_coins'">Coins<input type="number" name="coins" min="1" max="1000000" class="ak-input border rounded-xl p-2"></label>
@endif

            @if($actor->hasPermission('users.badges.bulk'))<label class="block" x-show="action === 'assign_badge' || action === 'remove_badge'">Badge<select name="badge_id" class="ak-input border rounded-xl p-2"><option value="">Choose badge</option>@foreach($badges as $badge)<option value="{{ $badge->id }}">{{ $badge->name }}</option>
@endforeach
</select></label>
@endif

            <label class="block">Reason<input name="reason" maxlength="255" class="ak-input border rounded-xl p-2"></label>
            <button class="bg-blue-600 text-white rounded-xl p-3">Apply to entered accounts</button>
        </form>
    </details>

@endif

</div>
@endsection
