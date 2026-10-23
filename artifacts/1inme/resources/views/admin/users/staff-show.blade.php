@extends('admin.layouts.app')
@section('title', 'Account #' . $account['id'])
@section('page-title', 'Account #' . $account['id'])
@section('content')
<div class="max-w-6xl space-y-5">
    <a href="{{ route('admin.users.index') }}" class="underline">Back to accounts</a>
    <div class="glass border border-white/10 rounded-2xl p-5">
        <h1 class="text-xl font-semibold">{{ $account['label'] }}</h1>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">@foreach($account as $field => $value)@if(!in_array($field, ['id','label']))<div><dt class="text-sm ak-muted">{{ ucwords(str_replace('_', ' ', $field)) }}</dt><dd class="break-words">{{ $value ?? '—' }}</dd></div>
@endif

@endforeach
</dl>
        @if($balance !== null)<p class="mt-4 font-semibold">Coin balance: {{ number_format($balance) }}</p>
@endif

    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    @if($actor->hasPermission('users.assign_plan'))
    <section class="glass border border-white/10 rounded-2xl p-5"><h2 class="font-semibold">Assign plan</h2><p class="text-sm ak-muted">Included plan coins are granted through the normal plan activation rules.</p>
        <form method="POST" action="{{ route('admin.users.assign-plan', $account['id']) }}" class="space-y-3 mt-3">@csrf
            <label class="block">Plan<select name="plan_id" required class="ak-input border rounded-xl p-2 w-full">@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>
@endforeach
</select></label>
            @if($actor->hasPermission('users.plan.comp'))<label class="block">Complimentary days (optional)<input name="comp_days" type="number" min="1" max="3650" class="ak-input border rounded-xl p-2 w-full"></label>
@endif

            <button class="bg-blue-600 text-white rounded-xl p-3">Assign selected plan</button>
        </form>
    </section>
@endif

    @if($actor->hasAnyPermission(['users.coins.grant','users.coins.deduct']))
    <section class="glass border border-white/10 rounded-2xl p-5"><h2 class="font-semibold">Adjust coins</h2>
        <p class="text-sm ak-muted">Positive amounts grant coins; negative amounts deduct coins. Each direction requires its own permission.</p>
        <form method="POST" action="{{ route('admin.users.wallet.adjust', $account['id']) }}" class="space-y-3 mt-3">@csrf
            <label class="block">Coin adjustment<input name="delta" type="number" required @if(!$actor->hasPermission('users.coins.deduct')) min="1"
@endif
 @if(!$actor->hasPermission('users.coins.grant')) max="-1"
@endif
 class="ak-input border rounded-xl p-2 w-full"></label>
            <label class="block">Reason<input name="reason" required maxlength="255" class="ak-input border rounded-xl p-2 w-full"></label>
            <button class="bg-blue-600 text-white rounded-xl p-3">Record adjustment</button>
        </form>
    </section>
@endif

    @if($actor->hasPermission('users.badges.assign'))
    <section class="glass border border-white/10 rounded-2xl p-5"><h2 class="font-semibold">Account badges</h2><form method="POST" action="{{ route('admin.users.badges.update', $account['id']) }}" class="mt-3 space-y-3">@csrf @method('PUT')
        @foreach($badges as $badge)<label class="block"><input name="badge_ids[]" type="checkbox" value="{{ $badge->id }}" @checked(in_array($badge->id, $assignedBadges))> {{ $badge->name }}</label>
@endforeach

        <button class="bg-blue-600 text-white rounded-xl p-3">Save badges</button>
    </form></section>
@endif

    @if($actor->hasPermission('users.links.assign'))
    <section class="glass border border-white/10 rounded-2xl p-5"><h2 class="font-semibold">Assign a link</h2><p class="text-sm ak-muted">Moves the link and its related ownership records into this account. Both owners are notified. Protected accounts are excluded.</p>
        <form method="POST" action="{{ route('admin.users.assign-link', $account['id']) }}" class="mt-3 space-y-3">@csrf
            <label class="block">Current owner account number<input type="number" min="1" name="source_user_id" required class="ak-input border rounded-xl p-2 w-full"></label>
            <label class="block">Link number<input type="number" min="1" name="link_id" required class="ak-input border rounded-xl p-2 w-full"></label>
            <label class="block">Reason<input name="reason" required maxlength="255" class="ak-input border rounded-xl p-2 w-full"></label>
            <button class="bg-blue-600 text-white rounded-xl p-3">Move link to this account</button>
        </form>
    </section>
@endif

    @if($actor->hasPermission('users.suspend'))
    <section class="glass border border-white/10 rounded-2xl p-5"><h2 class="font-semibold">Account access</h2><form method="POST" action="{{ route('admin.users.suspend', $account['id']) }}" class="mt-3 space-y-3">@csrf
        <label class="block">Suspension reason<input name="reason" required maxlength="255" class="ak-input border rounded-xl p-2 w-full"></label><button class="border rounded-xl p-3">Suspend account</button>
    </form><form method="POST" action="{{ route('admin.users.reactivate', $account['id']) }}" class="mt-3">@csrf<button class="border rounded-xl p-3">Reactivate account</button></form></section>
@endif

    @if($actor->hasPermission('users.transfer.manage'))
    <section class="glass border border-white/10 rounded-2xl p-5"><h2 class="font-semibold">User asset-transfer capability</h2><form method="POST" action="{{ route('admin.users.transfer-capability', $account['id']) }}" class="mt-3 space-y-3">@csrf
        <label class="block">Access<select name="grant" class="ak-input border rounded-xl p-2"><option value="1">Enable</option><option value="0">Disable</option></select></label><button class="border rounded-xl p-3">Save transfer access</button>
    </form></section>
@endif

    </div>
    @if($actor->hasPermission('users.wallet.history'))<section class="glass border border-white/10 rounded-2xl p-5 overflow-x-auto"><h2 class="font-semibold">Recent coin transactions</h2><table class="w-full text-sm"><thead><tr><th class="text-left p-2">Date</th><th class="text-left p-2">Type</th><th class="text-right p-2">Coins</th></tr></thead><tbody>@forelse($transactions as $transaction)<tr><td class="p-2">{{ $transaction['date'] }}</td><td class="p-2">{{ $transaction['type'] }}</td><td class="text-right p-2">{{ $transaction['coins'] }}</td></tr>
@empty
<tr><td class="p-2">No transactions.</td></tr>
@endforelse
</tbody></table></section>
@endif

    @if(\App\Modules\Admin\Support\StaffPermissionCatalog::fullIdentity($actor))
        @include('admin.users.staff-private-actions')

@endif

</div>
@endsection
