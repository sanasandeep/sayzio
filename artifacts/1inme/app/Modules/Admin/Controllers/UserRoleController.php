<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function edit(User $user)
    {
        $roles = Role::query()
            ->where('guard', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        $assigned = $user->roles()->pluck('roles.id')->all();

        return view('admin.users.roles', [
            'user'     => $user,
            'roles'    => $roles,
            'assigned' => $assigned,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_ids'   => 'array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $ids = collect($validated['role_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();

        // Restrict to web-guard roles only — the admin guard is for the
        // back-office Admin model and must not leak onto user accounts.
        $webGuardIds = Role::query()
            ->where('guard', 'web')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $user->roles()->sync($webGuardIds);
        $user->flushPermissionCache();

        return redirect()
            ->route('admin.users.roles.edit', $user)
            ->with('success', 'Roles updated for ' . $user->name . '.');
    }
}
