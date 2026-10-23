<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Modules\Admin\Support\StaffPermissionCatalog;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::where('guard', 'admin')->with('permissions')->withCount(['admins', 'permissions'])->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function show(Role $role)
    {
        abort_unless($role->guard === 'admin', 404);
        $role->load('permissions');
        return view('admin.roles.show', compact('role'));
    }

    public function create()
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        return view('admin.roles.create', compact('permissions'));
    }

    public function duplicate(Role $role)
    {
        StaffPermissionCatalog::assertAssignable(auth('admin')->user(), $role);
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        $rolePermissions = $role->slug === 'super-admin'
            ? $permissions->flatten()->pluck('id')->all()
            : $role->permissions->pluck('id')->all();
        $base = Str::limit(Str::slug($role->name.' copy') ?: 'role-copy', 240, '');
        $copySlug = $base;
        for ($suffix = 2; Role::where('slug', $copySlug)->exists(); $suffix++) $copySlug = $base.'-'.$suffix;
        $copyName = Str::limit($role->name, 250, '').' copy';
        $copyDescription = $role->description;
        $copyNotice = $role->slug === 'super-admin'
            ? 'This is a restricted copy with explicit permissions. It has no Super Admin bypass and will not inherit future permissions. Review the critical selections before saving.'
            : 'Review this copy before saving. Existing staff assignments and the original role are unchanged.';
        return view('admin.roles.create', compact('permissions', 'rolePermissions', 'copyName', 'copySlug', 'copyDescription', 'copyNotice'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        abort_if($validated['slug'] === 'super-admin', 422, 'This slug is reserved.');
        $this->assertPermissions($validated['permissions'] ?? []);
        $role = Role::create([
            'guard' => 'admin',
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        StaffPermissionCatalog::assertAssignable(auth('admin')->user(), $role);
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        StaffPermissionCatalog::assertAssignable(auth('admin')->user(), $role);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $this->assertPermissions($validated['permissions'] ?? []);
        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($role->slug !== 'super-admin') {
            $role->permissions()->sync($validated['permissions'] ?? []);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        StaffPermissionCatalog::assertAssignable(auth('admin')->user(), $role);
        if ($role->slug === 'super-admin') {
            return back()->with('error', 'Cannot delete the Super Admin role.');
        }

        if ($role->admins()->count() > 0) {
            return back()->with('error', 'Cannot delete a role that has staff members assigned.');
        }

        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }
    private function assertPermissions(array $ids): void
    {
        $actor = auth('admin')->user();
        if ($actor->isSuperAdmin()) return;
        foreach (Permission::whereIn('id', $ids)->get() as $permission) {
            abort_unless($actor->hasPermission($permission->slug), 403, 'Cannot delegate permissions you do not hold.');
        }
    }
}
