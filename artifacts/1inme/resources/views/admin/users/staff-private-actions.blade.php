<section class="glass border border-white/10 rounded-2xl p-5 space-y-4"><h2 class="font-semibold">Additional permitted actions</h2>
@if($actor->hasPermission('users.profile.edit'))
<form method="POST" action="{{ route('admin.users.update', $account['id']) }}" class="space-y-3">@csrf @method('PUT')
    <label class="block">Name<input name="name" required value="{{ $account['name'] ?? '' }}" class="ak-input border rounded-xl p-2 w-full"></label>
    <button class="border rounded-xl p-3">Save profile</button>
</form>
@endif

@if($actor->hasPermission('users.password.set'))
<form method="POST" action="{{ route('admin.users.set-password', $account['id']) }}" class="space-y-3">@csrf
    <label class="block">New password<input name="password" type="password" autocomplete="new-password" required minlength="8" class="ak-input border rounded-xl p-2 w-full"></label>
    <label class="block">Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" required class="ak-input border rounded-xl p-2 w-full"></label>
    <button class="border rounded-xl p-3">Set password</button>
</form>
@endif

@if($actor->hasAnyPermission(['users.assign_roles','users.grant_admin','users.revoke_admin']))<a href="{{ route('admin.users.roles.edit', $account['id']) }}" class="inline-block underline">Manage user roles and staff access</a>
@endif

@if($actor->hasPermission('users.audit.view'))<a href="{{ route('admin.users.role-audits.index', ['target_user_id' => $account['id']]) }}" class="inline-block underline">Role audit history</a>
@endif

@if($actor->hasPermission('users.impersonate'))<form method="POST" action="{{ route('admin.users.impersonate', $account['id']) }}">@csrf<button class="border rounded-xl p-3">Sign in as this user</button></form>
@endif

@if($actor->hasPermission('users.delete'))<form method="POST" action="{{ route('admin.users.destroy', $account['id']) }}" onsubmit="return confirm('Permanently delete this account?')">@csrf @method('DELETE')<button class="border rounded-xl p-3 text-red-600">Delete account</button></form>
@endif

</section>
